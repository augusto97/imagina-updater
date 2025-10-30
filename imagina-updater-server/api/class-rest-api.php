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
     * Obtener IP del cliente (compatible con proxies, CDN, load balancers)
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Cloudflare
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Proxy/Load Balancer
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            // Nginx
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }

        return filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : '';
    }

    /**
     * Verificar rate limiting mejorado con protección multinivel
     * - 60 peticiones por minuto por API key
     * - 100 peticiones por minuto por IP (para servidores compartidos)
     * - Ban temporal después de múltiples violaciones
     */
    private function check_rate_limit($api_key) {
        $client_ip = $this->get_client_ip();

        // Verificar si la IP está bloqueada temporalmente
        $ip_ban_key = 'imagina_updater_ip_ban_' . md5($client_ip);
        if (get_transient($ip_ban_key)) {
            imagina_updater_server_log('IP bloqueada temporalmente por múltiples violaciones: ' . $client_ip, 'warning');
            return false;
        }

        // Rate limiting por API key (60/minuto)
        $api_cache_key = 'imagina_updater_rate_api_' . md5($api_key);
        $api_requests = get_transient($api_cache_key);

        if ($api_requests === false) {
            set_transient($api_cache_key, 1, 60);
        } else {
            if ($api_requests >= 60) {
                $this->increment_violation($api_key, $client_ip);
                imagina_updater_server_log('Rate limit excedido para API key: ' . substr($api_key, 0, 10) . '...', 'warning');
                return false;
            }
            set_transient($api_cache_key, $api_requests + 1, 60);
        }

        // Rate limiting por IP (100/minuto) - más permisivo para hosting compartido
        if (!empty($client_ip)) {
            $ip_cache_key = 'imagina_updater_rate_ip_' . md5($client_ip);
            $ip_requests = get_transient($ip_cache_key);

            if ($ip_requests === false) {
                set_transient($ip_cache_key, 1, 60);
            } else {
                if ($ip_requests >= 100) {
                    $this->increment_violation($api_key, $client_ip);
                    imagina_updater_server_log('Rate limit excedido para IP: ' . $client_ip, 'warning');
                    return false;
                }
                set_transient($ip_cache_key, $ip_requests + 1, 60);
            }
        }

        return true;
    }

    /**
     * Incrementar contador de violaciones y aplicar ban temporal si es necesario
     */
    private function increment_violation($api_key, $ip) {
        if (empty($ip)) {
            return;
        }

        $violation_key = 'imagina_updater_violations_' . md5($ip);
        $violations = get_transient($violation_key) ?: 0;
        $violations++;

        set_transient($violation_key, $violations, 3600); // Rastrear por 1 hora

        // Ban temporal después de 5 violaciones en 1 hora
        if ($violations >= 5) {
            $ban_key = 'imagina_updater_ip_ban_' . md5($ip);
            set_transient($ban_key, true, 900); // Ban de 15 minutos
            imagina_updater_server_log('IP baneada temporalmente (15 min) por múltiples violaciones: ' . $ip . ' | API Key: ' . substr($api_key, 0, 10) . '...', 'error');
        }
    }

    /**
     * Registrar rutas de la API
     */
    public function register_routes() {
        // Listar todos los plugins disponibles (SOLO activation token)
        register_rest_route(self::NAMESPACE, '/plugins', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_plugins'),
            'permission_callback' => array($this, 'check_activation_token_only')
        ));

        // Obtener información de un plugin específico (SOLO activation token)
        register_rest_route(self::NAMESPACE, '/plugin/(?P<slug>[a-zA-Z0-9-_]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_plugin_info'),
            'permission_callback' => array($this, 'check_activation_token_only'),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                )
            )
        ));

        // Verificar actualizaciones para múltiples plugins (SOLO activation token)
        register_rest_route(self::NAMESPACE, '/check-updates', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'check_updates'),
            'permission_callback' => array($this, 'check_activation_token_only')
        ));

        // Descargar plugin (SOLO activation token)
        register_rest_route(self::NAMESPACE, '/download/(?P<slug>[a-zA-Z0-9-_]+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'download_plugin'),
            'permission_callback' => array($this, 'check_activation_token_only'),
            'args' => array(
                'slug' => array(
                    'required' => true,
                    'validate_callback' => function($param) {
                        return is_string($param);
                    }
                )
            )
        ));

        // Validar API Key (SOLO API key - antes de activar)
        register_rest_route(self::NAMESPACE, '/validate', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'validate_api_key'),
            'permission_callback' => array($this, 'check_api_key_only')
        ));

        // Activar sitio con API Key
        register_rest_route(self::NAMESPACE, '/activate', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'activate_site'),
            'permission_callback' => '__return_true', // No requiere autenticación previa
            'args' => array(
                'api_key' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                ),
                'site_domain' => array(
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function($param) {
                        return is_string($param) && !empty($param);
                    }
                )
            )
        ));

        // Desactivar sitio (admin - SOLO activation token)
        register_rest_route(self::NAMESPACE, '/deactivate/(?P<activation_id>\d+)', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'deactivate_site'),
            'permission_callback' => array($this, 'check_activation_token_only'),
            'args' => array(
                'activation_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    }
                )
            )
        ));

        // Desactivar sitio actual (cliente desactiva su propia licencia)
        register_rest_route(self::NAMESPACE, '/deactivate-self', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'deactivate_self'),
            'permission_callback' => array($this, 'check_activation_token_only')
        ));
    }

    /**
     * Verificar SOLO Activation Token (para endpoints regulares)
     * NO acepta API keys
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_activation_token_only($request) {
        $token = $this->get_api_key_from_request($request);

        if (empty($token)) {
            return new WP_Error(
                'missing_credentials',
                __('Se requiere token de activación. Activa tu sitio primero.', 'imagina-updater-server'),
                array('status' => 401)
            );
        }

        // SOLO aceptar activation tokens
        if (strpos($token, 'iat_') === 0) {
            return $this->validate_activation_token($request, $token);
        } elseif (strpos($token, 'ius_') === 0) {
            return new WP_Error(
                'api_key_not_allowed',
                __('Debe usar token de activación, no API Key. Por favor activa tu sitio primero.', 'imagina-updater-server'),
                array('status' => 403)
            );
        } else {
            return new WP_Error(
                'invalid_credentials',
                __('Formato de token inválido', 'imagina-updater-server'),
                array('status' => 401)
            );
        }
    }

    /**
     * Verificar SOLO API Key (para endpoint /validate)
     * NO acepta activation tokens
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function check_api_key_only($request) {
        $token = $this->get_api_key_from_request($request);

        if (empty($token)) {
            return new WP_Error(
                'missing_credentials',
                __('Se requiere API Key', 'imagina-updater-server'),
                array('status' => 401)
            );
        }

        // SOLO aceptar API keys
        if (strpos($token, 'ius_') === 0) {
            return $this->validate_api_key_auth($request, $token);
        } else {
            return new WP_Error(
                'invalid_credentials',
                __('Debe usar API Key para validación', 'imagina-updater-server'),
                array('status' => 401)
            );
        }
    }

    /**
     * Validar API Key
     */
    private function validate_api_key_auth($request, $api_key) {
        // Validar que el API key existe y es válido
        $key_data = Imagina_Updater_Server_API_Keys::validate($api_key);

        if (!$key_data) {
            return new WP_Error(
                'invalid_api_key',
                __('API Key inválida o inactiva', 'imagina-updater-server'),
                array('status' => 403)
            );
        }

        // Rate limiting
        if (!$this->check_rate_limit($api_key)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Límite de peticiones excedido. Máximo 60 peticiones por minuto.', 'imagina-updater-server'),
                array('status' => 429)
            );
        }

        // Guardar datos de la API key
        $request->set_param('_api_key_data', $key_data);

        return true;
    }

    /**
     * Validar Activation Token
     */
    private function validate_activation_token($request, $activation_token) {
        // Obtener dominio del cliente (enviado en headers o body)
        $site_domain = $request->get_header('X-Site-Domain');
        if (empty($site_domain)) {
            $site_domain = $request->get_param('site_domain');
        }
        if (empty($site_domain)) {
            $site_domain = home_url(); // Fallback
        }

        // Validar token
        $activation = Imagina_Updater_Server_Activations::validate_token($activation_token, $site_domain);

        if (!$activation) {
            return new WP_Error(
                'invalid_activation',
                __('Token de activación inválido o el sitio no coincide', 'imagina-updater-server'),
                array('status' => 403)
            );
        }

        // Rate limiting (usando el token como clave)
        if (!$this->check_rate_limit($activation_token)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Límite de peticiones excedido. Máximo 60 peticiones por minuto.', 'imagina-updater-server'),
                array('status' => 429)
            );
        }

        // Obtener datos del API key asociado
        $key_data = Imagina_Updater_Server_API_Keys::get_by_id($activation->api_key_id);

        // Guardar datos
        $request->set_param('_api_key_data', $key_data);
        $request->set_param('_activation_data', $activation);

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
     * Endpoint: Activar sitio
     */
    public function activate_site($request) {
        $api_key = $request->get_param('api_key');
        $site_domain = $request->get_param('site_domain');

        // Activar el sitio
        $result = Imagina_Updater_Server_Activations::activate_site($api_key, $site_domain);

        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($result);
    }

    /**
     * Endpoint: Desactivar sitio (admin)
     */
    public function deactivate_site($request) {
        $activation_id = $request->get_param('activation_id');

        $result = Imagina_Updater_Server_Activations::deactivate_site($activation_id);

        if (!$result) {
            return new WP_Error(
                'deactivation_failed',
                __('No se pudo desactivar el sitio', 'imagina-updater-server'),
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Sitio desactivado correctamente', 'imagina-updater-server')
        ));
    }

    /**
     * Endpoint: Desactivar sitio actual (cliente se desactiva a sí mismo)
     */
    public function deactivate_self($request) {
        // Obtener activation token y dominio del request
        $token = $this->get_api_key_from_request($request);
        $site_domain = $request->get_header('X-Site-Domain');

        if (empty($token) || empty($site_domain)) {
            return new WP_Error(
                'missing_data',
                __('Datos insuficientes para desactivar', 'imagina-updater-server'),
                array('status' => 400)
            );
        }

        $result = Imagina_Updater_Server_Activations::deactivate_by_token($token, $site_domain);

        if (!$result) {
            return new WP_Error(
                'deactivation_failed',
                __('No se pudo desactivar tu licencia', 'imagina-updater-server'),
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Licencia desactivada correctamente', 'imagina-updater-server')
        ));
    }

    /**
     * Endpoint: Validar API Key
     */
    public function validate_api_key($request) {
        $api_key_data = $request->get_param('_api_key_data');
        $activation_data = $request->get_param('_activation_data');

        $response = array(
            'valid' => true,
            'site_name' => $api_key_data->site_name,
            'site_url' => $api_key_data->site_url,
            'created_at' => $api_key_data->created_at
        );

        // Si está usando activation token, agregar info
        if ($activation_data) {
            $response['activated'] = true;
            $response['activation_domain'] = $activation_data->site_domain;
            $response['activated_at'] = $activation_data->activated_at;
        }

        return rest_ensure_response($response);
    }
}
