<?php
/**
 * Interfaz de administración del plugin servidor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_Admin {

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
    }

    /**
     * Agregar páginas al menú de administración
     */
    public function add_menu_pages() {
        add_menu_page(
            __('Imagina Updater', 'imagina-updater-server'),
            __('Imagina Updater', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-server',
            array($this, 'render_dashboard_page'),
            'dashicons-update',
            30
        );

        add_submenu_page(
            'imagina-updater-server',
            __('Dashboard', 'imagina-updater-server'),
            __('Dashboard', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-server',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'imagina-updater-server',
            __('Plugins', 'imagina-updater-server'),
            __('Plugins', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-plugins',
            array($this, 'render_plugins_page')
        );

        add_submenu_page(
            'imagina-updater-server',
            __('API Keys', 'imagina-updater-server'),
            __('API Keys', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-api-keys',
            array($this, 'render_api_keys_page')
        );
    }

    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'imagina-updater') === false) {
            return;
        }

        wp_enqueue_style(
            'imagina-updater-server-admin',
            IMAGINA_UPDATER_SERVER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            IMAGINA_UPDATER_SERVER_VERSION
        );
    }

    /**
     * Manejar acciones de formularios
     */
    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Crear API Key
        if (isset($_POST['imagina_create_api_key']) && check_admin_referer('imagina_create_api_key')) {
            $site_name = sanitize_text_field($_POST['site_name']);
            $site_url = esc_url_raw($_POST['site_url']);

            $result = Imagina_Updater_Server_API_Keys::create($site_name, $site_url);

            if (is_wp_error($result)) {
                add_settings_error('imagina_updater', 'create_error', $result->get_error_message(), 'error');
            } else {
                add_settings_error('imagina_updater', 'create_success', __('API Key creada exitosamente', 'imagina-updater-server'), 'success');
                set_transient('imagina_new_api_key', $result['api_key'], 60);
            }
        }

        // Eliminar API Key
        if (isset($_GET['action']) && $_GET['action'] === 'delete_api_key' && isset($_GET['id']) && check_admin_referer('delete_api_key_' . $_GET['id'])) {
            Imagina_Updater_Server_API_Keys::delete(intval($_GET['id']));
            add_settings_error('imagina_updater', 'delete_success', __('API Key eliminada', 'imagina-updater-server'), 'success');
        }

        // Activar/Desactivar API Key
        if (isset($_GET['action']) && $_GET['action'] === 'toggle_api_key' && isset($_GET['id']) && check_admin_referer('toggle_api_key_' . $_GET['id'])) {
            $id = intval($_GET['id']);
            $key = Imagina_Updater_Server_API_Keys::get_by_id($id);
            if ($key) {
                Imagina_Updater_Server_API_Keys::set_active($id, !$key->is_active);
                add_settings_error('imagina_updater', 'toggle_success', __('Estado actualizado', 'imagina-updater-server'), 'success');
            }
        }

        // Subir plugin
        if (isset($_POST['imagina_upload_plugin']) && check_admin_referer('imagina_upload_plugin')) {
            if (isset($_FILES['plugin_file']) && $_FILES['plugin_file']['error'] === UPLOAD_ERR_OK) {
                $changelog = isset($_POST['changelog']) ? $_POST['changelog'] : '';

                $result = Imagina_Updater_Server_Plugin_Manager::upload_plugin($_FILES['plugin_file'], $changelog);

                if (is_wp_error($result)) {
                    add_settings_error('imagina_updater', 'upload_error', $result->get_error_message(), 'error');
                } else {
                    add_settings_error('imagina_updater', 'upload_success', sprintf(
                        __('Plugin "%s" versión %s subido exitosamente', 'imagina-updater-server'),
                        $result['name'],
                        $result['version']
                    ), 'success');
                }
            } else {
                add_settings_error('imagina_updater', 'upload_error', __('Error al subir el archivo', 'imagina-updater-server'), 'error');
            }
        }

        // Eliminar plugin
        if (isset($_GET['action']) && $_GET['action'] === 'delete_plugin' && isset($_GET['id']) && check_admin_referer('delete_plugin_' . $_GET['id'])) {
            $result = Imagina_Updater_Server_Plugin_Manager::delete_plugin(intval($_GET['id']));

            if (is_wp_error($result)) {
                add_settings_error('imagina_updater', 'delete_error', $result->get_error_message(), 'error');
            } else {
                add_settings_error('imagina_updater', 'delete_success', __('Plugin eliminado', 'imagina-updater-server'), 'success');
            }
        }
    }

    /**
     * Renderizar página de dashboard
     */
    public function render_dashboard_page() {
        global $wpdb;

        $total_plugins = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_plugins");
        $total_api_keys = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_api_keys WHERE is_active = 1");
        $total_downloads = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_downloads");

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Renderizar página de plugins
     */
    public function render_plugins_page() {
        $plugins = Imagina_Updater_Server_Plugin_Manager::get_all_plugins();

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/plugins.php';
    }

    /**
     * Renderizar página de API Keys
     */
    public function render_api_keys_page() {
        $api_keys = Imagina_Updater_Server_API_Keys::get_all();
        $new_api_key = get_transient('imagina_new_api_key');

        if ($new_api_key) {
            delete_transient('imagina_new_api_key');
        }

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/api-keys.php';
    }
}
