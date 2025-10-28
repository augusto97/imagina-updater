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
        imagina_updater_log('Constructor de Updater ejecutado');

        $this->config = imagina_updater_client()->get_config();
        imagina_updater_log('Configuración cargada en Updater: ' . print_r($this->config, true));

        // Crear cliente API
        $this->api_client = new Imagina_Updater_Client_API(
            $this->config['server_url'],
            $this->config['api_key']
        );

        imagina_updater_log('Cliente API creado');

        $this->init_hooks();
        imagina_updater_log('Hooks inicializados');
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        imagina_updater_log('Registrando hooks...');

        // Hook principal para verificar actualizaciones (prioridad 10 - estándar)
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'), 10);
        imagina_updater_log('Hook pre_set_site_transient_update_plugins registrado');

        // Hook para bloquear actualizaciones externas (prioridad 100 - tarde pero no extremo)
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

        imagina_updater_log('Todos los hooks registrados correctamente');
    }

    /**
     * Verificar actualizaciones disponibles
     */
    public function check_for_updates($transient) {
        imagina_updater_log('check_for_updates EJECUTADO');

        if (empty($transient->checked)) {
            imagina_updater_log('transient->checked está vacío');
            return $transient;
        }

        // Solo verificar plugins habilitados
        $enabled_plugins = $this->config['enabled_plugins'];
        imagina_updater_log('Plugins habilitados: ' . print_r($enabled_plugins, true));

        if (empty($enabled_plugins)) {
            imagina_updater_log('No hay plugins habilitados, retornando');
            return $transient;
        }

        // PASO 1: Limpiar actualizaciones previas de plugins gestionados
        imagina_updater_log('PASO 1 - Limpiar actualizaciones previas');
        $managed_files = array();
        foreach ($enabled_plugins as $plugin_slug) {
            imagina_updater_log('Buscando archivo para plugin: ' . $plugin_slug);
            $plugin_file = $this->find_plugin_file($plugin_slug);
            imagina_updater_log('Archivo encontrado: ' . ($plugin_file ? $plugin_file : 'NO ENCONTRADO'));

            if ($plugin_file) {
                $managed_files[] = $plugin_file;
                // Remover actualizaciones existentes de otros sistemas
                if (isset($transient->response[$plugin_file])) {
                    unset($transient->response[$plugin_file]);
                    imagina_updater_log('Actualización externa removida para: ' . $plugin_file);
                }
            }
        }

        // PASO 2: Preparar lista de plugins para verificar
        imagina_updater_log('PASO 2 - Preparar lista de plugins para verificar');
        imagina_updater_log('Plugins en transient->checked: ' . print_r(array_keys($transient->checked), true));
        $plugins_to_check = array();

        foreach ($enabled_plugins as $plugin_slug) {
            $plugin_file = $this->find_plugin_file($plugin_slug);

            if ($plugin_file) {
                imagina_updater_log('Plugin ' . $plugin_slug . ' -> archivo: ' . $plugin_file);

                if (isset($transient->checked[$plugin_file])) {
                    $plugins_to_check[$plugin_slug] = $transient->checked[$plugin_file];
                    imagina_updater_log('Plugin agregado a verificar: ' . $plugin_slug . ' v' . $transient->checked[$plugin_file]);
                } else {
                    imagina_updater_log('Plugin NO está en transient->checked: ' . $plugin_file, 'warning');
                }
            } else {
                imagina_updater_log('NO se encontró archivo para plugin: ' . $plugin_slug, 'error');
            }
        }

        imagina_updater_log('Total de plugins a verificar: ' . count($plugins_to_check));

        if (empty($plugins_to_check)) {
            imagina_updater_log('No hay plugins para verificar, retornando');
            return $transient;
        }

        // PASO 3: Consultar servidor
        imagina_updater_log('PASO 3 - Consultando servidor');
        imagina_updater_log('Enviando al servidor: ' . print_r($plugins_to_check, true));
        $updates = $this->api_client->check_updates($plugins_to_check);

        if (is_wp_error($updates)) {
            // Log del error
            imagina_updater_log('ERROR del servidor: ' . $updates->get_error_message(), 'error');
            return $transient;
        }

        imagina_updater_log('Respuesta del servidor: ' . print_r($updates, true));

        // PASO 4: Forzar actualizaciones desde nuestro servidor
        imagina_updater_log('PASO 4 - Procesar actualizaciones recibidas');
        foreach ($updates as $plugin_slug => $update_data) {
            imagina_updater_log('Procesando actualización para: ' . $plugin_slug);
            $plugin_file = $this->find_plugin_file($plugin_slug);

            if ($plugin_file) {
                imagina_updater_log('Creando objeto de actualización para: ' . $plugin_file);
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
                imagina_updater_log('Actualización agregada a transient->response para: ' . $plugin_file);

                // Remover de no_update si existe
                if (isset($transient->no_update[$plugin_file])) {
                    unset($transient->no_update[$plugin_file]);
                    imagina_updater_log('Plugin removido de no_update: ' . $plugin_file);
                }
            } else {
                imagina_updater_log('ERROR - No se encontró archivo para plugin: ' . $plugin_slug, 'error');
            }
        }

        imagina_updater_log('Proceso completado exitosamente. Total de actualizaciones agregadas: ' . count($transient->response));

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
     */
    public function block_external_updates($transient) {
        if (empty($transient) || !is_object($transient)) {
            return $transient;
        }

        $enabled_plugins = $this->config['enabled_plugins'];

        if (empty($enabled_plugins)) {
            return $transient;
        }

        // Obtener todos los plugin files de los plugins gestionados
        $managed_plugin_files = array();
        foreach ($enabled_plugins as $plugin_slug) {
            $plugin_file = $this->find_plugin_file($plugin_slug);
            if ($plugin_file) {
                $managed_plugin_files[] = $plugin_file;
            }
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

            // Inyectar header de autorización
            $args['headers']['Authorization'] = 'Bearer ' . $this->api_client->get_api_key();

            imagina_updater_log('Headers de autenticación inyectados para descarga: ' . $url);
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
                    imagina_updater_log('Bloqueando request externo para plugin gestionado: ' . $plugin_slug . ' a ' . $url);
                    break;
                }

                // También verificar por el archivo del plugin
                $plugin_file = $this->find_plugin_file($plugin_slug);
                if ($plugin_file && stripos($body_content, $plugin_file) !== false) {
                    $managed_plugin_identified = true;
                    imagina_updater_log('Bloqueando request externo para plugin gestionado: ' . $plugin_file . ' a ' . $url);
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
     * Deshabilitar verificaciones de actualización personalizadas
     */
    public function disable_custom_update_checks() {
        if (empty($this->config['enabled_plugins'])) {
            return;
        }

        // Obtener los archivos de plugins gestionados
        $managed_files = array();
        foreach ($this->config['enabled_plugins'] as $slug) {
            $file = $this->find_plugin_file($slug);
            if ($file) {
                $managed_files[] = $file;
            }
        }

        if (empty($managed_files)) {
            return;
        }

        // Deshabilitar hooks comunes de sistemas de actualización de terceros
        $this->disable_woocommerce_updates($managed_files);
        $this->disable_freemius_updates($managed_files);
        $this->disable_edd_updates($managed_files);
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
     * Encontrar archivo del plugin por slug (optimizado con caché y una sola iteración)
     */
    private function find_plugin_file($slug) {
        // Verificar caché primero
        if (isset($this->plugin_file_cache[$slug])) {
            imagina_updater_log('✓ Plugin encontrado en caché: ' . $slug . ' -> ' . $this->plugin_file_cache[$slug]);
            return $this->plugin_file_cache[$slug];
        }

        imagina_updater_log('find_plugin_file() iniciado para: ' . $slug);

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $slug_lower = strtolower($slug);
        imagina_updater_log('Total de plugins instalados: ' . count($all_plugins));

        // Criterios de búsqueda con prioridad
        $found = false;
        $partial_match = false;

        // Una sola iteración para todos los criterios
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_slug = dirname($plugin_file);
            $file_basename = strtolower(basename($plugin_file, '.php'));

            // Criterio 1: Slug del directorio (EXACTO) - Prioridad alta
            if ($plugin_slug !== '.' && strtolower($plugin_slug) === $slug_lower) {
                imagina_updater_log('✓ Encontrado por Criterio 1 (directorio): ' . $plugin_file);
                $found = $plugin_file;
                break; // Salir inmediatamente, es coincidencia exacta
            }

            // Criterio 2: Nombre del archivo (para plugins de archivo único) - Prioridad alta
            if ($plugin_slug === '.' && $file_basename === $slug_lower) {
                imagina_updater_log('✓ Encontrado por Criterio 2 (archivo único): ' . $plugin_file);
                $found = $plugin_file;
                break;
            }

            // Criterio 3: Comparar con el nombre sanitizado del plugin (EXACTO) - Prioridad media
            if (!$found) {
                $plugin_name_slug = sanitize_title($plugin_data['Name']);
                if (strtolower($plugin_name_slug) === $slug_lower) {
                    imagina_updater_log('✓ Encontrado por Criterio 3 (nombre sanitizado): ' . $plugin_file);
                    $found = $plugin_file;
                    break;
                }
            }

            // Criterio 4: Comparar con TextDomain si está definido (EXACTO) - Prioridad media
            if (!$found && !empty($plugin_data['TextDomain'])) {
                if (strtolower($plugin_data['TextDomain']) === $slug_lower) {
                    imagina_updater_log('✓ Encontrado por Criterio 4 (TextDomain): ' . $plugin_file);
                    $found = $plugin_file;
                    break;
                }
            }

            // Criterio 5 (ÚLTIMA OPCIÓN): Coincidencia parcial - Prioridad baja
            // Solo si no se ha encontrado coincidencia exacta
            if (!$found && !$partial_match) {
                if ($plugin_slug !== '.' && strpos($plugin_slug, $slug_lower . '-') === 0) {
                    imagina_updater_log('⚠ Posible coincidencia parcial (Criterio 5 - slug): ' . $plugin_file);
                    $partial_match = $plugin_file;
                }

                if (!$partial_match && strpos($file_basename, $slug_lower . '-') === 0) {
                    imagina_updater_log('⚠ Posible coincidencia parcial (Criterio 5 - basename): ' . $plugin_file);
                    $partial_match = $plugin_file;
                }
            }
        }

        // Si no se encontró coincidencia exacta, usar la parcial
        if (!$found && $partial_match) {
            imagina_updater_log('✓ Usando coincidencia parcial: ' . $partial_match);
            $found = $partial_match;
        }

        if ($found) {
            // Guardar en caché
            $this->plugin_file_cache[$slug] = $found;
            return $found;
        }

        imagina_updater_log('✗ NO se encontró archivo para slug: ' . $slug, 'warning');

        // Guardar resultado negativo en caché para evitar búsquedas repetidas
        $this->plugin_file_cache[$slug] = false;

        return false;
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
            // Saltar este plugin y plugins de WordPress.org que tienen update_uri
            if (
                $plugin_file === plugin_basename(IMAGINA_UPDATER_CLIENT_PLUGIN_FILE) ||
                strpos($plugin_file, 'imagina-updater-client') !== false
            ) {
                continue;
            }

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
