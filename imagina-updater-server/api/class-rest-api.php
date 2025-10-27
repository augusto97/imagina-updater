<?php
/**
 * API REST para consultas de actualizaciones
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_REST_API {

    /**
     * Namespace de la API
     */
    const NAMESPACE = 'imagina-updater/v1';

    /**
     * Instancia única
     */
    private static $instance = null;

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
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Registrar rutas de la API
     */
    public function register_routes() {
        // Listar todos los plugins disponibles
        register_rest_route(self::NAMESPACE, '/plugins', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_plugins'),
            'permission_callback' => array($this, 'check_api_key')
        ));

        // Obtener información de un plugin específico
        register_rest_route(self::NAMESPACE, '/plugin/(?P<slug>[a-zA-Z0-9-_]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_plugin_info'),
            'permission_callback' => array($this, 'check_api_key'),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                )
            )
        ));

        // Verificar actualizaciones para múltiples plugins
        register_rest_route(self::NAMESPACE, '/check-updates', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'check_updates'),
            'permission_callback' => array($this, 'check_api_key')
        ));

        // Descargar plugin
        register_rest_route(self::NAMESPACE, '/download/(?P<slug>[a-zA-Z0-9-_]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'download_plugin'),
            'permission_callback' => array($this, 'check_api_key'),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                )
            )
        ));

        // Validar API Key (para testing)
        register_rest_route(self::NAMESPACE, '/validate', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'validate_api_key'),
            'permission_callback' => array($this, 'check_api_key')
        ));
    }

    /**
     * Verificar API Key en la petición
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function check_api_key($request) {
        $api_key = $this->get_api_key_from_request($request);

        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                __('API Key es requerida', 'imagina-updater-server'),
                array('status' => 401)
            );
        }

        $key_data = Imagina_Updater_Server_API_Keys::validate($api_key);

        if (!$key_data) {
            return new WP_Error(
                'invalid_api_key',
                __('API Key inválida o inactiva', 'imagina-updater-server'),
                array('status' => 403)
            );
        }

        // Guardar datos de la API key en el request para uso posterior
        $request->set_param('_api_key_data', $key_data);

        return true;
    }

    /**
     * Obtener API Key del request
     */
    private function get_api_key_from_request($request) {
        // Intentar obtener de header Authorization
        $auth_header = $request->get_header('Authorization');
        if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
            return substr($auth_header, 7);
        }

        // Intentar obtener del header X-API-Key
        $api_key_header = $request->get_header('X-API-Key');
        if ($api_key_header) {
            return $api_key_header;
        }

        // Intentar obtener de parámetro de query
        return $request->get_param('api_key');
    }

    /**
     * Endpoint: Obtener lista de plugins
     */
    public function get_plugins($request) {
        $plugins = Imagina_Updater_Server_Plugin_Manager::get_all_plugins();

        $result = array();
        foreach ($plugins as $plugin) {
            $result[] = array(
                'slug' => $plugin->slug,
                'name' => $plugin->name,
                'description' => $plugin->description,
                'version' => $plugin->current_version,
                'author' => $plugin->author,
                'homepage' => $plugin->homepage,
                'last_updated' => $plugin->uploaded_at
            );
        }

        return rest_ensure_response($result);
    }

    /**
     * Endpoint: Obtener información de un plugin
     */
    public function get_plugin_info($request) {
        $slug = $request->get_param('slug');

        $plugin = Imagina_Updater_Server_Plugin_Manager::get_plugin_by_slug($slug);

        if (!$plugin) {
            return new WP_Error(
                'plugin_not_found',
                __('Plugin no encontrado', 'imagina-updater-server'),
                array('status' => 404)
            );
        }

        return rest_ensure_response(array(
            'slug' => $plugin->slug,
            'name' => $plugin->name,
            'description' => $plugin->description,
            'version' => $plugin->current_version,
            'author' => $plugin->author,
            'homepage' => $plugin->homepage,
            'last_updated' => $plugin->uploaded_at,
            'download_url' => rest_url(self::NAMESPACE . '/download/' . $plugin->slug)
        ));
    }

    /**
     * Endpoint: Verificar actualizaciones para múltiples plugins
     */
    public function check_updates($request) {
        $plugins = $request->get_param('plugins');

        if (!is_array($plugins) || empty($plugins)) {
            return new WP_Error(
                'invalid_request',
                __('Se requiere un array de plugins', 'imagina-updater-server'),
                array('status' => 400)
            );
        }

        $updates = array();

        foreach ($plugins as $plugin_slug => $installed_version) {
            $plugin = Imagina_Updater_Server_Plugin_Manager::get_plugin_by_slug($plugin_slug);

            if ($plugin && version_compare($plugin->current_version, $installed_version, '>')) {
                $updates[$plugin_slug] = array(
                    'slug' => $plugin->slug,
                    'new_version' => $plugin->current_version,
                    'package' => rest_url(self::NAMESPACE . '/download/' . $plugin->slug),
                    'tested' => get_bloginfo('version'),
                    'requires_php' => '7.4',
                    'last_updated' => $plugin->uploaded_at,
                    'name' => $plugin->name,
                    'author' => $plugin->author,
                    'homepage' => $plugin->homepage
                );
            }
        }

        return rest_ensure_response($updates);
    }

    /**
     * Endpoint: Descargar plugin
     */
    public function download_plugin($request) {
        $slug = $request->get_param('slug');
        $api_key_data = $request->get_param('_api_key_data');

        $plugin = Imagina_Updater_Server_Plugin_Manager::get_plugin_by_slug($slug);

        if (!$plugin) {
            return new WP_Error(
                'plugin_not_found',
                __('Plugin no encontrado', 'imagina-updater-server'),
                array('status' => 404)
            );
        }

        if (!file_exists($plugin->file_path)) {
            return new WP_Error(
                'file_not_found',
                __('Archivo del plugin no encontrado', 'imagina-updater-server'),
                array('status' => 404)
            );
        }

        // Registrar descarga
        Imagina_Updater_Server_Plugin_Manager::log_download(
            $api_key_data->id,
            $plugin->id,
            $plugin->current_version
        );

        // Enviar archivo
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($plugin->file_path) . '"');
        header('Content-Length: ' . filesize($plugin->file_path));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($plugin->file_path);
        exit;
    }

    /**
     * Endpoint: Validar API Key
     */
    public function validate_api_key($request) {
        $api_key_data = $request->get_param('_api_key_data');

        return rest_ensure_response(array(
            'valid' => true,
            'site_name' => $api_key_data->site_name,
            'site_url' => $api_key_data->site_url,
            'created_at' => $api_key_data->created_at
        ));
    }
}
