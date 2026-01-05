<?php
/**
 * Script de diagnóstico para sistema de licencias
 *
 * INSTRUCCIONES:
 * 1. Sube este archivo a la raíz de tu WordPress
 * 2. Accede a: http://tu-sitio.com/diagnostico-licencias.php
 * 3. Copia el resultado completo y envíalo
 */

// Cargar WordPress
require_once('./wp-load.php');

if (!current_user_can('manage_options')) {
    die('Acceso denegado. Debes estar logueado como administrador.');
}

header('Content-Type: text/plain; charset=utf-8');

echo "==========================================\n";
echo "DIAGNÓSTICO DEL SISTEMA DE LICENCIAS\n";
echo "==========================================\n\n";

// 1. Verificar plugins activos
echo "1. PLUGINS ACTIVOS:\n";
echo "-------------------\n";

$active_plugins = get_option('active_plugins');
$license_extension_active = false;
$server_active = false;

foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'imagina-updater-server') !== false) {
        echo "✅ Plugin Servidor: $plugin\n";
        $server_active = true;
    }
    if (strpos($plugin, 'imagina-updater-license-extension') !== false) {
        echo "✅ Extensión de Licencias: $plugin\n";
        $license_extension_active = true;
    }
}

if (!$license_extension_active) {
    echo "❌ Extensión de Licencias NO está activa\n";
}
if (!$server_active) {
    echo "❌ Plugin Servidor NO está activo\n";
}

echo "\n";

// 2. Verificar estructura de base de datos
echo "2. ESTRUCTURA DE BASE DE DATOS:\n";
echo "--------------------------------\n";

global $wpdb;
$table = $wpdb->prefix . 'imagina_updater_plugins';

// Verificar si la tabla existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

if ($table_exists) {
    echo "✅ Tabla existe: $table\n";

    // Verificar campo is_premium
    $column = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'is_premium'");

    if (!empty($column)) {
        echo "✅ Campo 'is_premium' existe\n";
        echo "   Tipo: " . $column[0]->Type . "\n";
        echo "   Default: " . $column[0]->Default . "\n";
    } else {
        echo "❌ Campo 'is_premium' NO existe\n";
        echo "   SOLUCIÓN: La extensión debe crear este campo al activarse\n";
    }
} else {
    echo "❌ Tabla NO existe: $table\n";
}

echo "\n";

// 3. Verificar plugins en base de datos
echo "3. PLUGINS EN BASE DE DATOS:\n";
echo "----------------------------\n";

if ($table_exists) {
    $plugins = $wpdb->get_results("SELECT id, slug, name, current_version, is_premium FROM $table LIMIT 10");

    if ($plugins) {
        foreach ($plugins as $plugin) {
            $premium_status = isset($plugin->is_premium) ? ($plugin->is_premium == 1 ? '🔒 PREMIUM' : '🔓 Gratuito') : '❓ Campo no existe';
            echo "  - {$plugin->name} (v{$plugin->current_version}): $premium_status\n";
        }
    } else {
        echo "  No hay plugins subidos\n";
    }
} else {
    echo "  No se puede verificar (tabla no existe)\n";
}

echo "\n";

// 4. Verificar clases de licencias
echo "4. CLASES DE LICENCIAS CARGADAS:\n";
echo "---------------------------------\n";

$classes = array(
    'Imagina_License_Database' => 'Base de datos',
    'Imagina_License_SDK_Injector' => 'Inyector de SDK',
    'Imagina_License_Admin' => 'Administración',
    'Imagina_License_API' => 'API REST',
    'Imagina_License_Crypto' => 'Criptografía'
);

foreach ($classes as $class => $description) {
    if (class_exists($class)) {
        echo "✅ $class ($description)\n";
    } else {
        echo "❌ $class ($description) - NO CARGADA\n";
    }
}

echo "\n";

// 5. Verificar hooks registrados
echo "5. HOOKS REGISTRADOS:\n";
echo "---------------------\n";

global $wp_filter;

$hooks_to_check = array(
    'imagina_updater_after_upload_form',
    'imagina_updater_after_move_plugin_file',
    'imagina_updater_after_upload_plugin',
    'imagina_updater_plugins_table_header',
    'imagina_updater_plugins_table_row'
);

