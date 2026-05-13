<?php
/**
 * API REST exclusiva para la SPA admin del servidor (Fase 5).
 *
 * Namespace separado del público (`imagina-updater/v1`) para garantizar
 * retrocompatibilidad: los sitios cliente en producción dependen de
 * `v1` y NO se debe romper su contrato (CLAUDE.md §4 regla 5).
 *
 * Auth: cookie de WordPress + nonce `wp_rest`. Capability `manage_options`.
 *       Solo accesible desde el wp-admin del propio servidor.
 *
 * @package Imagina_Updater_Server
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// Justificación (Fase 2/5): este archivo opera sobre tablas custom propias
// del plugin. Las queries directas son intencionales (no hay caché de
// objetos compartido que invalidar para datos de baja cardinalidad), y los
// nombres de tabla se construyen con $wpdb->prefix concatenado a constantes
// literales, nunca a partir de input de usuario.

class Imagina_Updater_Server_Admin_REST_API {

    /**
     * Namespace REST exclusivo de la SPA admin.
     */
    const NAMESPACE = 'imagina-updater/admin/v1';

    /**
     * Singleton.
     *
     * @var self|null
     */
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Permission callback común a todos los endpoints admin.
     *
     * Combina dos comprobaciones:
     *   1. `current_user_can('manage_options')` — requisito mínimo.
     *   2. Nonce `wp_rest` (validado automáticamente por WordPress al
     *      detectar el header `X-WP-Nonce` en la petición).
     *
     * @return bool|WP_Error
     */
    public function check_admin_permissions() {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'iaud_forbidden',
                __('Permisos insuficientes.', 'imagina-updater-server'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * Registro de rutas. Las pantallas se irán añadiendo en commits
     * sucesivos (5.1 dashboard, 5.2 api-keys, 5.3 plugins, etc.).
     */
    public function register_routes() {
        register_rest_route(
            self::NAMESPACE,
            '/dashboard/stats',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_dashboard_stats'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/dashboard/downloads-30d',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_downloads_30d'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/dashboard/recent-downloads',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_recent_downloads'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/dashboard/top-plugins',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_top_plugins'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );
    }

    /**
     * GET /admin/v1/dashboard/stats
     *
     * Endpoint mínimo para validar el cableado completo SPA ↔ REST en
     * Fase 5.0. Las KPI reales (gráficos, top plugins, etc.) se añaden
     * en Fase 5.1.
     */
    public function get_dashboard_stats(WP_REST_Request $request) {
        global $wpdb;

        unset($request); // Sin parámetros en este endpoint.

        $plugins_table     = $wpdb->prefix . 'imagina_updater_plugins';
        $api_keys_table    = $wpdb->prefix . 'imagina_updater_api_keys';
        $activations_table = $wpdb->prefix . 'imagina_updater_activations';
        $downloads_table   = $wpdb->prefix . 'imagina_updater_downloads';

        $total_plugins = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$plugins_table}"
        );

        $active_api_keys = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$api_keys_table} WHERE is_active = 1"
        );

        $active_activations = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$activations_table} WHERE is_active = 1"
        );

        $downloads_24h = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$downloads_table} WHERE downloaded_at >= %s",
                gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)
            )
        );

        return rest_ensure_response(
            array(
                'total_plugins'      => $total_plugins,
                'active_api_keys'    => $active_api_keys,
                'active_activations' => $active_activations,
                'downloads_24h'      => $downloads_24h,
            )
        );
    }

    /**
     * GET /admin/v1/dashboard/downloads-30d
     *
     * Devuelve la serie diaria de descargas de los últimos 30 días,
     * con días vacíos rellenados a 0 para que el chart no salte huecos.
     *
     * Formato: array<{ day: 'YYYY-MM-DD', count: int }> (30 entries).
     */
    public function get_downloads_30d(WP_REST_Request $request) {
        global $wpdb;

        unset($request);

        $downloads_table = $wpdb->prefix . 'imagina_updater_downloads';
        $start_unix      = time() - (29 * DAY_IN_SECONDS);
        $start_date      = gmdate('Y-m-d 00:00:00', $start_unix);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(downloaded_at) AS day, COUNT(*) AS count
                 FROM {$downloads_table}
                 WHERE downloaded_at >= %s
                 GROUP BY DATE(downloaded_at)",
                $start_date
            ),
            ARRAY_A
        );

        $by_day = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $by_day[$row['day']] = (int) $row['count'];
            }
        }

        $series = array();
        for ($i = 0; $i < 30; $i++) {
            $day            = gmdate('Y-m-d', $start_unix + ($i * DAY_IN_SECONDS));
            $series[]       = array(
                'day'   => $day,
                'count' => isset($by_day[$day]) ? $by_day[$day] : 0,
            );
        }

        return rest_ensure_response($series);
    }

    /**
     * GET /admin/v1/dashboard/recent-downloads
     *
     * Últimas 10 descargas con plugin y sitio cliente para mostrar
     * actividad reciente en el dashboard.
     */
    public function get_recent_downloads(WP_REST_Request $request) {
        global $wpdb;

        unset($request);

        $downloads_table = $wpdb->prefix . 'imagina_updater_downloads';
        $plugins_table   = $wpdb->prefix . 'imagina_updater_plugins';
        $api_keys_table  = $wpdb->prefix . 'imagina_updater_api_keys';

        $rows = $wpdb->get_results(
            "SELECT d.id, d.version, d.ip_address, d.downloaded_at,
                    p.slug AS plugin_slug,
                    p.name AS plugin_name,
                    ak.site_name
             FROM {$downloads_table} d
             LEFT JOIN {$plugins_table} p ON p.id = d.plugin_id
             LEFT JOIN {$api_keys_table} ak ON ak.id = d.api_key_id
             ORDER BY d.downloaded_at DESC
             LIMIT 10",
            ARRAY_A
        );

        if (!is_array($rows)) {
            $rows = array();
        }

        // Cast numeric columns para que el frontend no tenga que
        // adivinar tipos (PHP devuelve todo como string desde MySQL).
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
        }
        unset($row);

        return rest_ensure_response($rows);
    }

    /**
     * GET /admin/v1/dashboard/top-plugins
     *
     * Top 5 plugins por descargas totales.
     */
    public function get_top_plugins(WP_REST_Request $request) {
        global $wpdb;

        unset($request);

        $downloads_table = $wpdb->prefix . 'imagina_updater_downloads';
        $plugins_table   = $wpdb->prefix . 'imagina_updater_plugins';

        $rows = $wpdb->get_results(
            "SELECT p.id, p.slug, p.name, p.current_version,
                    COUNT(d.id) AS downloads
             FROM {$plugins_table} p
             LEFT JOIN {$downloads_table} d ON d.plugin_id = p.id
             GROUP BY p.id, p.slug, p.name, p.current_version
             ORDER BY downloads DESC, p.name ASC
             LIMIT 5",
            ARRAY_A
        );

        if (!is_array($rows)) {
            $rows = array();
        }

        foreach ($rows as &$row) {
            $row['id']        = (int) $row['id'];
            $row['downloads'] = (int) $row['downloads'];
        }
        unset($row);

        return rest_ensure_response($rows);
    }
}
