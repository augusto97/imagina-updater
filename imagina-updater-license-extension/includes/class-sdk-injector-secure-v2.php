<?php
/**
 * Sistema de protección multicapa AVANZADO para plugins premium
 * Versión 2.0 - Con kill switch remoto y ofuscación mejorada
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_SDK_Injector_Secure_V2 {

    /**
     * Generar código de protección multicapa avanzado (10 capas)
     *
     * Capas de seguridad:
     * 1. SDK Presence Check
     * 2. Obfuscated Variables
     * 3. Distributed Check Functions
     * 4. Kill Switch Check (Server-side blacklist)
     * 5. Early Initialization
     * 6. Multi-point Validation
     * 7. Integrity Monitoring
     * 8. Telemetry & Server Sync
     * 9. Heartbeat with Server Sync
     * 10. Prevent Direct File Access to SDK
     *
     * @param string $plugin_name Nombre del plugin
     * @param string $plugin_slug Slug del plugin
     * @return string Código PHP ofuscado y ultra-seguro
     */
    public static function generate_ultra_secure_code($plugin_name, $plugin_slug) {
        // Generar identificadores únicos por plugin
        $hash = substr(hash('sha256', $plugin_name . $plugin_slug . time()), 0, 12);
        $check_func = '_ilc_' . substr(md5($plugin_slug), 0, 8);
        $init_func = '_ili_' . substr(md5($plugin_name), 0, 8);
        $kill_func = '_ilk_' . substr(md5($hash), 0, 8);
        $telemetry_func = '_ilt_' . substr(md5($hash . 'telemetry'), 0, 8);

        // Ofuscar strings críticos
        $sdk_class = base64_encode('Imagina_License_SDK');
        $validator_method = base64_encode('get_validator');
        $is_valid_method = base64_encode('is_valid');
        $init_method = base64_encode('init');

        // Generar token único de integridad
        $random_salt = bin2hex(random_bytes(16));
        $integrity_token = hash('sha256', $plugin_slug . $random_salt . time());

        $code = <<<PHPCODE

// Protection System v2.0 - Hash: $hash
if (!defined('ABSPATH')) { exit; }

// [Layer 1] SDK Presence Check
\$_sdk_path = plugin_dir_path(__FILE__) . 'imagina-license-sdk/loader.php';
if (!file_exists(\$_sdk_path)) {
    add_action('admin_init', function() {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('Protection system missing. Please reinstall from official source.', 'imagina-license'));
    }, 1);
    return;
}
require_once \$_sdk_path;

// [Layer 2] Obfuscated Variables
\$_ob1 = '$plugin_slug';
\$_ob2 = '$plugin_name';
\$_ob3 = base64_decode('$sdk_class');
\$_ob4 = base64_decode('$validator_method');
\$_ob5 = base64_decode('$is_valid_method');
\$_ob6 = base64_decode('$init_method');
\$_ob7 = '$integrity_token';

// [Layer 3] Distributed Check Functions
if (!function_exists('$check_func')) {
    function $check_func() {
        global \$_ob1, \$_ob3, \$_ob4, \$_ob5;
        if (!class_exists(\$_ob3)) return false;
        \$v = call_user_func(array(\$_ob3, \$_ob4), \$_ob1);
        return \$v ? call_user_func(array(\$v, \$_ob5)) : false;
    }
}

if (!function_exists('$init_func')) {
    function $init_func() {
        global \$_ob1, \$_ob2, \$_ob3, \$_ob6;
        if (!class_exists(\$_ob3)) return;
        call_user_func(array(\$_ob3, \$_ob6), array(
            'plugin_slug' => \$_ob1,
            'plugin_name' => \$_ob2,
            'plugin_file' => __FILE__
        ));
    }
}

// [Layer 4] Kill Switch Check (Server-side blacklist)
if (!function_exists('$kill_func')) {
    function $kill_func() {
        global \$_ob1;

        // Verificar blacklist remota cada 6 horas
        \$cache_key = 'il_killswitch_' . \$_ob1;
        \$cached = get_transient(\$cache_key);

        if (\$cached !== false) {
            return \$cached === 'active';
        }

        // Verificar con el servidor si esta instalación está bloqueada
        if (class_exists('Imagina_Updater_License_Manager')) {
            \$server_url = get_option('imagina_updater_server_url');
            \$activation_token = get_option('imagina_updater_activation_token');

            if (\$server_url && \$activation_token) {
                \$response = wp_remote_post(\$server_url . '/wp-json/imagina-license/v1/killswitch', array(
                    'timeout' => 5,
                    'body' => array(
                        'plugin_slug' => \$_ob1,
                        'activation_token' => \$activation_token,
                        'site_url' => home_url()
                    )
                ));

                if (!is_wp_error(\$response)) {
                    \$body = json_decode(wp_remote_retrieve_body(\$response), true);
                    \$is_blocked = isset(\$body['blocked']) && \$body['blocked'];

                    // Cachear resultado por 6 horas
                    set_transient(\$cache_key, \$is_blocked ? 'blocked' : 'active', 6 * HOUR_IN_SECONDS);

                    return !\$is_blocked;
                }
            }
        }

        // Si no se puede verificar, permitir por ahora pero re-intentar pronto
        set_transient(\$cache_key, 'active', 30 * MINUTE_IN_SECONDS);
        return true;
    }
}

// [Layer 5] Early Initialization
add_action('plugins_loaded', function() {
    $init_func();
}, 1);

