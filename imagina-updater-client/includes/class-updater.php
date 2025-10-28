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

        // Crear cliente API
        $this->api_client = new Imagina_Updater_Client_API(
            $this->config['server_url'],
            $this->config['api_key']
        );

        $this->init_hooks();
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        // Hook para verificar actualizaciones (prioridad alta para ejecutar primero)
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'), 20);

        // Hook para bloquear actualizaciones externas de plugins gestionados
        add_filter('site_transient_update_plugins', array($this, 'block_external_updates'), 999);

        // Hook para información del plugin en el modal de actualizaciones
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);

        // Hook para modificar la URL de descarga
        add_filter('upgrader_pre_download', array($this, 'modify_download_url'), 10, 3);

        // Bloquear HTTP requests de actualizaciones para plugins gestionados
        add_filter('http_request_args', array($this, 'block_update_requests'), 10, 2);
    }

    /**
     * Verificar actualizaciones disponibles
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

        // Preparar lista de plugins para verificar
        $plugins_to_check = array();

        foreach ($enabled_plugins as $plugin_slug) {
            // Buscar el plugin en los plugins instalados
            $plugin_file = $this->find_plugin_file($plugin_slug);

            if ($plugin_file && isset($transient->checked[$plugin_file])) {
                $plugins_to_check[$plugin_slug] = $transient->checked[$plugin_file];
            }
        }

        if (empty($plugins_to_check)) {
            return $transient;
        }

        // Consultar servidor
        $updates = $this->api_client->check_updates($plugins_to_check);

        if (is_wp_error($updates)) {
            // Log del error
            error_log('Imagina Updater Client: ' . $updates->get_error_message());
            return $transient;
        }

        // Agregar actualizaciones al transient
        foreach ($updates as $plugin_slug => $update_data) {
            $plugin_file = $this->find_plugin_file($plugin_slug);

            if ($plugin_file) {
                $transient->response[$plugin_file] = (object) array(
                    'slug' => $plugin_slug,
                    'plugin' => $plugin_file,
                    'new_version' => $update_data['new_version'],
                    'url' => $update_data['homepage'],
                    'package' => $this->api_client->get_download_url($plugin_slug),
                    'tested' => $update_data['tested'],
                    'requires_php' => $update_data['requires_php'],
                    'compatibility' => new stdClass()
                );
            }
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

        // Remover actualizaciones externas de plugins gestionados
        if (!empty($transient->response)) {
            foreach ($managed_plugin_files as $plugin_file) {
                // Solo remover si NO es una actualización de nuestro servidor
                if (isset($transient->response[$plugin_file])) {
                    $update = $transient->response[$plugin_file];

                    // Si no es de nuestro servidor, removerla
                    if (is_object($update) &&
                        isset($update->package) &&
                        strpos($update->package, $this->config['server_url']) === false) {
                        unset($transient->response[$plugin_file]);
                    }
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
     * Modificar URL de descarga si es necesario
     */
    public function modify_download_url($reply, $package, $upgrader) {
        // Si el package ya es de nuestro servidor, agregar autenticación si falta
        if (strpos($package, $this->config['server_url']) !== false) {
            // La URL ya incluye el api_key en get_download_url()
            return $reply;
        }

        return $reply;
    }

    /**
     * Encontrar archivo del plugin por slug (mejorado con múltiples criterios)
     */
    private function find_plugin_file($slug) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $slug_lower = strtolower($slug);

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            // Criterio 1: Slug del directorio
            $plugin_slug = dirname($plugin_file);

            if ($plugin_slug !== '.' && strtolower($plugin_slug) === $slug_lower) {
                return $plugin_file;
            }

            // Criterio 2: Nombre del archivo (para plugins de archivo único)
            if ($plugin_slug === '.' && strtolower(basename($plugin_file, '.php')) === $slug_lower) {
                return $plugin_file;
            }

            // Criterio 3: Comparar con el nombre sanitizado del plugin
            $plugin_name_slug = sanitize_title($plugin_data['Name']);
            if (strtolower($plugin_name_slug) === $slug_lower) {
                return $plugin_file;
            }

            // Criterio 4: Comparar con TextDomain si está definido
            if (!empty($plugin_data['TextDomain']) && strtolower($plugin_data['TextDomain']) === $slug_lower) {
                return $plugin_file;
            }

            // Criterio 5: Buscar coincidencia parcial en el nombre del archivo
            $file_basename = strtolower(basename($plugin_file, '.php'));
            if (strpos($file_basename, $slug_lower) !== false || strpos($slug_lower, $file_basename) !== false) {
                return $plugin_file;
            }
        }

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
