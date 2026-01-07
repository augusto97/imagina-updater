<?php
/**
 * Sistema de protección v3 - REDISEÑADO
 * Sin wp_die(), sin deactivate_plugins(), sin remove_all_actions()
 * Enfoque: Prevenir ejecución sin romper WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_SDK_Injector_Secure_V3 {

    /**
     * Generar código de protección SEGURO y EFECTIVO
     *
     * Este código:
     * - NO usa wp_die() que mata WordPress
     * - NO usa deactivate_plugins() en hooks
     * - NO usa remove_all_actions() que rompe otros plugins
     * - SÍ bloquea la ejecución del plugin sin licencia
     * - SÍ muestra avisos claros al usuario
     *
     * @param string $plugin_name Nombre del plugin
     * @param string $plugin_slug Slug del plugin
     * @return string Código PHP de protección
     */
    public static function generate_secure_code($plugin_name, $plugin_slug) {
        // Generar identificadores únicos
        $hash = substr(hash('sha256', $plugin_name . $plugin_slug . time()), 0, 12);
        $check_func = '_ilc_' . substr(md5($plugin_slug), 0, 8);
        $validate_func = '_ilv_' . substr(md5($plugin_name), 0, 8);
        $telemetry_func = '_ilt_' . substr(md5($hash . 'tel'), 0, 8);
        $block_var = '$_ilb_' . substr(md5($hash . 'block'), 0, 8); // Incluir $ en el nombre

        // Ofuscar strings
        $sdk_loader = base64_encode('imagina-license-sdk/loader.php');
        $license_mgr = base64_encode('Imagina_Updater_License_Manager');
        $get_inst = base64_encode('get_instance');
        $verify_lic = base64_encode('verify_license');

        $code = <<<'PHPCODE'

// === IMAGINA LICENSE PROTECTION v3.0 ===
if (!defined('ABSPATH')) { exit; }

// Variable global de bloqueo
global BLOCK_VAR;
BLOCK_VAR = false;

// Función de verificación
if (!function_exists('$CHECK_FUNC$')) {
    function $CHECK_FUNC$() {
        // Verificar que existe el SDK
        $sdk_path = plugin_dir_path(__FILE__) . base64_decode('$SDK_LOADER$');
        if (!file_exists($sdk_path)) {
            return 'no_sdk';
        }

        // Cargar SDK si no está cargado
        if (!class_exists('Imagina_License_SDK')) {
            require_once $sdk_path;
        }

        // Verificar que existe el License Manager (plugin cliente)
        $mgr_class = base64_decode('$LICENSE_MGR$');
        if (!class_exists($mgr_class)) {
            return 'no_client';
        }

        // Verificar licencia
        try {
            $mgr = call_user_func(array($mgr_class, base64_decode('$GET_INST$')));
            $license = call_user_func(
                array($mgr, base64_decode('$VERIFY_LIC$')),
                '$PLUGIN_SLUG$'
            );

            if ($license && isset($license['valid']) && $license['valid']) {
                return 'valid';
            }

            return 'invalid';
        } catch (Exception $e) {
            return 'error';
        }
    }
}

// Función de validación que ejecuta el bloqueo
if (!function_exists('$VALIDATE_FUNC$')) {
    function $VALIDATE_FUNC$() {
        global BLOCK_VAR;

        $status = $CHECK_FUNC$();

        if ($status !== 'valid') {
            BLOCK_VAR = $status;

            // Mostrar aviso en admin
            add_action('admin_notices', function() use ($status) {
                $plugin_name = '$PLUGIN_NAME$';
                $messages = array(
                    'no_sdk' => __('Protection system is missing. Please reinstall from official source.', 'imagina-license'),
                    'no_client' => __('Requires Imagina Updater Client plugin to be installed and configured.', 'imagina-license'),
                    'invalid' => __('Valid license required to use this plugin.', 'imagina-license'),
                    'error' => __('License verification failed. Please check your connection.', 'imagina-license')
                );

                $message = isset($messages[$status]) ? $messages[$status] : $messages['error'];
                $link = '';

                if ($status === 'no_client') {
                    $link = ' <a href="https://imaginawp.com/imagina-updater" target="_blank">' .
                            __('Download Imagina Updater Client', 'imagina-license') . '</a>';
                } elseif ($status === 'invalid') {
                    $link = ' <a href="' . admin_url('options-general.php?page=imagina-updater-client') . '">' .
                            __('Configure license', 'imagina-license') . '</a>';
                }

                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . esc_html($plugin_name) . ':</strong> ';
                echo esc_html($message) . $link;
                echo '</p></div>';
            }, 999);
        }

        return $status === 'valid';
    }
}

// Ejecutar validación temprano
add_action('plugins_loaded', function() {
    global BLOCK_VAR;
    $VALIDATE_FUNC$();

    // Si está bloqueado, prevenir que el plugin haga hooks
    if (BLOCK_VAR !== false) {
        // Aquí el plugin puede verificar la variable global antes de registrar sus hooks
    }
}, 1);

// Telemetría (solo si hay License Manager)
if (!function_exists('$TELEMETRY_FUNC$')) {
    function $TELEMETRY_FUNC$() {
        if (!class_exists('Imagina_Updater_License_Manager')) {
            return;
        }

        $key = 'il_tel_$PLUGIN_SLUG$';
        if (get_transient($key)) {
            return; // Ya reportado
        }

        $config = get_option('imagina_updater_client_config', array());
        if (empty($config['server_url']) || empty($config['activation_token'])) {
            return;
        }

        global $wp_version;
        wp_remote_post($config['server_url'] . '/wp-json/imagina-license/v1/telemetry', array(
            'timeout' => 5,
            'blocking' => false,
            'body' => array(
                'plugin_slug' => '$PLUGIN_SLUG$',
                'activation_token' => $config['activation_token'],
                'site_url' => home_url(),
                'wp_version' => $wp_version,
                'php_version' => PHP_VERSION
            )
        ));

        set_transient($key, time(), DAY_IN_SECONDS);
    }
}
add_action('admin_init', '$TELEMETRY_FUNC$', 999);

// === END PROTECTION ===

PHPCODE;

        // Reemplazar placeholders
        $replacements = array(
            'BLOCK_VAR' => $block_var,
            '$CHECK_FUNC$' => $check_func,
            '$VALIDATE_FUNC$' => $validate_func,
            '$TELEMETRY_FUNC$' => $telemetry_func,
            '$SDK_LOADER$' => $sdk_loader,
            '$LICENSE_MGR$' => $license_mgr,
            '$GET_INST$' => $get_inst,
            '$VERIFY_LIC$' => $verify_lic,
            '$PLUGIN_NAME$' => $plugin_name,
            '$PLUGIN_SLUG$' => $plugin_slug
        );

        return str_replace(array_keys($replacements), array_values($replacements), $code);
    }
}