// [Layer 6] Multi-point Validation
\$_validation_hooks = array('admin_init', 'wp_loaded', 'init', 'wp');
foreach (\$_validation_hooks as \$_vh) {
    add_action(\$_vh, function() use (\$_vh) {
        global \$_ob2;

        // Verificar licencia
        if (!$check_func()) {
            if (\$_vh === 'admin_init' || \$_vh === 'wp_loaded') {
                add_action('admin_notices', function() use (\$_ob2) {
                    echo '<div class="notice notice-error"><p><strong>' . esc_html(\$_ob2) . '</strong>: ' .
                         __('License required.', 'imagina-license') . ' ' .
                         '<a href="' . admin_url('options-general.php?page=imagina-updater') . '">' .
                         __('Activate license', 'imagina-license') . '</a></p></div>';
                });

                add_action('admin_head', function() {
                    deactivate_plugins(plugin_basename(__FILE__));
                }, 1);
            }

            // Prevenir ejecución
            remove_all_actions('init');
            remove_all_actions('wp_loaded');
            remove_all_actions('wp');
            remove_all_filters('the_content');
            remove_all_filters('the_excerpt');

            return;
        }

        // Verificar kill switch
        if (\$_vh !== 'wp' && !$kill_func()) {
            add_action('admin_notices', function() use (\$_ob2) {
                echo '<div class="notice notice-error"><p><strong>' . esc_html(\$_ob2) . '</strong>: ' .
                     __('This installation has been blocked. Contact support.', 'imagina-license') . '</p></div>';
            });

            deactivate_plugins(plugin_basename(__FILE__));

            // Bloqueo inmediato
            remove_all_actions('init');
            remove_all_actions('wp_loaded');
            remove_all_filters('the_content');

            return;
        }
    }, 1);
}

// [Layer 7] Integrity Monitoring
add_action('admin_init', function() use (\$_sdk_path, \$_ob7) {
    if (file_exists(\$_sdk_path)) {
        // Verificar que el loader no fue modificado sustancialmente
        \$content = file_get_contents(\$_sdk_path);

        // Verificaciones simples de integridad
        \$checks = array(
            strpos(\$content, 'class Imagina_License_SDK') !== false,
            strpos(\$content, 'function init') !== false,
            strpos(\$content, 'Imagina_License_Validator') !== false
        );

        if (in_array(false, \$checks, true)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Protection System</strong>: ' .
                     __('Integrity check failed. Please reinstall from official source.', 'imagina-license') . '</p></div>';
            });

            deactivate_plugins(plugin_basename(__FILE__));
        }
    }
}, 2);

// [Layer 8] Telemetry & Server Sync
if (!function_exists('$telemetry_func')) {
    function $telemetry_func() {
        global \$_ob1;

        // Enviar telemetría al servidor cada 24 horas
        \$telemetry_key = 'il_telemetry_' . \$_ob1;
        \$last_report = get_transient(\$telemetry_key);

        if (\$last_report !== false) {
            return; // Ya se reportó recientemente
        }

        if (class_exists('Imagina_Updater_License_Manager')) {
            \$server_url = get_option('imagina_updater_server_url');
            \$activation_token = get_option('imagina_updater_activation_token');

            if (\$server_url && \$activation_token) {
                global \$wp_version;

                \$telemetry_data = array(
                    'plugin_slug' => \$_ob1,
                    'activation_token' => \$activation_token,
                    'site_url' => home_url(),
                    'site_name' => get_bloginfo('name'),
                    'wp_version' => \$wp_version,
                    'php_version' => PHP_VERSION,
                    'is_multisite' => is_multisite(),
                    'locale' => get_locale(),
                    'timestamp' => current_time('mysql')
                );

                wp_remote_post(\$server_url . '/wp-json/imagina-license/v1/telemetry', array(
                    'timeout' => 5,
                    'blocking' => false, // No bloquear ejecución
                    'body' => \$telemetry_data
                ));

                // Marcar como reportado por 24 horas
                set_transient(\$telemetry_key, time(), DAY_IN_SECONDS);
            }
        }
    }
}

// Reportar telemetría en admin_init (primera carga) y luego periódicamente
add_action('admin_init', '$telemetry_func', 999);

// [Layer 9] Heartbeat with Server Sync
\$_hb_event = '_il_hb_$hash';
if (!wp_next_scheduled(\$_hb_event)) {
    wp_schedule_event(time(), 'twicedaily', \$_hb_event);
}

add_action(\$_hb_event, function() {
    global \$_ob1;

    // Re-validar y limpiar cache
    if (!$check_func()) {
        delete_transient('imagina_license_cache_' . \$_ob1);
        delete_transient('il_killswitch_' . \$_ob1);
    }

    // Forzar re-verificación de kill switch
    delete_transient('il_killswitch_' . \$_ob1);
    $kill_func();

    // Enviar telemetría
    if (function_exists('$telemetry_func')) {
        $telemetry_func();
    }
});

// [Layer 10] Prevent Direct File Access to SDK
add_action('template_redirect', function() {
    \$request_uri = \$_SERVER['REQUEST_URI'] ?? '';
    if (strpos(\$request_uri, 'imagina-license-sdk') !== false) {
        if (!is_admin() && !wp_doing_ajax()) {
            wp_die(__('Direct access to protection files is not allowed.', 'imagina-license'), 403);
        }
    }
}, 1);

// End Protection System - 10 Layers Total

PHPCODE;

        return $code;
    }
}
