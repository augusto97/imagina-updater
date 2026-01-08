<?php
/**
 * Gestión de base de datos para el sistema de licencias
 *
 * @package Imagina_License_Extension
 * @version 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_Database {

    /**
     * Versión de la base de datos
     */
    const DB_VERSION = '5.0.0';

    /**
     * Crear/actualizar todas las tablas necesarias
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Agregar campo is_premium a la tabla de plugins existente
        self::add_premium_field();

        // 2. Crear tabla de License Keys
        self::create_license_keys_table($charset_collate);

        // 3. Crear tabla de License Activations
        self::create_license_activations_table($charset_collate);

        // Guardar versión
        update_option('imagina_license_db_version', self::DB_VERSION);

        imagina_license_log('Tablas de licencias creadas/actualizadas correctamente', 'info');
    }

    /**
     * Agregar campo is_premium a la tabla de plugins
     */
    private static function add_premium_field() {
        global $wpdb;

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
            $wpdb->query("
                ALTER TABLE $table_plugins
                ADD COLUMN is_premium tinyint(1) NOT NULL DEFAULT 0 AFTER current_version
            ");
            imagina_license_log('Campo is_premium agregado a la tabla de plugins', 'info');
        }
    }

    /**
     * Crear tabla de License Keys
     */
    private static function create_license_keys_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'imagina_license_keys';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_key varchar(64) NOT NULL,
            plugin_id bigint(20) unsigned NOT NULL,
            customer_email varchar(200) NOT NULL,
            customer_name varchar(200) DEFAULT '',
            status enum('active','inactive','expired','revoked') NOT NULL DEFAULT 'active',
            max_activations int(11) NOT NULL DEFAULT 1,
            activations_count int(11) NOT NULL DEFAULT 0,
            expires_at datetime DEFAULT NULL,
            order_id varchar(100) DEFAULT '',
            notes text DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY license_key (license_key),
            KEY plugin_id (plugin_id),
            KEY customer_email (customer_email),
            KEY status (status)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Crear tabla de License Activations
     */
    private static function create_license_activations_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'imagina_license_activations';

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            license_id bigint(20) unsigned NOT NULL,
            site_url varchar(255) NOT NULL,
            site_name varchar(255) DEFAULT '',
            site_local_key varchar(64) DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            activated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_check datetime DEFAULT NULL,
            deactivated_at datetime DEFAULT NULL,
            ip_address varchar(45) DEFAULT '',
            PRIMARY KEY (id),
            UNIQUE KEY license_site (license_id, site_url),
            KEY license_id (license_id),
            KEY site_url (site_url),
            KEY is_active (is_active)
        ) $charset_collate;";

        dbDelta($sql);
    }

    /**
     * Generar una license key única
     *
     * @param string $prefix Prefijo para la key
     * @return string
     */
    public static function generate_license_key($prefix = 'ILK') {
        $key = $prefix . '-';
        $key .= strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)) . '-';
        $key .= strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)) . '-';
        $key .= strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)) . '-';
        $key .= strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

        return $key;
    }

    /**
     * Crear una nueva license key
     *
     * @param array $data Datos de la licencia
     * @return int|false ID de la licencia o false si falla
     */
    public static function create_license($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_license_keys';

        $license_key = isset($data['license_key']) ? $data['license_key'] : self::generate_license_key();

        $result = $wpdb->insert(
            $table,
            array(
                'license_key'       => $license_key,
                'plugin_id'         => intval($data['plugin_id']),
                'customer_email'    => sanitize_email($data['customer_email']),
                'customer_name'     => sanitize_text_field($data['customer_name'] ?? ''),
                'status'            => 'active',
                'max_activations'   => intval($data['max_activations'] ?? 1),
                'expires_at'        => isset($data['expires_at']) ? $data['expires_at'] : null,
                'order_id'          => sanitize_text_field($data['order_id'] ?? ''),
                'notes'             => sanitize_textarea_field($data['notes'] ?? ''),
            ),
            array('%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        if ($result === false) {
            imagina_license_log('Error al crear licencia: ' . $wpdb->last_error, 'error');
            return false;
        }

        imagina_license_log('Licencia creada: ' . $license_key, 'info');
        return $wpdb->insert_id;
    }

    /**
     * Obtener licencia por key
     *
     * @param string $license_key
     * @return object|null
     */
    public static function get_license_by_key($license_key) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_license_keys';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE license_key = %s",
            $license_key
        ));
    }

    /**
     * Obtener licencia por ID
     *
     * @param int $id
     * @return object|null
     */
    public static function get_license_by_id($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_license_keys';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }

    /**
     * Obtener todas las licencias de un plugin
     *
     * @param int $plugin_id
     * @return array
     */
    public static function get_licenses_by_plugin($plugin_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_license_keys';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE plugin_id = %d ORDER BY created_at DESC",
            $plugin_id
        ));
    }

    /**
     * Obtener activaciones de una licencia
     *
     * @param int $license_id
     * @return array
     */
    public static function get_license_activations($license_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_license_activations';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE license_id = %d ORDER BY activated_at DESC",
            $license_id
        ));
    }

    /**
     * Activar licencia en un sitio
     *
     * @param int    $license_id
     * @param string $site_url
     * @param string $site_name
     * @return array
     */
    public static function activate_license($license_id, $site_url, $site_name = '') {
        global $wpdb;

        $license = self::get_license_by_id($license_id);

        if (!$license) {
            return array('success' => false, 'error' => 'license_not_found');
        }

        if ($license->status !== 'active') {
            return array('success' => false, 'error' => 'license_not_active');
        }

        // Verificar expiración
        if ($license->expires_at && strtotime($license->expires_at) < time()) {
            $wpdb->update(
                $wpdb->prefix . 'imagina_license_keys',
                array('status' => 'expired'),
                array('id' => $license_id),
                array('%s'),
                array('%d')
            );
            return array('success' => false, 'error' => 'license_expired');
        }

        // Normalizar URL
        $site_url = trailingslashit(strtolower(preg_replace('#^https?://#', '', $site_url)));

        // Verificar si ya está activada en este sitio
        $table_activations = $wpdb->prefix . 'imagina_license_activations';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_activations WHERE license_id = %d AND site_url = %s",
            $license_id,
            $site_url
        ));

        if ($existing) {
            if ($existing->is_active) {
                // Ya está activa, actualizar last_check
                $wpdb->update(
                    $table_activations,
                    array('last_check' => current_time('mysql')),
                    array('id' => $existing->id),
                    array('%s'),
                    array('%d')
                );
                return array(
                    'success' => true,
                    'message' => 'already_active',
                    'activation_id' => $existing->id
                );
            } else {
                // Reactivar
                $wpdb->update(
                    $table_activations,
                    array(
                        'is_active' => 1,
                        'last_check' => current_time('mysql'),
                        'deactivated_at' => null
                    ),
                    array('id' => $existing->id),
                    array('%d', '%s', '%s'),
                    array('%d')
                );

                // Incrementar contador
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}imagina_license_keys SET activations_count = activations_count + 1 WHERE id = %d",
                    $license_id
                ));

                return array(
                    'success' => true,
                    'message' => 'reactivated',
                    'activation_id' => $existing->id
                );
            }
        }

        // Verificar límite de activaciones
        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_activations WHERE license_id = %d AND is_active = 1",
            $license_id
        ));

        if ($active_count >= $license->max_activations) {
            return array('success' => false, 'error' => 'max_activations_reached');
        }

        // Generar key local para el sitio
        $site_local_key = wp_generate_password(32, false);

        // Crear activación
        $result = $wpdb->insert(
            $table_activations,
            array(
                'license_id'     => $license_id,
                'site_url'       => $site_url,
                'site_name'      => sanitize_text_field($site_name),
                'site_local_key' => $site_local_key,
                'is_active'      => 1,
                'last_check'     => current_time('mysql'),
                'ip_address'     => self::get_client_ip(),
            ),
            array('%d', '%s', '%s', '%s', '%d', '%s', '%s')
        );

        if ($result === false) {
            return array('success' => false, 'error' => 'database_error');
        }

        // Incrementar contador
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}imagina_license_keys SET activations_count = activations_count + 1 WHERE id = %d",
            $license_id
        ));

        return array(
            'success' => true,
            'message' => 'activated',
            'activation_id' => $wpdb->insert_id,
            'site_local_key' => $site_local_key
        );
    }

    /**
     * Desactivar licencia de un sitio
     *
     * @param int    $license_id
     * @param string $site_url
     * @return array
     */
    public static function deactivate_license($license_id, $site_url) {
        global $wpdb;

        $site_url = trailingslashit(strtolower(preg_replace('#^https?://#', '', $site_url)));

        $table_activations = $wpdb->prefix . 'imagina_license_activations';

        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_activations WHERE license_id = %d AND site_url = %s AND is_active = 1",
            $license_id,
            $site_url
        ));

        if (!$activation) {
            return array('success' => false, 'error' => 'activation_not_found');
        }

        $wpdb->update(
            $table_activations,
            array(
                'is_active' => 0,
                'deactivated_at' => current_time('mysql')
            ),
            array('id' => $activation->id),
            array('%d', '%s'),
            array('%d')
        );

        // Decrementar contador
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}imagina_license_keys SET activations_count = GREATEST(activations_count - 1, 0) WHERE id = %d",
            $license_id
        ));

        return array('success' => true, 'message' => 'deactivated');
    }

    /**
     * Verificar licencia
     *
     * @param string $license_key
     * @param string $site_url
     * @param int    $plugin_id
     * @return array
     */
    public static function verify_license($license_key, $site_url, $plugin_id = null) {
        global $wpdb;

        $license = self::get_license_by_key($license_key);

        if (!$license) {
            return array('valid' => false, 'error' => 'invalid_license_key');
        }

        // Verificar que la licencia es para el plugin correcto
        if ($plugin_id && $license->plugin_id != $plugin_id) {
            return array('valid' => false, 'error' => 'wrong_plugin');
        }

        if ($license->status !== 'active') {
            return array('valid' => false, 'error' => 'license_' . $license->status);
        }

        // Verificar expiración
        if ($license->expires_at && strtotime($license->expires_at) < time()) {
            $wpdb->update(
                $wpdb->prefix . 'imagina_license_keys',
                array('status' => 'expired'),
                array('id' => $license->id),
                array('%s'),
                array('%d')
            );
            return array('valid' => false, 'error' => 'license_expired');
        }

        // Normalizar URL
        $site_url = trailingslashit(strtolower(preg_replace('#^https?://#', '', $site_url)));

        // Verificar activación
        $table_activations = $wpdb->prefix . 'imagina_license_activations';
        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_activations WHERE license_id = %d AND site_url = %s AND is_active = 1",
            $license->id,
            $site_url
        ));

        if (!$activation) {
            return array('valid' => false, 'error' => 'not_activated_on_site');
        }

        // Actualizar last_check
        $wpdb->update(
            $table_activations,
            array('last_check' => current_time('mysql')),
            array('id' => $activation->id),
            array('%s'),
            array('%d')
        );

        return array(
            'valid' => true,
            'license_id' => $license->id,
            'expires_at' => $license->expires_at,
            'customer_email' => $license->customer_email
        );
    }

    /**
     * Obtener IP del cliente
     *
     * @return string
     */
    private static function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '';
    }

    /**
     * Eliminar todas las tablas (desinstalación)
     */
    public static function drop_tables() {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}imagina_license_activations");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}imagina_license_keys");

        // Eliminar campo is_premium
        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_plugins LIKE 'is_premium'");

        if (!empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_plugins DROP COLUMN is_premium");
        }

        delete_option('imagina_license_db_version');

        imagina_license_log('Tablas de licencias eliminadas', 'info');
    }
}
