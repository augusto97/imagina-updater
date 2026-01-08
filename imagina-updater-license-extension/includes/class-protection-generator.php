<?php
/**
 * Generador de Código de Protección v5.0
 *
 * Genera código de protección para plugins premium con:
 * - Página de activación de licencia integrada en cada plugin
 * - Verificación mediante License Keys (nuevo sistema)
 * - Múltiples puntos de verificación
 * - Sistema de heartbeat
 * - Grace period para problemas de conexión
 *
 * @package Imagina_License_Extension
 * @version 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_Protection_Generator {

    /**
     * Versión del sistema de protección
     */
    const PROTECTION_VERSION = '5.0.0';

    /**
     * Genera el código de protección completo para un plugin
     *
     * @param string $plugin_name Nombre del plugin
     * @param string $plugin_slug Slug del plugin
     * @param string $server_url  URL del servidor de licencias
     * @return string Código PHP de protección
     */
    public static function generate($plugin_name, $plugin_slug, $server_url = '') {
        $unique_id = self::generate_unique_id($plugin_slug);
        $class_name = 'ILP_' . $unique_id;

        $plugin_name_escaped = addslashes($plugin_name);
        $plugin_slug_escaped = addslashes($plugin_slug);
        $server_url_escaped = addslashes($server_url);

        $code = self::get_protection_code_template();

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
     */
    private static function generate_unique_id($plugin_slug) {
        return substr(md5($plugin_slug . 'imagina_license_v5'), 0, 8);
    }

    /**
     * Retorna la plantilla del código de protección
     */
    private static function get_protection_code_template() {
        return <<<'PHPCODE'

// ============================================================================
// IMAGINA LICENSE PROTECTION v{{VERSION}}
// Plugin: {{PLUGIN_NAME}}
// Generated: {{GENERATED_AT}}
// DO NOT MODIFY THIS CODE
// ============================================================================

if (!class_exists('{{CLASS_NAME}}')) {

    class {{CLASS_NAME}} {

        private static $plugin_slug = '{{PLUGIN_SLUG}}';
        private static $plugin_name = '{{PLUGIN_NAME}}';
        private static $server_url = '{{SERVER_URL}}';
        private static $unique_id = '{{UNIQUE_ID}}';
        private static $is_licensed = null;
        private static $license_data = array();
        private static $initialized = false;

        const GRACE_PERIOD = 604800; // 7 days
        const CACHE_DURATION = 21600; // 6 hours

        /**
         * Initialize protection system
         */
        public static function init() {
            if (self::$initialized) {
                return;
            }
            self::$initialized = true;

            // Add admin menu for license activation
            add_action('admin_menu', array(__CLASS__, 'add_license_menu'));

            // Handle license actions
            add_action('admin_init', array(__CLASS__, 'handle_license_actions'));

            // Verify license on load
            add_action('plugins_loaded', array(__CLASS__, 'verify_on_load'), 5);

            // Show admin notices
            add_action('admin_notices', array(__CLASS__, 'show_admin_notices'));

            // Add settings link to plugins page
            add_filter('plugin_action_links', array(__CLASS__, 'add_settings_link'), 10, 2);

            // Block functionality if not licensed
            add_action('admin_init', array(__CLASS__, 'maybe_block_functionality'), 1);
        }

        /**
         * Add license menu to admin
         */
        public static function add_license_menu() {
            add_options_page(
                self::$plugin_name . ' - ' . __('Licencia', 'imagina-license'),
                self::$plugin_name . ' License',
                'manage_options',
                self::$plugin_slug . '-license',
                array(__CLASS__, 'render_license_page')
            );
        }

        /**
         * Add settings link to plugins list
         */
        public static function add_settings_link($links, $file) {
            if (strpos($file, self::$plugin_slug) !== false) {
                $license_link = '<a href="' . admin_url('options-general.php?page=' . self::$plugin_slug . '-license') . '">' . __('Licencia', 'imagina-license') . '</a>';
                array_unshift($links, $license_link);
            }
            return $links;
        }

        /**
         * Render license activation page
         */
        public static function render_license_page() {
            $license_data = self::get_stored_license();
            $is_active = self::is_licensed();
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(self::$plugin_name); ?> - <?php _e('Licencia', 'imagina-license'); ?></h1>

                <?php settings_errors('ilp_license_' . self::$unique_id); ?>

                <div class="card" style="max-width: 600px; padding: 20px;">
                    <?php if ($is_active && !empty($license_data['license_key'])): ?>
                        <!-- License Active -->
                        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                            <strong style="color: #155724;">✓ <?php _e('Licencia Activa', 'imagina-license'); ?></strong>
                        </div>

                        <table class="form-table">
                            <tr>
                                <th><?php _e('License Key', 'imagina-license'); ?></th>
                                <td><code><?php echo esc_html(self::mask_license_key($license_data['license_key'])); ?></code></td>
                            </tr>
                            <?php if (!empty($license_data['customer_email'])): ?>
                            <tr>
                                <th><?php _e('Email', 'imagina-license'); ?></th>
                                <td><?php echo esc_html($license_data['customer_email']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($license_data['expires_at'])): ?>
                            <tr>
                                <th><?php _e('Expira', 'imagina-license'); ?></th>
                                <td><?php echo esc_html(date_i18n('d/m/Y', strtotime($license_data['expires_at']))); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th><?php _e('Sitio', 'imagina-license'); ?></th>
                                <td><?php echo esc_html(home_url()); ?></td>
                            </tr>
                        </table>

                        <form method="post" style="margin-top: 20px;">
                            <?php wp_nonce_field('ilp_deactivate_' . self::$unique_id); ?>
                            <input type="hidden" name="ilp_action" value="deactivate">
                            <input type="hidden" name="ilp_plugin" value="<?php echo esc_attr(self::$unique_id); ?>">
                            <button type="submit" class="button" onclick="return confirm('<?php esc_attr_e('¿Desactivar licencia de este sitio?', 'imagina-license'); ?>');">
                                <?php _e('Desactivar Licencia', 'imagina-license'); ?>
                            </button>
                        </form>

                    <?php else: ?>
                        <!-- License Not Active -->
                        <div style="background: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                            <strong style="color: #856404;">⚠ <?php _e('Licencia No Activa', 'imagina-license'); ?></strong>
                            <p style="margin: 10px 0 0; color: #856404;">
                                <?php _e('Ingresa tu license key para activar el plugin y recibir actualizaciones.', 'imagina-license'); ?>
                            </p>
                        </div>

                        <form method="post">
                            <?php wp_nonce_field('ilp_activate_' . self::$unique_id); ?>
                            <input type="hidden" name="ilp_action" value="activate">
                            <input type="hidden" name="ilp_plugin" value="<?php echo esc_attr(self::$unique_id); ?>">

                            <table class="form-table">
                                <tr>
                                    <th><label for="license_key"><?php _e('License Key', 'imagina-license'); ?></label></th>
                                    <td>
                                        <input type="text" name="license_key" id="license_key" class="regular-text"
                                               placeholder="ILK-XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX" required
                                               pattern="[A-Za-z0-9\-]+" style="font-family: monospace;">
                                        <p class="description">
                                            <?php _e('Ingresa la license key que recibiste al comprar el plugin.', 'imagina-license'); ?>
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Activar Licencia', 'imagina-license'); ?>
                                </button>
                            </p>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="card" style="max-width: 600px; padding: 15px; margin-top: 20px;">
                    <h3 style="margin-top: 0;"><?php _e('Información', 'imagina-license'); ?></h3>
                    <p><strong><?php _e('Servidor:', 'imagina-license'); ?></strong> <?php echo esc_html(self::get_server_url()); ?></p>
                    <p><strong><?php _e('Versión de protección:', 'imagina-license'); ?></strong> {{VERSION}}</p>
                </div>
            </div>
            <?php
        }

        /**
         * Handle license activation/deactivation
         */
        public static function handle_license_actions() {
            if (!isset($_POST['ilp_action']) || !isset($_POST['ilp_plugin'])) {
                return;
            }

            if ($_POST['ilp_plugin'] !== self::$unique_id) {
                return;
            }

            $action = sanitize_key($_POST['ilp_action']);

            if ($action === 'activate') {
                self::process_activation();
            } elseif ($action === 'deactivate') {
                self::process_deactivation();
            }
        }

        /**
         * Process license activation
         */
        private static function process_activation() {
            if (!check_admin_referer('ilp_activate_' . self::$unique_id)) {
                return;
            }

            $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';

            if (empty($license_key)) {
                add_settings_error('ilp_license_' . self::$unique_id, 'empty_key', __('Por favor ingresa una license key.', 'imagina-license'), 'error');
                return;
            }

            $server_url = self::get_server_url();
            $url = trailingslashit($server_url) . 'wp-json/imagina-license/v1/activate';

            $response = wp_remote_post($url, array(
                'timeout' => 30,
                'body' => array(
                    'license_key' => $license_key,
                    'site_url'    => home_url(),
                    'site_name'   => get_bloginfo('name'),
                    'plugin_slug' => self::$plugin_slug,
                ),
            ));

            if (is_wp_error($response)) {
                add_settings_error('ilp_license_' . self::$unique_id, 'connection_error',
                    __('Error de conexión: ', 'imagina-license') . $response->get_error_message(), 'error');
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code >= 400 || empty($body['success'])) {
                $message = isset($body['message']) ? $body['message'] : __('Error al activar licencia.', 'imagina-license');
                add_settings_error('ilp_license_' . self::$unique_id, 'activation_error', $message, 'error');
                return;
            }

            // Save license data
            $license_data = array(
                'license_key'    => $license_key,
                'site_url'       => home_url(),
                'activated_at'   => current_time('mysql'),
                'expires_at'     => isset($body['expires_at']) ? $body['expires_at'] : null,
                'customer_email' => isset($body['customer_email']) ? $body['customer_email'] : '',
                'plugin_name'    => isset($body['plugin_name']) ? $body['plugin_name'] : self::$plugin_name,
                'site_local_key' => isset($body['site_local_key']) ? $body['site_local_key'] : '',
            );

            update_option('ilp_license_' . self::$unique_id, $license_data);
            delete_transient('ilp_status_' . self::$unique_id);

            self::$is_licensed = true;
            self::$license_data = $license_data;

            add_settings_error('ilp_license_' . self::$unique_id, 'activated',
                __('¡Licencia activada correctamente!', 'imagina-license'), 'success');
        }

        /**
         * Process license deactivation
         */
        private static function process_deactivation() {
            if (!check_admin_referer('ilp_deactivate_' . self::$unique_id)) {
                return;
            }

            $license_data = self::get_stored_license();

            if (empty($license_data['license_key'])) {
                return;
            }

            $server_url = self::get_server_url();
            $url = trailingslashit($server_url) . 'wp-json/imagina-license/v1/deactivate';

            wp_remote_post($url, array(
                'timeout' => 15,
                'body' => array(
                    'license_key' => $license_data['license_key'],
                    'site_url'    => home_url(),
                ),
            ));

            // Clear local data regardless of server response
            delete_option('ilp_license_' . self::$unique_id);
            delete_transient('ilp_status_' . self::$unique_id);
            delete_option('ilp_grace_' . self::$unique_id);

            self::$is_licensed = false;
            self::$license_data = array();

            add_settings_error('ilp_license_' . self::$unique_id, 'deactivated',
                __('Licencia desactivada.', 'imagina-license'), 'success');
        }

        /**
         * Verify license on page load
         */
        public static function verify_on_load() {
            self::verify_license();
        }

        /**
         * Main license verification
         */
        public static function verify_license($force = false) {
            // Check cache first
            if (!$force) {
                $cached = get_transient('ilp_status_' . self::$unique_id);
                if ($cached !== false) {
                    self::$is_licensed = ($cached === 'valid');
                    return self::$is_licensed;
                }
            }

            $license_data = self::get_stored_license();

            if (empty($license_data['license_key'])) {
                self::$is_licensed = false;
                set_transient('ilp_status_' . self::$unique_id, 'invalid', self::CACHE_DURATION);
                return false;
            }

            // Verify with server
            $result = self::verify_with_server($license_data['license_key']);

            if ($result['valid']) {
                self::$is_licensed = true;
                self::$license_data = $license_data;
                set_transient('ilp_status_' . self::$unique_id, 'valid', self::CACHE_DURATION);
                delete_option('ilp_grace_' . self::$unique_id);
                return true;
            }

            // Check grace period for connection errors
            if (in_array($result['error'], array('connection_error', 'timeout'))) {
                if (self::check_grace_period()) {
                    self::$is_licensed = true;
                    return true;
                }
            }

            self::$is_licensed = false;
            set_transient('ilp_status_' . self::$unique_id, 'invalid', self::CACHE_DURATION);
            return false;
        }

        /**
         * Verify license with server
         */
        private static function verify_with_server($license_key) {
            $server_url = self::get_server_url();
            $url = trailingslashit($server_url) . 'wp-json/imagina-license/v1/check';

            $response = wp_remote_post($url, array(
                'timeout' => 15,
                'body' => array(
                    'license_key' => $license_key,
                    'site_url'    => home_url(),
                    'plugin_slug' => self::$plugin_slug,
                ),
            ));

            if (is_wp_error($response)) {
                return array('valid' => false, 'error' => 'connection_error');
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!is_array($body)) {
                return array('valid' => false, 'error' => 'invalid_response');
            }

            return array(
                'valid' => !empty($body['valid']),
                'error' => isset($body['error']) ? $body['error'] : null,
            );
        }

        /**
         * Check grace period
         */
        private static function check_grace_period() {
            $grace_data = get_option('ilp_grace_' . self::$unique_id);
            $license_data = self::get_stored_license();

            if (empty($license_data['license_key'])) {
                return false;
            }

            if (!$grace_data) {
                $grace_data = array('started_at' => time());
                update_option('ilp_grace_' . self::$unique_id, $grace_data);
            }

            $elapsed = time() - $grace_data['started_at'];
            return $elapsed < self::GRACE_PERIOD;
        }

        /**
         * Show admin notices
         */
        public static function show_admin_notices() {
            if (!current_user_can('manage_options')) {
                return;
            }

            // Don't show on license page
            if (isset($_GET['page']) && $_GET['page'] === self::$plugin_slug . '-license') {
                return;
            }

            if (!self::is_licensed()) {
                $license_url = admin_url('options-general.php?page=' . self::$plugin_slug . '-license');
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php echo esc_html(self::$plugin_name); ?>:</strong>
                        <?php _e('Por favor activa tu licencia para usar todas las funciones y recibir actualizaciones.', 'imagina-license'); ?>
                        <a href="<?php echo esc_url($license_url); ?>" class="button button-primary" style="margin-left: 10px;">
                            <?php _e('Activar Licencia', 'imagina-license'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }

        /**
         * Block functionality if not licensed (optional - plugins can use is_licensed())
         */
        public static function maybe_block_functionality() {
            // This method can be extended by plugins that want to block features
            // For now, just trigger an action
            if (!self::is_licensed()) {
                do_action('imagina_license_not_active_' . self::$plugin_slug);
            }
        }

        /**
         * Get stored license data
         */
        private static function get_stored_license() {
            if (!empty(self::$license_data)) {
                return self::$license_data;
            }
            return get_option('ilp_license_' . self::$unique_id, array());
        }

        /**
         * Get server URL
         */
        private static function get_server_url() {
            if (!empty(self::$server_url)) {
                return self::$server_url;
            }
            // Fallback to client config
            $config = get_option('imagina_updater_client_config', array());
            return isset($config['server_url']) ? $config['server_url'] : '';
        }

        /**
         * Mask license key for display
         */
        private static function mask_license_key($key) {
            if (strlen($key) < 20) {
                return $key;
            }
            return substr($key, 0, 8) . str_repeat('*', strlen($key) - 16) . substr($key, -8);
        }

        /**
         * Check if plugin is licensed (public API)
         */
        public static function is_licensed() {
            if (self::$is_licensed === null) {
                self::verify_license();
            }
            return self::$is_licensed;
        }

        /**
         * Get license data (public API)
         */
        public static function get_license_data() {
            return self::get_stored_license();
        }

        /**
         * Force recheck (public API)
         */
        public static function recheck() {
            delete_transient('ilp_status_' . self::$unique_id);
            return self::verify_license(true);
        }
    }

    // Initialize
    {{CLASS_NAME}}::init();
}

// ============================================================================
// END IMAGINA LICENSE PROTECTION
// ============================================================================

PHPCODE;
    }

    /**
     * Genera código minificado
     */
    public static function generate_minified($plugin_name, $plugin_slug, $server_url = '') {
        $code = self::generate($plugin_name, $plugin_slug, $server_url);
        $code = preg_replace('/\/\/.*$/m', '', $code);
        $code = preg_replace('/\/\*.*?\*\//s', '', $code);
        $code = preg_replace('/\n\s*\n/', "\n", $code);
        return $code;
    }

    /**
     * Calcula checksum del código
     */
    public static function calculate_checksum($code) {
        $normalized = preg_replace('/\s+/', ' ', $code);
        return hash('sha256', $normalized);
    }
}
