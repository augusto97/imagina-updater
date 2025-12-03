<?php
/**
 * Gestión de base de datos para la extensión de licencias
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_Database {

    /**
     * Crear/actualizar tablas necesarias
     */
    public static function create_tables() {
        global $wpdb;

        // Agregar campo is_premium a la tabla de plugins si no existe
        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';

        // Verificar si la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_plugins'") === $table_plugins;

        if (!$table_exists) {
            imagina_license_log('Tabla de plugins no existe. Asegúrate de que imagina-updater-server esté activo.', 'error');
            return;
        }

        // Verificar si el campo is_premium ya existe
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_plugins LIKE 'is_premium'");

        if (empty($column_exists)) {
            // Agregar campo is_premium
            $result = $wpdb->query("
                ALTER TABLE $table_plugins
                ADD COLUMN is_premium tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si es 1, se inyectará automáticamente el SDK de licencias' AFTER current_version
            ");

            if ($result === false) {
                imagina_license_log('Error al agregar campo is_premium: ' . $wpdb->last_error, 'error');
            } else {
                imagina_license_log('Campo is_premium agregado exitosamente a la tabla de plugins', 'info');
            }
        } else {
            imagina_license_log('Campo is_premium ya existe en la tabla de plugins', 'info');
        }
    }

    /**
     * Eliminar modificaciones (desinstalación limpia)
     */
    public static function drop_modifications() {
        global $wpdb;

        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';

        // Verificar si la columna existe antes de intentar eliminarla
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_plugins LIKE 'is_premium'");

        if (!empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_plugins DROP COLUMN is_premium");
            imagina_license_log('Campo is_premium eliminado de la tabla de plugins', 'info');
        }
    }
}
