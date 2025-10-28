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
 * Usa strings simples para evitar referencias a clases antes de cargarlas
 */
function imagina_updater_log($message, $level = 'info', $context = array()) {
    // Solo proceder si el Logger está cargado
    if (!class_exists('Imagina_Updater_Client_Logger')) {
        return;
    }

    // Convertir nivel a mayúsculas para consistencia
    $level = strtoupper($level);

    // Validar nivel
    $valid_levels = array('DEBUG', 'INFO', 'WARNING', 'ERROR');
    if (!in_array($level, $valid_levels)) {
        $level = 'INFO';
    }

    // Usar el Logger
    Imagina_Updater_Client_Logger::get_instance()->log($message, $level, $context);
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
        imagina_updater_log('Imagina Updater Client inicializado', 'info');

        // Solo inicializar si está configurado
        if ($this->is_configured()) {
            imagina_updater_log('Plugin configurado correctamente, inicializando sistema de actualizaciones', 'info');
            Imagina_Updater_Client_Updater::get_instance();
        } else {
            imagina_updater_log('Plugin sin configurar. Configure la URL del servidor y la API Key en Ajustes > Imagina Updater', 'warning');
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

// Iniciar el plugin (sin log aquí porque la clase Logger aún no está cargada)
imagina_updater_client();
