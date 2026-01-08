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

        // Obtener la ruta del archivo ZIP
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/imagina-updater-plugins/' . $plugin->file_path;

        if (!file_exists($file_path)) {
            set_transient('imagina_license_premium_error', __('Archivo del plugin no encontrado', 'imagina-updater-license'), 30);
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
            // Obtener la ruta del archivo ZIP
            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['basedir'] . '/imagina-updater-plugins/' . $plugin->file_path;

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

        $upload_dir = wp_upload_dir();
        $base_path = $upload_dir['basedir'] . '/imagina-updater-plugins/';

        foreach ($premium_plugins as $plugin) {
            $file_path = $base_path . $plugin->file_path;

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
}
