<?php
/**
 * Integración con el sistema de actualizaciones de WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Client_Updater {

    /**
     * Instancia única
     */
    private static $instance = null;

    /**
     * Cliente API
     */
    private $api_client;

    /**
     * Configuración
     */
    private $config;

    /**
     * Caché de búsqueda de plugins (para evitar múltiples iteraciones)
     */
    private $plugin_file_cache = array();

    /**
     * Índice global de plugins (mapeo slug -> archivo)
     * Se construye una sola vez al inicio para optimizar búsquedas
     */
    private $plugin_index = null;

    /**
     * Caché de archivos de plugins gestionados (cacheado en transient)
     * Evita llamadas repetidas a find_plugin_file()
     */
    private $managed_plugin_files = null;

    /**
     * Flag para evitar re-ejecución de disable_custom_update_checks
     */
    private $custom_updates_disabled = false;

    /**
     * Obtener instancia
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->config = imagina_updater_client()->get_config();

        // Crear cliente API con el token correcto (activation_token o api_key)
        $this->api_client = imagina_updater_client()->get_api_client();

        $this->init_hooks();
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Solo cargar hooks de actualización en admin (mejora rendimiento frontend)
        if (!is_admin()) {
            return;
        }

        // Hook principal para verificar actualizaciones cuando se GUARDA el transient (prioridad 10)
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'), 10);

        // Hook para inyectar actualizaciones cuando se LEE el transient (prioridad 10 - antes de bloquear)
        add_filter('site_transient_update_plugins', array($this, 'inject_updates_on_read'), 10);

        // Hook para bloquear actualizaciones externas (prioridad 100 - después de inyectar las nuestras)
        add_filter('site_transient_update_plugins', array($this, 'block_external_updates'), 100);

        // Hook para información del plugin en el modal
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);

        // Hook específico para plugins_api_result
        add_filter('plugins_api_result', array($this, 'override_plugin_api_result'), 20, 3);

        // Hook para inyectar autenticación en descargas (prioridad 15)
        add_filter('http_request_args', array($this, 'inject_auth_headers'), 15, 2);

        // Bloquear HTTP requests de actualizaciones (prioridad 5 - temprano pero razonable)
        add_filter('http_request_args', array($this, 'block_update_requests'), 5, 2);
        add_filter('pre_http_request', array($this, 'block_external_api_requests'), 5, 3);

        // Filtrar headers de plugins
        add_filter('extra_plugin_headers', array($this, 'remove_update_uri_header'));
        add_filter('all_plugins', array($this, 'filter_plugin_headers'), 50);

        // Bloquear custom update URIs (prioridad 50)
        add_filter('update_plugins_api.wordpress.org', '__return_false', 50);
        add_filter('update_plugins_woocommerce.com', '__return_false', 50);

        // Hook genérico para deshabilitar sistemas de actualización
        add_action('plugins_loaded', array($this, 'disable_custom_update_checks'), 5);

        // Limpiar cache después de actualizar un plugin
        add_action('upgrader_process_complete', array($this, 'clear_cache_after_update'), 10, 2);

        // Limpiar índice de plugins cuando se activan/desactivan
        add_action('activated_plugin', array($this, 'clear_plugin_index_cache'));
        add_action('deactivated_plugin', array($this, 'clear_plugin_index_cache'));
    }

    /**
     * Verificar actualizaciones disponibles
     * OPTIMIZACIÓN: Usa caché de managed files
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Solo verificar plugins habilitados
        $enabled_plugins = $this->config['enabled_plugins'];

        if (empty($enabled_plugins)) {
            return $transient;
        }

        // OPTIMIZACIÓN: Obtener managed files una sola vez
        $managed_files = $this->get_managed_plugin_files();

        // PASO 1: Limpiar actualizaciones previas de plugins gestionados
        foreach ($managed_files as $plugin_file) {
            // Remover actualizaciones existentes de otros sistemas
            if (isset($transient->response[$plugin_file])) {
                unset($transient->response[$plugin_file]);
            }
        }

        // PASO 2: Preparar lista de plugins para verificar
        $plugins_to_check = array();

        foreach ($enabled_plugins as $plugin_slug) {
            $plugin_file = $this->find_plugin_file($plugin_slug);

            if ($plugin_file && isset($transient->checked[$plugin_file])) {
                $plugins_to_check[$plugin_slug] = $transient->checked[$plugin_file];
            }
        }

        if (empty($plugins_to_check)) {
            return $transient;
        }

        // OPTIMIZACIÓN: Caché de 12 horas para evitar peticiones HTTP constantes
        $cache_key = 'imagina_updater_check_' . md5(serialize($plugins_to_check) . $this->config['server_url']);
        $cached_updates = get_transient($cache_key);

        // Si hay caché válido, usar esos datos
        if ($cached_updates !== false) {
            $updates = $cached_updates;
        } else {
            // PASO 3: Consultar servidor solo si no hay caché
            $updates = $this->api_client->check_updates($plugins_to_check);

            if (is_wp_error($updates)) {
                // En caso de error, cachear respuesta vacía por 5 minutos
                set_transient($cache_key, array(), 5 * MINUTE_IN_SECONDS);
                return $transient;
            }

            // Cachear resultado exitoso por 12 horas
            set_transient($cache_key, $updates, 12 * HOUR_IN_SECONDS);
        }

        // PASO 4: Forzar actualizaciones desde nuestro servidor
        foreach ($updates as $plugin_slug => $update_data) {
            $plugin_file = $this->find_plugin_file($plugin_slug);

            if ($plugin_file) {
                // Obtener versión instalada actual
                $installed_version = '';
                if (isset($transient->checked[$plugin_file])) {
                    $installed_version = $transient->checked[$plugin_file];
                }

                // Solo agregar si hay una nueva versión disponible
                if (empty($installed_version) || version_compare($update_data['new_version'], $installed_version, '>')) {

                    // Crear objeto de actualización con flag especial
                    $update_object = (object) array(
                        'id' => 'imagina-updater/' . $plugin_slug,
                        'slug' => $plugin_slug,
                        'plugin' => $plugin_file,
                        'new_version' => $update_data['new_version'],
                        'url' => $update_data['homepage'],
                        'package' => $this->api_client->get_download_url($plugin_slug),
                        'tested' => $update_data['tested'],
                        'requires_php' => $update_data['requires_php'],
                        'compatibility' => new stdClass(),
                        'icons' => array(),
                        'banners' => array(),
                        'banners_rtl' => array(),
                        'requires' => '5.8',
                        '_imagina_updater' => true // Flag para identificar nuestras actualizaciones
                    );

                    // Forzar la actualización en response
                    $transient->response[$plugin_file] = $update_object;

                    // Remover de no_update si existe
                    if (isset($transient->no_update[$plugin_file])) {
                        unset($transient->no_update[$plugin_file]);
                    }
                }
            }
        }

        return $transient;
    }

    /**
     * Inyectar actualizaciones cuando se lee el transient (para evitar problemas con cache)
     * OPTIMIZACIÓN: Retorna temprano si ya tenemos updates, usa caché agresivo
     */
    public function inject_updates_on_read($transient) {
        if (empty($transient) || !is_object($transient)) {
            return $transient;
        }

        $enabled_plugins = $this->config['enabled_plugins'];
        if (empty($enabled_plugins)) {
            return $transient;
        }

        // Verificar si ya tenemos actualizaciones de Imagina Updater en el transient
        $has_our_updates = false;
        if (!empty($transient->response)) {
            foreach ($transient->response as $update) {
                if (is_object($update) && isset($update->_imagina_updater) && $update->_imagina_updater === true) {
                    $has_our_updates = true;
                    break;
                }
            }
        }

        // Si ya tenemos nuestras actualizaciones, no hacer nada más
        if ($has_our_updates) {
            return $transient;
        }

        // Verificar cache de actualizaciones (válido por 1 hora)
        $cache_key = 'imagina_updater_cached_updates';
        $cached_updates = get_transient($cache_key);

        if ($cached_updates !== false && is_array($cached_updates)) {
            // Usar actualizaciones cacheadas
            foreach ($cached_updates as $plugin_file => $update_object) {
                $transient->response[$plugin_file] = $update_object;

                // Remover de no_update si existe
                if (isset($transient->no_update[$plugin_file])) {
                    unset($transient->no_update[$plugin_file]);
                }
            }

            return $transient;
        }

        // OPTIMIZACIÓN: Si no hay caché pero el transient tiene checked, usar eso en lugar de get_plugin_data
        // get_plugin_data es MUY costoso porque abre y parsea archivos
        $plugins_to_check = array();

        if (!empty($transient->checked)) {
            foreach ($enabled_plugins as $plugin_slug) {
                $plugin_file = $this->find_plugin_file($plugin_slug);

                if ($plugin_file && isset($transient->checked[$plugin_file])) {
                    $plugins_to_check[$plugin_slug] = $transient->checked[$plugin_file];
                }
            }
        }

        // Si no podemos usar checked, retornar temprano (no hacer consultas costosas)
        if (empty($plugins_to_check)) {
            return $transient;
        }

        // Consultar servidor
        $updates = $this->api_client->check_updates($plugins_to_check);

        if (is_wp_error($updates)) {
            return $transient;
        }

        // Preparar actualizaciones y cachearlas
        $updates_to_cache = array();

        foreach ($updates as $plugin_slug => $update_data) {
            $plugin_file = $this->find_plugin_file($plugin_slug);

            if ($plugin_file && isset($plugins_to_check[$plugin_slug])) {
                $installed_version = $plugins_to_check[$plugin_slug];

                // Solo agregar si hay una nueva versión disponible
                if (version_compare($update_data['new_version'], $installed_version, '>')) {
                    $update_object = (object) array(
                        'id' => 'imagina-updater/' . $plugin_slug,
                        'slug' => $plugin_slug,
                        'plugin' => $plugin_file,
                        'new_version' => $update_data['new_version'],
                        'url' => $update_data['homepage'],
                        'package' => $this->api_client->get_download_url($plugin_slug),
                        'tested' => $update_data['tested'],
                        'requires_php' => $update_data['requires_php'],
                        'compatibility' => new stdClass(),
                        'icons' => array(),
                        'banners' => array(),
                        'banners_rtl' => array(),
                        'requires' => '5.8',
                        '_imagina_updater' => true
                    );

                    $transient->response[$plugin_file] = $update_object;
                    $updates_to_cache[$plugin_file] = $update_object;

                    // Remover de no_update si existe
                    if (isset($transient->no_update[$plugin_file])) {
                        unset($transient->no_update[$plugin_file]);
                    }
                }
            }
        }

        // Cachear actualizaciones por 1 hora
        if (!empty($updates_to_cache)) {
            set_transient($cache_key, $updates_to_cache, HOUR_IN_SECONDS);
        }

        return $transient;
    }

    /**
     * Proporcionar información del plugin para el modal de detalles
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        // Verificar si es uno de nuestros plugins
        if (!in_array($args->slug, $this->config['enabled_plugins'])) {
            return $result;
        }

        // Obtener información del servidor
        $plugin_info = $this->api_client->get_plugin_info($args->slug);

        if (is_wp_error($plugin_info)) {
            return $result;
        }

        // Convertir a objeto para WordPress
        $result = (object) array(
            'name' => $plugin_info['name'],
            'slug' => $plugin_info['slug'],
            'version' => $plugin_info['version'],
            'author' => $plugin_info['author'],
            'homepage' => $plugin_info['homepage'],
            'requires' => '5.8',
            'tested' => get_bloginfo('version'),
            'requires_php' => '7.4',
            'last_updated' => $plugin_info['last_updated'],
            'sections' => array(
                'description' => $plugin_info['description'],
            ),
            'download_link' => $this->api_client->get_download_url($plugin_info['slug'])
        );

        return $result;
    }

    /**
     * Bloquear actualizaciones externas para plugins gestionados
     * Este se ejecuta al final (PHP_INT_MAX) para limpiar cualquier cosa que hayan agregado otros plugins
     * OPTIMIZACIÓN: Usa caché de managed files
     */
    public function block_external_updates($transient) {
        if (empty($transient) || !is_object($transient)) {
            return $transient;
        }

        // OPTIMIZACIÓN: Usar método cacheado
        $managed_plugin_files = $this->get_managed_plugin_files();

        if (empty($managed_plugin_files)) {
            return $transient;
        }

        // LIMPIEZA AGRESIVA: Remover TODAS las actualizaciones externas de plugins gestionados
        if (!empty($transient->response)) {
            foreach ($managed_plugin_files as $plugin_file) {
                if (isset($transient->response[$plugin_file])) {
                    $update = $transient->response[$plugin_file];

                    // Determinar si es nuestra actualización
                    $is_our_update = false;

                    if (is_object($update)) {
                        // Verificar por flag especial
                        if (isset($update->_imagina_updater) && $update->_imagina_updater === true) {
                            $is_our_update = true;
                        }
                        // Verificar por URL del package
                        elseif (isset($update->package) && strpos($update->package, $this->config['server_url']) !== false) {
                            $is_our_update = true;
                        }
                        // Verificar por ID
                        elseif (isset($update->id) && strpos($update->id, 'imagina-updater/') === 0) {
                            $is_our_update = true;
                        }
                    }

                    // Si NO es nuestra actualización, removerla completamente
                    if (!$is_our_update) {
                        unset($transient->response[$plugin_file]);
                    }
                }
            }
        }

        // También limpiar de no_update (algunos plugins lo usan)
        if (!empty($transient->no_update)) {
            foreach ($managed_plugin_files as $plugin_file) {
                if (isset($transient->no_update[$plugin_file])) {
                    unset($transient->no_update[$plugin_file]);
                }
            }
        }

        return $transient;
    }

    /**
     * Bloquear peticiones HTTP de actualización para plugins gestionados
     */
    public function block_update_requests($args, $url) {
        // Bloquear peticiones a api.wordpress.org y otros servicios de actualización
        if (strpos($url, 'api.wordpress.org') !== false ||
            strpos($url, '/update-check/') !== false ||
            strpos($url, '/plugin-updates/') !== false) {

            $enabled_plugins = $this->config['enabled_plugins'];

            if (!empty($enabled_plugins)) {
                // Si la petición es para verificar actualizaciones de plugins,
                // filtrar los plugins gestionados del body
                if (isset($args['body']['plugins'])) {
                    $plugins_data = json_decode($args['body']['plugins'], true);

                    if (is_array($plugins_data) && isset($plugins_data['plugins'])) {
                        foreach ($enabled_plugins as $plugin_slug) {
                            $plugin_file = $this->find_plugin_file($plugin_slug);
                            if ($plugin_file && isset($plugins_data['plugins'][$plugin_file])) {
                                unset($plugins_data['plugins'][$plugin_file]);
                            }
                        }

                        $args['body']['plugins'] = json_encode($plugins_data);
                    }
                }
            }
        }

        return $args;
    }

    /**
     * Inyectar headers de autenticación en peticiones de descarga
     * (Más seguro que pasar API key en URL)
     */
    public function inject_auth_headers($args, $url) {
        // Solo inyectar para descargas de nuestro servidor
        if (strpos($url, $this->config['server_url']) !== false &&
            strpos($url, '/imagina-updater/v1/download/') !== false) {

            if (!isset($args['headers'])) {
                $args['headers'] = array();
            }

            // Obtener dominio del sitio
            $site_domain = parse_url(home_url(), PHP_URL_HOST);

            // Inyectar headers de autorización
            $args['headers']['Authorization'] = 'Bearer ' . $this->api_client->get_api_key();
            $args['headers']['X-Site-Domain'] = $site_domain; // Requerido para validación de activation token
        }

        return $args;
    }

    /**
     * Sobrescribir resultados de plugins_api para plugins gestionados
     */
    public function override_plugin_api_result($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        // Verificar si es uno de nuestros plugins
        if (isset($args->slug) && in_array($args->slug, $this->config['enabled_plugins'])) {
            return $this->plugin_info($result, $action, $args);
        }

        return $result;
    }

    /**
     * Bloquear peticiones HTTP a APIs externas SOLO para plugins gestionados
     * (Evita falsos positivos bloqueando todos los requests)
     */
    public function block_external_api_requests($preempt, $args, $url) {
        // Solo procesar si tenemos plugins habilitados
        if (empty($this->config['enabled_plugins'])) {
            return $preempt;
        }

        // Lista de dominios de actualización a verificar
        $update_domains = array(
            'woocommerce.com',
            'woothemes.com',
            'automattic.com',
            'freemius.com',
            'appsero.com',
            'kernl.us',
            'wp-updates.com'
        );

        // Verificar si la URL es de un dominio de actualización
        $is_update_domain = false;
        foreach ($update_domains as $domain) {
            if (strpos($url, $domain) !== false) {
                $is_update_domain = true;
                break;
            }
        }

        if (!$is_update_domain) {
            return $preempt;
        }

        // Verificar si es una petición de actualización
        $is_update_request = (
            strpos($url, '/update') !== false ||
            strpos($url, '/version') !== false ||
            strpos($url, '/api') !== false ||
            strpos($url, '/check') !== false
        );

        if (!$is_update_request) {
            return $preempt;
        }

        // Intentar identificar qué plugin está involucrado en el request
        $managed_plugin_identified = false;

        // Buscar en el body del request
        if (isset($args['body'])) {
            $body_content = is_string($args['body']) ? $args['body'] : json_encode($args['body']);

            // Buscar menciones de nuestros plugins gestionados
            foreach ($this->config['enabled_plugins'] as $plugin_slug) {
                if (stripos($body_content, $plugin_slug) !== false) {
                    $managed_plugin_identified = true;
                    break;
                }

                // También verificar por el archivo del plugin
                $plugin_file = $this->find_plugin_file($plugin_slug);
                if ($plugin_file && stripos($body_content, $plugin_file) !== false) {
                    $managed_plugin_identified = true;
                    break;
                }
            }
        }

        // Solo bloquear si se identificó un plugin gestionado
        if ($managed_plugin_identified) {
            return array(
                'response' => array(
                    'code' => 200,
                    'message' => 'OK'
                ),
                'body' => json_encode(array()),
                'headers' => array(),
                'cookies' => array()
            );
        }

        return $preempt;
    }

    /**
     * Remover Update URI header para plugins gestionados
     */
    public function remove_update_uri_header($headers) {
        // Este filtro se llama al leer headers de plugins
        return $headers;
    }

    /**
     * Filtrar headers de plugins para remover Update URI de plugins gestionados
     */
    public function filter_plugin_headers($plugins) {
        if (empty($this->config['enabled_plugins'])) {
            return $plugins;
        }

        foreach ($plugins as $plugin_file => $plugin_data) {
            // Verificar si es un plugin gestionado
            $plugin_slug = dirname($plugin_file);
            if ($plugin_slug === '.') {
                $plugin_slug = basename($plugin_file, '.php');
            }

            if (in_array($plugin_slug, $this->config['enabled_plugins'])) {
                // Remover Update URI si existe
                if (isset($plugins[$plugin_file]['UpdateURI'])) {
                    unset($plugins[$plugin_file]['UpdateURI']);
                }
                if (isset($plugins[$plugin_file]['Update URI'])) {
                    unset($plugins[$plugin_file]['Update URI']);
                }
            }
        }

        return $plugins;
    }

    /**
     * Limpiar cache después de actualizar un plugin
     */
    public function clear_cache_after_update($upgrader_object, $options) {
        // Solo limpiar si fue una actualización de plugin
        if ($options['action'] !== 'update' || $options['type'] !== 'plugin') {
            return;
        }

        // Limpiar cache de actualizaciones (incluyendo el nuevo caché con wildcard)
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_imagina_updater_check_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_imagina_updater_check_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_imagina_updater_managed_files_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_imagina_updater_managed_files_%'");

        delete_transient('imagina_updater_cached_updates');
        delete_transient('imagina_updater_plugin_index');
        delete_site_transient('update_plugins');

        // Limpiar caché en memoria
        $this->plugin_index = null;
        $this->managed_plugin_files = null;
    }

    /**
     * Limpiar caché del índice de plugins
     * Se ejecuta cuando se activan/desactivan plugins
     */
    public function clear_plugin_index_cache() {
        global $wpdb;

        delete_transient('imagina_updater_plugin_index');
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_imagina_updater_managed_files_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_imagina_updater_managed_files_%'");

        // Limpiar también la caché en memoria para esta request
        $this->plugin_index = null;
        $this->managed_plugin_files = null;
    }

    /**
     * Obtener archivos de plugins gestionados (con caché)
     * OPTIMIZACIÓN: Cachea la lista para evitar llamadas repetidas a find_plugin_file()
     */
    private function get_managed_plugin_files() {
        // Si ya lo calculamos en esta request, retornar
        if ($this->managed_plugin_files !== null) {
            return $this->managed_plugin_files;
        }

        if (empty($this->config['enabled_plugins'])) {
            $this->managed_plugin_files = array();
            return $this->managed_plugin_files;
        }

        // Intentar cargar del caché transient
        $cache_key = 'imagina_updater_managed_files_' . md5(serialize($this->config['enabled_plugins']));
        $cached = get_transient($cache_key);

        if ($cached !== false && is_array($cached)) {
            $this->managed_plugin_files = $cached;
            return $this->managed_plugin_files;
        }

        // Si no hay caché, calcular
        $managed_files = array();
        foreach ($this->config['enabled_plugins'] as $slug) {
            $file = $this->find_plugin_file($slug);
            if ($file) {
                $managed_files[] = $file;
            }
        }

        // Cachear por 12 horas
        set_transient($cache_key, $managed_files, 12 * HOUR_IN_SECONDS);

        $this->managed_plugin_files = $managed_files;
        return $this->managed_plugin_files;
    }

    /**
     * Deshabilitar verificaciones de actualización personalizadas
     * OPTIMIZACIÓN: Solo se ejecuta una vez por request
     */
    public function disable_custom_update_checks() {
        // Early return si ya se ejecutó
        if ($this->custom_updates_disabled) {
            return;
        }

        $managed_files = $this->get_managed_plugin_files();

        if (empty($managed_files)) {
            $this->custom_updates_disabled = true;
            return;
        }

        // Deshabilitar hooks comunes de sistemas de actualización de terceros
        $this->disable_woocommerce_updates($managed_files);
        $this->disable_freemius_updates($managed_files);
        $this->disable_edd_updates($managed_files);

        // Marcar como ejecutado
        $this->custom_updates_disabled = true;
    }

    /**
     * Deshabilitar actualizaciones de WooCommerce
     */
    private function disable_woocommerce_updates($managed_files) {
        global $wp_filter;

        // Si existe la clase de WooCommerce Helper
        if (class_exists('WC_Helper_Updater')) {
            remove_filter('pre_set_site_transient_update_plugins', array('WC_Helper_Updater', 'transient_update_plugins'));
        }

        // Remover hooks específicos de WooCommerce que puedan interferir
        if (isset($wp_filter['pre_set_site_transient_update_plugins'])) {
            foreach ($wp_filter['pre_set_site_transient_update_plugins']->callbacks as $priority => $callbacks) {
                if ($priority > 5 && $priority < PHP_INT_MAX) { // No remover el nuestro (prioridad 5) ni el bloqueador final
                    foreach ($callbacks as $callback) {
                        if (is_array($callback['function'])) {
                            $class = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                            if (strpos($class, 'WC_') === 0 || strpos($class, 'WooCommerce') !== false) {
                                remove_filter('pre_set_site_transient_update_plugins', $callback['function'], $priority);
                            }
                        }
                    }
                }
            }
        }

        // Bloquear filtros update_plugins_* de WooCommerce
        add_filter('update_plugins_woocommerce.com', function($update, $plugin_data, $plugin_file) use ($managed_files) {
            if (in_array($plugin_file, $managed_files)) {
                return false;
            }
            return $update;
        }, PHP_INT_MAX, 3);
    }

    /**
     * Deshabilitar actualizaciones de Freemius
     */
    private function disable_freemius_updates($managed_files) {
        // Freemius usa una clase global
        if (function_exists('fs_dynamic_init')) {
            foreach ($managed_files as $file) {
                add_filter('fs_is_plugin_update_' . dirname($file), '__return_false', PHP_INT_MAX);
            }
        }
    }

    /**
     * Deshabilitar actualizaciones de Easy Digital Downloads (EDD)
     */
    private function disable_edd_updates($managed_files) {
        // EDD Software Licensing
        if (class_exists('EDD_SL_Plugin_Updater')) {
            foreach ($managed_files as $file) {
                remove_action('admin_init', array('EDD_SL_Plugin_Updater', 'check_for_updates'));
            }
        }
    }

    /**
     * Verificar si se puede detectar un plugin por su slug
     * Método público para evitar uso de Reflection
     *
     * @param string $slug Slug del plugin
     * @return bool True si el plugin puede ser detectado
     */
    public function can_detect_plugin($slug) {
        return $this->find_plugin_file($slug) !== false;
    }

    /**
     * Construir índice de plugins instalados para búsquedas rápidas
     * Se ejecuta una sola vez y cachea todos los mapeos posibles
     */
    private function build_plugin_index() {
        if ($this->plugin_index !== null) {
            return; // Ya construido en memoria
        }

        // OPTIMIZACIÓN: Intentar cargar desde caché persistente (transient)
        $cached_index = get_transient('imagina_updater_plugin_index');
        if ($cached_index !== false && is_array($cached_index)) {
            $this->plugin_index = $cached_index;
            return;
        }

        // Si no hay caché, construir el índice (operación costosa)
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $this->plugin_index = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_slug = dirname($plugin_file);
            $file_basename = basename($plugin_file, '.php');

            // Índice 1: Por slug del directorio
            if ($plugin_slug !== '.') {
                $this->plugin_index[strtolower($plugin_slug)] = $plugin_file;
            } else {
                // Índice 2: Por nombre de archivo (plugins de archivo único)
                $this->plugin_index[strtolower($file_basename)] = $plugin_file;
            }

            // Índice 3: Por TextDomain
            if (!empty($plugin_data['TextDomain'])) {
                $text_domain_lower = strtolower($plugin_data['TextDomain']);
                if (!isset($this->plugin_index[$text_domain_lower])) {
                    $this->plugin_index[$text_domain_lower] = $plugin_file;
                }
            }

            // Índice 4: Por nombre sanitizado (pre-calculado)
            $plugin_name_slug = strtolower(sanitize_title($plugin_data['Name']));
            if (!isset($this->plugin_index[$plugin_name_slug])) {
                $this->plugin_index[$plugin_name_slug] = $plugin_file;
            }
        }

        // Cachear el índice por 12 horas (evita llamadas costosas a get_plugins())
        set_transient('imagina_updater_plugin_index', $this->plugin_index, 12 * HOUR_IN_SECONDS);
    }

    /**
     * Encontrar archivo del plugin por slug (optimizado con índice pre-construido)
     */
    private function find_plugin_file($slug) {
        // Verificar caché primero
        if (isset($this->plugin_file_cache[$slug])) {
            return $this->plugin_file_cache[$slug];
        }

        // Construir índice si no existe (solo se ejecuta una vez)
        $this->build_plugin_index();

        $slug_lower = strtolower($slug);

        // Búsqueda directa en índice (O(1) en lugar de O(n))
        $found = isset($this->plugin_index[$slug_lower]) ? $this->plugin_index[$slug_lower] : false;

        // Si no se encontró, intentar coincidencia parcial como fallback
        if (!$found) {
            foreach ($this->plugin_index as $indexed_slug => $plugin_file) {
                // Coincidencia parcial: slug-* (ej: "plugin" coincide con "plugin-pro")
                if (strpos($indexed_slug, $slug_lower . '-') === 0) {
                    $found = $plugin_file;
                    break;
                }
            }
        }

        // Cachear resultado (positivo o negativo)
        $this->plugin_file_cache[$slug] = $found;

        return $found;
    }

    /**
     * Obtener todos los plugins instalados que podrían ser gestionados
     */
    public function get_installed_plugins() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $plugins = array();

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_slug = dirname($plugin_file);

            if ($plugin_slug === '.') {
                $plugin_slug = basename($plugin_file, '.php');
            }

            $plugins[$plugin_slug] = array(
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'file' => $plugin_file,
                'slug' => $plugin_slug
            );
        }

        return $plugins;
    }
}
