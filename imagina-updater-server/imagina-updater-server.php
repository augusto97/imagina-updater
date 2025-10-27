<?php
/**
 * Plugin Name: Imagina Updater Server
 * Plugin URI: https://github.com/augusto97/imagina-updater
 * Description: Sistema central para gestionar y distribuir actualizaciones de plugins propios a sitios cliente
 * Version: 1.0.0
 * Author: Imagina
 * Author URI: https://imagina.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: imagina-updater-server
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Si se accede directamente, salir
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('IMAGINA_UPDATER_SERVER_VERSION', '1.0.0');
define('IMAGINA_UPDATER_SERVER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMAGINA_UPDATER_SERVER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IMAGINA_UPDATER_SERVER_PLUGIN_FILE', __FILE__);

/**
 * Clase principal del plugin servidor
 */
class Imagina_Updater_Server {

    /**
     * Instancia única de la clase
     */
    private static $instance = null;

    /**
     * Obtener instancia única (Singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor privado para Singleton
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        require_once IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'includes/class-database.php';
        require_once IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'includes/class-api-keys.php';
        require_once IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'includes/class-plugin-manager.php';
        require_once IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/class-admin.php';
        require_once IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'api/class-rest-api.php';
    }

    /**
     * Inicializar hooks de WordPress
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Inicializar componentes del plugin
     */
    public function init() {
        // Inicializar componentes
        Imagina_Updater_Server_Admin::get_instance();
        Imagina_Updater_Server_REST_API::get_instance();
    }

    /**
     * Cargar traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'imagina-updater-server',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Activar plugin - crear tablas y estructura necesaria
     */
    public function activate() {
        Imagina_Updater_Server_Database::create_tables();

        // Crear directorio de uploads si no existe
        $upload_dir = wp_upload_dir();
        $plugin_upload_dir = $upload_dir['basedir'] . '/imagina-updater-plugins';

        if (!file_exists($plugin_upload_dir)) {
            wp_mkdir_p($plugin_upload_dir);

            // Proteger directorio con .htaccess
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files *.zip>\n";
            $htaccess_content .= "    Require all denied\n";
            $htaccess_content .= "</Files>";

            file_put_contents($plugin_upload_dir . '/.htaccess', $htaccess_content);
            file_put_contents($plugin_upload_dir . '/index.php', '<?php // Silence is golden');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Desactivar plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

/**
 * Iniciar el plugin
 */
function imagina_updater_server() {
    return Imagina_Updater_Server::get_instance();
}

// Iniciar el plugin
imagina_updater_server();
