<?php
/**
 * Plugin Name: Imagina Updater License Extension
 * Plugin URI: https://github.com/augusto97/imagina-updater
 * Description: Extensión para el plugin Imagina Updater Server que agrega sistema de gestión de licencias para plugins premium
 * Version: 1.0.0
 * Author: Imagina
 * Author URI: https://imagina.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: imagina-updater-license
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * Requires Plugins: imagina-updater-server
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('IMAGINA_LICENSE_VERSION', '1.0.0');
define('IMAGINA_LICENSE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMAGINA_LICENSE_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Verificar que el plugin base esté activo
 */
function imagina_license_check_dependencies() {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // Verificar si imagina-updater-server está activo
    $server_plugin_paths = array(
        'imagina-updater-server/imagina-updater-server.php',
        'imagina-updater/imagina-updater-server.php'
    );

    $server_active = false;
    foreach ($server_plugin_paths as $path) {
        if (is_plugin_active($path)) {
            $server_active = true;
            break;
        }
    }

    if (!$server_active) {
        add_action('admin_notices', 'imagina_license_dependency_notice');
        return false;
    }

    return true;
}

/**
 * Mostrar aviso de dependencia
 */
function imagina_license_dependency_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php _e('Imagina Updater License Extension', 'imagina-updater-license'); ?></strong>:
            <?php _e('Este plugin requiere que "Imagina Updater Server" esté instalado y activo.', 'imagina-updater-license'); ?>
        </p>
    </div>
    <?php
}

/**
 * Activación del plugin
 */
function imagina_license_activate() {
    if (!imagina_license_check_dependencies()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('Este plugin requiere que "Imagina Updater Server" esté instalado y activo.', 'imagina-updater-license'),
            __('Error de Activación', 'imagina-updater-license'),
            array('back_link' => true)
        );
    }

    // Crear/actualizar tablas
    require_once IMAGINA_LICENSE_PLUGIN_DIR . 'includes/class-database.php';
    Imagina_License_Database::create_tables();
}
register_activation_hook(__FILE__, 'imagina_license_activate');

/**
 * Inicialización del plugin
 */
function imagina_license_init() {
    // Verificar dependencias
    if (!imagina_license_check_dependencies()) {
        return;
    }

    // Cargar archivos necesarios
    require_once IMAGINA_LICENSE_PLUGIN_DIR . 'includes/class-database.php';
    require_once IMAGINA_LICENSE_PLUGIN_DIR . 'includes/class-sdk-injector.php';
    require_once IMAGINA_LICENSE_PLUGIN_DIR . 'includes/class-license-crypto-server.php';
    require_once IMAGINA_LICENSE_PLUGIN_DIR . 'includes/class-license-api.php';
    require_once IMAGINA_LICENSE_PLUGIN_DIR . 'includes/class-admin.php';

    // Inicializar API REST
    add_action('rest_api_init', array('Imagina_License_API', 'register_routes'));

    // Inicializar interfaz de administración
    if (is_admin()) {
        Imagina_License_Admin::init();
    }

    // Asegurar que las tablas estén actualizadas
    $current_version = get_option('imagina_license_db_version', '0');
    if (version_compare($current_version, IMAGINA_LICENSE_VERSION, '<')) {
        Imagina_License_Database::create_tables();
        update_option('imagina_license_db_version', IMAGINA_LICENSE_VERSION);
    }
}
add_action('plugins_loaded', 'imagina_license_init', 20); // Prioridad 20 para cargar después del plugin base

/**
 * Función de log helper
 */
function imagina_license_log($message, $level = 'info', $context = array()) {
    if (function_exists('imagina_updater_server_log')) {
        imagina_updater_server_log('[LICENSE] ' . $message, $level, $context);
    } else {
        error_log('[IMAGINA LICENSE ' . strtoupper($level) . '] ' . $message);
    }
}
