<?php
/**
 * Generador de Código de Protección v5.2
 *
 * @package Imagina_License_Extension
 * @version 5.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_Protection_Generator {

    const PROTECTION_VERSION = '5.2.0';

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

    private static function generate_unique_id($plugin_slug) {
        return substr(md5($plugin_slug . 'imagina_license_v5'), 0, 8);
    }

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
        private static $default_server_url = '{{SERVER_URL}}';
        private static $unique_id = '{{UNIQUE_ID}}';
        private static $is_licensed = null;
        private static $license_data = array();
        private static $last_error = '';
        private static $initialized = false;
        private static $notices_shown = false;

        const GRACE_PERIOD = 604800;
        const CACHE_DURATION = 21600;

        public static function init() {
            if (self::$initialized) return;
            self::$initialized = true;

            // Verificar licencia inmediatamente (sin hacer request al servidor aún)
            self::quick_license_check();

            add_action('admin_menu', array(__CLASS__, 'add_license_menu'));
            add_action('admin_init', array(__CLASS__, 'handle_license_actions'));
            add_action('plugins_loaded', array(__CLASS__, 'verify_on_load'), 5);
            add_action('admin_notices', array(__CLASS__, 'show_admin_notices'));
            add_filter('plugin_action_links', array(__CLASS__, 'add_settings_link'), 10, 2);
        }

        /**
         * Verificación rápida de licencia (solo cache local, sin HTTP)
         */
        private static function quick_license_check() {
            // Verificar cache primero
            $cached = get_transient('ilp_status_' . self::$unique_id);
            if ($cached !== false) {
                self::$is_licensed = ($cached === 'valid');
                return;
            }

            // Verificar si hay datos de licencia guardados
            $license_data = get_option('ilp_license_' . self::$unique_id, array());
            if (empty($license_data['license_key'])) {
                self::$is_licensed = false;
                return;
            }

            // Si hay licencia guardada pero no hay cache, asumir válida temporalmente
            // La verificación real se hará en plugins_loaded
            self::$is_licensed = true;
            self::$license_data = $license_data;
        }

        /**
         * Verificar si el plugin debe cargarse
         * Retorna true si tiene licencia o false si no
         */
        public static function should_load_plugin() {
            if (self::$is_licensed === null) {
                self::quick_license_check();
            }
            return self::$is_licensed === true;
        }

        public static function add_license_menu() {
            add_options_page(
                self::$plugin_name . ' - Licencia',
                'Licencia: ' . self::get_short_name(),
                'manage_options',
                self::$plugin_slug . '-license',
                array(__CLASS__, 'render_license_page')
            );
        }

        private static function get_short_name() {
            $name = self::$plugin_name;
            if (strlen($name) > 20) {
                $words = explode(' ', $name);
                $name = $words[0];
                if (isset($words[1])) $name .= ' ' . $words[1];
            }
            return $name;
        }

        public static function add_settings_link($links, $file) {
            if (strpos($file, self::$plugin_slug) !== false) {
                $url = admin_url('options-general.php?page=' . self::$plugin_slug . '-license');
                array_unshift($links, '<a href="' . $url . '">Licencia</a>');
            }
            return $links;
        }

        public static function render_license_page() {
            $license_data = self::get_stored_license();
            $is_active = self::is_licensed();
            $server_url = self::get_server_url();
            ?>
            <div class="wrap">
                <h1><?php echo esc_html(self::$plugin_name); ?> - Licencia</h1>

                <div class="card" style="max-width: 600px; padding: 20px;">
                    <?php if ($is_active && !empty($license_data['license_key'])): ?>
                        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                            <strong style="color: #155724;">✓ Licencia Activa</strong>
                        </div>
                        <table class="form-table">
                            <tr><th>License Key</th><td><code><?php echo esc_html(self::mask_license_key($license_data['license_key'])); ?></code></td></tr>
                            <?php if (!empty($license_data['customer_email'])): ?>
                            <tr><th>Email</th><td><?php echo esc_html($license_data['customer_email']); ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($license_data['expires_at'])): ?>
                            <tr><th>Expira</th><td><?php echo esc_html(date_i18n('d/m/Y', strtotime($license_data['expires_at']))); ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Sitio</th><td><?php echo esc_html(home_url()); ?></td></tr>
                        </table>
                        <form method="post" style="margin-top: 20px;">
                            <?php wp_nonce_field('ilp_deactivate_' . self::$unique_id); ?>
                            <input type="hidden" name="ilp_action" value="deactivate">
                            <input type="hidden" name="ilp_plugin" value="<?php echo esc_attr(self::$unique_id); ?>">
                            <button type="submit" class="button" onclick="return confirm('¿Desactivar licencia de este sitio?');">Desactivar Licencia</button>
                        </form>
                    <?php else: ?>
                        <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                            <strong style="color: #721c24;">✗ Licencia No Activa</strong>
                            <p style="margin: 10px 0 0; color: #721c24;">
                                Las funciones premium están deshabilitadas. Ingresa tu license key para activar el plugin.
                            </p>
                        </div>
                        <?php if (!empty(self::$last_error)): ?>
                        <div style="background: #fff3cd; border: 1px solid #ffeeba; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                            <strong style="color: #856404;">Último error:</strong>
                            <span style="color: #856404;"><?php echo esc_html(self::$last_error); ?></span>
                        </div>
                        <?php endif; ?>
                        <form method="post">
                            <?php wp_nonce_field('ilp_activate_' . self::$unique_id); ?>
                            <input type="hidden" name="ilp_action" value="activate">
                            <input type="hidden" name="ilp_plugin" value="<?php echo esc_attr(self::$unique_id); ?>">
                            <table class="form-table">
                                <tr>
                                    <th><label for="license_key">License Key</label></th>
                                    <td>
                                        <input type="text" name="license_key" id="license_key" class="regular-text" required style="font-family: monospace;">
                                        <p class="description">Ingresa la license key que recibiste al comprar el plugin.</p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary">Activar Licencia</button>
                            </p>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Configuración del servidor -->
                <div class="card" style="max-width: 600px; padding: 15px; margin-top: 20px;">
                    <h3 style="margin-top: 0;">Configuración del Servidor</h3>
                    <form method="post">
                        <?php wp_nonce_field('ilp_update_server_' . self::$unique_id); ?>
                        <input type="hidden" name="ilp_action" value="update_server">
                        <input type="hidden" name="ilp_plugin" value="<?php echo esc_attr(self::$unique_id); ?>">
                        <table class="form-table">
                            <tr>
                                <th><label for="server_url">URL del Servidor</label></th>
                                <td>
                                    <input type="url" name="server_url" id="server_url" class="regular-text"
                                           value="<?php echo esc_attr($server_url); ?>" placeholder="https://tu-servidor.com">
                                    <p class="description">URL del servidor de licencias. Déjalo vacío para usar el predeterminado.</p>
                                </td>
                            </tr>
                        </table>
                        <p>
                            <button type="submit" class="button">Guardar Configuración</button>
                        </p>
                    </form>
                    <p style="margin-top: 10px; color: #666;">
                        <strong>Servidor actual:</strong> <?php echo esc_html($server_url ?: 'No configurado'); ?><br>
                        <strong>Versión de protección:</strong> {{VERSION}}
                    </p>
                </div>
            </div>
            <?php
        }

        public static function handle_license_actions() {
            if (!isset($_POST['ilp_action']) || !isset($_POST['ilp_plugin'])) return;
            if ($_POST['ilp_plugin'] !== self::$unique_id) return;

            $action = sanitize_key($_POST['ilp_action']);
            $redirect_url = admin_url('options-general.php?page=' . self::$plugin_slug . '-license');

            if ($action === 'activate') {
                self::process_activation();
                wp_redirect($redirect_url . '&ilp_notice=activated');
                exit;
            } elseif ($action === 'deactivate') {
                self::process_deactivation();
                wp_redirect($redirect_url . '&ilp_notice=deactivated');
                exit;
            } elseif ($action === 'update_server') {
                self::process_update_server();
                wp_redirect($redirect_url . '&ilp_notice=server_updated');
                exit;
            }
        }

        private static function process_activation() {
            if (!check_admin_referer('ilp_activate_' . self::$unique_id)) return;

            $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
            if (empty($license_key)) {
                update_option('ilp_notice_' . self::$unique_id, array('type' => 'error', 'message' => 'Por favor ingresa una license key.'));
                return;
            }

            $server_url = self::get_server_url();
            if (empty($server_url)) {
                update_option('ilp_notice_' . self::$unique_id, array('type' => 'error', 'message' => 'No hay servidor configurado. Configura la URL del servidor primero.'));
                return;
            }

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
                $error_msg = 'Error de conexión: ' . $response->get_error_message();
                self::save_last_error($error_msg);
                update_option('ilp_notice_' . self::$unique_id, array('type' => 'error', 'message' => $error_msg));
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $status_code = wp_remote_retrieve_response_code($response);

            if ($status_code >= 400 || empty($body['success'])) {
                $error = isset($body['error']) ? $body['error'] : 'unknown';
                $message = self::get_error_message($error, isset($body['message']) ? $body['message'] : '');
                self::save_last_error($message);
                update_option('ilp_notice_' . self::$unique_id, array('type' => 'error', 'message' => $message));
                return;
            }

            $license_data = array(
                'license_key'    => $license_key,
                'site_url'       => home_url(),
                'activated_at'   => current_time('mysql'),
                'expires_at'     => isset($body['expires_at']) ? $body['expires_at'] : null,
                'customer_email' => isset($body['customer_email']) ? $body['customer_email'] : '',
                'plugin_name'    => isset($body['plugin_name']) ? $body['plugin_name'] : self::$plugin_name,
            );

            update_option('ilp_license_' . self::$unique_id, $license_data);
            delete_transient('ilp_status_' . self::$unique_id);
            delete_option('ilp_last_error_' . self::$unique_id);

            self::$is_licensed = true;
            self::$license_data = $license_data;

            update_option('ilp_notice_' . self::$unique_id, array('type' => 'success', 'message' => '¡Licencia activada correctamente! Todas las funciones premium están habilitadas.'));
        }

        private static function process_deactivation() {
            if (!check_admin_referer('ilp_deactivate_' . self::$unique_id)) return;

            $license_data = self::get_stored_license();
            if (!empty($license_data['license_key'])) {
                $server_url = self::get_server_url();
                if (!empty($server_url)) {
                    wp_remote_post(trailingslashit($server_url) . 'wp-json/imagina-license/v1/deactivate', array(
                        'timeout' => 15,
                        'body' => array(
                            'license_key' => $license_data['license_key'],
                            'site_url'    => home_url(),
                        ),
                    ));
                }
            }

            delete_option('ilp_license_' . self::$unique_id);
            delete_transient('ilp_status_' . self::$unique_id);
            delete_option('ilp_grace_' . self::$unique_id);

            self::$is_licensed = false;
            self::$license_data = array();

            update_option('ilp_notice_' . self::$unique_id, array('type' => 'warning', 'message' => 'Licencia desactivada. Las funciones premium han sido deshabilitadas.'));
        }

        private static function process_update_server() {
            if (!check_admin_referer('ilp_update_server_' . self::$unique_id)) return;

            $server_url = isset($_POST['server_url']) ? esc_url_raw($_POST['server_url']) : '';
            update_option('ilp_server_' . self::$unique_id, $server_url);
            delete_transient('ilp_status_' . self::$unique_id);

            update_option('ilp_notice_' . self::$unique_id, array('type' => 'success', 'message' => 'Configuración del servidor actualizada.'));
        }

        private static function get_error_message($error, $default = '') {
            $messages = array(
                'invalid_license_key' => 'La license key ingresada no existe. Verifica que la hayas copiado correctamente.',
                'wrong_plugin' => 'Esta license key es para otro plugin, no para "' . self::$plugin_name . '". Verifica que estés usando la licencia correcta.',
                'license_expired' => 'Esta licencia ha expirado. Contacta al proveedor para renovarla.',
                'license_not_active' => 'Esta licencia está desactivada. Contacta al proveedor.',
                'license_revoked' => 'Esta licencia ha sido revocada. Contacta al proveedor.',
                'max_activations_reached' => 'Has alcanzado el límite de activaciones para esta licencia. Desactiva la licencia en otro sitio primero o adquiere más activaciones.',
                'not_activated_on_site' => 'Esta licencia no está activada en este sitio.',
                'missing_parameters' => 'Faltan parámetros requeridos.',
            );
            return isset($messages[$error]) ? $messages[$error] : ($default ?: 'Error desconocido: ' . $error);
        }

        private static function save_last_error($message) {
            update_option('ilp_last_error_' . self::$unique_id, $message);
            self::$last_error = $message;
        }

        public static function verify_on_load() {
            self::$last_error = get_option('ilp_last_error_' . self::$unique_id, '');
            self::verify_license();
        }

        public static function verify_license($force = false) {
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

            $server_url = self::get_server_url();
            if (empty($server_url)) {
                self::$is_licensed = false;
                return false;
            }

            $result = self::verify_with_server($license_data['license_key']);

            if ($result['valid']) {
                self::$is_licensed = true;
                self::$license_data = $license_data;
                set_transient('ilp_status_' . self::$unique_id, 'valid', self::CACHE_DURATION);
                delete_option('ilp_grace_' . self::$unique_id);
                delete_option('ilp_last_error_' . self::$unique_id);
                return true;
            }

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

        private static function verify_with_server($license_key) {
            $server_url = self::get_server_url();
            $response = wp_remote_post(trailingslashit($server_url) . 'wp-json/imagina-license/v1/check', array(
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

        private static function check_grace_period() {
            $license_data = self::get_stored_license();
            if (empty($license_data['license_key'])) return false;

            $grace_data = get_option('ilp_grace_' . self::$unique_id);
            if (!$grace_data) {
                update_option('ilp_grace_' . self::$unique_id, array('started_at' => time()));
                return true;
            }

            return (time() - $grace_data['started_at']) < self::GRACE_PERIOD;
        }

        public static function show_admin_notices() {
            if (self::$notices_shown) return;
            self::$notices_shown = true;

            if (!current_user_can('manage_options')) return;

            // Mostrar notificación guardada (solo una vez)
            $notice = get_option('ilp_notice_' . self::$unique_id);
            if ($notice && isset($_GET['page']) && $_GET['page'] === self::$plugin_slug . '-license') {
                $type = isset($notice['type']) ? $notice['type'] : 'info';
                $message = isset($notice['message']) ? $notice['message'] : '';
                if ($message) {
                    echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
                }
                delete_option('ilp_notice_' . self::$unique_id);
                return;
            }

            // Mostrar aviso de licencia no activa (excepto en página de licencia)
            if (isset($_GET['page']) && $_GET['page'] === self::$plugin_slug . '-license') return;

            if (!self::is_licensed()) {
                $url = admin_url('options-general.php?page=' . self::$plugin_slug . '-license');
                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . esc_html(self::$plugin_name) . ':</strong> ';
                echo 'Licencia no activa. Las funciones premium están deshabilitadas. ';
                echo '<a href="' . esc_url($url) . '" class="button button-primary" style="margin-left:10px;">Activar Licencia</a>';
                echo '</p></div>';
            }
        }

        private static function get_stored_license() {
            if (!empty(self::$license_data)) return self::$license_data;
            return get_option('ilp_license_' . self::$unique_id, array());
        }

        private static function get_server_url() {
            $custom = get_option('ilp_server_' . self::$unique_id, '');
            if (!empty($custom)) return $custom;
            if (!empty(self::$default_server_url)) return self::$default_server_url;
            $config = get_option('imagina_updater_client_config', array());
            return isset($config['server_url']) ? $config['server_url'] : '';
        }

        private static function mask_license_key($key) {
            if (strlen($key) < 20) return $key;
            return substr($key, 0, 8) . str_repeat('*', strlen($key) - 16) . substr($key, -8);
        }

        public static function is_licensed() {
            if (self::$is_licensed === null) self::verify_license();
            return self::$is_licensed;
        }

        public static function get_license_data() {
            return self::get_stored_license();
        }

        public static function recheck() {
            delete_transient('ilp_status_' . self::$unique_id);
            return self::verify_license(true);
        }
    }

    {{CLASS_NAME}}::init();
}

// ============================================================================
// PLUGIN PROTECTION - BLOCK LOADING IF UNLICENSED
// ============================================================================

if (!{{CLASS_NAME}}::should_load_plugin()) {
    // Plugin sin licencia - no cargar funcionalidad
    return;
}

// ============================================================================
// END IMAGINA LICENSE PROTECTION
// ============================================================================

PHPCODE;
    }

    public static function generate_minified($plugin_name, $plugin_slug, $server_url = '') {
        $code = self::generate($plugin_name, $plugin_slug, $server_url);
        $code = preg_replace('/\/\/.*$/m', '', $code);
        $code = preg_replace('/\/\*.*?\*\//s', '', $code);
        $code = preg_replace('/\n\s*\n/', "\n", $code);
        return $code;
    }

    public static function calculate_checksum($code) {
        return hash('sha256', preg_replace('/\s+/', ' ', $code));
    }
}
