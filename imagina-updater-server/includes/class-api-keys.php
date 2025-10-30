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
     * @param string $access_type Tipo de acceso: 'all', 'specific', 'groups'
     * @param array $allowed_plugins IDs de plugins permitidos (si access_type es 'specific')
     * @param array $allowed_groups IDs de grupos permitidos (si access_type es 'groups')
     * @return array|WP_Error Array con la API key o WP_Error en caso de error
     */
    public static function create($site_name, $site_url, $access_type = 'all', $allowed_plugins = array(), $allowed_groups = array()) {
        global $wpdb;

        // Validar datos
        $site_name = sanitize_text_field($site_name);
        $site_url = esc_url_raw($site_url);

        if (empty($site_name) || empty($site_url)) {
            return new WP_Error('invalid_data', __('Nombre y URL del sitio son obligatorios', 'imagina-updater-server'));
        }

        // Validar access_type
        if (!in_array($access_type, array('all', 'specific', 'groups'))) {
            $access_type = 'all';
        }

        // Generar API Key única
        $api_key = self::generate_key();
        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        // Preparar datos de permisos
        $allowed_plugins_json = null;
        $allowed_groups_json = null;

        if ($access_type === 'specific' && !empty($allowed_plugins)) {
            $allowed_plugins_json = json_encode(array_map('intval', $allowed_plugins));
        }

        if ($access_type === 'groups' && !empty($allowed_groups)) {
            $allowed_groups_json = json_encode(array_map('intval', $allowed_groups));
        }

        $result = $wpdb->insert(
            $table,
            array(
                'api_key' => $api_key,
                'site_name' => $site_name,
                'site_url' => $site_url,
                'is_active' => 1,
                'access_type' => $access_type,
                'allowed_plugins' => $allowed_plugins_json,
                'allowed_groups' => $allowed_groups_json,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
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
     * Obtener API Key por key (sin validar is_active ni actualizar last_used)
     *
     * @param string $api_key API Key
     * @return object|null
     */
    public static function get_by_key($api_key) {
        global $wpdb;

        if (empty($api_key) || !is_string($api_key)) {
            return null;
        }

        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_key = %s",
            $api_key
        ));
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
     * Actualizar permisos de una API Key
     *
     * @param int $id ID de la API Key
     * @param string $access_type Tipo de acceso: 'all', 'specific', 'groups'
     * @param array $allowed_plugins IDs de plugins permitidos (si access_type es 'specific')
     * @param array $allowed_groups IDs de grupos permitidos (si access_type es 'groups')
     * @return bool|WP_Error
     */
    public static function update_permissions($id, $access_type, $allowed_plugins = array(), $allowed_groups = array()) {
        global $wpdb;

        // Validar que la API key existe
        $key = self::get_by_id($id);
        if (!$key) {
            return new WP_Error('not_found', __('API Key no encontrada', 'imagina-updater-server'));
        }

        // Validar access_type
        if (!in_array($access_type, array('all', 'specific', 'groups'))) {
            return new WP_Error('invalid_access_type', __('Tipo de acceso inválido', 'imagina-updater-server'));
        }

        // Preparar datos de permisos
        $allowed_plugins_json = null;
        $allowed_groups_json = null;

        if ($access_type === 'specific' && !empty($allowed_plugins)) {
            $allowed_plugins_json = json_encode(array_map('intval', $allowed_plugins));
        }

        if ($access_type === 'groups' && !empty($allowed_groups)) {
            $allowed_groups_json = json_encode(array_map('intval', $allowed_groups));
        }

        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        $result = $wpdb->update(
            $table,
            array(
                'access_type' => $access_type,
                'allowed_plugins' => $allowed_plugins_json,
                'allowed_groups' => $allowed_groups_json
            ),
            array('id' => $id),
            array('%s', '%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Error al actualizar permisos', 'imagina-updater-server'));
        }

        return true;
    }

    /**
     * Regenerar API Key manteniendo todos los demás datos
     *
     * @param int $id ID de la API Key a regenerar
     * @return array|WP_Error Array con el nuevo API key o WP_Error en caso de error
     */
    public static function regenerate_key($id) {
        global $wpdb;

        // Validar que la API key existe
        $existing_key = self::get_by_id($id);
        if (!$existing_key) {
            return new WP_Error('not_found', __('API Key no encontrada', 'imagina-updater-server'));
        }

        // Generar nueva API Key
        $new_api_key = self::generate_key();
        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        // Actualizar solo el campo api_key, manteniendo todo lo demás
        $result = $wpdb->update(
            $table,
            array('api_key' => $new_api_key),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Error al regenerar la API Key', 'imagina-updater-server'));
        }

        return array(
            'id' => $id,
            'api_key' => $new_api_key,
            'site_name' => $existing_key->site_name,
            'site_url' => $existing_key->site_url
        );
    }

    /**
     * Actualizar información del sitio (nombre y URL)
     *
     * @param int $id ID de la API Key
     * @param string $site_name Nuevo nombre del sitio
     * @param string $site_url Nueva URL del sitio
     * @return bool|WP_Error True si se actualizó correctamente, WP_Error en caso de error
     */
    public static function update_site_info($id, $site_name, $site_url) {
        global $wpdb;

        // Validar que la API key existe
        $existing_key = self::get_by_id($id);
        if (!$existing_key) {
            return new WP_Error('not_found', __('API Key no encontrada', 'imagina-updater-server'));
        }

        // Validar datos
        $site_name = sanitize_text_field($site_name);
        $site_url = esc_url_raw($site_url);

        if (empty($site_name) || empty($site_url)) {
            return new WP_Error('invalid_data', __('Nombre y URL del sitio son obligatorios', 'imagina-updater-server'));
        }

        $table = $wpdb->prefix . 'imagina_updater_api_keys';

        // Actualizar nombre y URL del sitio
        $result = $wpdb->update(
            $table,
            array(
                'site_name' => $site_name,
                'site_url' => $site_url
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Error al actualizar información del sitio', 'imagina-updater-server'));
        }

        return true;
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
