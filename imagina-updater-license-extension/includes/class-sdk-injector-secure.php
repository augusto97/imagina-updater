<?php
/**
 * Sistema de protección multicapa para plugins premium
 * Este código reemplaza el simple que inyectábamos antes
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_SDK_Injector_Secure {

    /**
     * Generar código de protección multicapa
     *
     * @param string $plugin_name Nombre del plugin
     * @param string $plugin_slug Slug del plugin
     * @return string Código PHP ofuscado y seguro
     */
    public static function generate_secure_code($plugin_name, $plugin_slug) {
        // Ofuscar variables sensibles
        $var1 = base64_encode('imagina_license_validator');
        $var2 = base64_encode('is_valid');
        $var3 = base64_encode('Imagina_License_SDK');

        // Generar hash único para verificación de integridad
        $integrity_hash = hash('sha256', $plugin_name . $plugin_slug . wp_generate_password(32, false));

        // Código PHP con múltiples capas de protección
        $code = <<<'PHPCODE'

// ===== IMAGINA LICENSE PROTECTION SYSTEM =====
if (!defined('ABSPATH')) { exit; }

// Capa 1: Carga del SDK
if (file_exists(plugin_dir_path(__FILE__) . 'imagina-license-sdk/loader.php')) {
    require_once plugin_dir_path(__FILE__) . 'imagina-license-sdk/loader.php';
} else {
    // SDK faltante - desactivar inmediatamente
    add_action('admin_init', function() {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Sistema de validación no encontrado. Reinstale el plugin desde el servidor oficial.');
    }, 1);
    return;
}

// Capa 2: Variables ofuscadas
$_v1 = 'PLUGIN_SLUG_PLACEHOLDER';
$_v2 = 'PLUGIN_NAME_PLACEHOLDER';
$_v3 = base64_decode('VAR3_PLACEHOLDER');
$_v4 = base64_decode('VAR1_PLACEHOLDER');
$_v5 = base64_decode('VAR2_PLACEHOLDER');

// Capa 3: Función de verificación encapsulada
if (!function_exists('_il_check_HASH_PLACEHOLDER')) {
    function _il_check_HASH_PLACEHOLDER() {
        global $_v1, $_v3, $_v4, $_v5;

        if (!class_exists($_v3)) {
            return false;
        }

        $instance = call_user_func(array($_v3, 'get_validator'), $_v1);

        if (!$instance) {
            return false;
        }

        return call_user_func(array($instance, $_v5));
    }
}

// Capa 4: Inicialización del validador
add_action('plugins_loaded', function() {
    global $_v1, $_v2, $_v3;

    if (class_exists($_v3)) {
        call_user_func(array($_v3, 'init'), array(
            'plugin_slug' => $_v1,
            'plugin_name' => $_v2,
            'plugin_file' => __FILE__
        ));
    }
}, 1);

// Capa 5: Verificación en múltiples hooks críticos
$_hooks = array('admin_init', 'wp_loaded', 'init');
foreach ($_hooks as $_h) {
    add_action($_h, function() use ($_v2) {
        if (!function_exists('_il_check_HASH_PLACEHOLDER') || !_il_check_HASH_PLACEHOLDER()) {
            // Licencia inválida - mostrar aviso y desactivar
            add_action('admin_notices', function() use ($_v2) {
                echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html($_v2) . '</strong>: Licencia requerida. <a href="' . admin_url('options-general.php?page=imagina-updater') . '">Activar licencia</a></p></div>';
            });

            add_action('admin_head', function() {
                deactivate_plugins(plugin_basename(__FILE__));
            });

            // Prevenir ejecución de código del plugin
            remove_all_actions('init');
            remove_all_actions('wp_loaded');
            remove_all_filters('the_content');
            remove_all_filters('the_excerpt');

            return;
        }
    }, 1);
}

// Capa 6: Verificación de integridad del SDK
add_action('admin_init', function() {
    $sdk_path = plugin_dir_path(__FILE__) . 'imagina-license-sdk/loader.php';

    if (file_exists($sdk_path)) {
        $expected_hash = 'INTEGRITY_HASH_PLACEHOLDER';
        $actual_content = file_get_contents($sdk_path);

        // Verificar que el archivo no fue modificado (comentado por ahora para no romper)
        // En producción, descomentar y poner el hash real
        /*
        if (md5($actual_content) !== $expected_hash) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('El sistema de protección ha sido modificado. Reinstale desde el servidor oficial.');
        }
        */
    }
}, 2);

// Capa 7: Heartbeat de verificación periódica
if (!wp_next_scheduled('_il_heartbeat_HASH_PLACEHOLDER')) {
    wp_schedule_event(time(), 'hourly', '_il_heartbeat_HASH_PLACEHOLDER');
}

add_action('_il_heartbeat_HASH_PLACEHOLDER', function() {
    if (!function_exists('_il_check_HASH_PLACEHOLDER') || !_il_check_HASH_PLACEHOLDER()) {
        // Forzar re-validación en próxima carga
        delete_transient('imagina_license_cache_' . 'PLUGIN_SLUG_PLACEHOLDER');
    }
});

// ===== FIN PROTECTION SYSTEM =====

PHPCODE;

        // Reemplazar placeholders
        $code = str_replace('PLUGIN_SLUG_PLACEHOLDER', addslashes($plugin_slug), $code);
        $code = str_replace('PLUGIN_NAME_PLACEHOLDER', addslashes($plugin_name), $code);
        $code = str_replace('VAR1_PLACEHOLDER', $var1, $code);
        $code = str_replace('VAR2_PLACEHOLDER', $var2, $code);
        $code = str_replace('VAR3_PLACEHOLDER', $var3, $code);
        $code = str_replace('HASH_PLACEHOLDER', substr($integrity_hash, 0, 8), $code);
        $code = str_replace('INTEGRITY_HASH_PLACEHOLDER', $integrity_hash, $code);

        return $code;
    }

    /**
     * Generar código adicional para interceptar funciones críticas del plugin
     * Este código se inyecta en diferentes partes del plugin
     *
     * @param string $plugin_slug Slug del plugin
     * @return array Array de snippets de código
     */
    public static function generate_function_wrappers($plugin_slug) {
        $hash = substr(hash('sha256', $plugin_slug), 0, 8);

        return array(
            // Wrapper para funciones críticas
            'function_wrapper' => <<<PHPCODE

// Protección de funciones críticas
if (!function_exists('_il_wrap_$hash')) {
    function _il_wrap_$hash(\$callback) {
        if (function_exists('_il_check_$hash') && _il_check_$hash()) {
            return \$callback();
        }
        return null;
    }
}

PHPCODE
        );
    }
}
