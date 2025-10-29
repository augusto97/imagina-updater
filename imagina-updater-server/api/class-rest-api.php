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
     * Verificar rate limiting simple (máx 60 peticiones por minuto por API key)
     */
    private function check_rate_limit($api_key) {
        $cache_key = 'imagina_updater_rate_limit_' . md5($api_key);
        $requests = get_transient($cache_key);

        if ($requests === false) {
            // Primera petición en este minuto
            set_transient($cache_key, 1, 60); // 60 segundos
            return true;
        }

        if ($requests >= 60) {
            return false; // Límite excedido
        }

        // Incrementar contador
        set_transient($cache_key, $requests + 1, 60);
        return true;
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

        // Verificar rate limiting
        if (!$this->check_rate_limit($api_key)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Límite de peticiones excedido. Máximo 60 peticiones por minuto.', 'imagina-updater-server'),
                array('status' => 429)
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
     * Filtrar plugins según permisos de la API key
     *
     * @param array $plugins Lista de plugins
     * @param object $api_key_data Datos de la API key
     * @return array Lista filtrada de plugins
     */
    private function filter_plugins_by_permissions($plugins, $api_key_data) {
        // Si no hay datos de API key o access_type es 'all', devolver todos
        if (!$api_key_data || $api_key_data->access_type === 'all') {
            return $plugins;
        }

        $allowed_plugin_ids = array();

        // Filtrar por plugins específicos
        if ($api_key_data->access_type === 'specific' && !empty($api_key_data->allowed_plugins)) {
            $allowed_plugin_ids = json_decode($api_key_data->allowed_plugins, true);
            if (!is_array($allowed_plugin_ids)) {
                $allowed_plugin_ids = array();
            }
        }

        // Filtrar por grupos
        elseif ($api_key_data->access_type === 'groups' && !empty($api_key_data->allowed_groups)) {
            $allowed_group_ids = json_decode($api_key_data->allowed_groups, true);
            if (!is_array($allowed_group_ids)) {
                $allowed_group_ids = array();
            }

            // Obtener todos los plugins de los grupos permitidos
            foreach ($allowed_group_ids as $group_id) {
                $group_plugin_ids = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_ids($group_id);
                $allowed_plugin_ids = array_merge($allowed_plugin_ids, $group_plugin_ids);
            }

            // Eliminar duplicados
            $allowed_plugin_ids = array_unique($allowed_plugin_ids);
        }

        // Si no hay plugins permitidos, devolver array vacío
        if (empty($allowed_plugin_ids)) {
            return array();
        }

        // Filtrar plugins
        $filtered = array();
        foreach ($plugins as $plugin) {
            if (in_array($plugin->id, $allowed_plugin_ids)) {
                $filtered[] = $plugin;
            }
        }

        return $filtered;
    }

    /**
     * Endpoint: Obtener lista de plugins
     */
    public function get_plugins($request) {
        // Obtener todos los plugins
        $all_plugins = Imagina_Updater_Server_Plugin_Manager::get_all_plugins();

        // Obtener datos de la API key para filtrar
        $api_key_data = $request->get_param('_api_key_data');

        // Filtrar según permisos
        $plugins = $this->filter_plugins_by_permissions($all_plugins, $api_key_data);

        $result = array();
        foreach ($plugins as $plugin) {
            // Usar slug_override si existe, sino slug
            $effective_slug = !empty($plugin->slug_override) ? $plugin->slug_override : $plugin->slug;

            $result[] = array(
                'slug' => $effective_slug,
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
     * Verificar si un plugin está permitido para la API key
     *
     * @param object $plugin Plugin a verificar
     * @param object $api_key_data Datos de la API key
     * @return bool
     */
    private function is_plugin_allowed($plugin, $api_key_data) {
        // Si no hay datos de API key o access_type es 'all', permitir
        if (!$api_key_data || $api_key_data->access_type === 'all') {
            return true;
        }

        $plugin_id = $plugin->id;

        // Verificar si está en plugins específicos
        if ($api_key_data->access_type === 'specific' && !empty($api_key_data->allowed_plugins)) {
            $allowed_plugin_ids = json_decode($api_key_data->allowed_plugins, true);
            if (is_array($allowed_plugin_ids) && in_array($plugin_id, $allowed_plugin_ids)) {
                return true;
            }
        }

        // Verificar si está en grupos permitidos
        if ($api_key_data->access_type === 'groups' && !empty($api_key_data->allowed_groups)) {
            $allowed_group_ids = json_decode($api_key_data->allowed_groups, true);
            if (is_array($allowed_group_ids)) {
                foreach ($allowed_group_ids as $group_id) {
                    $group_plugin_ids = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_ids($group_id);
                    if (in_array($plugin_id, $group_plugin_ids)) {
                        return true;
                    }
                }
            }
        }

        return false;
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

        // Verificar permisos
        $api_key_data = $request->get_param('_api_key_data');
        if (!$this->is_plugin_allowed($plugin, $api_key_data)) {
            return new WP_Error(
                'access_denied',
                __('No tienes permiso para acceder a este plugin', 'imagina-updater-server'),
                array('status' => 403)
            );
        }

        // Usar slug_override si existe
        $effective_slug = !empty($plugin->slug_override) ? $plugin->slug_override : $plugin->slug;

        return rest_ensure_response(array(
            'slug' => $effective_slug,
            'name' => $plugin->name,
            'description' => $plugin->description,
            'version' => $plugin->current_version,
            'author' => $plugin->author,
            'homepage' => $plugin->homepage,
            'last_updated' => $plugin->uploaded_at,
            'download_url' => rest_url(self::NAMESPACE . '/download/' . $effective_slug)
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
                // Usar slug_override si existe
                $effective_slug = !empty($plugin->slug_override) ? $plugin->slug_override : $plugin->slug;

                $updates[$plugin_slug] = array(
                    'slug' => $effective_slug,
                    'new_version' => $plugin->current_version,
                    'package' => rest_url(self::NAMESPACE . '/download/' . $effective_slug),
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

        // Verificar permisos
        if (!$this->is_plugin_allowed($plugin, $api_key_data)) {
            return new WP_Error(
                'access_denied',
                __('No tienes permiso para descargar este plugin', 'imagina-updater-server'),
                array('status' => 403)
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

        // Verificar que no se hayan enviado headers
        if (headers_sent($file, $line)) {
            error_log('IMAGINA UPDATER SERVER: Headers ya enviados en ' . $file . ':' . $line);
            return new WP_Error(
                'headers_sent',
                __('No se puede enviar el archivo, headers ya enviados', 'imagina-updater-server'),
                array('status' => 500)
            );
        }

        // Limpiar cualquier output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

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
