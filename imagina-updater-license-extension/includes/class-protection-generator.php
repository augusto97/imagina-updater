<?php
/**
 * Generador de Código de Protección Híbrido v4.0
 *
 * Genera código de protección para plugins premium con múltiples capas de seguridad:
 * - Capa 1: Verificación vía License Manager del cliente (si existe)
 * - Capa 2: Verificación directa al servidor (fallback)
 * - Capa 3: Binding al dominio
 * - Capa 4: Múltiples puntos de verificación
 * - Capa 5: Sistema de heartbeat
 * - Capa 6: Verificación de integridad (checksum)
 *
 * @package Imagina_License_Extension
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_Protection_Generator {

    /**
     * Versión del sistema de protección
     */
    const PROTECTION_VERSION = '4.0.0';

    /**
     * Genera el código de protección completo para un plugin
     *
     * @param string $plugin_name Nombre del plugin
     * @param string $plugin_slug Slug del plugin
     * @param string $server_url  URL del servidor de licencias (para fallback)
     * @return string Código PHP de protección
     */
    public static function generate($plugin_name, $plugin_slug, $server_url = '') {
        // Generar identificadores únicos basados en el plugin
        $unique_id = self::generate_unique_id($plugin_slug);
        $class_name = 'ILP_' . $unique_id;

        // Escapar valores para uso en código
        $plugin_name_escaped = addslashes($plugin_name);
        $plugin_slug_escaped = addslashes($plugin_slug);
        $server_url_escaped = addslashes($server_url);

        // Generar el código
        $code = self::get_protection_code_template();

        // Reemplazar placeholders
        $replacements = array(
            '{{CLASS_NAME}}'   => $class_name,
            '{{UNIQUE_ID}}'    => $unique_id,
            '{{PLUGIN_NAME}}'  => $plugin_name_escaped,
            '{{PLUGIN_SLUG}}'  => $plugin_slug_escaped,
            '{{SERVER_URL}}'   => $server_url_escaped,
            '{{VERSION}}'      => self::PROTECTION_VERSION,
            '{{GENERATED_AT}}' => date('Y-m-d H:i:s'),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $code);
    }

    /**
     * Genera un ID único para el plugin
     *
     * @param string $plugin_slug
     * @return string
     */
    private static function generate_unique_id($plugin_slug) {
        return substr(md5($plugin_slug . 'imagina_license'), 0, 8);
    }

    /**
     * Retorna la plantilla del código de protección
     *
     * @return string
     */
    private static function get_protection_code_template() {
        return <<<'PHPCODE'

// ============================================================================
// IMAGINA LICENSE PROTECTION v{{VERSION}}
// Plugin: {{PLUGIN_NAME}}
// Generated: {{GENERATED_AT}}
// DO NOT MODIFY THIS CODE - Integrity verification is active
// ============================================================================

if (!class_exists('{{CLASS_NAME}}')) {

    /**
     * Sistema de Protección de Licencias
     */
    class {{CLASS_NAME}} {

        /** @var string Plugin slug */
        private static $plugin_slug = '{{PLUGIN_SLUG}}';

        /** @var string Plugin name */
        private static $plugin_name = '{{PLUGIN_NAME}}';

        /** @var string Server URL for direct verification */
        private static $server_url = '{{SERVER_URL}}';

        /** @var string Unique ID */
        private static $unique_id = '{{UNIQUE_ID}}';

        /** @var bool License status */
        private static $is_licensed = null;

        /** @var array License data */
        private static $license_data = array();

        /** @var bool Whether verification has run */
        private static $verified = false;

        /** @var int Grace period in seconds (7 days) */
        const GRACE_PERIOD = 604800;

        /** @var int Cache duration in seconds (6 hours) */
        const CACHE_DURATION = 21600;

        /** @var int Heartbeat interval in seconds (12 hours) */
        const HEARTBEAT_INTERVAL = 43200;

        /**
         * Initialize the protection system
         */
        public static function init() {
            // Only run once
            if (self::$verified) {
                return self::$is_licensed;
            }

            // Register hooks for multiple verification points
            add_action('plugins_loaded', array(__CLASS__, 'verify_on_load'), 1);
            add_action('admin_init', array(__CLASS__, 'verify_on_admin'), 1);
            add_action('rest_api_init', array(__CLASS__, 'register_verification_check'));
            add_action('wp_ajax_' . self::$plugin_slug . '_action', array(__CLASS__, 'verify_before_ajax'), 0);

            // Schedule heartbeat
            add_action('init', array(__CLASS__, 'schedule_heartbeat'));
            add_action('imagina_license_heartbeat_' . self::$unique_id, array(__CLASS__, 'run_heartbeat'));

            return true;
        }

        /**
         * Verify license on plugins_loaded
         */
        public static function verify_on_load() {
            self::verify_license();

            if (!self::$is_licensed) {
                // Hook to show admin notice
                add_action('admin_notices', array(__CLASS__, 'show_license_notice'));

                // Trigger action for plugin to handle unlicensed state
                do_action('imagina_license_invalid_' . self::$plugin_slug);
            }
        }

        /**
         * Verify on admin_init for extra security
         */
        public static function verify_on_admin() {
            if (!self::$verified) {
                self::verify_license();
            }
        }

        /**
         * Register REST API verification
         */
        public static function register_verification_check() {
            // Intercept REST requests for this plugin
            add_filter('rest_pre_dispatch', array(__CLASS__, 'verify_before_rest'), 10, 3);
        }

        /**
         * Verify before REST API calls
         */
        public static function verify_before_rest($result, $server, $request) {
            $route = $request->get_route();

            // Only check routes belonging to this plugin
            if (strpos($route, '/' . self::$plugin_slug . '/') !== false) {
                if (!self::is_licensed()) {
                    return new WP_Error(
                        'license_required',
                        sprintf(__('%s requires a valid license.', 'imagina-license'), self::$plugin_name),
                        array('status' => 403)
                    );
                }
            }

            return $result;
        }

        /**
         * Verify before AJAX calls
         */
        public static function verify_before_ajax() {
            if (!self::is_licensed()) {
                wp_send_json_error(array(
                    'message' => sprintf(__('%s requires a valid license.', 'imagina-license'), self::$plugin_name),
                    'code' => 'license_required'
                ));
            }
        }

        /**
         * Main license verification method
         *
         * @param bool $force Force remote verification
         * @return bool
         */
        public static function verify_license($force = false) {
            // Check memory cache first
            if (self::$verified && !$force) {
                return self::$is_licensed;
            }

            // Check persistent cache
            if (!$force) {
                $cached = self::get_cached_status();
                if ($cached !== null) {
                    self::$is_licensed = $cached;
                    self::$verified = true;
                    return self::$is_licensed;
                }
            }

            // LAYER 1: Try via License Manager (client plugin)
            $result = self::verify_via_license_manager();

            // LAYER 2: If client not available, try direct verification
            if ($result === null) {
                $result = self::verify_direct();
            }

            // LAYER 3: Handle verification result with grace period
            self::$is_licensed = self::process_verification_result($result);
            self::$verified = true;

            // Cache the result
            self::cache_status(self::$is_licensed);

            return self::$is_licensed;
        }

        /**
         * Verify via Imagina Updater Client License Manager
         *
         * @return array|null Result array or null if not available
         */
        private static function verify_via_license_manager() {
            // Check if License Manager exists
            if (!class_exists('Imagina_Updater_License_Manager')) {
                return null;
            }

            try {
                $manager = Imagina_Updater_License_Manager::get_instance();

                // Check if client is configured
                if (!$manager->is_configured()) {
                    return array(
                        'valid' => false,
                        'error' => 'not_configured',
                        'message' => 'License client not configured'
                    );
                }

                // Verify the license
                $result = $manager->verify_plugin_license(self::$plugin_slug);

                self::$license_data = $result;
                return $result;

            } catch (Exception $e) {
                return array(
                    'valid' => false,
                    'error' => 'exception',
                    'message' => $e->getMessage()
                );
            }
        }

        /**
         * Direct verification with server (fallback)
         *
         * @return array
         */
        private static function verify_direct() {
            // Get server URL
            $server_url = self::get_server_url();

            if (empty($server_url)) {
                return array(
                    'valid' => false,
                    'error' => 'no_server',
                    'message' => 'No license server configured'
                );
            }

            // Get stored activation data
            $activation_data = self::get_stored_activation();

            if (empty($activation_data['token'])) {
                return array(
                    'valid' => false,
                    'error' => 'not_activated',
                    'message' => 'Plugin not activated'
                );
            }

            // Make direct API call
            $url = trailingslashit($server_url) . 'wp-json/imagina-updater/v1/license/verify';
            $site_domain = parse_url(home_url(), PHP_URL_HOST);

            $response = wp_remote_post($url, array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $activation_data['token'],
                    'Content-Type' => 'application/json',
                    'X-Site-Domain' => $site_domain,
                ),
                'body' => wp_json_encode(array(
                    'plugin_slug' => self::$plugin_slug,
                )),
            ));

            if (is_wp_error($response)) {
                return array(
                    'valid' => false,
                    'error' => 'connection_error',
                    'message' => $response->get_error_message()
                );
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code >= 400 || !isset($body['is_valid'])) {
                return array(
                    'valid' => false,
                    'error' => isset($body['code']) ? $body['code'] : 'server_error',
                    'message' => isset($body['message']) ? $body['message'] : 'Server error'
                );
            }

            return array(
                'valid' => (bool) $body['is_valid'],
                'error' => $body['is_valid'] ? null : 'invalid_license',
                'message' => $body['is_valid'] ? 'License valid' : 'License invalid',
                'data' => $body
            );
        }

        /**
         * Process verification result with grace period handling
         *
         * @param array $result
         * @return bool
         */
        private static function process_verification_result($result) {
            if (!is_array($result)) {
                return false;
            }

            // If valid, reset grace period and return true
            if (!empty($result['valid'])) {
                self::reset_grace_period();
                return true;
            }

            // Check if it's a connection error (not license error)
            $connection_errors = array('connection_error', 'timeout', 'dns_error', 'exception');
            $is_connection_error = in_array($result['error'], $connection_errors);

            if (!$is_connection_error) {
                // License error - no grace period
                self::reset_grace_period();
                return false;
            }

            // Connection error - check grace period
            return self::check_grace_period();
        }

        /**
         * Check if within grace period
         *
         * @return bool
         */
        private static function check_grace_period() {
            $grace_key = 'ilp_grace_' . self::$unique_id;
            $grace_data = get_option($grace_key);

            // Check if we had a valid license before
            $had_valid = get_option('ilp_valid_' . self::$unique_id, false);

            if (!$had_valid) {
                // Never had valid license, no grace period
                return false;
            }

            // Initialize grace period if not started
            if (!$grace_data) {
                $grace_data = array(
                    'started_at' => time(),
                    'failures' => 1
                );
                update_option($grace_key, $grace_data, false);
            } else {
                $grace_data['failures']++;
                update_option($grace_key, $grace_data, false);
            }

            // Check if still within grace period
            $elapsed = time() - $grace_data['started_at'];

            if ($elapsed < self::GRACE_PERIOD) {
                return true; // Allow during grace period
            }

            // Grace period expired
            return false;
        }

        /**
         * Reset grace period (called when license verified successfully)
         */
        private static function reset_grace_period() {
            delete_option('ilp_grace_' . self::$unique_id);
            update_option('ilp_valid_' . self::$unique_id, true, false);
        }

        /**
         * Get cached license status
         *
         * @return bool|null
         */
        private static function get_cached_status() {
            $cache_key = 'ilp_status_' . self::$unique_id;
            $cached = get_transient($cache_key);

            if ($cached === false) {
                return null;
            }

            return (bool) $cached;
        }

        /**
         * Cache license status
         *
         * @param bool $status
         */
        private static function cache_status($status) {
            $cache_key = 'ilp_status_' . self::$unique_id;
            set_transient($cache_key, $status ? '1' : '0', self::CACHE_DURATION);
        }

        /**
         * Get server URL (from client config or hardcoded)
         *
         * @return string
         */
        private static function get_server_url() {
            // Try to get from client config first
            $config = get_option('imagina_updater_client_config', array());

            if (!empty($config['server_url'])) {
                return $config['server_url'];
            }

            // Use hardcoded server URL
            return self::$server_url;
        }

        /**
         * Get stored activation data
         *
         * @return array
         */
        private static function get_stored_activation() {
            // Try client config first
            $config = get_option('imagina_updater_client_config', array());

            if (!empty($config['activation_token'])) {
                return array('token' => $config['activation_token']);
            }

            // Try plugin-specific activation
            $activation = get_option('ilp_activation_' . self::$unique_id, array());

            return $activation;
        }

        /**
         * Schedule heartbeat for periodic verification
         */
        public static function schedule_heartbeat() {
            $hook = 'imagina_license_heartbeat_' . self::$unique_id;

            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + self::HEARTBEAT_INTERVAL, 'twicedaily', $hook);
            }
        }

        /**
         * Run heartbeat verification
         */
        public static function run_heartbeat() {
            // Force fresh verification
            self::verify_license(true);

            // Send telemetry if licensed
            if (self::$is_licensed) {
                self::send_telemetry();
            }
        }

        /**
         * Send telemetry data to server
         */
        private static function send_telemetry() {
            $server_url = self::get_server_url();

            if (empty($server_url)) {
                return;
            }

            $activation = self::get_stored_activation();

            if (empty($activation['token'])) {
                return;
            }

            $url = trailingslashit($server_url) . 'wp-json/imagina-license/v1/telemetry';

            global $wp_version;

            wp_remote_post($url, array(
                'timeout' => 5,
                'blocking' => false,
                'body' => array(
                    'plugin_slug' => self::$plugin_slug,
                    'activation_token' => $activation['token'],
                    'site_url' => home_url(),
                    'site_name' => get_bloginfo('name'),
                    'wp_version' => $wp_version,
                    'php_version' => PHP_VERSION,
                    'is_multisite' => is_multisite(),
                    'locale' => get_locale(),
                    'timestamp' => current_time('mysql'),
                ),
            ));
        }

        /**
         * Show admin notice when license is invalid
         */
        public static function show_license_notice() {
            if (!current_user_can('manage_options')) {
                return;
            }

            $message = self::get_license_message();
            $configure_url = admin_url('options-general.php?page=imagina-updater-client');
            $has_client = class_exists('Imagina_Updater_License_Manager');

            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php echo esc_html(self::$plugin_name); ?>:</strong>
                    <?php echo esc_html($message); ?>
                </p>
                <?php if (!$has_client): ?>
                    <p>
                        <?php _e('Please install and configure the Imagina Updater Client plugin.', 'imagina-license'); ?>
                    </p>
                <?php else: ?>
                    <p>
                        <a href="<?php echo esc_url($configure_url); ?>" class="button button-primary">
                            <?php _e('Configure License', 'imagina-license'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }

        /**
         * Get appropriate license message based on state
         *
         * @return string
         */
        private static function get_license_message() {
            if (!class_exists('Imagina_Updater_License_Manager')) {
                return __('Requires Imagina Updater Client to validate the license.', 'imagina-license');
            }

            if (empty(self::$license_data)) {
                return __('License verification required.', 'imagina-license');
            }

            $error = isset(self::$license_data['error']) ? self::$license_data['error'] : 'unknown';

            $messages = array(
                'not_configured' => __('License client is not configured.', 'imagina-license'),
                'not_activated' => __('Please activate your license.', 'imagina-license'),
                'invalid_license' => __('Invalid license. Please check your subscription.', 'imagina-license'),
                'no_access' => __('Your license does not include access to this plugin.', 'imagina-license'),
                'connection_error' => __('Cannot connect to license server.', 'imagina-license'),
                'grace_period_expired' => __('Grace period expired. Please reconnect to the license server.', 'imagina-license'),
            );

            return isset($messages[$error]) ? $messages[$error] : __('License validation failed.', 'imagina-license');
        }

        /**
         * Check if plugin is licensed (public API)
         *
         * @return bool
         */
        public static function is_licensed() {
            if (!self::$verified) {
                self::verify_license();
            }

            return self::$is_licensed;
        }

        /**
         * Get license data (public API)
         *
         * @return array
         */
        public static function get_license_data() {
            return self::$license_data;
        }

        /**
         * Force license re-check (public API)
         *
         * @return bool
         */
        public static function recheck() {
            delete_transient('ilp_status_' . self::$unique_id);
            return self::verify_license(true);
        }

        /**
         * Deactivate and clean up (public API)
         */
        public static function deactivate() {
            delete_transient('ilp_status_' . self::$unique_id);
            delete_option('ilp_grace_' . self::$unique_id);
            delete_option('ilp_valid_' . self::$unique_id);
            delete_option('ilp_activation_' . self::$unique_id);

            // Clear scheduled heartbeat
            wp_clear_scheduled_hook('imagina_license_heartbeat_' . self::$unique_id);
        }
    }

    // Initialize protection
    {{CLASS_NAME}}::init();
}

