<?php
/**
 * Interfaz de administración del plugin cliente
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Client_Admin {

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
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_notices', array($this, 'show_config_notice'));
    }

    /**
     * Agregar páginas al menú
     */
    public function add_menu_pages() {
        add_options_page(
            __('Imagina Updater', 'imagina-updater-client'),
            __('Imagina Updater', 'imagina-updater-client'),
            'manage_options',
            'imagina-updater-client',
            array($this, 'render_settings_page')
        );

        // Submenú para logs
        add_submenu_page(
            'options-general.php',
            __('Imagina Updater - Logs', 'imagina-updater-client'),
            __('Imagina Updater Logs', 'imagina-updater-client'),
            'manage_options',
            'imagina-updater-client-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_imagina-updater-client') {
            return;
        }

        wp_enqueue_style(
            'imagina-updater-client-admin',
            IMAGINA_UPDATER_CLIENT_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            IMAGINA_UPDATER_CLIENT_VERSION
        );

        wp_enqueue_script(
            'imagina-updater-client-admin',
            IMAGINA_UPDATER_CLIENT_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            IMAGINA_UPDATER_CLIENT_VERSION,
            true
        );
    }

    /**
     * Mostrar aviso si no está configurado
     */
    public function show_config_notice() {
        if (!imagina_updater_client()->is_configured()) {
            $screen = get_current_screen();

            if ($screen->id !== 'settings_page_imagina-updater-client') {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Imagina Updater Client:', 'imagina-updater-client'); ?></strong>
                        <?php _e('El plugin aún no está configurado.', 'imagina-updater-client'); ?>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=imagina-updater-client')); ?>">
                            <?php _e('Configurar ahora', 'imagina-updater-client'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Manejar acciones
     */
    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Guardar configuración
        if (isset($_POST['imagina_save_config']) && check_admin_referer('imagina_save_config')) {
            $server_url = esc_url_raw($_POST['server_url']);
            $api_key = sanitize_text_field($_POST['api_key']);
            $enable_logging = isset($_POST['enable_logging']) ? true : false;
            $log_level = isset($_POST['log_level']) ? sanitize_text_field($_POST['log_level']) : 'INFO';

            // Validar campos
            if (empty($server_url) || empty($api_key)) {
                add_settings_error('imagina_updater_client', 'missing_fields', __('URL del servidor y API Key son requeridos', 'imagina-updater-client'), 'error');
                return;
            }

            // Validar conexión
            $api_client = new Imagina_Updater_Client_API($server_url, $api_key);
            $validation = $api_client->validate();

            if (is_wp_error($validation)) {
                add_settings_error('imagina_updater_client', 'connection_error', sprintf(
                    __('Error de conexión: %s', 'imagina-updater-client'),
                    $validation->get_error_message()
                ), 'error');
                return;
            }

            // Guardar configuración
            imagina_updater_client()->update_config(array(
                'server_url' => $server_url,
                'api_key' => $api_key,
                'enable_logging' => $enable_logging,
                'log_level' => $log_level
            ));

            add_settings_error('imagina_updater_client', 'config_saved', __('Configuración guardada exitosamente', 'imagina-updater-client'), 'success');

            // Limpiar cache de actualizaciones y plugins del servidor
            delete_site_transient('update_plugins');
            delete_transient('imagina_updater_cached_updates');
            delete_transient('imagina_updater_server_plugins_' . md5($server_url));
        }

        // Guardar plugins habilitados
        if (isset($_POST['imagina_save_plugins']) && check_admin_referer('imagina_save_plugins')) {
            $enabled_plugins = isset($_POST['enabled_plugins']) && is_array($_POST['enabled_plugins'])
                ? array_map('sanitize_text_field', $_POST['enabled_plugins'])
                : array();

            imagina_updater_client()->update_config(array(
                'enabled_plugins' => $enabled_plugins
            ));

            add_settings_error('imagina_updater_client', 'plugins_saved', __('Plugins actualizados', 'imagina-updater-client'), 'success');

            // Limpiar cache de actualizaciones
            delete_site_transient('update_plugins');
            delete_transient('imagina_updater_cached_updates');
        }

        // Test de conexión
        if (isset($_POST['imagina_test_connection']) && check_admin_referer('imagina_test_connection')) {
            $config = imagina_updater_client()->get_config();

            $api_client = new Imagina_Updater_Client_API($config['server_url'], $config['api_key']);
            $validation = $api_client->validate();

            if (is_wp_error($validation)) {
                add_settings_error('imagina_updater_client', 'test_failed', sprintf(
                    __('Test fallido: %s', 'imagina-updater-client'),
                    $validation->get_error_message()
                ), 'error');
            } else {
                add_settings_error('imagina_updater_client', 'test_success', sprintf(
                    __('Conexión exitosa con: %s', 'imagina-updater-client'),
                    $validation['site_name']
                ), 'success');
            }
        }

        // Limpiar logs
        if (isset($_POST['imagina_clear_logs']) && check_admin_referer('imagina_clear_logs')) {
            Imagina_Updater_Client_Logger::get_instance()->clear_logs();
            add_settings_error('imagina_updater_client', 'logs_cleared', __('Logs eliminados exitosamente', 'imagina-updater-client'), 'success');
        }

        // Descargar logs
        if (isset($_GET['action']) && $_GET['action'] === 'download_log' && check_admin_referer('download_log', 'nonce')) {
            $this->download_log();
        }
    }

    /**
     * Descargar archivo de log
     */
    private function download_log() {
        $logger = Imagina_Updater_Client_Logger::get_instance();
        $log_file = $logger->get_log_file_path();

        if (!file_exists($log_file)) {
            wp_die(__('Archivo de log no encontrado', 'imagina-updater-client'));
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="imagina-updater-' . date('Y-m-d-His') . '.log"');
        header('Content-Length: ' . filesize($log_file));
        readfile($log_file);
        exit;
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        $config = imagina_updater_client()->get_config();
        $is_configured = imagina_updater_client()->is_configured();

        // Obtener plugins disponibles del servidor con caché
        $server_plugins = array();
        if ($is_configured) {
            // Intentar obtener de caché (24 horas)
            $cache_key = 'imagina_updater_server_plugins_' . md5($config['server_url']);
            $server_plugins = get_transient($cache_key);

            if ($server_plugins === false) {
                // No hay caché, consultar servidor
                $api_client = new Imagina_Updater_Client_API($config['server_url'], $config['api_key']);
                $result = $api_client->get_plugins();

                if (!is_wp_error($result)) {
                    $server_plugins = $result;
                    // Guardar en caché por 24 horas
                    set_transient($cache_key, $server_plugins, 24 * HOUR_IN_SECONDS);
                } else {
                    $server_plugins = array();
                }
            }
        }

        // Obtener plugins instalados
        $installed_plugins = array();
        if ($is_configured && class_exists('Imagina_Updater_Client_Updater')) {
            $updater = Imagina_Updater_Client_Updater::get_instance();
            $installed_plugins = $updater->get_installed_plugins();
        }

        include IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Renderizar página de logs
     */
    public function render_logs_page() {
        $logger = Imagina_Updater_Client_Logger::get_instance();
        $log_content = $logger->get_log_content(1000); // Últimas 1000 líneas
        $log_size = $logger->get_log_size();
        $log_files = $logger->get_log_files();
        $is_enabled = $logger->is_enabled();

        include IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'admin/views/logs.php';
    }
}
