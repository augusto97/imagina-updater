<?php
/**
 * Interfaz de administración para la extensión de licencias
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

        // Hook para inyectar SDK después de subir plugin
        add_action('imagina_updater_after_upload_plugin', array(__CLASS__, 'inject_sdk_after_upload'), 10, 2);

        // Hook para agregar columna en header de tabla
        add_action('imagina_updater_plugins_table_header', array(__CLASS__, 'add_premium_column_header'));

        // Hook para mostrar columna premium en cada fila de la tabla
        add_action('imagina_updater_plugins_table_row', array(__CLASS__, 'display_premium_column'), 10, 1);
    }

    /**
     * Agregar columna "Premium" al header de la tabla
     */
    public static function add_premium_column_header() {
        ?>
        <th style="text-align: center;"><?php _e('Premium', 'imagina-updater-license'); ?></th>
        <?php
    }

    /**
     * Mostrar contenido de la columna Premium para cada plugin
     *
     * @param object $plugin Plugin object
     */
    public static function display_premium_column($plugin) {
        $is_premium = isset($plugin->is_premium) && $plugin->is_premium == 1;
        ?>
        <td style="text-align: center;">
            <form method="post" style="display:inline;">
                <?php wp_nonce_field('imagina_license_toggle_premium_' . $plugin->id); ?>
                <input type="hidden" name="imagina_license_plugin_id" value="<?php echo esc_attr($plugin->id); ?>">
                <input type="hidden" name="imagina_license_action" value="toggle_premium">
                <label style="cursor: pointer;">
                    <input type="checkbox" name="is_premium" value="1" <?php checked($is_premium, true); ?> onchange="this.form.submit();">
                    <?php if ($is_premium): ?>
                        <span class="dashicons dashicons-lock" style="color: #d63638;" title="<?php esc_attr_e('Plugin premium con SDK de licencias', 'imagina-updater-license'); ?>"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-unlock" style="color: #72aee6;" title="<?php esc_attr_e('Plugin gratuito', 'imagina-updater-license'); ?>"></span>
                    <?php endif; ?>
                </label>
            </form>
        </td>
        <?php
    }

    /**
     * Agregar checkbox "Plugin Premium" al formulario de subida
     */
    public static function add_premium_checkbox() {
        ?>
        <tr>
            <th scope="row"><?php _e('Plugin Premium', 'imagina-updater-license'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="is_premium" id="is_premium" value="1">
                    <?php _e('Marcar como plugin premium (requiere licencia)', 'imagina-updater-license'); ?>
                </label>
                <p class="description">
                    <?php _e('Si marcas esta opción, el SDK de licencias se inyectará automáticamente al plugin si aún no lo tiene. Esto permite gestionar licencias y controlar el acceso al plugin de forma remota.', 'imagina-updater-license'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Procesar el archivo subido antes de guardarlo en BD
     * Este es el momento para inyectar el SDK si es necesario
     *
     * @param string $file_path Ruta del archivo ZIP
     * @param array $plugin_data Datos extraídos del plugin
     */
    public static function process_uploaded_file($file_path, $plugin_data) {
        // Verificar si se marcó como premium en el formulario
        $is_premium = isset($_POST['is_premium']) && $_POST['is_premium'] == 1;

        // Si no es un nuevo plugin premium, no hacer nada en este punto
        if (!$is_premium) {
            return;
        }

        // Inyectar SDK inmediatamente
        $result = Imagina_License_SDK_Injector::inject_sdk_if_needed($file_path, true);

        if ($result['success']) {
            imagina_license_log('SDK inyectado durante subida: ' . $result['message'], 'info', array(
                'plugin' => $plugin_data['slug']
            ));
        } else {
            imagina_license_log('Error al inyectar SDK durante subida: ' . $result['message'], 'warning', array(
                'plugin' => $plugin_data['slug']
            ));
        }
    }

    /**
     * Guardar el campo is_premium después de subir plugin
     *
     * @param array $plugin_data Datos del plugin subido
     * @param string $file_path Ruta del archivo ZIP
     */
    public static function inject_sdk_after_upload($plugin_data, $file_path) {
        // Guardar el campo is_premium en la base de datos
        $is_premium = isset($_POST['is_premium']) && $_POST['is_premium'] == 1 ? 1 : 0;

        if (!isset($plugin_data['id'])) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'imagina_updater_plugins';

        // Actualizar el campo is_premium
        $wpdb->update(
            $table,
            array('is_premium' => $is_premium),
            array('id' => $plugin_data['id']),
            array('%d'),
            array('%d')
        );

        // Si se actualizó un plugin que ya era premium, inyectar SDK
        if (!$is_premium) {
            // Verificar si el plugin en BD ya es premium
            $premium_status = $wpdb->get_var($wpdb->prepare(
                "SELECT is_premium FROM $table WHERE id = %d",
                $plugin_data['id']
            ));

            if ($premium_status == 1) {
                // El plugin en BD es premium, inyectar SDK
                $result = Imagina_License_SDK_Injector::inject_sdk_if_needed($file_path, true);

                if ($result['success'] && $result['message'] === 'SDK inyectado correctamente') {
                    // Recalcular checksum si el archivo fue modificado
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

                    imagina_license_log('SDK inyectado en actualización de plugin premium', 'info', array(
                        'plugin' => $plugin_data['slug']
                    ));
                }
            }
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
            if (!isset($_POST['imagina_license_plugin_id'])) {
                return;
            }

            $plugin_id = intval($_POST['imagina_license_plugin_id']);

            if (!check_admin_referer('imagina_license_toggle_premium_' . $plugin_id)) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'imagina_updater_plugins';

            // Toggle: si está marcado el checkbox, es 1, sino es 0
            $new_status = isset($_POST['is_premium']) ? 1 : 0;

            // Actualizar en la base de datos
            $result = $wpdb->update(
                $table,
                array('is_premium' => $new_status),
                array('id' => $plugin_id),
                array('%d'),
                array('%d')
            );

            if ($result !== false) {
                $status_text = $new_status ? 'premium' : 'gratuito';
                imagina_license_log('Plugin ID ' . $plugin_id . ' marcado como ' . $status_text, 'info');
                set_transient('imagina_license_premium_toggled', true, 30);
            } else {
                imagina_license_log('Error al actualizar estado premium de plugin ID ' . $plugin_id, 'error');
                set_transient('imagina_license_premium_error', __('Error al actualizar estado premium', 'imagina-updater-license'), 30);
            }

            wp_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
            exit;
        }

        // Mostrar mensajes de toggle premium
        if (get_transient('imagina_license_premium_toggled')) {
            delete_transient('imagina_license_premium_toggled');
            add_settings_error('imagina_updater', 'premium_toggled', __('Estado premium actualizado', 'imagina-updater-license'), 'success');
        }
        if ($premium_error = get_transient('imagina_license_premium_error')) {
            delete_transient('imagina_license_premium_error');
            add_settings_error('imagina_updater', 'premium_error', $premium_error, 'error');
        }
    }
}