// ============================================================================
// END IMAGINA LICENSE PROTECTION
// ============================================================================

PHPCODE;
    }

    /**
     * Genera código minificado (opcional, para ofuscación básica)
     *
     * @param string $plugin_name
     * @param string $plugin_slug
     * @param string $server_url
     * @return string
     */
    public static function generate_minified($plugin_name, $plugin_slug, $server_url = '') {
        $code = self::generate($plugin_name, $plugin_slug, $server_url);

        // Remover comentarios de una línea
        $code = preg_replace('/\/\/.*$/m', '', $code);

        // Remover comentarios de bloque
        $code = preg_replace('/\/\*.*?\*\//s', '', $code);

        // Remover líneas vacías múltiples
        $code = preg_replace('/\n\s*\n/', "\n", $code);

        // Remover espacios al inicio de líneas
        $code = preg_replace('/^\s+/m', '', $code);

        return $code;
    }

    /**
     * Calcula el checksum del código de protección
     *
     * @param string $code
     * @return string
     */
    public static function calculate_checksum($code) {
        // Normalizar el código (remover espacios y saltos de línea extra)
        $normalized = preg_replace('/\s+/', ' ', $code);
        return hash('sha256', $normalized);
    }

    /**
     * Verifica la integridad del código de protección
     *
     * @param string $code
     * @param string $expected_checksum
     * @return bool
     */
    public static function verify_integrity($code, $expected_checksum) {
        return hash_equals($expected_checksum, self::calculate_checksum($code));
    }
}
