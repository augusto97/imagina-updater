<?php
/**
 * Plugin Name: Imagina Updater Client
 * Plugin URI: https://github.com/augusto97/imagina-updater
 * Description: Cliente para recibir actualizaciones de plugins desde un servidor central Imagina Updater
 * Version: 1.0.0
 * Author: Imagina
 * Author URI: https://imagina.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: imagina-updater-client
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// Si se accede directamente, salir
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('IMAGINA_UPDATER_CLIENT_VERSION', '1.0.1');
define('IMAGINA_UPDATER_CLIENT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMAGINA_UPDATER_CLIENT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IMAGINA_UPDATER_CLIENT_PLUGIN_FILE', __FILE__);

/**
 * Función helper para logging usando el sistema propio
 */
function imagina_updater_log($message, $level = 'info', $context = array()) {
    // Mapear nivel a constantes de Logger
    $level_map = array(
        'debug' => Imagina_Updater_Client_Logger::LEVEL_DEBUG,
        'info' => Imagina_Updater_Client_Logger::LEVEL_INFO,
        'warning' => Imagina_Updater_Client_Logger::LEVEL_WARNING,
        'error' => Imagina_Updater_Client_Logger::LEVEL_ERROR
    );

    $log_level = isset($level_map[$level]) ? $level_map[$level] : Imagina_Updater_Client_Logger::LEVEL_INFO;

    // Usar el Logger
    if (class_exists('Imagina_Updater_Client_Logger')) {
        Imagina_Updater_Client_Logger::get_instance()->log($message, $log_level, $context);
    }
}

/**
 * Clase principal del plugin cliente
 */
class Imagina_Updater_Client {

    /**
     * Instancia única de la clase
     */
    private static $instance = null;

    /**
     * Configuración del cliente
     */
    private $config = array();

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
        $this->load_config();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Cargar configuración guardada
     */
    private function load_config() {
        $this->config = get_option('imagina_updater_client_config', array(
            'server_url' => '',
            'api_key' => '',
            'enabled_plugins' => array(),
            'enable_logging' => false, // Logs desactivados por defecto
            'log_level' => 'INFO' // Nivel por defecto
        ));
    }

    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        require_once IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'includes/class-logger.php';
        require_once IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'includes/class-api-client.php';
        require_once IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'includes/class-updater.php';
        require_once IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'admin/class-admin.php';
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
        imagina_updater_log('Método init() ejecutado');
        imagina_updater_log('Configuración actual: ' . print_r($this->config, true));

        // Solo inicializar si está configurado
        if ($this->is_configured()) {
            imagina_updater_log('Plugin configurado, inicializando Updater');
            Imagina_Updater_Client_Updater::get_instance();
        } else {
            imagina_updater_log('Plugin NO configurado, Updater no se inicializa', 'warning');
            imagina_updater_log('server_url: ' . ($this->config['server_url'] ?? 'vacío'));
            imagina_updater_log('api_key: ' . (empty($this->config['api_key']) ? 'vacío' : 'presente'));
        }

        // Siempre inicializar admin
        Imagina_Updater_Client_Admin::get_instance();
    }

    /**
     * Cargar traducciones
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'imagina-updater-client',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    /**
     * Verificar si el plugin está configurado
     */
    public function is_configured() {
        return !empty($this->config['server_url']) && !empty($this->config['api_key']);
    }

    /**
     * Obtener configuración
     */
    public function get_config($key = null) {
        if ($key === null) {
            return $this->config;
        }

        return isset($this->config[$key]) ? $this->config[$key] : null;
    }

    /**
     * Actualizar configuración
     */
    public function update_config($config) {
        $this->config = array_merge($this->config, $config);
        update_option('imagina_updater_client_config', $this->config);
    }

    /**
     * Activar plugin
     */
    public function activate() {
        // Crear configuración por defecto si no existe
        if (!get_option('imagina_updater_client_config')) {
            add_option('imagina_updater_client_config', array(
                'server_url' => '',
                'api_key' => '',
                'enabled_plugins' => array(),
                'enable_logging' => false,
                'log_level' => 'INFO'
            ));
        }
    }

    /**
     * Desactivar plugin
     */
    public function deactivate() {
        // Limpiar transients
        delete_transient('imagina_updater_client_check');
    }
}

/**
 * Iniciar el plugin
 */
function imagina_updater_client() {
    return Imagina_Updater_Client::get_instance();
}

// Iniciar el plugin
imagina_updater_log('Plugin cargado por WordPress');
imagina_updater_client();
