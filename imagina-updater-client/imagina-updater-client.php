<?php
/**
 * Plugin Name: Imagina Updater Client
 * Plugin URI: https://imaginawp.com/actualizador-de-plugins/
 * Description: Cliente para recibir actualizaciones de plugins desde un servidor central Imagina Updater
 * Version: 1.0.0
 * Author: Imagina WP
 * Author URI: https://imaginawp.com
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
 * Función stub vacía para mantener compatibilidad con código existente
 * (el sistema de logs fue removido por no ser útil)
 */
function imagina_updater_log($message, $level = 'info', $context = array()) {
    // Sistema de logs deshabilitado
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
            'plugin_display_mode' => 'installed_only' // Modo de visualización por defecto
        ));
    }

    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
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
        // Solo inicializar si está configurado
        if ($this->is_configured()) {
            Imagina_Updater_Client_Updater::get_instance();
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
     * Requiere activation_token (no solo API key)
     */
    public function is_configured() {
        return !empty($this->config['server_url']) && !empty($this->config['activation_token']);
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
     * Obtener instancia del API client
     * SOLO funciona con activation_token (requiere sitio activado)
     *
     * @return Imagina_Updater_Client_API|null
     */
    public function get_api_client() {
        if (!$this->is_configured()) {
            return null;
        }

        return new Imagina_Updater_Client_API($this->config['server_url'], $this->config['activation_token']);
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
                'enabled_plugins' => array()
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
