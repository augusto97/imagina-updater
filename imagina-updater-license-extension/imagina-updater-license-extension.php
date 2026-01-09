<?php
/**
 * Plugin Name: Imagina Updater License Extension
 * Plugin URI: https://github.com/augusto97/imagina-updater
 * Description: Extensión para el plugin Imagina Updater Server que agrega sistema de gestión de licencias para plugins premium con protección híbrida multicapa.
 * Version: 5.3.0
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
 *
 * Sistema de Licencias v5.3.0 - Protección Multinivel
 * ====================================================
 * - Sistema completo de License Keys por plugin
 * - Activación/desactivación de licencias por sitio
 * - Panel de administración para gestión de licencias
 * - API REST para activar, desactivar y verificar licencias
 * - Límite de activaciones por licencia
 * - Expiración de licencias
 * - Tracking de sitios activados
 * - Auto-corrección de slug_override en activación
 * - BLOQUEO REAL de funcionalidad sin licencia
 * - PROTECCIÓN MULTINIVEL v5.3.0:
 *   - 5 capas de verificación distribuidas
 *   - Nombres de funciones ofuscados
 *   - Verificación de integridad del código
 *   - Firma de licencia local
 *   - Anti-tampering detection
 *   - Hooks en múltiples puntos del ciclo de WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('IMAGINA_LICENSE_VERSION', '5.3.0');
define('IMAGINA_LICENSE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMAGINA_LICENSE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IMAGINA_LICENSE_PLUGIN_FILE', __FILE__);

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

    // Guardar URL del servidor para uso en protección directa
    update_option('imagina_updater_server_url', home_url());
}
register_activation_hook(__FILE__, 'imagina_license_activate');

/**
 * Desactivación del plugin
 */
function imagina_license_deactivate() {
    // Limpiar transients
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_imagina_license_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_imagina_license_%'");
}
register_deactivation_hook(__FILE__, 'imagina_license_deactivate');

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
    require_once IMAGINA_LICENSE_PLUGIN_DIR . 'includes/class-protection-generator.php';
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
add_action('plugins_loaded', 'imagina_license_init', 20);

/**
 * Función de log helper
 *
 * @param string $message Mensaje a registrar
 * @param string $level Nivel de log (info, warning, error, debug)
 * @param array $context Contexto adicional
 */
function imagina_license_log($message, $level = 'info', $context = array()) {
    if (function_exists('imagina_updater_server_log')) {
        imagina_updater_server_log('[LICENSE] ' . $message, $level, $context);
    } else {
        $context_str = !empty($context) ? ' | ' . wp_json_encode($context) : '';
        error_log('[IMAGINA LICENSE ' . strtoupper($level) . '] ' . $message . $context_str);
    }
}

/**
 * Obtener versión del sistema de protección
 *
 * @return string
 */
function imagina_license_get_protection_version() {
    if (class_exists('Imagina_License_Protection_Generator')) {
        return Imagina_License_Protection_Generator::PROTECTION_VERSION;
    }
    return IMAGINA_LICENSE_VERSION;
}

/**
 * CLI: Inyectar protección en todos los plugins premium
 * Uso: wp eval "imagina_license_inject_all_protection();"
 */
function imagina_license_inject_all_protection() {
    if (!class_exists('Imagina_License_Admin')) {
        require_once IMAGINA_LICENSE_PLUGIN_DIR . 'includes/class-admin.php';
    }

    $results = Imagina_License_Admin::inject_protection_in_all_premium_plugins();

    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::log("Protección inyectada en {$results['success']} plugins.");
        WP_CLI::log("Saltados: {$results['skipped']}");
        WP_CLI::log("Errores: {$results['failed']}");

        foreach ($results['details'] as $detail) {
            WP_CLI::log("  - {$detail['plugin']}: {$detail['status']} - {$detail['message']}");
        }
    }

    return $results;
}
