<?php
/**
 * Gestión de grupos de plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_Plugin_Groups {

    /**
     * Crear un nuevo grupo de plugins
     *
     * @param string $name Nombre del grupo
     * @param string $description Descripción del grupo
     * @param array $plugin_ids IDs de plugins a incluir
     * @return int|WP_Error ID del grupo creado o error
     */
    public static function create_group($name, $description = '', $plugin_ids = array()) {
        global $wpdb;

        $table_groups = $wpdb->prefix . 'imagina_updater_plugin_groups';

        // Validar nombre
        if (empty($name)) {
            return new WP_Error('invalid_name', __('El nombre del grupo es requerido', 'imagina-updater-server'));
        }

        // Verificar si ya existe un grupo con ese nombre
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_groups WHERE name = %s",
            $name
        ));

        if ($exists > 0) {
            return new WP_Error('group_exists', __('Ya existe un grupo con ese nombre', 'imagina-updater-server'));
        }

        // Insertar grupo
        $result = $wpdb->insert(
            $table_groups,
            array(
                'name' => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Error al crear el grupo', 'imagina-updater-server'));
        }

        $group_id = $wpdb->insert_id;

        // Agregar plugins al grupo
        if (!empty($plugin_ids)) {
            self::set_group_plugins($group_id, $plugin_ids);
        }

        return $group_id;
    }

    /**
     * Actualizar un grupo de plugins
     *
     * @param int $group_id ID del grupo
     * @param string $name Nombre del grupo
     * @param string $description Descripción del grupo
     * @param array $plugin_ids IDs de plugins a incluir
     * @return bool|WP_Error
     */
    public static function update_group($group_id, $name, $description = '', $plugin_ids = array()) {
        global $wpdb;

        $table_groups = $wpdb->prefix . 'imagina_updater_plugin_groups';

        // Validar que el grupo existe
        $group = self::get_group($group_id);
        if (!$group) {
            return new WP_Error('not_found', __('Grupo no encontrado', 'imagina-updater-server'));
        }

        // Validar nombre
        if (empty($name)) {
            return new WP_Error('invalid_name', __('El nombre del grupo es requerido', 'imagina-updater-server'));
        }

        // Verificar si ya existe otro grupo con ese nombre
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_groups WHERE name = %s AND id != %d",
            $name,
            $group_id
        ));

        if ($exists > 0) {
            return new WP_Error('group_exists', __('Ya existe otro grupo con ese nombre', 'imagina-updater-server'));
        }

        // Actualizar grupo
        $result = $wpdb->update(
            $table_groups,
            array(
                'name' => sanitize_text_field($name),
                'description' => sanitize_textarea_field($description)
            ),
            array('id' => $group_id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Error al actualizar el grupo', 'imagina-updater-server'));
        }

        // Actualizar plugins del grupo
        self::set_group_plugins($group_id, $plugin_ids);

        return true;
    }

    /**
     * Establecer los plugins de un grupo (reemplaza los existentes)
     *
     * @param int $group_id ID del grupo
     * @param array $plugin_ids IDs de plugins
     * @return bool
     */
    public static function set_group_plugins($group_id, $plugin_ids) {
        global $wpdb;

        $table_items = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        // Eliminar plugins existentes del grupo
        $wpdb->delete($table_items, array('group_id' => $group_id), array('%d'));

        // Agregar nuevos plugins
        if (!empty($plugin_ids)) {
            foreach ($plugin_ids as $plugin_id) {
                $wpdb->insert(
                    $table_items,
                    array(
                        'group_id' => $group_id,
                        'plugin_id' => intval($plugin_id)
                    ),
                    array('%d', '%d')
                );
            }
        }

        return true;
    }

    /**
     * Obtener un grupo por ID
     *
     * @param int $group_id ID del grupo
     * @return object|null
     */
    public static function get_group($group_id) {
        global $wpdb;

        $table_groups = $wpdb->prefix . 'imagina_updater_plugin_groups';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_groups WHERE id = %d",
            $group_id
        ));
    }

    /**
     * Obtener todos los grupos
     *
     * @return array
     */
    public static function get_all_groups() {
        global $wpdb;

        $table_groups = $wpdb->prefix . 'imagina_updater_plugin_groups';

        return $wpdb->get_results("SELECT * FROM $table_groups ORDER BY name ASC");
    }

    /**
     * Obtener plugins de un grupo
     *
     * @param int $group_id ID del grupo
     * @return array
     */
    public static function get_group_plugins($group_id) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables require direct query
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.*
            FROM {$wpdb->prefix}imagina_updater_plugins p
            INNER JOIN {$wpdb->prefix}imagina_updater_plugin_group_items gi ON p.id = gi.plugin_id
            WHERE gi.group_id = %d
            ORDER BY p.name ASC",
            $group_id
        ));
    }

    /**
     * Obtener IDs de plugins de un grupo
     *
     * @param int $group_id ID del grupo
     * @return array Array de IDs
     */
    public static function get_group_plugin_ids($group_id) {
        global $wpdb;

        $table_items = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT plugin_id FROM $table_items WHERE group_id = %d",
            $group_id
        ));

        return array_map('intval', $results);
    }

    /**
     * Eliminar un grupo
     *
     * @param int $group_id ID del grupo
     * @return bool|WP_Error
     */
    public static function delete_group($group_id) {
        global $wpdb;

        $table_groups = $wpdb->prefix . 'imagina_updater_plugin_groups';
        $table_items = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        // Validar que el grupo existe
        $group = self::get_group($group_id);
        if (!$group) {
            return new WP_Error('not_found', __('Grupo no encontrado', 'imagina-updater-server'));
        }

        // Eliminar items del grupo
        $wpdb->delete($table_items, array('group_id' => $group_id), array('%d'));

        // Eliminar grupo
        $result = $wpdb->delete($table_groups, array('id' => $group_id), array('%d'));

        if ($result === false) {
            return new WP_Error('db_error', __('Error al eliminar el grupo', 'imagina-updater-server'));
        }

        return true;
    }

    /**
     * Obtener conteo de plugins en un grupo
     *
     * @param int $group_id ID del grupo
     * @return int
     */
    public static function get_group_plugin_count($group_id) {
        global $wpdb;

        $table_items = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_items WHERE group_id = %d",
            $group_id
        ));
    }
}
