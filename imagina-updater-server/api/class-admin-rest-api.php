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

        // ---- Fase 5.2: API Keys CRUD ---------------------------------

        register_rest_route(
            self::NAMESPACE,
            '/api-keys',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'list_api_keys'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                    'args'                => array(
                        'lite'     => array('type' => 'boolean', 'default' => false),
                        'page'     => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
                        'per_page' => array('type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100),
                        'status'   => array('type' => 'string', 'enum' => array('all', 'active', 'inactive'), 'default' => 'all'),
                        'search'   => array('type' => 'string', 'default' => ''),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'create_api_key'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/api-keys/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE, // PUT/PATCH
                    'callback'            => array($this, 'update_api_key'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'delete_api_key'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/api-keys/(?P<id>\d+)/regenerate',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'regenerate_api_key'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/api-keys/(?P<id>\d+)/toggle-active',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'toggle_api_key_active'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        // Lookup endpoints minimalistas para los pickers del drawer.
        // Versiones completas (con paginación, etc.) llegan en 5.3 y 5.4.
        register_rest_route(
            self::NAMESPACE,
            '/plugins',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'list_plugins'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'lite'     => array('type' => 'boolean', 'default' => false),
                    'page'     => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
                    'per_page' => array('type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100),
                    'search'   => array('type' => 'string', 'default' => ''),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/plugin-groups',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'list_plugin_groups'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                    'args'                => array(
                        'lite' => array('type' => 'boolean', 'default' => false),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'create_plugin_group'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/plugin-groups/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_plugin_group'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'delete_plugin_group'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
            )
        );

        // ---- Fase 5.3: Plugins CRUD + upload + versions -------------

        register_rest_route(
            self::NAMESPACE,
            '/plugins/upload',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'upload_plugin'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/plugins/(?P<id>\d+)',
            array(
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_plugin'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'delete_plugin'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/plugins/(?P<id>\d+)/toggle-premium',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'toggle_plugin_premium'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/plugins/(?P<id>\d+)/reinject-protection',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'reinject_plugin_protection'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/plugins/(?P<id>\d+)/versions',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'list_plugin_versions'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/plugins/(?P<id>\d+)/download',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'download_plugin_zip'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        // ---- Fase 5.5: Activations ----------------------------------

        register_rest_route(
            self::NAMESPACE,
            '/activations',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'list_activations'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(
                    'page'        => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
                    'per_page'    => array('type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100),
                    'status'      => array('type' => 'string', 'enum' => array('all', 'active', 'inactive'), 'default' => 'all'),
                    'api_key_id'  => array('type' => 'integer', 'default' => 0),
                    'search'      => array('type' => 'string', 'default' => ''),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/activations/(?P<id>\d+)/deactivate',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'deactivate_activation'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        // ---- Fase 5.6: Logs -----------------------------------------

        register_rest_route(
            self::NAMESPACE,
            '/logs',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'list_logs'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                    'args'                => array(
                        'page'     => array('type' => 'integer', 'default' => 1, 'minimum' => 1),
                        'per_page' => array('type' => 'integer', 'default' => 100, 'minimum' => 10, 'maximum' => 500),
                        'level'    => array('type' => 'string', 'default' => 'all'),
                        'search'   => array('type' => 'string', 'default' => ''),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => array($this, 'clear_logs'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/logs/download',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'download_logs'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        // ---- Fase 5.7: Settings + Maintenance -----------------------

        register_rest_route(
            self::NAMESPACE,
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array($this, 'get_settings'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'update_settings'),
                    'permission_callback' => array($this, 'check_admin_permissions'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/maintenance/run-migrations',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'run_migrations'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/maintenance/clear-rate-limits',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'clear_rate_limits_endpoint'),
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

    // =========================================================
    // Fase 5.2 — API Keys
    // =========================================================

    /**
     * Mascara una API key para exposición segura: muestra los primeros
     * 4 chars del prefijo (`ius_`) y los últimos 4 del cuerpo.
     */
    private function mask_api_key($plain) {
        if (!is_string($plain) || strlen($plain) < 8) {
            return '';
        }
        return substr($plain, 0, 4) . '••••' . substr($plain, -4);
    }

    /**
     * Serializa una fila cruda de la tabla wp_imagina_updater_api_keys
     * a la forma que consume la SPA. NUNCA incluye el `api_key` en
     * claro; ese solo se devuelve en `create` y `regenerate`.
     */
    private function serialize_api_key($row, $activations_used) {
        $allowed_plugins = array();
        if (!empty($row->allowed_plugins)) {
            $decoded = json_decode($row->allowed_plugins, true);
            if (is_array($decoded)) {
                $allowed_plugins = array_values(array_map('intval', $decoded));
            }
        }

        $allowed_groups = array();
        if (!empty($row->allowed_groups)) {
            $decoded = json_decode($row->allowed_groups, true);
            if (is_array($decoded)) {
                $allowed_groups = array_values(array_map('intval', $decoded));
            }
        }

        return array(
            'id'               => (int) $row->id,
            'site_name'        => (string) $row->site_name,
            'site_url'         => (string) $row->site_url,
            'api_key_masked'   => $this->mask_api_key($row->api_key),
            'is_active'        => (int) $row->is_active === 1,
            'access_type'      => (string) $row->access_type,
            'allowed_plugins'  => $allowed_plugins,
            'allowed_groups'   => $allowed_groups,
            'max_activations'  => (int) $row->max_activations,
            'activations_used' => (int) $activations_used,
            'created_at'       => (string) $row->created_at,
            'last_used'        => $row->last_used ? (string) $row->last_used : null,
        );
    }

    /**
     * GET /admin/v1/api-keys
     *
     * Modo dual:
     *   - `?lite=1` → array `[{id, site_name}]` para dropdowns (Fase 5.5).
     *   - Sin `lite` → paginado con filtros por estado y búsqueda libre.
     *     La columna `activations_used` se calcula con LEFT JOIN sobre
     *     activations (is_active=1) para evitar N+1.
     */
    public function list_api_keys(WP_REST_Request $request) {
        global $wpdb;

        if ($request->get_param('lite')) {
            $table = $wpdb->prefix . 'imagina_updater_api_keys';
            $rows  = $wpdb->get_results(
                "SELECT id, site_name FROM {$table} ORDER BY site_name ASC",
                ARRAY_A
            );
            if (!is_array($rows)) {
                $rows = array();
            }
            foreach ($rows as &$row) {
                $row['id'] = (int) $row['id'];
            }
            unset($row);
            return rest_ensure_response($rows);
        }

        $page     = max(1, (int) $request->get_param('page'));
        $per_page = max(1, min(100, (int) $request->get_param('per_page')));
        $status   = (string) $request->get_param('status');
        $search   = trim((string) $request->get_param('search'));
        $offset   = ($page - 1) * $per_page;

        $api_keys_table    = $wpdb->prefix . 'imagina_updater_api_keys';
        $activations_table = $wpdb->prefix . 'imagina_updater_activations';

        $where     = array();
        $where_args = array();
        if ('active' === $status) {
            $where[] = 'k.is_active = 1';
        } elseif ('inactive' === $status) {
            $where[] = 'k.is_active = 0';
        }
        if ('' !== $search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[]      = '(k.site_name LIKE %s OR k.site_url LIKE %s)';
            $where_args[] = $like;
            $where_args[] = $like;
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total filtrado (sin paginar) para que el frontend pinte el
        // pager correcto.
        $count_sql = "SELECT COUNT(*) FROM {$api_keys_table} k {$where_sql}";
        $total     = (int) ($where_args
            ? $wpdb->get_var($wpdb->prepare($count_sql, $where_args))
            : $wpdb->get_var($count_sql));

        $list_sql = "SELECT k.*, COALESCE(a.cnt, 0) AS activations_used
                     FROM {$api_keys_table} k
                     LEFT JOIN (
                         SELECT api_key_id, COUNT(*) AS cnt
                         FROM {$activations_table}
                         WHERE is_active = 1
                         GROUP BY api_key_id
                     ) a ON a.api_key_id = k.id
                     {$where_sql}
                     ORDER BY k.created_at DESC
                     LIMIT %d OFFSET %d";

        $list_args = array_merge($where_args, array($per_page, $offset));
        $rows      = $wpdb->get_results($wpdb->prepare($list_sql, $list_args));

        $items = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $items[] = $this->serialize_api_key($row, isset($row->activations_used) ? $row->activations_used : 0);
            }
        }

        return rest_ensure_response(
            array(
                'items'    => $items,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $per_page,
            )
        );
    }

    /**
     * Extrae y valida el payload común a create + update.
     */
    private function parse_api_key_payload(WP_REST_Request $request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = $request->get_params();
        }

        $payload = array(
            'site_name'       => isset($body['site_name']) ? sanitize_text_field((string) $body['site_name']) : '',
            'site_url'        => isset($body['site_url']) ? esc_url_raw((string) $body['site_url']) : '',
            'access_type'     => isset($body['access_type']) ? sanitize_key((string) $body['access_type']) : 'all',
            'allowed_plugins' => array(),
            'allowed_groups'  => array(),
            'max_activations' => isset($body['max_activations']) ? max(0, (int) $body['max_activations']) : 1,
        );

        if (!in_array($payload['access_type'], array('all', 'specific', 'groups'), true)) {
            $payload['access_type'] = 'all';
        }

        if (isset($body['allowed_plugins']) && is_array($body['allowed_plugins'])) {
            $payload['allowed_plugins'] = array_values(array_unique(array_map('intval', $body['allowed_plugins'])));
        }
        if (isset($body['allowed_groups']) && is_array($body['allowed_groups'])) {
            $payload['allowed_groups'] = array_values(array_unique(array_map('intval', $body['allowed_groups'])));
        }

        if ('' === $payload['site_name'] || '' === $payload['site_url']) {
            return new WP_Error(
                'iaud_invalid_payload',
                __('Nombre y URL del sitio son obligatorios.', 'imagina-updater-server'),
                array('status' => 400)
            );
        }

        return $payload;
    }

    /**
     * Carga una fila de api_keys y su activations_used. Devuelve la
     * forma serializada lista para responder.
     */
    private function load_api_key_serialized($id) {
        global $wpdb;

        $api_keys_table    = $wpdb->prefix . 'imagina_updater_api_keys';
        $activations_table = $wpdb->prefix . 'imagina_updater_activations';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$api_keys_table} WHERE id = %d", $id)
        );
        if (!$row) {
            return null;
        }
        $used = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$activations_table} WHERE api_key_id = %d AND is_active = 1",
                $id
            )
        );

        return array('row' => $row, 'serialized' => $this->serialize_api_key($row, $used));
    }

    /**
     * POST /admin/v1/api-keys
     *
     * Crea una API key. Devuelve el item serializado + la API key en
     * claro UNA SOLA VEZ — el frontend muestra un banner con copy y
     * después no se vuelve a exponer (CLAUDE.md §6 fase 5.2).
     */
    public function create_api_key(WP_REST_Request $request) {
        $payload = $this->parse_api_key_payload($request);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $result = Imagina_Updater_Server_API_Keys::create(
            $payload['site_name'],
            $payload['site_url'],
            $payload['access_type'],
            $payload['allowed_plugins'],
            $payload['allowed_groups'],
            $payload['max_activations']
        );
        if (is_wp_error($result)) {
            $result->add_data(array('status' => 400));
            return $result;
        }

        $loaded = $this->load_api_key_serialized((int) $result['id']);
        if (null === $loaded) {
            return new WP_Error('iaud_create_failed', __('No se pudo recuperar la API key creada.', 'imagina-updater-server'), array('status' => 500));
        }

        return rest_ensure_response(
            array(
                'item'      => $loaded['serialized'],
                'plain_key' => (string) $result['api_key'],
            )
        );
    }

    /**
     * PUT /admin/v1/api-keys/{id}
     *
     * Actualiza site_name, site_url, access_type, allowed_*, max_activations.
     * NO regenera la API key (eso es endpoint aparte).
     */
    public function update_api_key(WP_REST_Request $request) {
        global $wpdb;

        $id      = (int) $request['id'];
        $payload = $this->parse_api_key_payload($request);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $api_keys_table = $wpdb->prefix . 'imagina_updater_api_keys';

        // Actualización atómica de site info + permisos en una sola
        // operación. Reusa los métodos estáticos cuando es posible.
        $perms_result = Imagina_Updater_Server_API_Keys::update_permissions(
            $id,
            $payload['access_type'],
            $payload['allowed_plugins'],
            $payload['allowed_groups']
        );
        if (is_wp_error($perms_result)) {
            return $perms_result;
        }

        $site_result = $wpdb->update(
            $api_keys_table,
            array(
                'site_name'       => $payload['site_name'],
                'site_url'        => $payload['site_url'],
                'max_activations' => $payload['max_activations'],
            ),
            array('id' => $id),
            array('%s', '%s', '%d'),
            array('%d')
        );
        if (false === $site_result) {
            return new WP_Error('iaud_db_error', __('Error al actualizar la API key.', 'imagina-updater-server'), array('status' => 500));
        }

        $loaded = $this->load_api_key_serialized($id);
        if (null === $loaded) {
            return new WP_Error('iaud_not_found', __('API Key no encontrada.', 'imagina-updater-server'), array('status' => 404));
        }
        return rest_ensure_response(array('item' => $loaded['serialized']));
    }

    /**
     * DELETE /admin/v1/api-keys/{id}
     */
    public function delete_api_key(WP_REST_Request $request) {
        $id = (int) $request['id'];
        $ok = Imagina_Updater_Server_API_Keys::delete($id);
        if (!$ok) {
            return new WP_Error('iaud_delete_failed', __('No se pudo eliminar la API key.', 'imagina-updater-server'), array('status' => 500));
        }
        return rest_ensure_response(array('deleted' => true, 'id' => $id));
    }

    /**
     * POST /admin/v1/api-keys/{id}/regenerate
     *
     * Genera una API key nueva manteniendo el resto de campos. Devuelve
     * la clave en claro una sola vez.
     */
    public function regenerate_api_key(WP_REST_Request $request) {
        $id     = (int) $request['id'];
        $result = Imagina_Updater_Server_API_Keys::regenerate_key($id);
        if (is_wp_error($result)) {
            $result->add_data(array('status' => 404));
            return $result;
        }
        $loaded = $this->load_api_key_serialized($id);
        if (null === $loaded) {
            return new WP_Error('iaud_not_found', __('API Key no encontrada.', 'imagina-updater-server'), array('status' => 404));
        }
        return rest_ensure_response(
            array(
                'item'      => $loaded['serialized'],
                'plain_key' => (string) $result['api_key'],
            )
        );
    }

    /**
     * POST /admin/v1/api-keys/{id}/toggle-active
     *
     * Acción idempotente respecto al body: el body define el estado
     * deseado (`{ "is_active": true|false }`). Si no viene, alterna.
     */
    public function toggle_api_key_active(WP_REST_Request $request) {
        $id  = (int) $request['id'];
        $row = Imagina_Updater_Server_API_Keys::get_by_id($id);
        if (!$row) {
            return new WP_Error('iaud_not_found', __('API Key no encontrada.', 'imagina-updater-server'), array('status' => 404));
        }

        $body         = $request->get_json_params();
        $next_is_active = isset($body['is_active'])
            ? (bool) $body['is_active']
            : !((int) $row->is_active === 1);

        $ok = Imagina_Updater_Server_API_Keys::set_active($id, $next_is_active);
        if (!$ok) {
            return new WP_Error('iaud_toggle_failed', __('No se pudo cambiar el estado.', 'imagina-updater-server'), array('status' => 500));
        }

        $loaded = $this->load_api_key_serialized($id);
        if (null === $loaded) {
            return new WP_Error('iaud_not_found', __('API Key no encontrada.', 'imagina-updater-server'), array('status' => 404));
        }
        return rest_ensure_response(array('item' => $loaded['serialized']));
    }

    /**
     * GET /admin/v1/plugins
     *
     * Modo dual:
     *   - `?lite=1` → array plano `[{id, slug, effective_slug, name}]`,
     *     usado por los pickers del drawer de API Keys.
     *   - Sin `lite` → paginado con todos los campos para la pantalla
     *     de Plugins (Fase 5.3).
     */
    public function list_plugins(WP_REST_Request $request) {
        if ($request->get_param('lite')) {
            return $this->list_plugins_lite_response();
        }
        return $this->list_plugins_full_response($request);
    }

    private function list_plugins_lite_response() {
        global $wpdb;

        $plugins_table = $wpdb->prefix . 'imagina_updater_plugins';

        $rows = $wpdb->get_results(
            "SELECT id, slug, slug_override, name FROM {$plugins_table} ORDER BY name ASC",
            ARRAY_A
        );
        if (!is_array($rows)) {
            $rows = array();
        }
        foreach ($rows as &$row) {
            $row['id']            = (int) $row['id'];
            $row['effective_slug'] = !empty($row['slug_override']) ? $row['slug_override'] : $row['slug'];
        }
        unset($row);

        return rest_ensure_response($rows);
    }

    private function list_plugins_full_response(WP_REST_Request $request) {
        global $wpdb;

        $page     = max(1, (int) $request->get_param('page'));
        $per_page = max(1, min(100, (int) $request->get_param('per_page')));
        $search   = trim((string) $request->get_param('search'));
        $offset   = ($page - 1) * $per_page;

        $plugins_table   = $wpdb->prefix . 'imagina_updater_plugins';
        $downloads_table = $wpdb->prefix . 'imagina_updater_downloads';
        $items_table     = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        $premium_active = $this->license_extension_active();
        $premium_select = $premium_active ? 'p.is_premium AS is_premium,' : '0 AS is_premium,';

        $where      = array();
        $where_args = array();
        if ('' !== $search) {
            $like         = '%' . $wpdb->esc_like($search) . '%';
            $where[]      = '(p.name LIKE %s OR p.slug LIKE %s OR p.slug_override LIKE %s)';
            $where_args[] = $like;
            $where_args[] = $like;
            $where_args[] = $like;
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count_sql = "SELECT COUNT(*) FROM {$plugins_table} p {$where_sql}";
        $total     = (int) ($where_args
            ? $wpdb->get_var($wpdb->prepare($count_sql, $where_args))
            : $wpdb->get_var($count_sql));

        $list_sql = "SELECT p.id, p.slug, p.slug_override, p.name, p.description,
                            p.author, p.homepage, p.current_version, p.file_size,
                            p.uploaded_at, {$premium_select}
                            COALESCE(d.cnt, 0) AS total_downloads
                     FROM {$plugins_table} p
                     LEFT JOIN (
                         SELECT plugin_id, COUNT(*) AS cnt
                         FROM {$downloads_table}
                         GROUP BY plugin_id
                     ) d ON d.plugin_id = p.id
                     {$where_sql}
                     ORDER BY p.uploaded_at DESC
                     LIMIT %d OFFSET %d";

        $list_args = array_merge($where_args, array($per_page, $offset));
        $rows      = $wpdb->get_results($wpdb->prepare($list_sql, $list_args));

        $items = array();
        if (is_array($rows)) {
            // Cargar group_ids en bulk para evitar N+1.
            $plugin_ids = array_map(static function ($r) { return (int) $r->id; }, $rows);
            $group_ids_by_plugin = $this->load_group_ids_for_plugins($plugin_ids, $items_table);

            foreach ($rows as $row) {
                $items[] = $this->serialize_plugin($row, $group_ids_by_plugin, $premium_active);
            }
        }

        return rest_ensure_response(
            array(
                'items'                    => $items,
                'total'                    => $total,
                'page'                     => $page,
                'per_page'                 => $per_page,
                'license_extension_active' => $premium_active,
            )
        );
    }

    /**
     * @param int[]  $plugin_ids
     * @param string $items_table
     * @return array<int, int[]>
     */
    private function load_group_ids_for_plugins($plugin_ids, $items_table) {
        global $wpdb;

        $by_plugin = array();
        if (empty($plugin_ids)) {
            return $by_plugin;
        }
        $placeholders = implode(',', array_fill(0, count($plugin_ids), '%d'));
        $rows         = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT plugin_id, group_id FROM {$items_table} WHERE plugin_id IN ({$placeholders})",
                $plugin_ids
            )
        );
        if (!is_array($rows)) {
            return $by_plugin;
        }
        foreach ($rows as $row) {
            $pid = (int) $row->plugin_id;
            if (!isset($by_plugin[$pid])) {
                $by_plugin[$pid] = array();
            }
            $by_plugin[$pid][] = (int) $row->group_id;
        }
        return $by_plugin;
    }

    private function serialize_plugin($row, $group_ids_by_plugin, $premium_active) {
        $pid       = (int) $row->id;
        $group_ids = isset($group_ids_by_plugin[$pid]) ? $group_ids_by_plugin[$pid] : array();

        return array(
            'id'              => $pid,
            'slug'            => (string) $row->slug,
            'slug_override'   => $row->slug_override ? (string) $row->slug_override : null,
            'effective_slug'  => !empty($row->slug_override) ? (string) $row->slug_override : (string) $row->slug,
            'name'            => (string) $row->name,
            'description'     => $row->description ? (string) $row->description : null,
            'author'          => $row->author ? (string) $row->author : null,
            'homepage'        => $row->homepage ? (string) $row->homepage : null,
            'current_version' => (string) $row->current_version,
            'file_size'       => (int) $row->file_size,
            'uploaded_at'     => (string) $row->uploaded_at,
            'is_premium'      => $premium_active ? ((int) $row->is_premium === 1) : false,
            'group_ids'       => $group_ids,
            'total_downloads' => (int) $row->total_downloads,
        );
    }

    /**
     * Bool: ¿está activa la extensión de licencias?
     *
     * Nos sirve para (a) gatear los endpoints de premium / reinject y
     * (b) decidir si la columna `is_premium` existe (la migración la
     * crea solo cuando la extensión está activa).
     */
    private function license_extension_active() {
        return class_exists('Imagina_License_SDK_Injector');
    }

    /**
     * GET /admin/v1/plugin-groups
     *
     * Modo dual igual que /plugins: `?lite=1` devuelve `[{id, name}]`
     * (consumido por los pickers de drawers); sin `lite`, lista
     * completa con descripción, plugin_count y linked_api_keys.
     */
    public function list_plugin_groups(WP_REST_Request $request) {
        if ($request->get_param('lite')) {
            return $this->list_plugin_groups_lite_response();
        }
        return $this->list_plugin_groups_full_response();
    }

    private function list_plugin_groups_lite_response() {
        global $wpdb;

        $groups_table = $wpdb->prefix . 'imagina_updater_plugin_groups';

        $rows = $wpdb->get_results(
            "SELECT id, name FROM {$groups_table} ORDER BY name ASC",
            ARRAY_A
        );
        if (!is_array($rows)) {
            $rows = array();
        }
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
        }
        unset($row);

        return rest_ensure_response($rows);
    }

    private function list_plugin_groups_full_response() {
        global $wpdb;

        $groups_table = $wpdb->prefix . 'imagina_updater_plugin_groups';
        $items_table  = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        $rows = $wpdb->get_results(
            "SELECT g.id, g.name, g.description, g.created_at,
                    COALESCE(c.cnt, 0) AS plugin_count
             FROM {$groups_table} g
             LEFT JOIN (
                 SELECT group_id, COUNT(*) AS cnt
                 FROM {$items_table}
                 GROUP BY group_id
             ) c ON c.group_id = g.id
             ORDER BY g.name ASC"
        );
        if (!is_array($rows)) {
            $rows = array();
        }

        // Bulk: para cada grupo, contar API keys que lo referencian.
        // Las allowed_groups vive como JSON en api_keys; usamos
        // JSON_CONTAINS si MySQL ≥ 5.7 / MariaDB ≥ 10.2.4 (común);
        // si la función no existe, se cae a 0 silenciosamente sin
        // romper la pantalla.
        $linked_by_group = $this->load_linked_api_keys_count_by_group(
            array_map(static function ($r) { return (int) $r->id; }, $rows)
        );

        $items = array();
        foreach ($rows as $row) {
            $gid     = (int) $row->id;
            $items[] = array(
                'id'                     => $gid,
                'name'                   => (string) $row->name,
                'description'            => $row->description ? (string) $row->description : null,
                'plugin_count'           => (int) $row->plugin_count,
                'linked_api_keys_count'  => isset($linked_by_group[$gid]) ? $linked_by_group[$gid] : 0,
                'created_at'             => (string) $row->created_at,
            );
        }
        return rest_ensure_response(array('items' => $items));
    }

    /**
     * @param int[] $group_ids
     * @return array<int, int>
     */
    private function load_linked_api_keys_count_by_group(array $group_ids) {
        global $wpdb;

        $by_group = array();
        if (empty($group_ids)) {
            return $by_group;
        }

        $api_keys_table = $wpdb->prefix . 'imagina_updater_api_keys';

        // JSON_CONTAINS tira excepción si no existe; suprimimos vía @
        // y caemos a array vacío. La pantalla seguirá funcional sin
        // ese contador.
        $previous = $wpdb->suppress_errors(true);
        foreach ($group_ids as $gid) {
            $count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$api_keys_table}
                     WHERE access_type = 'groups'
                       AND allowed_groups IS NOT NULL
                       AND JSON_CONTAINS(allowed_groups, %s)",
                    (string) $gid
                )
            );
            if (null !== $count) {
                $by_group[$gid] = (int) $count;
            }
        }
        $wpdb->suppress_errors($previous);
        return $by_group;
    }

    private function load_plugin_group_serialized($id) {
        global $wpdb;

        $groups_table = $wpdb->prefix . 'imagina_updater_plugin_groups';
        $items_table  = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT g.id, g.name, g.description, g.created_at,
                        COALESCE(c.cnt, 0) AS plugin_count
                 FROM {$groups_table} g
                 LEFT JOIN (
                     SELECT group_id, COUNT(*) AS cnt
                     FROM {$items_table}
                     WHERE group_id = %d
                     GROUP BY group_id
                 ) c ON c.group_id = g.id
                 WHERE g.id = %d",
                $id,
                $id
            )
        );
        if (!$row) {
            return null;
        }

        $linked = $this->load_linked_api_keys_count_by_group(array((int) $row->id));
        $plugin_ids = array();
        if (class_exists('Imagina_Updater_Server_Plugin_Groups')) {
            $raw = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_ids((int) $row->id);
            if (is_array($raw)) {
                $plugin_ids = array_values(array_map('intval', $raw));
            }
        }

        return array(
            'id'                    => (int) $row->id,
            'name'                  => (string) $row->name,
            'description'           => $row->description ? (string) $row->description : null,
            'plugin_count'          => (int) $row->plugin_count,
            'plugin_ids'            => $plugin_ids,
            'linked_api_keys_count' => isset($linked[(int) $row->id]) ? $linked[(int) $row->id] : 0,
            'created_at'            => (string) $row->created_at,
        );
    }

    /**
     * Extrae payload común a create + update.
     */
    private function parse_plugin_group_payload(WP_REST_Request $request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = $request->get_params();
        }

        $name        = isset($body['name']) ? sanitize_text_field((string) $body['name']) : '';
        $description = isset($body['description']) ? sanitize_textarea_field((string) $body['description']) : '';
        $plugin_ids  = array();
        if (isset($body['plugin_ids']) && is_array($body['plugin_ids'])) {
            $plugin_ids = array_values(array_unique(array_map('intval', $body['plugin_ids'])));
        }

        if ('' === $name) {
            return new WP_Error(
                'iaud_invalid_payload',
                __('El nombre del grupo es obligatorio.', 'imagina-updater-server'),
                array('status' => 400)
            );
        }

        return array(
            'name'        => $name,
            'description' => $description,
            'plugin_ids'  => $plugin_ids,
        );
    }

    /**
     * POST /admin/v1/plugin-groups
     */
    public function create_plugin_group(WP_REST_Request $request) {
        if (!class_exists('Imagina_Updater_Server_Plugin_Groups')) {
            return new WP_Error('iaud_unavailable', __('Plugin groups manager no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        $payload = $this->parse_plugin_group_payload($request);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $result = Imagina_Updater_Server_Plugin_Groups::create_group(
            $payload['name'],
            $payload['description'],
            $payload['plugin_ids']
        );
        if (is_wp_error($result)) {
            $result->add_data(array('status' => 400));
            return $result;
        }
        $id = is_array($result) && isset($result['id']) ? (int) $result['id'] : (int) $result;
        $serialized = $this->load_plugin_group_serialized($id);
        if (null === $serialized) {
            return new WP_Error('iaud_create_failed', __('Grupo creado pero no se pudo recuperar.', 'imagina-updater-server'), array('status' => 500));
        }
        return rest_ensure_response(array('item' => $serialized));
    }

    /**
     * PUT /admin/v1/plugin-groups/{id}
     */
    public function update_plugin_group(WP_REST_Request $request) {
        if (!class_exists('Imagina_Updater_Server_Plugin_Groups')) {
            return new WP_Error('iaud_unavailable', __('Plugin groups manager no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        $id      = (int) $request['id'];
        $payload = $this->parse_plugin_group_payload($request);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $result = Imagina_Updater_Server_Plugin_Groups::update_group(
            $id,
            $payload['name'],
            $payload['description'],
            $payload['plugin_ids']
        );
        if (is_wp_error($result)) {
            $result->add_data(array('status' => 400));
            return $result;
        }
        $serialized = $this->load_plugin_group_serialized($id);
        if (null === $serialized) {
            return new WP_Error('iaud_not_found', __('Grupo no encontrado.', 'imagina-updater-server'), array('status' => 404));
        }
        return rest_ensure_response(array('item' => $serialized));
    }

    /**
     * DELETE /admin/v1/plugin-groups/{id}
     *
     * Devuelve `linked_api_keys_count` cuando >0 para que la SPA
     * pueda mostrar un mensaje útil. La eliminación procede igualmente
     * — las API keys referenciando el grupo no rompen, solo dejan de
     * conceder acceso a sus plugins.
     */
    public function delete_plugin_group(WP_REST_Request $request) {
        if (!class_exists('Imagina_Updater_Server_Plugin_Groups')) {
            return new WP_Error('iaud_unavailable', __('Plugin groups manager no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        $id     = (int) $request['id'];
        $linked = $this->load_linked_api_keys_count_by_group(array($id));
        $ok     = Imagina_Updater_Server_Plugin_Groups::delete_group($id);
        if (!$ok) {
            return new WP_Error('iaud_delete_failed', __('No se pudo eliminar el grupo.', 'imagina-updater-server'), array('status' => 500));
        }
        return rest_ensure_response(
            array(
                'deleted'                   => true,
                'id'                        => $id,
                'orphaned_api_keys_count'   => isset($linked[$id]) ? $linked[$id] : 0,
            )
        );
    }

    // =========================================================
    // Fase 5.3 — Plugins
    // =========================================================

    /**
     * Recarga un plugin completo a partir del ID y lo serializa.
     */
    private function load_plugin_serialized($id) {
        global $wpdb;

        $plugins_table   = $wpdb->prefix . 'imagina_updater_plugins';
        $downloads_table = $wpdb->prefix . 'imagina_updater_downloads';
        $items_table     = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        $premium_active = $this->license_extension_active();
        $premium_select = $premium_active ? 'p.is_premium AS is_premium,' : '0 AS is_premium,';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT p.id, p.slug, p.slug_override, p.name, p.description,
                        p.author, p.homepage, p.current_version, p.file_size,
                        p.uploaded_at, {$premium_select}
                        COALESCE(d.cnt, 0) AS total_downloads
                 FROM {$plugins_table} p
                 LEFT JOIN (
                     SELECT plugin_id, COUNT(*) AS cnt
                     FROM {$downloads_table}
                     GROUP BY plugin_id
                 ) d ON d.plugin_id = p.id
                 WHERE p.id = %d",
                $id
            )
        );
        if (!$row) {
            return null;
        }

        $group_ids_by_plugin = $this->load_group_ids_for_plugins(array((int) $row->id), $items_table);
        return $this->serialize_plugin($row, $group_ids_by_plugin, $premium_active);
    }

    /**
     * Sustituye las membresías de grupo de un plugin por el set
     * indicado, en una sola operación.
     */
    private function set_plugin_groups($plugin_id, array $group_ids) {
        global $wpdb;

        $items_table = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        $wpdb->delete($items_table, array('plugin_id' => $plugin_id), array('%d'));

        foreach (array_unique(array_map('intval', $group_ids)) as $gid) {
            if ($gid <= 0) continue;
            $wpdb->insert(
                $items_table,
                array('plugin_id' => $plugin_id, 'group_id' => $gid),
                array('%d', '%d')
            );
        }
    }

    /**
     * POST /admin/v1/plugins/upload (multipart)
     *
     * Body multipart con un campo `plugin_file` (ZIP) y opcionalmente:
     *   - `changelog` (texto)
     *   - `is_premium` ('1' / '0')
     *   - `group_ids[]` (IDs de grupos)
     *   - `description` (texto)
     *
     * Estrategia premium: subimos el plugin con `is_premium=0` (las
     * acciones de hook legacy NO inyectan), y si el body pidió
     * is_premium=1 hacemos UPDATE + reinject manualmente. Esto
     * desacopla la SPA del lectura `$_POST` que hace la legacy
     * y la mantenemos compatible (CLAUDE.md §4 regla 2: hooks no
     * eliminados, siguen funcionando para el form PHP viejo).
     */
    public function upload_plugin(WP_REST_Request $request) {
        if (!class_exists('Imagina_Updater_Server_Plugin_Manager')) {
            return new WP_Error('iaud_unavailable', __('Plugin manager no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        $files = $request->get_file_params();
        if (empty($files) || empty($files['plugin_file']) || !is_array($files['plugin_file'])) {
            return new WP_Error('iaud_no_file', __('Falta el archivo plugin_file.', 'imagina-updater-server'), array('status' => 400));
        }

        $file_param = $files['plugin_file'];
        if ((int) (isset($file_param['error']) ? $file_param['error'] : UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new WP_Error('iaud_upload_error', __('Error de upload.', 'imagina-updater-server'), array('status' => 400));
        }

        $body         = $request->get_params();
        $changelog    = isset($body['changelog']) ? sanitize_textarea_field((string) $body['changelog']) : null;
        $description  = isset($body['description']) ? sanitize_textarea_field((string) $body['description']) : null;
        $want_premium = isset($body['is_premium']) && '1' === (string) $body['is_premium'];
        $group_ids    = array();
        if (isset($body['group_ids'])) {
            $raw = is_array($body['group_ids']) ? $body['group_ids'] : explode(',', (string) $body['group_ids']);
            $group_ids = array_values(array_unique(array_map('intval', $raw)));
        }

        $result = Imagina_Updater_Server_Plugin_Manager::upload_plugin($file_param, $changelog, false);
        if (is_wp_error($result)) {
            $result->add_data(array('status' => 400));
            return $result;
        }

        $plugin_id = (int) $result['id'];

        // Description y groups si se especificaron.
        global $wpdb;
        $plugins_table = $wpdb->prefix . 'imagina_updater_plugins';
        if (null !== $description) {
            $wpdb->update(
                $plugins_table,
                array('description' => $description),
                array('id' => $plugin_id),
                array('%s'),
                array('%d')
            );
        }
        if (!empty($group_ids)) {
            $this->set_plugin_groups($plugin_id, $group_ids);
        }

        // Premium opcional + inyección.
        if ($want_premium) {
            if (!$this->license_extension_active()) {
                return new WP_Error(
                    'iaud_premium_unavailable',
                    __('La extensión de licencias no está activa; no se puede marcar como premium.', 'imagina-updater-server'),
                    array('status' => 400)
                );
            }
            $wpdb->update(
                $plugins_table,
                array('is_premium' => 1),
                array('id' => $plugin_id),
                array('%d'),
                array('%d')
            );
            $file_path = isset($result['file_path']) ? (string) $result['file_path'] : '';
            if ('' !== $file_path && file_exists($file_path)) {
                Imagina_License_SDK_Injector::inject_sdk_if_needed($file_path, true);
            }
        }

        $serialized = $this->load_plugin_serialized($plugin_id);
        if (null === $serialized) {
            return new WP_Error('iaud_post_upload_load_failed', __('No se pudo recuperar el plugin tras subirlo.', 'imagina-updater-server'), array('status' => 500));
        }
        return rest_ensure_response(array('item' => $serialized));
    }

    /**
     * PUT /admin/v1/plugins/{id}
     *
     * Edita campos editables: slug_override, description, group_ids.
     * El `is_premium` se cambia con su endpoint dedicado.
     */
    public function update_plugin(WP_REST_Request $request) {
        global $wpdb;

        $id = (int) $request['id'];
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = $request->get_params();
        }

        $plugins_table = $wpdb->prefix . 'imagina_updater_plugins';

        $update = array();
        $format = array();

        if (array_key_exists('slug_override', $body)) {
            $raw = is_string($body['slug_override']) ? sanitize_title($body['slug_override']) : '';
            $update['slug_override'] = '' === $raw ? null : $raw;
            $format[] = '%s';
        }
        if (array_key_exists('description', $body)) {
            $update['description'] = isset($body['description']) ? sanitize_textarea_field((string) $body['description']) : null;
            $format[] = '%s';
        }

        if (!empty($update)) {
            $ok = $wpdb->update($plugins_table, $update, array('id' => $id), $format, array('%d'));
            if (false === $ok) {
                return new WP_Error('iaud_db_error', __('No se pudo actualizar el plugin.', 'imagina-updater-server'), array('status' => 500));
            }
        }

        if (array_key_exists('group_ids', $body) && is_array($body['group_ids'])) {
            $this->set_plugin_groups($id, $body['group_ids']);
        }

        $serialized = $this->load_plugin_serialized($id);
        if (null === $serialized) {
            return new WP_Error('iaud_not_found', __('Plugin no encontrado.', 'imagina-updater-server'), array('status' => 404));
        }
        return rest_ensure_response(array('item' => $serialized));
    }

    /**
     * DELETE /admin/v1/plugins/{id}
     */
    public function delete_plugin(WP_REST_Request $request) {
        if (!class_exists('Imagina_Updater_Server_Plugin_Manager')) {
            return new WP_Error('iaud_unavailable', __('Plugin manager no disponible.', 'imagina-updater-server'), array('status' => 500));
        }
        $id = (int) $request['id'];
        $ok = Imagina_Updater_Server_Plugin_Manager::delete_plugin($id);
        if (!$ok) {
            return new WP_Error('iaud_delete_failed', __('No se pudo eliminar el plugin.', 'imagina-updater-server'), array('status' => 500));
        }
        return rest_ensure_response(array('deleted' => true, 'id' => $id));
    }

    /**
     * POST /admin/v1/plugins/{id}/toggle-premium
     *
     * Body opcional `{ is_premium: bool }`. Si no se envía, alterna.
     * Cuando enciende premium, intenta inyectar protección al ZIP.
     * Cuando apaga, NO desinyecta el código existente (eso requiere
     * un flujo separado y queda fuera del alcance de 5.3).
     */
    public function toggle_plugin_premium(WP_REST_Request $request) {
        global $wpdb;

        if (!$this->license_extension_active()) {
            return new WP_Error(
                'iaud_premium_unavailable',
                __('La extensión de licencias no está activa.', 'imagina-updater-server'),
                array('status' => 400)
            );
        }

        $id = (int) $request['id'];
        $plugins_table = $wpdb->prefix . 'imagina_updater_plugins';

        $row = $wpdb->get_row($wpdb->prepare("SELECT id, is_premium, file_path FROM {$plugins_table} WHERE id = %d", $id));
        if (!$row) {
            return new WP_Error('iaud_not_found', __('Plugin no encontrado.', 'imagina-updater-server'), array('status' => 404));
        }

        $body = $request->get_json_params();
        $next = isset($body['is_premium']) ? (bool) $body['is_premium'] : !((int) $row->is_premium === 1);

        $wpdb->update(
            $plugins_table,
            array('is_premium' => $next ? 1 : 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        if ($next && !empty($row->file_path) && file_exists($row->file_path)) {
            Imagina_License_SDK_Injector::inject_sdk_if_needed($row->file_path, true);
        }

        $serialized = $this->load_plugin_serialized($id);
        if (null === $serialized) {
            return new WP_Error('iaud_not_found', __('Plugin no encontrado.', 'imagina-updater-server'), array('status' => 404));
        }
        return rest_ensure_response(array('item' => $serialized));
    }

    /**
     * POST /admin/v1/plugins/{id}/reinject-protection
     */
    public function reinject_plugin_protection(WP_REST_Request $request) {
        global $wpdb;

        if (!$this->license_extension_active()) {
            return new WP_Error('iaud_premium_unavailable', __('La extensión de licencias no está activa.', 'imagina-updater-server'), array('status' => 400));
        }
        $id = (int) $request['id'];
        $plugins_table = $wpdb->prefix . 'imagina_updater_plugins';

        $row = $wpdb->get_row($wpdb->prepare("SELECT id, is_premium, file_path FROM {$plugins_table} WHERE id = %d", $id));
        if (!$row) {
            return new WP_Error('iaud_not_found', __('Plugin no encontrado.', 'imagina-updater-server'), array('status' => 404));
        }
        if ((int) $row->is_premium !== 1) {
            return new WP_Error('iaud_not_premium', __('Este plugin no está marcado como premium.', 'imagina-updater-server'), array('status' => 400));
        }
        if (empty($row->file_path) || !file_exists($row->file_path)) {
            return new WP_Error('iaud_zip_missing', __('El ZIP del plugin no se encuentra en disco.', 'imagina-updater-server'), array('status' => 500));
        }

        $result = Imagina_License_SDK_Injector::inject_sdk_if_needed($row->file_path, true);
        if (!is_array($result) || empty($result['success'])) {
            $message = is_array($result) && !empty($result['message'])
                ? (string) $result['message']
                : __('La inyección de protección falló.', 'imagina-updater-server');
            return new WP_Error('iaud_inject_failed', $message, array('status' => 500));
        }

        $serialized = $this->load_plugin_serialized($id);
        if (null === $serialized) {
            return new WP_Error('iaud_not_found', __('Plugin no encontrado.', 'imagina-updater-server'), array('status' => 404));
        }
        return rest_ensure_response(array('item' => $serialized, 'reinjected' => true, 'message' => $result['message']));
    }

    /**
     * GET /admin/v1/plugins/{id}/versions
     */
    public function list_plugin_versions(WP_REST_Request $request) {
        global $wpdb;

        $id = (int) $request['id'];
        $versions_table = $wpdb->prefix . 'imagina_updater_versions';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, version, file_size, changelog, uploaded_at
                 FROM {$versions_table}
                 WHERE plugin_id = %d
                 ORDER BY uploaded_at DESC",
                $id
            ),
            ARRAY_A
        );
        if (!is_array($rows)) {
            $rows = array();
        }
        foreach ($rows as &$row) {
            $row['id']        = (int) $row['id'];
            $row['file_size'] = (int) $row['file_size'];
        }
        unset($row);

        return rest_ensure_response($rows);
    }

    /**
     * GET /admin/v1/plugins/{id}/download
     *
     * Streamea el ZIP actual del plugin con `Content-Disposition:
     * attachment` para que el navegador dispare save-as. Mismo
     * patrón de 8 KB-chunks que la descarga de logs (Fase 5.6) y
     * que el endpoint público de Fase 3.3.
     *
     * Auth: cookie admin + nonce wp_rest. No expone tokens ni
     * habilita acceso anónimo (eso es para el endpoint público
     * `imagina-updater/v1/download/{slug}` con API key).
     */
    public function download_plugin_zip(WP_REST_Request $request) {
        global $wpdb;

        $id = (int) $request['id'];
        $plugins_table = $wpdb->prefix . 'imagina_updater_plugins';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT slug, slug_override, current_version, file_path
                 FROM {$plugins_table} WHERE id = %d",
                $id
            )
        );
        if (!$row) {
            return new WP_Error('iaud_not_found', __('Plugin no encontrado.', 'imagina-updater-server'), array('status' => 404));
        }
        if (empty($row->file_path) || !file_exists($row->file_path) || !is_readable($row->file_path)) {
            return new WP_Error('iaud_zip_missing', __('El archivo ZIP del plugin no se encuentra en disco.', 'imagina-updater-server'), array('status' => 500));
        }

        $slug = !empty($row->slug_override) ? (string) $row->slug_override : (string) $row->slug;
        $download_name = sanitize_file_name(
            $slug . '-' . $row->current_version . '.zip'
        );

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . (string) filesize($row->file_path));

        $handle = fopen($row->file_path, 'rb');
        if (false === $handle) {
            return new WP_Error('iaud_read_failed', __('No se pudo abrir el ZIP del plugin.', 'imagina-updater-server'), array('status' => 500));
        }
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if (false === $chunk) break;
            echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            flush();
        }
        fclose($handle);
        exit;
    }

    // =========================================================
    // Fase 5.5 — Activations
    // =========================================================

    /**
     * Mascara el activation_token (formato `iat_` + 60 hex). Mismo
     * patrón que las API keys: 4 primeros + 4 últimos.
     */
    private function mask_activation_token($token) {
        if (!is_string($token) || strlen($token) < 8) {
            return '';
        }
        return substr($token, 0, 4) . '••••' . substr($token, -4);
    }

    private function serialize_activation($row) {
        return array(
            'id'                  => (int) $row->id,
            'api_key_id'          => (int) $row->api_key_id,
            'site_name'           => $row->site_name ? (string) $row->site_name : null,
            'site_domain'         => (string) $row->site_domain,
            'token_masked'        => $this->mask_activation_token($row->activation_token),
            'is_active'           => (int) $row->is_active === 1,
            'activated_at'        => (string) $row->activated_at,
            'last_verified'       => $row->last_verified ? (string) $row->last_verified : null,
            'deactivated_at'      => $row->deactivated_at ? (string) $row->deactivated_at : null,
        );
    }

    /**
     * GET /admin/v1/activations
     *
     * Listado paginado con filtros por API key, estado y búsqueda
     * libre por dominio. LEFT JOIN sobre api_keys para mostrar el
     * site_name asociado sin un round-trip extra.
     */
    public function list_activations(WP_REST_Request $request) {
        global $wpdb;

        $page       = max(1, (int) $request->get_param('page'));
        $per_page   = max(1, min(100, (int) $request->get_param('per_page')));
        $status     = (string) $request->get_param('status');
        $api_key_id = (int) $request->get_param('api_key_id');
        $search     = trim((string) $request->get_param('search'));
        $offset     = ($page - 1) * $per_page;

        $activations_table = $wpdb->prefix . 'imagina_updater_activations';
        $api_keys_table    = $wpdb->prefix . 'imagina_updater_api_keys';

        $where      = array();
        $where_args = array();
        if ('active' === $status) {
            $where[] = 'a.is_active = 1';
        } elseif ('inactive' === $status) {
            $where[] = 'a.is_active = 0';
        }
        if ($api_key_id > 0) {
            $where[]      = 'a.api_key_id = %d';
            $where_args[] = $api_key_id;
        }
        if ('' !== $search) {
            $like         = '%' . $wpdb->esc_like($search) . '%';
            $where[]      = 'a.site_domain LIKE %s';
            $where_args[] = $like;
        }
        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $count_sql = "SELECT COUNT(*) FROM {$activations_table} a {$where_sql}";
        $total     = (int) ($where_args
            ? $wpdb->get_var($wpdb->prepare($count_sql, $where_args))
            : $wpdb->get_var($count_sql));

        $list_sql = "SELECT a.id, a.api_key_id, a.site_domain, a.activation_token,
                            a.is_active, a.activated_at, a.last_verified, a.deactivated_at,
                            k.site_name
                     FROM {$activations_table} a
                     LEFT JOIN {$api_keys_table} k ON k.id = a.api_key_id
                     {$where_sql}
                     ORDER BY a.activated_at DESC
                     LIMIT %d OFFSET %d";

        $list_args = array_merge($where_args, array($per_page, $offset));
        $rows      = $wpdb->get_results($wpdb->prepare($list_sql, $list_args));

        $items = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $items[] = $this->serialize_activation($row);
            }
        }

        return rest_ensure_response(
            array(
                'items'    => $items,
                'total'    => $total,
                'page'     => $page,
                'per_page' => $per_page,
            )
        );
    }

    /**
     * POST /admin/v1/activations/{id}/deactivate
     */
    public function deactivate_activation(WP_REST_Request $request) {
        global $wpdb;

        if (!class_exists('Imagina_Updater_Server_Activations')) {
            return new WP_Error('iaud_unavailable', __('Activations manager no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        $id = (int) $request['id'];
        $ok = Imagina_Updater_Server_Activations::deactivate_site($id);
        if (!$ok) {
            return new WP_Error('iaud_deactivate_failed', __('No se pudo desactivar la activación.', 'imagina-updater-server'), array('status' => 500));
        }

        $activations_table = $wpdb->prefix . 'imagina_updater_activations';
        $api_keys_table    = $wpdb->prefix . 'imagina_updater_api_keys';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT a.id, a.api_key_id, a.site_domain, a.activation_token,
                        a.is_active, a.activated_at, a.last_verified, a.deactivated_at,
                        k.site_name
                 FROM {$activations_table} a
                 LEFT JOIN {$api_keys_table} k ON k.id = a.api_key_id
                 WHERE a.id = %d",
                $id
            )
        );
        if (!$row) {
            return new WP_Error('iaud_not_found', __('Activación no encontrada.', 'imagina-updater-server'), array('status' => 404));
        }

        return rest_ensure_response(array('item' => $this->serialize_activation($row)));
    }

    // =========================================================
    // Fase 5.6 — Logs
    // =========================================================

    /**
     * Lee y parsea el archivo de log completo en memoria. El logger
     * rota el archivo cuando excede su umbral, así que el tamaño en
     * disco siempre está acotado y este parse es asumible.
     *
     * Formato esperado por línea (ver Logger::format_message):
     *   [YYYY-MM-DD HH:MM:SS] [LEVEL] message[ | Context: {json}]
     *
     * Líneas mal formadas se conservan como `level=UNKNOWN` para no
     * tirar entradas legítimas de versiones antiguas del logger.
     *
     * @return array<int, array{timestamp:string,level:string,message:string,context:?string}>
     */
    private function parse_log_file($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path)) {
            return array();
        }

        $contents = file_get_contents($file_path);
        if (false === $contents || '' === $contents) {
            return array();
        }

        $entries = array();
        $lines   = preg_split('/\r?\n/', $contents);
        foreach ($lines as $raw) {
            $line = trim($raw);
            if ('' === $line) continue;

            $entry = array(
                'timestamp' => '',
                'level'     => 'UNKNOWN',
                'message'   => $line,
                'context'   => null,
            );

            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[([A-Z]+)\] (.*)$/u', $line, $m)) {
                $entry['timestamp'] = $m[1];
                $entry['level']     = $m[2];
                $entry['message']   = $m[3];

                // Si el mensaje incluye `| Context: {...}` lo separamos.
                $ctx_pos = strpos($entry['message'], ' | Context: ');
                if (false !== $ctx_pos) {
                    $entry['context'] = (string) substr($entry['message'], $ctx_pos + 12);
                    $entry['message'] = (string) substr($entry['message'], 0, $ctx_pos);
                }
            }
            $entries[] = $entry;
        }
        return $entries;
    }

    /**
     * GET /admin/v1/logs
     *
     * Lee el archivo completo, filtra por nivel y búsqueda libre, y
     * devuelve la página solicitada en orden cronológico inverso (más
     * reciente primero).
     */
    public function list_logs(WP_REST_Request $request) {
        if (!class_exists('Imagina_Updater_Server_Logger')) {
            return new WP_Error('iaud_unavailable', __('Logger no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        $logger    = Imagina_Updater_Server_Logger::get_instance();
        $log_file  = $logger->get_log_file();
        $entries   = $this->parse_log_file($log_file);

        $level_filter = strtoupper((string) $request->get_param('level'));
        $search       = trim((string) $request->get_param('search'));
        $page         = max(1, (int) $request->get_param('page'));
        $per_page     = max(10, min(500, (int) $request->get_param('per_page')));

        $valid_levels = array('DEBUG', 'INFO', 'WARNING', 'ERROR', 'UNKNOWN');
        if (!in_array($level_filter, $valid_levels, true)) {
            $level_filter = '';
        }

        if ('' !== $level_filter || '' !== $search) {
            $needle = $search !== '' ? function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search) : '';
            $entries = array_values(array_filter(
                $entries,
                static function ($e) use ($level_filter, $needle) {
                    if ('' !== $level_filter && $e['level'] !== $level_filter) {
                        return false;
                    }
                    if ('' === $needle) return true;
                    $hay = $e['message'] . ' ' . (string) $e['context'];
                    $hay = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
                    return false !== strpos($hay, $needle);
                }
            ));
        }

        // Más reciente primero.
        $entries = array_reverse($entries);

        $total = count($entries);
        $offset = ($page - 1) * $per_page;
        $items  = array_slice($entries, $offset, $per_page);

        $log_enabled = $logger->is_enabled();

        return rest_ensure_response(
            array(
                'items'        => $items,
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $per_page,
                'log_enabled'  => $log_enabled,
                'log_file'     => is_readable($log_file) ? basename($log_file) : null,
            )
        );
    }

    /**
     * DELETE /admin/v1/logs
     */
    public function clear_logs(WP_REST_Request $request) {
        unset($request);

        if (!class_exists('Imagina_Updater_Server_Logger')) {
            return new WP_Error('iaud_unavailable', __('Logger no disponible.', 'imagina-updater-server'), array('status' => 500));
        }
        $logger = Imagina_Updater_Server_Logger::get_instance();
        $logger->clear_logs();
        return rest_ensure_response(array('cleared' => true));
    }

    /**
     * GET /admin/v1/logs/download
     *
     * Streamea el archivo crudo. Mismo patrón que la descarga de ZIP
     * (Fase 3.3) — chunks de 8 KB, memoria constante.
     */
    public function download_logs(WP_REST_Request $request) {
        unset($request);

        if (!class_exists('Imagina_Updater_Server_Logger')) {
            return new WP_Error('iaud_unavailable', __('Logger no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        $logger   = Imagina_Updater_Server_Logger::get_instance();
        $log_file = $logger->get_log_file();
        if (!file_exists($log_file)) {
            return new WP_Error('iaud_no_log', __('No hay archivo de log todavía.', 'imagina-updater-server'), array('status' => 404));
        }

        $download_name = 'imagina-updater-' . gmdate('Ymd-His') . '.log';

        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $download_name . '"');
        header('Content-Length: ' . (string) filesize($log_file));

        $handle = fopen($log_file, 'rb');
        if (false === $handle) {
            return new WP_Error('iaud_read_failed', __('No se pudo abrir el archivo de log.', 'imagina-updater-server'), array('status' => 500));
        }
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if (false === $chunk) break;
            echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            flush();
        }
        fclose($handle);
        exit; // Necesario: no devolvemos JSON.
    }

    // =========================================================
    // Fase 5.7 — Settings + Maintenance
    // =========================================================

    /**
     * Lee `imagina_updater_server_config` y devuelve siempre la forma
     * canónica (con los campos esperados aunque la opción no exista).
     */
    private function read_settings_config() {
        $raw = get_option('imagina_updater_server_config', array());
        if (!is_array($raw)) {
            $raw = array();
        }
        return array(
            'enable_logging' => array_key_exists('enable_logging', $raw)
                ? (bool) $raw['enable_logging']
                : true,
            'log_level'      => isset($raw['log_level']) && in_array($raw['log_level'], array('DEBUG', 'INFO', 'WARNING', 'ERROR'), true)
                ? (string) $raw['log_level']
                : 'INFO',
        );
    }

    /**
     * Info de plugin/PHP/WP útil para la pestaña General de la
     * pantalla de Configuración.
     */
    private function read_system_info() {
        global $wpdb;

        $db_version = get_option('imagina_updater_server_db_version', '0.0.0');

        return array(
            'plugin_version'           => defined('IMAGINA_UPDATER_SERVER_VERSION') ? IMAGINA_UPDATER_SERVER_VERSION : null,
            'db_version'               => (string) $db_version,
            'php_version'              => PHP_VERSION,
            'wp_version'               => get_bloginfo('version'),
            'mysql_version'            => $wpdb->db_version(),
            'license_extension_active' => $this->license_extension_active(),
            'object_cache_supported'   => function_exists('wp_cache_supports') && wp_cache_supports('flush_group'),
        );
    }

    /**
     * GET /admin/v1/settings
     */
    public function get_settings(WP_REST_Request $request) {
        unset($request);

        return rest_ensure_response(
            array(
                'settings' => $this->read_settings_config(),
                'system'   => $this->read_system_info(),
            )
        );
    }

    /**
     * PUT /admin/v1/settings
     */
    public function update_settings(WP_REST_Request $request) {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = $request->get_params();
        }

        $next = $this->read_settings_config();

        if (array_key_exists('enable_logging', $body)) {
            $next['enable_logging'] = (bool) $body['enable_logging'];
        }
        if (array_key_exists('log_level', $body)) {
            $level = strtoupper((string) $body['log_level']);
            if (!in_array($level, array('DEBUG', 'INFO', 'WARNING', 'ERROR'), true)) {
                return new WP_Error(
                    'iaud_invalid_log_level',
                    __('Nivel de log inválido. Debe ser DEBUG, INFO, WARNING o ERROR.', 'imagina-updater-server'),
                    array('status' => 400)
                );
            }
            $next['log_level'] = $level;
        }

        update_option('imagina_updater_server_config', $next);

        return rest_ensure_response(
            array(
                'settings' => $next,
                'system'   => $this->read_system_info(),
            )
        );
    }

    /**
     * POST /admin/v1/maintenance/run-migrations
     *
     * Ejecuta `Database::create_tables()` (idempotente, usa dbDelta) y
     * `Database::run_migrations()` (también idempotente, chequea
     * `SHOW COLUMNS` antes de tocar).
     */
    public function run_migrations(WP_REST_Request $request) {
        unset($request);

        if (!class_exists('Imagina_Updater_Server_Database')) {
            return new WP_Error('iaud_unavailable', __('Database manager no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        Imagina_Updater_Server_Database::create_tables();
        Imagina_Updater_Server_Database::run_migrations();

        $db_version = get_option('imagina_updater_server_db_version', '0.0.0');

        return rest_ensure_response(
            array(
                'success'    => true,
                'db_version' => (string) $db_version,
            )
        );
    }

    /**
     * POST /admin/v1/maintenance/clear-rate-limits
     *
     * Wrapper sobre `Imagina_Updater_Server_REST_API::clear_rate_limits()`
     * (Fase 3.4). El método ya valida `manage_options` + throttle de
     * 60 s, así que respeta su propio contrato; aquí solo serializamos
     * el resultado.
     */
    public function clear_rate_limits_endpoint(WP_REST_Request $request) {
        unset($request);

        if (!class_exists('Imagina_Updater_Server_REST_API') ||
            !method_exists('Imagina_Updater_Server_REST_API', 'clear_rate_limits')) {
            return new WP_Error('iaud_unavailable', __('REST API no disponible.', 'imagina-updater-server'), array('status' => 500));
        }

        $ok = Imagina_Updater_Server_REST_API::clear_rate_limits();

        return rest_ensure_response(
            array(
                'success'  => (bool) $ok,
                'message'  => $ok
                    ? __('Rate limits limpiados.', 'imagina-updater-server')
                    : __('Operación rechazada por throttle (60 s) o por permisos.', 'imagina-updater-server'),
            )
        );
    }
}
