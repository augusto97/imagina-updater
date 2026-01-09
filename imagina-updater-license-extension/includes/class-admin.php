<?php
/**
 * Interfaz de administración para la extensión de licencias
 *
 * @package Imagina_License_Extension
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_License_Admin {

    /**
     * Inicializar hooks de administración
     */
    public static function init() {
        // Hook para manejar acciones de formularios
        add_action('admin_init', array(__CLASS__, 'handle_actions'), 15);

        // Hook para agregar checkbox en formulario de subida
        add_action('imagina_updater_after_upload_form', array(__CLASS__, 'add_premium_checkbox'));

        // Hook para procesar archivo antes de guardarlo en BD
        add_action('imagina_updater_after_move_plugin_file', array(__CLASS__, 'process_uploaded_file'), 10, 2);

        // Hook para inyectar protección después de subir plugin
        add_action('imagina_updater_after_upload_plugin', array(__CLASS__, 'inject_protection_after_upload'), 10, 2);

        // Hook para agregar columna en header de tabla
        add_action('imagina_updater_plugins_table_header', array(__CLASS__, 'add_premium_column_header'));

        // Hook para mostrar columna premium en cada fila de la tabla
        add_action('imagina_updater_plugins_table_row', array(__CLASS__, 'display_premium_column'), 10, 1);

        // Hook para estilos admin
        add_action('admin_head', array(__CLASS__, 'admin_styles'));

        // Agregar menú de administración de licencias
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'), 20);
    }

    /**
     * Agregar menú de administración de licencias
     */
    public static function add_admin_menu() {
        // Submenú bajo Imagina Updater Server
        add_submenu_page(
            'imagina-updater-server',
            __('License Keys', 'imagina-updater-license'),
            __('License Keys', 'imagina-updater-license'),
            'manage_options',
            'imagina-license-keys',
            array(__CLASS__, 'render_license_keys_page')
        );
    }

    /**
     * Estilos CSS para el admin
     */
    public static function admin_styles() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'imagina-updater') === false) {
            return;
        }
        ?>
        <style>
            .imagina-premium-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 500;
            }
            .imagina-premium-badge.is-premium {
                background: #fef0f0;
                color: #d63638;
                border: 1px solid #d63638;
            }
            .imagina-premium-badge.is-free {
                background: #f0f6fc;
                color: #2271b1;
                border: 1px solid #2271b1;
            }
            .imagina-protection-status {
                font-size: 10px;
                color: #666;
                margin-top: 2px;
            }
            .imagina-protection-status.has-protection {
                color: #00a32a;
            }
            .imagina-protection-status.needs-update {
                color: #dba617;
            }
            .imagina-protection-status.no-protection {
                color: #d63638;
            }
        </style>
        <?php
    }

    /**
     * Agregar columna "Premium" al header de la tabla
     */
    public static function add_premium_column_header() {
        ?>
        <th style="text-align: center; width: 120px;">
            <?php _e('Licencia', 'imagina-updater-license'); ?>
        </th>
        <?php
    }

    /**
     * Mostrar contenido de la columna Premium para cada plugin
     *
     * @param object $plugin Plugin object
     */
    public static function display_premium_column($plugin) {
        $is_premium = isset($plugin->is_premium) && $plugin->is_premium == 1;

        // Verificar estado de protección
        $protection_status = null;
        if ($is_premium && class_exists('Imagina_License_SDK_Injector')) {
            $plugin_slug = !empty($plugin->slug_override) ? $plugin->slug_override : $plugin->slug;
            $protection_status = Imagina_License_SDK_Injector::check_protection_status($plugin_slug);
        }

        $needs_injection = $is_premium && $protection_status && !$protection_status['has_protection'];
        $needs_update = $is_premium && $protection_status && $protection_status['has_protection'] && $protection_status['needs_update'];
        ?>
        <td style="text-align: center;">
            <form method="post" style="display:inline;">
                <?php wp_nonce_field('imagina_license_toggle_premium_' . $plugin->id); ?>
                <input type="hidden" name="imagina_license_plugin_id" value="<?php echo esc_attr($plugin->id); ?>">
                <input type="hidden" name="imagina_license_action" value="toggle_premium">
                <label style="cursor: pointer; display: block;">
                    <input type="checkbox" name="is_premium" value="1" <?php checked($is_premium, true); ?> onchange="this.form.submit();" style="display: none;">
                    <?php if ($is_premium): ?>
                        <span class="imagina-premium-badge is-premium">
                            <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px;"></span>
                            <?php _e('Premium', 'imagina-updater-license'); ?>
                        </span>
                    <?php else: ?>
                        <span class="imagina-premium-badge is-free">
                            <span class="dashicons dashicons-unlock" style="font-size: 14px; width: 14px; height: 14px;"></span>
                            <?php _e('Gratuito', 'imagina-updater-license'); ?>
                        </span>
                    <?php endif; ?>
                </label>
            </form>

            <?php if ($is_premium && $protection_status): ?>
                <div class="imagina-protection-status <?php
                    if (!$protection_status['has_protection']) {
                        echo 'no-protection';
                    } elseif ($protection_status['needs_update']) {
                        echo 'needs-update';
                    } else {
                        echo 'has-protection';
                    }
                ?>">
                    <?php
                    if (!$protection_status['has_protection']) {
                        _e('Sin protección', 'imagina-updater-license');
                    } elseif ($protection_status['needs_update']) {
                        printf(__('v%s (actualizar)', 'imagina-updater-license'), $protection_status['installed_version']);
                    } else {
                        printf(__('v%s', 'imagina-updater-license'), $protection_status['installed_version']);
                    }
                    ?>
                </div>

                <?php if ($needs_injection || $needs_update): ?>
                    <form method="post" style="margin-top: 5px;">
                        <?php wp_nonce_field('imagina_license_inject_protection_' . $plugin->id); ?>
                        <input type="hidden" name="imagina_license_plugin_id" value="<?php echo esc_attr($plugin->id); ?>">
                        <input type="hidden" name="imagina_license_action" value="inject_protection">
                        <button type="submit" class="button button-small" style="font-size: 10px; padding: 0 6px; height: 22px; line-height: 20px;">
                            <?php echo $needs_update ? __('Actualizar', 'imagina-updater-license') : __('Inyectar', 'imagina-updater-license'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </td>
        <?php
    }

    /**
     * Agregar checkbox "Plugin Premium" al formulario de subida
     */
    public static function add_premium_checkbox() {
        ?>
        <tr>
            <th scope="row">
                <?php _e('Plugin Premium', 'imagina-updater-license'); ?>
            </th>
            <td>
                <label>
                    <input type="checkbox" name="is_premium" id="is_premium" value="1">
                    <?php _e('Marcar como plugin premium (requiere licencia)', 'imagina-updater-license'); ?>
                </label>
                <p class="description">
                    <?php _e('Al marcar esta opción, se inyectará automáticamente el código de protección de licencias en el plugin. Los clientes necesitarán una licencia válida para usar el plugin.', 'imagina-updater-license'); ?>
                </p>
                <p class="description" style="margin-top: 8px;">
                    <strong><?php _e('Características de la protección:', 'imagina-updater-license'); ?></strong>
                </p>
                <ul style="list-style: disc; margin-left: 20px; color: #666;">
                    <li><?php _e('Verificación de licencia al cargar el plugin', 'imagina-updater-license'); ?></li>
                    <li><?php _e('Heartbeat periódico cada 12 horas', 'imagina-updater-license'); ?></li>
                    <li><?php _e('Período de gracia de 7 días sin conexión', 'imagina-updater-license'); ?></li>
                    <li><?php _e('Bloqueo de funciones AJAX y REST si no hay licencia', 'imagina-updater-license'); ?></li>
                </ul>
            </td>
        </tr>
        <?php
    }

    /**
     * Procesar el archivo subido antes de guardarlo en BD
     *
     * @param string $file_path Ruta del archivo ZIP
     * @param array $plugin_data Datos extraídos del plugin
     */
    public static function process_uploaded_file($file_path, $plugin_data) {
        // Verificar si se marcó como premium en el formulario
        $is_premium = isset($_POST['is_premium']) && $_POST['is_premium'] == 1;

        if (!$is_premium) {
            return;
        }

        // Inyectar protección inmediatamente
        $result = Imagina_License_SDK_Injector::inject_sdk_if_needed($file_path, true);

        if ($result['success']) {
            imagina_license_log('Protección inyectada durante subida: ' . $result['message'], 'info', array(
                'plugin' => isset($plugin_data['slug']) ? $plugin_data['slug'] : 'unknown'
            ));

            // Recalcular checksum del archivo modificado
            if (file_exists($file_path)) {
                // El checksum se actualizará después en inject_protection_after_upload
            }
        } else {
            imagina_license_log('Error al inyectar protección durante subida: ' . $result['message'], 'warning', array(
                'plugin' => isset($plugin_data['slug']) ? $plugin_data['slug'] : 'unknown'
            ));

            // Mostrar mensaje de error al usuario
            set_transient('imagina_license_injection_error', $result['message'], 30);
        }
    }

    /**
     * Guardar el campo is_premium y actualizar checksums después de subir plugin
     *
     * @param array $plugin_data Datos del plugin subido
     * @param string $file_path Ruta del archivo ZIP
     */
    public static function inject_protection_after_upload($plugin_data, $file_path) {
        if (!isset($plugin_data['id'])) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'imagina_updater_plugins';

        // Determinar si es premium
        $is_premium = isset($_POST['is_premium']) && $_POST['is_premium'] == 1 ? 1 : 0;

        // Verificar si el plugin ya era premium (actualización)
        $existing_premium = $wpdb->get_var($wpdb->prepare(
            "SELECT is_premium FROM $table WHERE id = %d",
            $plugin_data['id']
        ));

        // Si ya era premium y no se marcó como premium, mantener el estado
        if ($existing_premium == 1 && $is_premium == 0) {
            // Es una actualización de un plugin premium, mantener como premium
            $is_premium = 1;

            // Inyectar protección en la nueva versión
            $result = Imagina_License_SDK_Injector::inject_sdk_if_needed($file_path, true);

            if ($result['success']) {
                imagina_license_log('Protección inyectada en actualización de plugin premium', 'info', array(
                    'plugin' => isset($plugin_data['slug']) ? $plugin_data['slug'] : 'unknown'
                ));
            }
        }

        // Actualizar el campo is_premium
        $wpdb->update(
            $table,
            array('is_premium' => $is_premium),
            array('id' => $plugin_data['id']),
            array('%d'),
            array('%d')
        );

        // Si es premium, actualizar checksum y tamaño del archivo (pudo haber cambiado)
        if ($is_premium && file_exists($file_path)) {
            $new_checksum = hash_file('sha256', $file_path);
            $new_size = filesize($file_path);

            $wpdb->update(
                $table,
                array(
                    'checksum' => $new_checksum,
                    'file_size' => $new_size
                ),
                array('id' => $plugin_data['id']),
                array('%s', '%d'),
                array('%d')
            );
        }
    }

    /**
     * Manejar acciones de formularios
     */
    public static function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Toggle premium status
        if (isset($_POST['imagina_license_action']) && $_POST['imagina_license_action'] === 'toggle_premium') {
            self::handle_toggle_premium();
        }

        // Inyectar protección manualmente
        if (isset($_POST['imagina_license_action']) && $_POST['imagina_license_action'] === 'inject_protection') {
            self::handle_inject_protection();
        }

        // Mostrar mensajes
        self::show_admin_notices();
    }

    /**
     * Manejar inyección de protección manual
     */
    private static function handle_inject_protection() {
        if (!isset($_POST['imagina_license_plugin_id'])) {
            return;
        }

        $plugin_id = intval($_POST['imagina_license_plugin_id']);

        if (!check_admin_referer('imagina_license_inject_protection_' . $plugin_id)) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'imagina_updater_plugins';

        // Obtener datos del plugin
        $plugin = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $plugin_id
        ));

        if (!$plugin) {
            set_transient('imagina_license_premium_error', __('Plugin no encontrado', 'imagina-updater-license'), 30);
            wp_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
            exit;
        }

        // El campo file_path ya contiene la ruta completa del archivo
        $file_path = $plugin->file_path;

        if (!file_exists($file_path)) {
            set_transient('imagina_license_premium_error', __('Archivo del plugin no encontrado: ' . $file_path, 'imagina-updater-license'), 30);
            wp_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
            exit;
        }

        // Inyectar protección
        $result = Imagina_License_SDK_Injector::inject_sdk_if_needed($file_path, true);

        if ($result['success']) {
            // Actualizar checksum
            $new_checksum = hash_file('sha256', $file_path);
            $new_size = filesize($file_path);

            $wpdb->update(
                $table,
                array(
                    'checksum' => $new_checksum,
                    'file_size' => $new_size
                ),
                array('id' => $plugin_id),
                array('%s', '%d'),
                array('%d')
            );

            imagina_license_log('Protección inyectada manualmente en: ' . $plugin->slug, 'info');
            set_transient('imagina_license_injection_success', $result['message'], 30);
        } else {
            imagina_license_log('Error al inyectar protección: ' . $result['message'], 'error');
            set_transient('imagina_license_premium_error', $result['message'], 30);
        }

        wp_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
        exit;
    }

    /**
     * Manejar toggle de estado premium
     */
    private static function handle_toggle_premium() {
        if (!isset($_POST['imagina_license_plugin_id'])) {
            return;
        }

        $plugin_id = intval($_POST['imagina_license_plugin_id']);

        if (!check_admin_referer('imagina_license_toggle_premium_' . $plugin_id)) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'imagina_updater_plugins';

        // Obtener datos del plugin
        $plugin = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $plugin_id
        ));

        if (!$plugin) {
            set_transient('imagina_license_premium_error', __('Plugin no encontrado', 'imagina-updater-license'), 30);
            wp_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
            exit;
        }

        // Toggle: si está marcado el checkbox, es 1, sino es 0
        $new_status = isset($_POST['is_premium']) ? 1 : 0;

        // Si se está activando como premium, inyectar protección
        if ($new_status == 1 && $plugin->is_premium != 1) {
            // El campo file_path ya contiene la ruta completa del archivo
            $file_path = $plugin->file_path;

            if (file_exists($file_path)) {
                $result = Imagina_License_SDK_Injector::inject_sdk_if_needed($file_path, true);

                if ($result['success']) {
                    // Actualizar checksum
                    $new_checksum = hash_file('sha256', $file_path);
                    $new_size = filesize($file_path);

                    $wpdb->update(
                        $table,
                        array(
                            'checksum' => $new_checksum,
                            'file_size' => $new_size
                        ),
                        array('id' => $plugin_id),
                        array('%s', '%d'),
                        array('%d')
                    );

                    imagina_license_log('Protección inyectada al marcar plugin como premium: ' . $plugin->slug, 'info');
                } else {
                    imagina_license_log('Error al inyectar protección: ' . $result['message'], 'error');
                    set_transient('imagina_license_injection_warning', $result['message'], 30);
                }
            }
        }

        // Actualizar estado premium
        $result = $wpdb->update(
            $table,
            array('is_premium' => $new_status),
            array('id' => $plugin_id),
            array('%d'),
            array('%d')
        );

        if ($result !== false) {
            $status_text = $new_status ? 'premium' : 'gratuito';
            imagina_license_log('Plugin ' . $plugin->slug . ' marcado como ' . $status_text, 'info');
            set_transient('imagina_license_premium_toggled', $new_status ? 'premium' : 'free', 30);
        } else {
            imagina_license_log('Error al actualizar estado premium de plugin: ' . $plugin->slug, 'error');
            set_transient('imagina_license_premium_error', __('Error al actualizar estado premium', 'imagina-updater-license'), 30);
        }

        wp_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
        exit;
    }

    /**
     * Mostrar mensajes de administración
     */
    private static function show_admin_notices() {
        // Mensaje de toggle exitoso
        $toggled = get_transient('imagina_license_premium_toggled');
        if ($toggled) {
            delete_transient('imagina_license_premium_toggled');
            if ($toggled === 'premium') {
                add_settings_error(
                    'imagina_updater',
                    'premium_toggled',
                    __('Plugin marcado como premium. La protección de licencias ha sido inyectada.', 'imagina-updater-license'),
                    'success'
                );
            } else {
                add_settings_error(
                    'imagina_updater',
                    'premium_toggled',
                    __('Plugin marcado como gratuito.', 'imagina-updater-license'),
                    'success'
                );
            }
        }

        // Mensaje de error
        $error = get_transient('imagina_license_premium_error');
        if ($error) {
            delete_transient('imagina_license_premium_error');
            add_settings_error('imagina_updater', 'premium_error', $error, 'error');
        }

        // Advertencia de inyección
        $warning = get_transient('imagina_license_injection_warning');
        if ($warning) {
            delete_transient('imagina_license_injection_warning');
            add_settings_error(
                'imagina_updater',
                'injection_warning',
                sprintf(__('Advertencia al inyectar protección: %s', 'imagina-updater-license'), $warning),
                'warning'
            );
        }

        // Éxito de inyección manual
        $injection_success = get_transient('imagina_license_injection_success');
        if ($injection_success) {
            delete_transient('imagina_license_injection_success');
            add_settings_error(
                'imagina_updater',
                'injection_success',
                __('Protección de licencias inyectada correctamente. Los clientes deben actualizar el plugin para obtener la versión protegida.', 'imagina-updater-license'),
                'success'
            );
        }

        // Error de inyección
        $injection_error = get_transient('imagina_license_injection_error');
        if ($injection_error) {
            delete_transient('imagina_license_injection_error');
            add_settings_error(
                'imagina_updater',
                'injection_error',
                sprintf(__('Error al inyectar protección: %s', 'imagina-updater-license'), $injection_error),
                'error'
            );
        }
    }

    /**
     * Inyectar protección en todos los plugins premium que no la tengan
     * (Útil para migración)
     *
     * @return array Resultados de la operación
     */
    public static function inject_protection_in_all_premium_plugins() {
        global $wpdb;
        $table = $wpdb->prefix . 'imagina_updater_plugins';

        $premium_plugins = $wpdb->get_results(
            "SELECT * FROM $table WHERE is_premium = 1"
        );

        $results = array(
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => array()
        );

        foreach ($premium_plugins as $plugin) {
            // El campo file_path ya contiene la ruta completa
            $file_path = $plugin->file_path;

            if (!file_exists($file_path)) {
                $results['failed']++;
                $results['details'][] = array(
                    'plugin' => $plugin->slug,
                    'status' => 'error',
                    'message' => 'Archivo no encontrado'
                );
                continue;
            }

            $result = Imagina_License_SDK_Injector::inject_sdk_if_needed($file_path, true);

            if ($result['success']) {
                if (strpos($result['message'], 'ya tiene') !== false) {
                    $results['skipped']++;
                    $results['details'][] = array(
                        'plugin' => $plugin->slug,
                        'status' => 'skipped',
                        'message' => $result['message']
                    );
                } else {
                    $results['success']++;
                    $results['details'][] = array(
                        'plugin' => $plugin->slug,
                        'status' => 'success',
                        'message' => $result['message']
                    );

                    // Actualizar checksum
                    $new_checksum = hash_file('sha256', $file_path);
                    $new_size = filesize($file_path);

                    $wpdb->update(
                        $table,
                        array(
                            'checksum' => $new_checksum,
                            'file_size' => $new_size
                        ),
                        array('id' => $plugin->id),
                        array('%s', '%d'),
                        array('%d')
                    );
                }
            } else {
                $results['failed']++;
                $results['details'][] = array(
                    'plugin' => $plugin->slug,
                    'status' => 'error',
                    'message' => $result['message']
                );
            }
        }

        return $results;
    }

    // ===============================================
    // PÁGINA DE GESTIÓN DE LICENSE KEYS
    // ===============================================

    /**
     * Renderizar página de License Keys
     */
    public static function render_license_keys_page() {
        // Procesar acciones
        self::process_license_actions();

        // Obtener datos
        global $wpdb;
        $table_licenses = $wpdb->prefix . 'imagina_license_keys';
        $table_plugins = $wpdb->prefix . 'imagina_updater_plugins';

        // Filtros
        $plugin_filter = isset($_GET['plugin_id']) ? intval($_GET['plugin_id']) : 0;
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';

        // Construir query
        $where = array('1=1');
        $where_values = array();

        if ($plugin_filter > 0) {
            $where[] = 'l.plugin_id = %d';
            $where_values[] = $plugin_filter;
        }

        if (!empty($status_filter)) {
            $where[] = 'l.status = %s';
            $where_values[] = $status_filter;
        }

        $where_clause = implode(' AND ', $where);

        $query = "SELECT l.*, p.name as plugin_name, p.slug as plugin_slug
                  FROM $table_licenses l
                  LEFT JOIN $table_plugins p ON l.plugin_id = p.id
                  WHERE $where_clause
                  ORDER BY l.created_at DESC";

        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }

        $licenses = $wpdb->get_results($query);

        // Obtener plugins premium para el formulario
        $premium_plugins = $wpdb->get_results(
            "SELECT id, name, slug FROM $table_plugins WHERE is_premium = 1 ORDER BY name"
        );

        // Modal de edición (si hay ID)
        $edit_license = null;
        if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
            $edit_license = Imagina_License_Database::get_license_by_id(intval($_GET['edit']));
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('License Keys', 'imagina-updater-license'); ?></h1>
            <a href="#" class="page-title-action" onclick="document.getElementById('new-license-form').style.display='block'; return false;">
                <?php _e('Agregar Nueva', 'imagina-updater-license'); ?>
            </a>
            <hr class="wp-header-end">

            <?php settings_errors('imagina_license'); ?>

            <!-- Formulario de nueva licencia -->
            <div id="new-license-form" class="card" style="display: <?php echo $edit_license ? 'block' : 'none'; ?>; max-width: 600px; margin-bottom: 20px;">
                <h2><?php echo $edit_license ? __('Editar Licencia', 'imagina-updater-license') : __('Nueva Licencia', 'imagina-updater-license'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('imagina_license_create'); ?>
                    <input type="hidden" name="imagina_license_action" value="<?php echo $edit_license ? 'update_license' : 'create_license'; ?>">
                    <?php if ($edit_license): ?>
                        <input type="hidden" name="license_id" value="<?php echo esc_attr($edit_license->id); ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="plugin_id"><?php _e('Plugin', 'imagina-updater-license'); ?></label></th>
                            <td>
                                <?php
                                // Permitir editar plugin si es licencia huérfana (plugin eliminado)
                                $plugin_exists = false;
                                if ($edit_license) {
                                    foreach ($premium_plugins as $p) {
                                        if ($p->id == $edit_license->plugin_id) {
                                            $plugin_exists = true;
                                            break;
                                        }
                                    }
                                }
                                $allow_change = !$edit_license || !$plugin_exists;
                                ?>
                                <select name="plugin_id" id="plugin_id" required <?php echo !$allow_change ? 'disabled' : ''; ?>>
                                    <option value=""><?php _e('Seleccionar plugin...', 'imagina-updater-license'); ?></option>
                                    <?php foreach ($premium_plugins as $plugin): ?>
                                        <option value="<?php echo esc_attr($plugin->id); ?>" <?php selected($edit_license ? $edit_license->plugin_id : '', $plugin->id); ?>>
                                            <?php echo esc_html($plugin->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($edit_license && !$plugin_exists): ?>
                                    <p class="description" style="color: #d63638;">
                                        <?php _e('El plugin original fue eliminado. Selecciona un nuevo plugin.', 'imagina-updater-license'); ?>
                                    </p>
                                <?php elseif (empty($premium_plugins)): ?>
                                    <p class="description" style="color: #d63638;">
                                        <?php _e('No hay plugins premium. Primero marca un plugin como premium.', 'imagina-updater-license'); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="customer_email"><?php _e('Email del Cliente', 'imagina-updater-license'); ?></label></th>
                            <td>
                                <input type="email" name="customer_email" id="customer_email" class="regular-text" required
                                       value="<?php echo $edit_license ? esc_attr($edit_license->customer_email) : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="customer_name"><?php _e('Nombre del Cliente', 'imagina-updater-license'); ?></label></th>
                            <td>
                                <input type="text" name="customer_name" id="customer_name" class="regular-text"
                                       value="<?php echo $edit_license ? esc_attr($edit_license->customer_name) : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><label for="max_activations"><?php _e('Máx. Activaciones', 'imagina-updater-license'); ?></label></th>
                            <td>
                                <input type="number" name="max_activations" id="max_activations" min="1" max="999" class="small-text"
                                       value="<?php echo $edit_license ? esc_attr($edit_license->max_activations) : '1'; ?>">
                                <p class="description"><?php _e('Número de sitios donde puede activarse esta licencia.', 'imagina-updater-license'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="expires_at"><?php _e('Fecha de Expiración', 'imagina-updater-license'); ?></label></th>
                            <td>
                                <input type="date" name="expires_at" id="expires_at"
                                       value="<?php echo $edit_license && $edit_license->expires_at ? esc_attr(date('Y-m-d', strtotime($edit_license->expires_at))) : ''; ?>">
                                <p class="description"><?php _e('Dejar vacío para licencia de por vida.', 'imagina-updater-license'); ?></p>
                            </td>
                        </tr>
                        <?php if ($edit_license): ?>
                        <tr>
                            <th><label for="status"><?php _e('Estado', 'imagina-updater-license'); ?></label></th>
                            <td>
                                <select name="status" id="status">
                                    <option value="active" <?php selected($edit_license->status, 'active'); ?>><?php _e('Activa', 'imagina-updater-license'); ?></option>
                                    <option value="inactive" <?php selected($edit_license->status, 'inactive'); ?>><?php _e('Inactiva', 'imagina-updater-license'); ?></option>
                                    <option value="expired" <?php selected($edit_license->status, 'expired'); ?>><?php _e('Expirada', 'imagina-updater-license'); ?></option>
                                    <option value="revoked" <?php selected($edit_license->status, 'revoked'); ?>><?php _e('Revocada', 'imagina-updater-license'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th><label for="order_id"><?php _e('ID de Orden', 'imagina-updater-license'); ?></label></th>
                            <td>
                                <input type="text" name="order_id" id="order_id" class="regular-text"
                                       value="<?php echo $edit_license ? esc_attr($edit_license->order_id) : ''; ?>">
                                <p class="description"><?php _e('Opcional. Referencia de WooCommerce u otro sistema.', 'imagina-updater-license'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="notes"><?php _e('Notas', 'imagina-updater-license'); ?></label></th>
                            <td>
                                <textarea name="notes" id="notes" rows="3" class="large-text"><?php echo $edit_license ? esc_textarea($edit_license->notes) : ''; ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php echo $edit_license ? __('Actualizar Licencia', 'imagina-updater-license') : __('Crear Licencia', 'imagina-updater-license'); ?>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=imagina-license-keys'); ?>" class="button">
                            <?php _e('Cancelar', 'imagina-updater-license'); ?>
                        </a>
                    </p>
                </form>
            </div>

            <!-- Filtros -->
            <div class="tablenav top">
                <form method="get">
                    <input type="hidden" name="page" value="imagina-license-keys">
                    <select name="plugin_id">
                        <option value=""><?php _e('Todos los plugins', 'imagina-updater-license'); ?></option>
                        <?php foreach ($premium_plugins as $plugin): ?>
                            <option value="<?php echo esc_attr($plugin->id); ?>" <?php selected($plugin_filter, $plugin->id); ?>>
                                <?php echo esc_html($plugin->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value=""><?php _e('Todos los estados', 'imagina-updater-license'); ?></option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>><?php _e('Activas', 'imagina-updater-license'); ?></option>
                        <option value="inactive" <?php selected($status_filter, 'inactive'); ?>><?php _e('Inactivas', 'imagina-updater-license'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php _e('Expiradas', 'imagina-updater-license'); ?></option>
                        <option value="revoked" <?php selected($status_filter, 'revoked'); ?>><?php _e('Revocadas', 'imagina-updater-license'); ?></option>
                    </select>
                    <button type="submit" class="button"><?php _e('Filtrar', 'imagina-updater-license'); ?></button>
                </form>
            </div>

            <!-- Toolbar: Búsqueda y Columnas -->
            <div class="imagina-table-toolbar">
                <div class="imagina-table-search">
                    <input type="text" placeholder="<?php esc_attr_e('Buscar licencia, cliente, email...', 'imagina-updater-license'); ?>">
                </div>

                <div class="imagina-column-toggle">
                    <button type="button" class="imagina-column-toggle-btn">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php _e('Columnas', 'imagina-updater-license'); ?>
                    </button>
                    <div class="imagina-column-dropdown">
                        <label><input type="checkbox" data-col="1" checked> <?php _e('License Key', 'imagina-updater-license'); ?></label>
                        <label><input type="checkbox" data-col="2" checked> <?php _e('Plugin', 'imagina-updater-license'); ?></label>
                        <label><input type="checkbox" data-col="3" checked> <?php _e('Cliente', 'imagina-updater-license'); ?></label>
                        <label><input type="checkbox" data-col="4" checked> <?php _e('Activaciones', 'imagina-updater-license'); ?></label>
                        <label><input type="checkbox" data-col="5" checked> <?php _e('Estado', 'imagina-updater-license'); ?></label>
                        <label><input type="checkbox" data-col="6"> <?php _e('Expira', 'imagina-updater-license'); ?></label>
                    </div>
                </div>

                <span class="imagina-table-count"><?php echo count($licenses); ?> registros</span>
            </div>

            <!-- Tabla de licencias -->
            <table id="licenses-table" class="wp-list-table widefat fixed striped imagina-table-enhanced">
                <thead>
                    <tr>
                        <th style="width: 260px;"><?php _e('License Key', 'imagina-updater-license'); ?></th>
                        <th><?php _e('Plugin', 'imagina-updater-license'); ?></th>
                        <th><?php _e('Cliente', 'imagina-updater-license'); ?></th>
                        <th style="width: 90px;"><?php _e('Activaciones', 'imagina-updater-license'); ?></th>
                        <th style="width: 75px;"><?php _e('Estado', 'imagina-updater-license'); ?></th>
                        <th style="width: 85px;"><?php _e('Expira', 'imagina-updater-license'); ?></th>
                        <th style="width: 90px;"><?php _e('Acciones', 'imagina-updater-license'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($licenses)): ?>
                        <tr>
                            <td colspan="7"><?php _e('No hay licencias registradas.', 'imagina-updater-license'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($licenses as $license): ?>
                            <tr>
                                <td>
                                    <code style="font-size: 10px; background: #f0f0f1; padding: 2px 5px; border-radius: 3px;">
                                        <?php echo esc_html($license->license_key); ?>
                                    </code>
                                    <button type="button" class="button-link" onclick="navigator.clipboard.writeText('<?php echo esc_js($license->license_key); ?>'); this.innerHTML='✓';" style="margin-left: 3px;" title="<?php esc_attr_e('Copiar', 'imagina-updater-license'); ?>">
                                        <span class="dashicons dashicons-clipboard" style="font-size: 13px; width:13px; height:13px;"></span>
                                    </button>
                                </td>
                                <td style="font-size: 12px;">
                                    <?php echo esc_html($license->plugin_name ?: __('Plugin eliminado', 'imagina-updater-license')); ?>
                                </td>
                                <td>
                                    <strong style="font-size: 12px;"><?php echo esc_html($license->customer_name ?: '-'); ?></strong><br>
                                    <small style="font-size: 11px;"><?php echo esc_html($license->customer_email); ?></small>
                                </td>
                                <td style="text-align: center;">
                                    <strong><?php echo intval($license->activations_count); ?></strong> / <?php echo intval($license->max_activations); ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = array(
                                        'active' => '#00a32a',
                                        'inactive' => '#72aee6',
                                        'expired' => '#dba617',
                                        'revoked' => '#d63638'
                                    );
                                    $status_labels = array(
                                        'active' => __('Activa', 'imagina-updater-license'),
                                        'inactive' => __('Inactiva', 'imagina-updater-license'),
                                        'expired' => __('Expirada', 'imagina-updater-license'),
                                        'revoked' => __('Revocada', 'imagina-updater-license')
                                    );
                                    $color = isset($status_colors[$license->status]) ? $status_colors[$license->status] : '#666';
                                    $label = isset($status_labels[$license->status]) ? $status_labels[$license->status] : $license->status;
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: 500; font-size: 11px;">
                                        <?php echo esc_html($label); ?>
                                    </span>
                                </td>
                                <td style="font-size: 11px;">
                                    <?php
                                    if ($license->expires_at) {
                                        $expires = strtotime($license->expires_at);
                                        $now = time();
                                        $color = $expires < $now ? '#d63638' : ($expires < $now + (30 * DAY_IN_SECONDS) ? '#dba617' : '#666');
                                        echo '<span style="color: ' . $color . ';">' . date_i18n('d/m/Y', $expires) . '</span>';
                                    } else {
                                        echo '<span style="color: #00a32a;">' . __('Nunca', 'imagina-updater-license') . '</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="imagina-actions-dropdown">
                                        <button type="button" class="imagina-actions-btn">
                                            <?php _e('Acciones', 'imagina-updater-license'); ?>
                                            <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                        </button>
                                        <div class="imagina-actions-menu">
                                            <a href="<?php echo admin_url('admin.php?page=imagina-license-keys&view=' . $license->id); ?>">
                                                <span class="dashicons dashicons-visibility"></span>
                                                <?php _e('Ver Detalles', 'imagina-updater-license'); ?>
                                            </a>
                                            <a href="<?php echo admin_url('admin.php?page=imagina-license-keys&edit=' . $license->id); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                                <?php _e('Editar', 'imagina-updater-license'); ?>
                                            </a>
                                            <form method="post" style="margin:0;">
                                                <?php wp_nonce_field('imagina_license_regenerate'); ?>
                                                <input type="hidden" name="imagina_license_action" value="regenerate_license">
                                                <input type="hidden" name="license_id" value="<?php echo esc_attr($license->id); ?>">
                                                <button type="submit" onclick="return confirm('<?php esc_attr_e('¿Regenerar esta license key?', 'imagina-updater-license'); ?>');">
                                                    <span class="dashicons dashicons-update"></span>
                                                    <?php _e('Regenerar Key', 'imagina-updater-license'); ?>
                                                </button>
                                            </form>
                                            <form method="post" style="margin:0;">
                                                <?php wp_nonce_field('imagina_license_delete'); ?>
                                                <input type="hidden" name="imagina_license_action" value="delete_license">
                                                <input type="hidden" name="license_id" value="<?php echo esc_attr($license->id); ?>">
                                                <button type="submit" class="action-delete" onclick="return confirm('<?php esc_attr_e('¿Eliminar esta licencia permanentemente?', 'imagina-updater-license'); ?>');">
                                                    <span class="dashicons dashicons-trash"></span>
                                                    <?php _e('Eliminar', 'imagina-updater-license'); ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php
            // Modal de ver detalles de licencia
            if (isset($_GET['view']) && intval($_GET['view']) > 0) {
                $view_license = Imagina_License_Database::get_license_by_id(intval($_GET['view']));
                if ($view_license) {
                    $activations = Imagina_License_Database::get_license_activations($view_license->id);
                    ?>
                    <div class="card" style="margin-top: 20px; max-width: 800px;">
                        <h2><?php _e('Detalles de Licencia', 'imagina-updater-license'); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th><?php _e('License Key', 'imagina-updater-license'); ?></th>
                                <td><code><?php echo esc_html($view_license->license_key); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php _e('Cliente', 'imagina-updater-license'); ?></th>
                                <td><?php echo esc_html($view_license->customer_name); ?> (<?php echo esc_html($view_license->customer_email); ?>)</td>
                            </tr>
                            <tr>
                                <th><?php _e('Creada', 'imagina-updater-license'); ?></th>
                                <td><?php echo date_i18n('d/m/Y H:i', strtotime($view_license->created_at)); ?></td>
                            </tr>
                        </table>

                        <h3><?php _e('Sitios Activados', 'imagina-updater-license'); ?> (<?php echo count($activations); ?>)</h3>
                        <?php if (!empty($activations)): ?>
                            <table class="wp-list-table widefat striped">
                                <thead>
                                    <tr>
                                        <th><?php _e('Sitio', 'imagina-updater-license'); ?></th>
                                        <th><?php _e('Estado', 'imagina-updater-license'); ?></th>
                                        <th><?php _e('Activado', 'imagina-updater-license'); ?></th>
                                        <th><?php _e('Última verificación', 'imagina-updater-license'); ?></th>
                                        <th><?php _e('Acciones', 'imagina-updater-license'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activations as $activation): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo esc_html($activation->site_name ?: '-'); ?></strong><br>
                                                <small><?php echo esc_html($activation->site_url); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($activation->is_active): ?>
                                                    <span style="color: #00a32a;"><?php _e('Activo', 'imagina-updater-license'); ?></span>
                                                <?php else: ?>
                                                    <span style="color: #666;"><?php _e('Desactivado', 'imagina-updater-license'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date_i18n('d/m/Y H:i', strtotime($activation->activated_at)); ?></td>
                                            <td><?php echo $activation->last_check ? date_i18n('d/m/Y H:i', strtotime($activation->last_check)) : '-'; ?></td>
                                            <td>
                                                <?php if ($activation->is_active): ?>
                                                    <form method="post" style="display: inline;">
                                                        <?php wp_nonce_field('imagina_license_deactivate_site'); ?>
                                                        <input type="hidden" name="imagina_license_action" value="deactivate_site">
                                                        <input type="hidden" name="activation_id" value="<?php echo esc_attr($activation->id); ?>">
                                                        <input type="hidden" name="license_id" value="<?php echo esc_attr($view_license->id); ?>">
                                                        <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e('¿Desactivar este sitio?', 'imagina-updater-license'); ?>');">
                                                            <?php _e('Desactivar', 'imagina-updater-license'); ?>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p><?php _e('Esta licencia no tiene activaciones.', 'imagina-updater-license'); ?></p>
                        <?php endif; ?>

                        <p style="margin-top: 15px;">
                            <a href="<?php echo admin_url('admin.php?page=imagina-license-keys'); ?>" class="button">
                                <?php _e('Volver', 'imagina-updater-license'); ?>
                            </a>
                        </p>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <?php
    }

    /**
     * Procesar acciones de licencias
     */
    private static function process_license_actions() {
        if (!isset($_POST['imagina_license_action'])) {
            return;
        }

        $action = sanitize_key($_POST['imagina_license_action']);

        switch ($action) {
            case 'create_license':
                if (!check_admin_referer('imagina_license_create')) {
                    return;
                }

                $data = array(
                    'plugin_id' => intval($_POST['plugin_id']),
                    'customer_email' => sanitize_email($_POST['customer_email']),
                    'customer_name' => sanitize_text_field($_POST['customer_name']),
                    'max_activations' => max(1, intval($_POST['max_activations'])),
                    'expires_at' => !empty($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) . ' 23:59:59' : null,
                    'order_id' => sanitize_text_field($_POST['order_id']),
                    'notes' => sanitize_textarea_field($_POST['notes']),
                );

                $license_id = Imagina_License_Database::create_license($data);

                if ($license_id) {
                    add_settings_error('imagina_license', 'created', __('Licencia creada correctamente.', 'imagina-updater-license'), 'success');
                } else {
                    add_settings_error('imagina_license', 'error', __('Error al crear la licencia.', 'imagina-updater-license'), 'error');
                }
                break;

            case 'update_license':
                if (!check_admin_referer('imagina_license_create')) {
                    return;
                }

                global $wpdb;
                $table = $wpdb->prefix . 'imagina_license_keys';

                $license_id = intval($_POST['license_id']);

                $update_data = array(
                    'customer_email' => sanitize_email($_POST['customer_email']),
                    'customer_name' => sanitize_text_field($_POST['customer_name']),
                    'max_activations' => max(1, intval($_POST['max_activations'])),
                    'expires_at' => !empty($_POST['expires_at']) ? sanitize_text_field($_POST['expires_at']) . ' 23:59:59' : null,
                    'status' => sanitize_key($_POST['status']),
                    'order_id' => sanitize_text_field($_POST['order_id']),
                    'notes' => sanitize_textarea_field($_POST['notes']),
                );
                $format = array('%s', '%s', '%d', '%s', '%s', '%s', '%s');

                // Permitir cambiar plugin_id si se envía (licencias huérfanas)
                if (!empty($_POST['plugin_id'])) {
                    $update_data['plugin_id'] = intval($_POST['plugin_id']);
                    $format[] = '%d';
                }

                $result = $wpdb->update($table, $update_data, array('id' => $license_id), $format, array('%d'));

                if ($result !== false) {
                    add_settings_error('imagina_license', 'updated', __('Licencia actualizada correctamente.', 'imagina-updater-license'), 'success');
                    wp_redirect(admin_url('admin.php?page=imagina-license-keys'));
                    exit;
                } else {
                    add_settings_error('imagina_license', 'error', __('Error al actualizar la licencia.', 'imagina-updater-license'), 'error');
                }
                break;

            case 'delete_license':
                if (!check_admin_referer('imagina_license_delete')) {
                    return;
                }

                global $wpdb;
                $license_id = intval($_POST['license_id']);

                // Eliminar activaciones asociadas
                $wpdb->delete(
                    $wpdb->prefix . 'imagina_license_activations',
                    array('license_id' => $license_id),
                    array('%d')
                );

                // Eliminar licencia
                $result = $wpdb->delete(
                    $wpdb->prefix . 'imagina_license_keys',
                    array('id' => $license_id),
                    array('%d')
                );

                if ($result) {
                    add_settings_error('imagina_license', 'deleted', __('Licencia eliminada correctamente.', 'imagina-updater-license'), 'success');
                } else {
                    add_settings_error('imagina_license', 'error', __('Error al eliminar la licencia.', 'imagina-updater-license'), 'error');
                }
                break;

            case 'regenerate_license':
                if (!check_admin_referer('imagina_license_regenerate')) {
                    return;
                }

                global $wpdb;
                $license_id = intval($_POST['license_id']);
                $new_key = Imagina_License_Database::generate_license_key();

                $result = $wpdb->update(
                    $wpdb->prefix . 'imagina_license_keys',
                    array('license_key' => $new_key),
                    array('id' => $license_id),
                    array('%s'),
                    array('%d')
                );

                if ($result) {
                    add_settings_error('imagina_license', 'regenerated', sprintf(__('License key regenerada: %s', 'imagina-updater-license'), $new_key), 'success');
                } else {
                    add_settings_error('imagina_license', 'error', __('Error al regenerar la license key.', 'imagina-updater-license'), 'error');
                }
                break;

            case 'deactivate_site':
                if (!check_admin_referer('imagina_license_deactivate_site')) {
                    return;
                }

                global $wpdb;
                $table = $wpdb->prefix . 'imagina_license_activations';
                $activation_id = intval($_POST['activation_id']);
                $license_id = intval($_POST['license_id']);

                $wpdb->update(
                    $table,
                    array(
                        'is_active' => 0,
                        'deactivated_at' => current_time('mysql')
                    ),
                    array('id' => $activation_id),
                    array('%d', '%s'),
                    array('%d')
                );

                // Decrementar contador
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}imagina_license_keys SET activations_count = GREATEST(activations_count - 1, 0) WHERE id = %d",
                    $license_id
                ));

                add_settings_error('imagina_license', 'deactivated', __('Sitio desactivado correctamente.', 'imagina-updater-license'), 'success');
                break;
        }
    }
}
