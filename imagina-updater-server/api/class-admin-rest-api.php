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
                'callback'            => array($this, 'list_plugins_lite'),
                'permission_callback' => array($this, 'check_admin_permissions'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/plugin-groups',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'list_plugin_groups_lite'),
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
     * Listado paginado con filtros por estado y búsqueda libre. La
     * columna `activations_used` se calcula con LEFT JOIN sobre
     * activations (is_active=1) para evitar N+1.
     */
    public function list_api_keys(WP_REST_Request $request) {
        global $wpdb;

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
     * GET /admin/v1/plugins (lite)
     *
     * Listado minimalista (id, slug, name) para los pickers del
     * drawer de creación/edición de API keys. La versión completa,
     * con subida de ZIPs, versiones y filtros, se construye en 5.3.
     */
    public function list_plugins_lite(WP_REST_Request $request) {
        global $wpdb;

        unset($request);

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

    /**
     * GET /admin/v1/plugin-groups (lite)
     */
    public function list_plugin_groups_lite(WP_REST_Request $request) {
        global $wpdb;

        unset($request);

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
}