foreach ($hooks_to_check as $hook) {
    if (isset($wp_filter[$hook])) {
        echo "✅ Hook registrado: $hook\n";

        // Mostrar callbacks registrados
        foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function'])) {
                    $class_name = is_object($callback['function'][0]) ? get_class($callback['function'][0]) : $callback['function'][0];
                    echo "   └─ $class_name::{$callback['function'][1]} (prioridad: $priority)\n";
                } else {
                    echo "   └─ {$callback['function']} (prioridad: $priority)\n";
                }
            }
        }
    } else {
        echo "❌ Hook NO registrado: $hook\n";
    }
}

echo "\n";

// 6. Verificar archivos del SDK
echo "6. ARCHIVOS DEL SDK EN LA EXTENSIÓN:\n";
echo "-------------------------------------\n";

$plugin_dir = WP_PLUGIN_DIR . '/imagina-updater-license-extension/includes/license-sdk/';

if (is_dir($plugin_dir)) {
    echo "✅ Directorio existe: $plugin_dir\n";

    $required_files = array('loader.php', 'class-crypto.php', 'class-license-validator.php', 'class-heartbeat.php');

    foreach ($required_files as $file) {
        if (file_exists($plugin_dir . $file)) {
            echo "  ✅ $file\n";
        } else {
            echo "  ❌ $file - FALTA\n";
        }
    }
} else {
    echo "❌ Directorio NO existe: $plugin_dir\n";
}

echo "\n";

// 7. Verificar última subida de plugin
echo "7. ÚLTIMO PLUGIN SUBIDO:\n";
echo "------------------------\n";

if ($table_exists) {
    $last_plugin = $wpdb->get_row("SELECT * FROM $table ORDER BY uploaded_at DESC LIMIT 1");

    if ($last_plugin) {
        echo "Nombre: {$last_plugin->name}\n";
        echo "Slug: {$last_plugin->slug}\n";
        echo "Versión: {$last_plugin->current_version}\n";
        echo "Premium: " . (isset($last_plugin->is_premium) && $last_plugin->is_premium == 1 ? 'SÍ' : 'NO') . "\n";
        echo "Archivo: {$last_plugin->file_path}\n";

        // Verificar si el archivo existe
        if (file_exists($last_plugin->file_path)) {
            echo "✅ Archivo ZIP existe\n";

            // Extraer y verificar contenido
            $zip = new ZipArchive();
            if ($zip->open($last_plugin->file_path) === true) {
                echo "✅ ZIP válido\n";

                // Buscar SDK en el ZIP
                $has_sdk = false;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (strpos($filename, 'imagina-license-sdk/loader.php') !== false) {
                        $has_sdk = true;
                        echo "✅ SDK encontrado en ZIP: $filename\n";
                        break;
                    }
                }

                if (!$has_sdk) {
                    echo "❌ SDK NO encontrado en el ZIP\n";
                    echo "   Esto significa que la inyección NO funcionó\n";
                }

                $zip->close();
            } else {
                echo "❌ No se pudo abrir el ZIP\n";
            }
        } else {
            echo "❌ Archivo ZIP NO existe\n";
        }
    } else {
        echo "No hay plugins subidos\n";
    }
}

echo "\n";

// 8. Verificar logs
echo "8. ÚLTIMAS ENTRADAS DE LOG:\n";
echo "----------------------------\n";

$log_file = WP_CONTENT_DIR . '/imagina-updater-server.log';

if (file_exists($log_file)) {
    $log_lines = file($log_file);
    $relevant_logs = array();

    foreach ($log_lines as $line) {
        if (stripos($line, 'license') !== false || stripos($line, 'sdk') !== false || stripos($line, 'premium') !== false) {
            $relevant_logs[] = $line;
        }
    }

    if (!empty($relevant_logs)) {
        $last_logs = array_slice($relevant_logs, -10);
        foreach ($last_logs as $log) {
            echo $log;
        }
    } else {
        echo "No hay logs relacionados con licencias/SDK\n";
    }
} else {
    echo "Archivo de log no existe: $log_file\n";
}

echo "\n";
echo "==========================================\n";
echo "FIN DEL DIAGNÓSTICO\n";
echo "==========================================\n";
