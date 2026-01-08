<?php
/**
 * Imagina Updater Client - License Manager
 *
 * Gestor de licencias para plugins premium.
 * Proporciona verificación de licencias usando el sistema de activación del cliente.
 *
 * @package Imagina_Updater_Client
 * @version 2.0.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase de gestión de licencias para plugins premium
 */
class Imagina_Updater_License_Manager {

    /**
     * Instancia única (Singleton)
     *
     * @var Imagina_Updater_License_Manager
     */
    private static $instance = null;

    /**
     * Configuración del cliente
     *
     * @var array
     */
    private $config;

    /**
     * Caché de validaciones en memoria
     *
     * @var array
     */
    private $validation_cache = array();

    /**
     * Tiempo de caché de validaciones (6 horas)
     *
     * @var int
     */
    const CACHE_EXPIRATION = 21600;

    /**
     * Grace period por defecto (7 días)
     *
     * @var int
     */
    const DEFAULT_GRACE_PERIOD = 604800;

    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        $this->config = get_option('imagina_updater_client_config', array());
    }

    /**
     * Inicializa el gestor de licencias
     */
    public static function init() {
        self::get_instance();
    }

    /**
     * Obtiene la instancia única
     *
     * @return Imagina_Updater_License_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Verifica si el cliente está configurado correctamente
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->config['server_url']) && !empty($this->config['activation_token']);
    }

    /**
     * Obtiene la URL del servidor
     *
     * @return string
     */
    public function get_server_url() {
        return isset($this->config['server_url']) ? trailingslashit($this->config['server_url']) : '';
    }

    /**
     * Obtiene el activation token
     *
     * @return string|null
     */
    public function get_activation_token() {
        return isset($this->config['activation_token']) ? $this->config['activation_token'] : null;
    }

    /**
     * Verifica la licencia de un plugin
     *
     * Este es el método principal que los plugins premium deben usar.
     *
     * @param string $plugin_slug Slug del plugin a verificar
     * @param bool   $force_check Forzar verificación remota (ignorar caché)
     * @return array Array con 'valid' => bool y datos adicionales
     */
    public function verify_plugin_license($plugin_slug, $force_check = false) {
        // Verificar que esté configurado
        if (!$this->is_configured()) {
            return $this->create_response(false, 'not_configured', 'Cliente no configurado');
        }

        $plugin_slug = sanitize_key($plugin_slug);

        // Verificar caché en memoria
        if (!$force_check && isset($this->validation_cache[$plugin_slug])) {
            return $this->validation_cache[$plugin_slug];
        }

        // Verificar caché persistente
        if (!$force_check) {
            $cached = $this->get_cached_validation($plugin_slug);
            if ($cached !== false) {
                $this->validation_cache[$plugin_slug] = $cached;
                return $cached;
            }
        }

        // Verificar con el servidor
        $result = $this->verify_with_server($plugin_slug);

        // Si falla la verificación remota, verificar grace period
        if (!$result['valid']) {
            $result = $this->handle_verification_failure($plugin_slug, $result);
        } else {
            // Éxito: guardar en caché y resetear grace period
            $this->cache_validation($plugin_slug, $result);
            $this->reset_grace_period($plugin_slug);
        }

        $this->validation_cache[$plugin_slug] = $result;
        return $result;
    }

    /**
     * Verifica la licencia directamente con el servidor
     *
     * @param string $plugin_slug Plugin slug
     * @return array
     */
    private function verify_with_server($plugin_slug) {
        $server_url = $this->get_server_url();
        $token = $this->get_activation_token();

        if (empty($server_url) || empty($token)) {
            return $this->create_response(false, 'not_configured', 'Configuración incompleta');
        }

        $url = $server_url . 'wp-json/imagina-updater/v1/license/verify';
        $site_domain = parse_url(home_url(), PHP_URL_HOST);

        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'X-Site-Domain' => $site_domain,
            ),
            'body' => wp_json_encode(array(
                'plugin_slug' => $plugin_slug,
            )),
        ));

        // Error de conexión
        if (is_wp_error($response)) {
            return $this->create_response(
                false,
                'connection_error',
                $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Error HTTP
        if ($status_code >= 400) {
            $error_message = isset($data['message']) ? $data['message'] : 'Error del servidor';
            $error_code = isset($data['code']) ? $data['code'] : 'server_error';
            return $this->create_response(false, $error_code, $error_message);
        }

        // Verificar respuesta válida
        if (!is_array($data) || !isset($data['is_valid'])) {
            return $this->create_response(false, 'invalid_response', 'Respuesta inválida del servidor');
        }

        // Verificar firma de la respuesta
        if (isset($data['signature'])) {
            if (!$this->verify_response_signature($data)) {
                return $this->create_response(false, 'invalid_signature', 'Firma de respuesta inválida');
            }
        }

        // Licencia válida
        if ($data['is_valid']) {
            return $this->create_response(true, 'valid', 'Licencia válida', array(
                'license_token' => isset($data['license_token']) ? $data['license_token'] : null,
                'expires_at'    => isset($data['expires_at']) ? $data['expires_at'] : null,
                'plugin_name'   => isset($data['plugin_name']) ? $data['plugin_name'] : null,
                'verified_at'   => time(),
            ));
        }

        // Licencia no válida
        return $this->create_response(
            false,
            isset($data['error']) ? $data['error'] : 'invalid_license',
            isset($data['message']) ? $data['message'] : 'Licencia no válida'
        );
    }

    /**
     * Verifica múltiples licencias en batch (para heartbeat)
     *
     * @param array $plugin_slugs Lista de plugin slugs
     * @return array Resultados indexados por slug
     */
    public function verify_batch($plugin_slugs) {
        if (!$this->is_configured() || empty($plugin_slugs)) {
            return array();
        }

        $server_url = $this->get_server_url();
        $token = $this->get_activation_token();
        $site_domain = parse_url(home_url(), PHP_URL_HOST);

        $url = $server_url . 'wp-json/imagina-updater/v1/license/verify-batch';

        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'X-Site-Domain' => $site_domain,
            ),
            'body' => wp_json_encode(array(
                'plugin_slugs' => $plugin_slugs,
            )),
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            return array();
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($data) || !isset($data['results'])) {
            return array();
        }

        // Cachear resultados válidos
        foreach ($data['results'] as $slug => $result) {
            if (isset($result['is_valid']) && $result['is_valid']) {
                $cached_result = $this->create_response(true, 'valid', 'Licencia válida', array(
                    'license_token' => isset($result['license_token']) ? $result['license_token'] : null,
                    'expires_at'    => isset($result['expires_at']) ? $result['expires_at'] : null,
                    'verified_at'   => time(),
                ));
                $this->cache_validation($slug, $cached_result);
                $this->validation_cache[$slug] = $cached_result;
            }
        }

        return $data['results'];
    }

    /**
     * Maneja el fallo de verificación con grace period
     *
     * @param string $plugin_slug Plugin slug
     * @param array  $result      Resultado de la verificación
     * @return array
     */
    private function handle_verification_failure($plugin_slug, $result) {
        $grace_key = 'imagina_license_grace_' . md5($plugin_slug);
        $grace_data = get_option($grace_key, null);

        // Si es error de conexión (no de licencia inválida), aplicar grace period
        $connection_errors = array('connection_error', 'timeout', 'dns_error');
        $is_connection_error = in_array($result['error'], $connection_errors);

        if (!$is_connection_error) {
            // Error de licencia (no conexión) - no aplicar grace period
            delete_option($grace_key);
            return $result;
        }

        // Verificar si tenemos una licencia previamente válida
        $cached = $this->get_cached_validation($plugin_slug);
        $had_valid_license = ($cached !== false && isset($cached['valid']) && $cached['valid']);

        if (!$had_valid_license && !$grace_data) {
            // Nunca tuvo licencia válida, no dar grace period
            return $result;
        }

        // Iniciar o continuar grace period
        if (!$grace_data) {
            $grace_data = array(
                'started_at'    => time(),
                'failure_count' => 1,
                'last_error'    => $result['error'],
            );
            update_option($grace_key, $grace_data, false);
        } else {
            $grace_data['failure_count']++;
            $grace_data['last_error'] = $result['error'];
            update_option($grace_key, $grace_data, false);
        }

        // Verificar si aún está dentro del grace period
        $time_in_grace = time() - $grace_data['started_at'];
        if ($time_in_grace < self::DEFAULT_GRACE_PERIOD) {
            // Aún en grace period - permitir funcionamiento
            $days_remaining = ceil((self::DEFAULT_GRACE_PERIOD - $time_in_grace) / DAY_IN_SECONDS);
            return $this->create_response(true, 'grace_period', 'En período de gracia', array(
                'grace_period'     => true,
                'days_remaining'   => $days_remaining,
                'original_error'   => $result['error'],
                'failure_count'    => $grace_data['failure_count'],
            ));
        }

        // Grace period expirado
        delete_option($grace_key);
        return $this->create_response(
            false,
            'grace_period_expired',
            'El período de gracia ha expirado. Por favor verifica tu conexión.'
        );
    }

    /**
     * Resetea el grace period de un plugin
     *
     * @param string $plugin_slug
     */
    private function reset_grace_period($plugin_slug) {
        $grace_key = 'imagina_license_grace_' . md5($plugin_slug);
        delete_option($grace_key);
    }

    /**
     * Verifica la firma de una respuesta del servidor
     *
     * @param array $response Respuesta del servidor
     * @return bool
     */
    private function verify_response_signature($response) {
        if (!isset($response['signature'])) {
            return true; // Si no hay firma, aceptar (compatibilidad)
        }

        $signature = $response['signature'];
        $data = $response;
        unset($data['signature']);

        // Cargar clase de criptografía si existe
        if (!class_exists('Imagina_License_Crypto')) {
            if (file_exists(IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'includes/class-license-crypto-client.php')) {
                require_once IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'includes/class-license-crypto-client.php';
            } else {
                return true; // Sin crypto, aceptar
            }
        }

        $token = $this->get_activation_token();
        if (!$token) {
            return false;
        }

        $data_string = wp_json_encode($data);
        return Imagina_License_Crypto::verify_signature($data_string, $signature, $token);
    }

    /**
     * Obtiene validación desde la caché persistente
     *
     * @param string $plugin_slug Plugin slug
     * @return array|false
     */
    private function get_cached_validation($plugin_slug) {
        $cache_key = 'imagina_license_' . md5($plugin_slug);
        $cached = get_transient($cache_key);

        if (!$cached || !is_array($cached)) {
            return false;
        }

        // Verificar expiración del license_token
        if (isset($cached['data']['expires_at']) && $cached['data']['expires_at'] < time()) {
            delete_transient($cache_key);
            return false;
        }

        return $cached;
    }

    /**
     * Guarda validación en caché persistente
     *
     * @param string $plugin_slug Plugin slug
     * @param array  $result      Resultado de validación
     */
    private function cache_validation($plugin_slug, $result) {
        $cache_key = 'imagina_license_' . md5($plugin_slug);
        set_transient($cache_key, $result, self::CACHE_EXPIRATION);
    }

    /**
     * Invalida la caché de un plugin
     *
     * @param string $plugin_slug Plugin slug
     */
    public function invalidate_cache($plugin_slug) {
        $cache_key = 'imagina_license_' . md5($plugin_slug);
        delete_transient($cache_key);
        unset($this->validation_cache[$plugin_slug]);
    }

    /**
     * Invalida toda la caché de licencias
     */
    public function invalidate_all_cache() {
        global $wpdb;

        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_imagina_license_%'
            OR option_name LIKE '_transient_timeout_imagina_license_%'"
        );

        $this->validation_cache = array();
    }

    /**
     * Crea una respuesta estructurada
     *
     * @param bool   $valid   Si la licencia es válida
     * @param string $code    Código de estado
     * @param string $message Mensaje descriptivo
     * @param array  $data    Datos adicionales
     * @return array
     */
    private function create_response($valid, $code, $message, $data = array()) {
        return array(
            'valid'   => $valid,
            'error'   => $valid ? null : $code,
            'code'    => $code,
            'message' => $message,
            'data'    => $data,
        );
    }

    /**
     * Verifica si un plugin tiene licencia válida (método de conveniencia)
     *
     * @param string $plugin_slug Plugin slug
     * @return bool
     */
    public function is_plugin_licensed($plugin_slug) {
        $result = $this->verify_plugin_license($plugin_slug);
        return isset($result['valid']) && $result['valid'] === true;
    }

    /**
     * Obtiene información de la licencia del cliente
     *
     * @return array|WP_Error
     */
    public function get_license_info() {
        if (!$this->is_configured()) {
            return new WP_Error('not_configured', 'Cliente no configurado');
        }

        $server_url = $this->get_server_url();
        $token = $this->get_activation_token();
        $site_domain = parse_url(home_url(), PHP_URL_HOST);

        $url = $server_url . 'wp-json/imagina-updater/v1/license/info';

        $response = wp_remote_post($url, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'X-Site-Domain' => $site_domain,
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400) {
            $message = isset($data['message']) ? $data['message'] : 'Error del servidor';
            return new WP_Error('server_error', $message);
        }

        return $data;
    }

    /**
     * Fuerza una verificación remota inmediata para un plugin
     *
     * @param string $plugin_slug Plugin slug
     * @return array
     */
    public function force_check($plugin_slug) {
        $this->invalidate_cache($plugin_slug);
        return $this->verify_plugin_license($plugin_slug, true);
    }

    /**
     * Obtiene estadísticas de licencias cacheadas
     *
     * @return array
     */
    public function get_stats() {
        global $wpdb;

        $cached = $wpdb->get_results(
            "SELECT option_name, option_value FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_imagina_license_%'
            AND option_name NOT LIKE '_transient_timeout_%'",
            ARRAY_A
        );

        $stats = array(
            'total_cached'     => count($cached),
            'valid_licenses'   => 0,
            'invalid_licenses' => 0,
            'in_grace_period'  => 0,
            'plugins'          => array(),
        );

        foreach ($cached as $row) {
            $data = maybe_unserialize($row['option_value']);
            if (!is_array($data)) {
                continue;
            }

            $is_valid = isset($data['valid']) && $data['valid'];
            $in_grace = isset($data['data']['grace_period']) && $data['data']['grace_period'];

            if ($is_valid) {
                $stats['valid_licenses']++;
                if ($in_grace) {
                    $stats['in_grace_period']++;
                }
            } else {
                $stats['invalid_licenses']++;
            }
        }

        return $stats;
    }
}
