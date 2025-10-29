<?php
/**
 * Gestión de base de datos
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_Database {

    /**
     * Crear tablas necesarias para el plugin
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Tabla de API Keys
        $table_api_keys = $wpdb->prefix . 'imagina_updater_api_keys';
        $sql_api_keys = "CREATE TABLE IF NOT EXISTS $table_api_keys (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_key varchar(64) NOT NULL,
            site_name varchar(255) NOT NULL,
            site_url varchar(255) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Tabla de plugins gestionados
        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';
        $sql_plugins = "CREATE TABLE IF NOT EXISTS $table_plugins (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(255) NOT NULL,
            slug_override varchar(255) DEFAULT NULL COMMENT 'Slug personalizado (si se establece, sobrescribe el auto-generado)',
            name varchar(255) NOT NULL,
            description text,
            author varchar(255),
            homepage varchar(255),
            current_version varchar(50) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) NOT NULL,
            checksum varchar(64) NOT NULL,
            uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY slug (slug),
            KEY slug_override (slug_override),
            KEY slug_version (slug, current_version)
        ) $charset_collate;";

        // Tabla de historial de versiones
        $table_versions = $wpdb->prefix . 'imagina_updater_versions';
        $sql_versions = "CREATE TABLE IF NOT EXISTS $table_versions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            plugin_id bigint(20) unsigned NOT NULL,
            version varchar(50) NOT NULL,
            file_path varchar(500) NOT NULL,
            file_size bigint(20) NOT NULL,
            checksum varchar(64) NOT NULL,
            changelog text,
            uploaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY plugin_id (plugin_id),
            KEY plugin_version (plugin_id, version)
        ) $charset_collate;";

        // Tabla de log de descargas
        $table_downloads = $wpdb->prefix . 'imagina_updater_downloads';
        $sql_downloads = "CREATE TABLE IF NOT EXISTS $table_downloads (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_key_id bigint(20) unsigned NOT NULL,
            plugin_id bigint(20) unsigned NOT NULL,
            version varchar(50) NOT NULL,
            ip_address varchar(45),
            user_agent text,
            downloaded_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY api_key_id (api_key_id),
            KEY plugin_id (plugin_id),
            KEY downloaded_at (downloaded_at)
        ) $charset_collate;";

        // Tabla de grupos de plugins
        $table_groups = $wpdb->prefix . 'imagina_updater_plugin_groups';
        $sql_groups = "CREATE TABLE IF NOT EXISTS $table_groups (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY name (name)
        ) $charset_collate;";

        // Tabla de relación plugins-grupos (muchos a muchos)
        $table_group_items = $wpdb->prefix . 'imagina_updater_plugin_group_items';
        $sql_group_items = "CREATE TABLE IF NOT EXISTS $table_group_items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id bigint(20) unsigned NOT NULL,
            plugin_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY group_plugin (group_id, plugin_id),
            KEY group_id (group_id),
            KEY plugin_id (plugin_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_api_keys);
        dbDelta($sql_plugins);
        dbDelta($sql_versions);
        dbDelta($sql_downloads);
        dbDelta($sql_groups);
        dbDelta($sql_group_items);

        // Ejecutar migraciones si es necesario
        self::run_migrations();

        // Guardar versión de la base de datos
        update_option('imagina_updater_server_db_version', IMAGINA_UPDATER_SERVER_VERSION);
    }

    /**
     * Ejecutar migraciones de base de datos
     */
    public static function run_migrations() {
        // Siempre intentar ejecutar migraciones (verifican internamente si son necesarias)
        self::migrate_add_slug_override();
        self::migrate_add_api_key_permissions();
    }

    /**
     * Migración: Agregar campo slug_override
     */
    private static function migrate_add_slug_override() {
        global $wpdb;

        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';

        // Verificar si el campo ya existe
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM $table_plugins LIKE %s",
                'slug_override'
            )
        );

        if (empty($column_exists)) {
            error_log('IMAGINA UPDATER SERVER: Iniciando migración para agregar slug_override');

            // Agregar columna slug_override después de slug
            $result = $wpdb->query(
                "ALTER TABLE $table_plugins
                ADD COLUMN slug_override varchar(255) DEFAULT NULL COMMENT 'Slug personalizado'
                AFTER slug"
            );

            if ($result === false) {
                error_log('IMAGINA UPDATER SERVER: ERROR en migración al agregar columna - ' . $wpdb->last_error);
                return false;
            }

            // Verificar si el índice ya existe
            $indexes = $wpdb->get_results("SHOW INDEX FROM $table_plugins WHERE Key_name = 'slug_override'");

            if (empty($indexes)) {
                // Agregar índice solo si no existe
                $index_result = $wpdb->query(
                    "ALTER TABLE $table_plugins
                    ADD KEY slug_override (slug_override)"
                );

                if ($index_result === false) {
                    error_log('IMAGINA UPDATER SERVER: ERROR al crear índice - ' . $wpdb->last_error);
                    // No retornar false porque la columna ya fue creada exitosamente
                }
            }

            error_log('IMAGINA UPDATER SERVER: Migración completada exitosamente - Campo slug_override agregado');
            return true;
        } else {
            error_log('IMAGINA UPDATER SERVER: Campo slug_override ya existe, migración no necesaria');
            return true;
        }
    }

    /**
     * Migración: Agregar campos de permisos a API keys
     */
    private static function migrate_add_api_key_permissions() {
        global $wpdb;

        $table_api_keys = $wpdb->prefix . 'imagina_updater_api_keys';

        // Verificar si el campo access_type ya existe
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM $table_api_keys LIKE %s",
                'access_type'
            )
        );

        if (empty($column_exists)) {
            error_log('IMAGINA UPDATER SERVER: Iniciando migración para agregar campos de permisos a API keys');

            // Agregar columnas de permisos
            $result = $wpdb->query(
                "ALTER TABLE $table_api_keys
                ADD COLUMN access_type ENUM('all', 'specific', 'groups') NOT NULL DEFAULT 'all' COMMENT 'Tipo de acceso' AFTER is_active,
                ADD COLUMN allowed_plugins TEXT NULL COMMENT 'IDs de plugins permitidos (JSON array)' AFTER access_type,
                ADD COLUMN allowed_groups TEXT NULL COMMENT 'IDs de grupos permitidos (JSON array)' AFTER allowed_plugins"
            );

            if ($result === false) {
                error_log('IMAGINA UPDATER SERVER: ERROR en migración al agregar columnas de permisos - ' . $wpdb->last_error);
                return false;
            }

            error_log('IMAGINA UPDATER SERVER: Migración completada exitosamente - Campos de permisos agregados a API keys');
            return true;
        } else {
            error_log('IMAGINA UPDATER SERVER: Campos de permisos ya existen en API keys, migración no necesaria');
            return true;
        }
    }

    /**
     * Obtener la versión de la base de datos
     */
    public static function get_db_version() {
        return get_option('imagina_updater_server_db_version', '0.0.0');
    }
}
