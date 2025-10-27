<?php
/**
 * Gestión de API Keys
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_API_Keys {

    /**
     * Generar una nueva API Key
     */
    public static function generate_key() {
        return 'ius_' . bin2hex(random_bytes(30));
    }

    /**
     * Crear una nueva API Key
     *
     * @param string $site_name Nombre del sitio
     * @param string $site_url URL del sitio
     * @return array|WP_Error Array con la API key o WP_Error en caso de error
     */
    public static function create($site_name, $site_url) {
        global $wpdb;

        // Validar datos
        $site_name = sanitize_text_field($site_name);
        $site_url = esc_url_raw($site_url);

        if (empty($site_name) || empty($site_url)) {
            return new WP_Error('invalid_data', __('Nombre y URL del sitio son obligatorios', 'imagina-updater-server'));
        }

        // Generar API Key única
        $api_key = self::generate_key();
        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        $result = $wpdb->insert(
            $table,
            array(
                'api_key' => $api_key,
                'site_name' => $site_name,
                'site_url' => $site_url,
                'is_active' => 1,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Error al crear la API Key', 'imagina-updater-server'));
        }

        return array(
            'id' => $wpdb->insert_id,
            'api_key' => $api_key,
            'site_name' => $site_name,
            'site_url' => $site_url
        );
    }

    /**
     * Validar una API Key
     *
     * @param string $api_key API Key a validar
     * @return bool|object False si no es válida, objeto con datos si es válida
     */
    public static function validate($api_key) {
        global $wpdb;

        if (empty($api_key) || !is_string($api_key)) {
            return false;
        }

        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        $key_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_key = %s AND is_active = 1",
            $api_key
        ));

        if (!$key_data) {
            return false;
        }

        // Actualizar último uso
        $wpdb->update(
            $table,
            array('last_used' => current_time('mysql')),
            array('id' => $key_data->id),
            array('%s'),
            array('%d')
        );

        return $key_data;
    }

    /**
     * Obtener todas las API Keys
     *
     * @return array
     */
    public static function get_all() {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        return $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
    }

    /**
     * Obtener una API Key por ID
     *
     * @param int $id ID de la API Key
     * @return object|null
     */
    public static function get_by_id($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
    }

    /**
     * Activar/Desactivar una API Key
     *
     * @param int $id ID de la API Key
     * @param bool $is_active Estado
     * @return bool
     */
    public static function set_active($id, $is_active) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        $result = $wpdb->update(
            $table,
            array('is_active' => $is_active ? 1 : 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Eliminar una API Key
     *
     * @param int $id ID de la API Key
     * @return bool
     */
    public static function delete($id) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        $result = $wpdb->delete(
            $table,
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * Obtener estadísticas de uso de una API Key
     *
     * @param int $api_key_id ID de la API Key
     * @return array
     */
    public static function get_usage_stats($api_key_id) {
        global $wpdb;

        $table_downloads = $wpdb->prefix . 'imagina_updater_downloads';

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_downloads WHERE api_key_id = %d",
            $api_key_id
        ));

        $last_30_days = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_downloads
            WHERE api_key_id = %d AND downloaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $api_key_id
        ));

        return array(
            'total_downloads' => intval($total),
            'last_30_days' => intval($last_30_days)
        );
    }
}
