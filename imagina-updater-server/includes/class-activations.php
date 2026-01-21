<?php
/**
 * Gestión de activaciones de sitios
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_Activations {

    /**
     * Generar token de activación único
     */
    private static function generate_activation_token() {
        return 'iat_' . bin2hex(random_bytes(30));
    }

    /**
     * Activar un sitio para una API key
     *
     * @param string $api_key API key
     * @param string $site_domain Dominio del sitio
     * @return array|WP_Error Array con activation_token o WP_Error
     */
    public static function activate_site($api_key, $site_domain) {
        global $wpdb;

        // Validar API key
        $key_data = Imagina_Updater_Server_API_Keys::get_by_key($api_key);
        if (!$key_data) {
            return new WP_Error('invalid_api_key', __('API Key inválida', 'imagina-updater-server'));
        }

        if (!$key_data->is_active) {
            return new WP_Error('inactive_api_key', __('API Key desactivada', 'imagina-updater-server'));
        }

        // Normalizar dominio
        $site_domain = self::normalize_domain($site_domain);
        if (empty($site_domain)) {
            return new WP_Error('invalid_domain', __('Dominio inválido', 'imagina-updater-server'));
        }

        $table = $wpdb->prefix . 'imagina_updater_activations';

        // Verificar si el sitio ya está activado
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE api_key_id = %d AND site_domain = %s AND is_active = 1",
            $key_data->id,
            $site_domain
        ));

        if ($existing) {
            // Ya está activado, devolver el token existente
            return array(
                'activation_token' => $existing->activation_token,
                'message' => __('Este sitio ya está activado', 'imagina-updater-server'),
                'already_activated' => true
            );
        }

        // Verificar límite de activaciones
        $current_activations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE api_key_id = %d AND is_active = 1",
            $key_data->id
        ));

        // max_activations: 0 = ilimitado, >0 = límite específico
        if ($key_data->max_activations > 0 && $current_activations >= $key_data->max_activations) {
            // Obtener dominios activados para mostrar en el error
            $activated_sites = $wpdb->get_col($wpdb->prepare(
                "SELECT site_domain FROM $table WHERE api_key_id = %d AND is_active = 1 ORDER BY activated_at DESC",
                $key_data->id
            ));

            return new WP_Error(
                'activation_limit_reached',
                sprintf(
                    /* translators: %1$d: current activations count, %2$d: maximum activations allowed, %3$s: list of activated sites */
                    __('Límite de activaciones alcanzado (%1$d/%2$d). Sitios activados: %3$s. Contacta al administrador para desactivar un sitio.', 'imagina-updater-server'),
                    $current_activations,
                    $key_data->max_activations,
                    implode(', ', $activated_sites)
                )
            );
        }

        // Generar token de activación
        $activation_token = self::generate_activation_token();

        // Insertar activación
        $result = $wpdb->insert(
            $table,
            array(
                'api_key_id' => $key_data->id,
                'site_domain' => $site_domain,
                'activation_token' => $activation_token,
                'is_active' => 1,
                'activated_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Error al activar el sitio', 'imagina-updater-server'));
        }

        imagina_updater_server_log(sprintf(
            'Sitio activado: %s para API key ID %d (Activación %d/%s)',
            $site_domain,
            $key_data->id,
            $current_activations + 1,
            $key_data->max_activations == 0 ? 'ilimitado' : $key_data->max_activations
        ), 'info');

        return array(
            'activation_token' => $activation_token,
            'site_domain' => $site_domain,
            'message' => __('Sitio activado exitosamente', 'imagina-updater-server'),
            'activations_used' => $current_activations + 1,
            'max_activations' => $key_data->max_activations
        );
    }

    /**
     * Validar activation token
     *
     * @param string $activation_token Token de activación
     * @param string $site_domain Dominio que reporta el cliente
     * @return object|false Datos de la activación o false
     */
    public static function validate_token($activation_token, $site_domain) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_activations';
        $site_domain = self::normalize_domain($site_domain);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables require direct query
        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, k.api_key, k.is_active as api_key_active
             FROM {$wpdb->prefix}imagina_updater_activations a
             INNER JOIN {$wpdb->prefix}imagina_updater_api_keys k ON a.api_key_id = k.id
             WHERE a.activation_token = %s AND a.is_active = 1",
            $activation_token
        ));

        if (!$activation) {
            return false;
        }

        // Verificar que el API key siga activo
        if (!$activation->api_key_active) {
            return false;
        }

        // Verificar que el dominio coincida
        if ($activation->site_domain !== $site_domain) {
            imagina_updater_server_log(sprintf(
                'Dominio no coincide para token %s. Registrado: %s | Reportado: %s',
                substr($activation_token, 0, 10) . '...',
                $activation->site_domain,
                $site_domain
            ), 'warning');
            return false;
        }

        // Actualizar last_verified
        $wpdb->update(
            $table,
            array('last_verified' => current_time('mysql')),
            array('id' => $activation->id),
            array('%s'),
            array('%d')
        );

        return $activation;
    }

    /**
     * Desactivar un sitio por ID (elimina el registro completamente)
     *
     * @param int $activation_id ID de la activación
     * @return bool
     */
    public static function deactivate_site($activation_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_activations';

        // Obtener datos antes de eliminar para el log
        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT site_domain, api_key_id FROM $table WHERE id = %d",
            $activation_id
        ));

        // Eliminar registro completamente
        $result = $wpdb->delete(
            $table,
            array('id' => $activation_id),
            array('%d')
        );

        if ($result !== false && $activation) {
            imagina_updater_server_log(sprintf(
                'Activación eliminada: %s (API key ID: %d, Activation ID: %d)',
                $activation->site_domain,
                $activation->api_key_id,
                $activation_id
            ), 'info');
        }

        return $result !== false;
    }

    /**
     * Desactivar sitio por token de activación (para que el cliente pueda desactivarse)
     *
     * @param string $activation_token Token de activación
     * @param string $site_domain Dominio del sitio
     * @return bool
     */
    public static function deactivate_by_token($activation_token, $site_domain) {
        global $wpdb;

        $site_domain = self::normalize_domain($site_domain);

        // Buscar la activación
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables require direct query
        $activation = $wpdb->get_row($wpdb->prepare(
            "SELECT id, api_key_id FROM {$wpdb->prefix}imagina_updater_activations WHERE activation_token = %s AND site_domain = %s AND is_active = 1",
            $activation_token,
            $site_domain
        ));

        if (!$activation) {
            return false;
        }

        // Eliminar registro
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete
        $result = $wpdb->delete(
            $wpdb->prefix . 'imagina_updater_activations',
            array('id' => $activation->id),
            array('%d')
        );

        if ($result !== false) {
            imagina_updater_server_log(sprintf(
                'Cliente desactivó su licencia: %s (API key ID: %d)',
                $site_domain,
                $activation->api_key_id
            ), 'info');
        }

        return $result !== false;
    }

    /**
     * Obtener activaciones de una API key
     *
     * @param int $api_key_id ID de la API key
     * @param bool $active_only Solo activaciones activas
     * @return array
     */
    public static function get_activations($api_key_id, $active_only = true) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_activations';

        $where = "api_key_id = %d";
        if ($active_only) {
            $where .= " AND is_active = 1";
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY activated_at DESC",
            $api_key_id
        ));
    }

    /**
     * Contar activaciones activas
     *
     * @param int $api_key_id ID de la API key
     * @return int
     */
    public static function count_active_activations($api_key_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_activations';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE api_key_id = %d AND is_active = 1",
            $api_key_id
        ));
    }

    /**
     * Normalizar dominio para comparación consistente
     */
    private static function normalize_domain($url) {
        if (empty($url)) {
            return '';
        }

        // Asegurarse de que tenga esquema
        if (strpos($url, 'http') !== 0) {
            $url = 'https://' . $url;
        }

        $parsed = wp_parse_url($url);
        if (!isset($parsed['host'])) {
            return '';
        }

        $host = strtolower($parsed['host']);

        // Eliminar www.
        $host = preg_replace('/^www\./', '', $host);

        return $host;
    }

    /**
     * Obtener activación por token
     */
    public static function get_by_token($activation_token) {
        global $wpdb;

        $table = $wpdb->prefix . 'imagina_updater_activations';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE activation_token = %s",
            $activation_token
        ));
    }
}
