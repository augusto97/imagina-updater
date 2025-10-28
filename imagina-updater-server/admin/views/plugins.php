<?php
/**
 * Vista: Gestión de Plugins
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Gestión de Plugins', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <div class="imagina-upload-section">
        <h2><?php _e('Subir Nuevo Plugin o Actualización', 'imagina-updater-server'); ?></h2>

        <form method="post" enctype="multipart/form-data" class="imagina-upload-form">
            <?php wp_nonce_field('imagina_upload_plugin'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="plugin_file"><?php _e('Archivo ZIP del Plugin', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <input type="file" name="plugin_file" id="plugin_file" accept=".zip" required>
                        <p class="description">
                            <?php _e('Sube el archivo ZIP del plugin. Si el plugin ya existe, se actualizará a la nueva versión.', 'imagina-updater-server'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="changelog"><?php _e('Notas de la Versión (Opcional)', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <textarea name="changelog" id="changelog" rows="5" class="large-text" placeholder="<?php esc_attr_e('Describe los cambios en esta versión...', 'imagina-updater-server'); ?>"></textarea>
                        <p class="description">
                            <?php _e('Changelog o notas de la versión que se mostrarán a los clientes.', 'imagina-updater-server'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="imagina_upload_plugin" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Subir Plugin', 'imagina-updater-server'); ?>
                </button>
            </p>
        </form>
    </div>

    <hr>

    <h2><?php _e('Plugins Gestionados', 'imagina-updater-server'); ?></h2>

    <?php if (empty($plugins)): ?>
        <div class="imagina-empty-state">
            <span class="dashicons dashicons-admin-plugins"></span>
            <p><?php _e('No hay plugins subidos aún. Sube tu primer plugin usando el formulario de arriba.', 'imagina-updater-server'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Plugin', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Slug', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Versión', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Autor', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Última Actualización', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Tamaño', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Acciones', 'imagina-updater-server'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $plugin):
                    $effective_slug = !empty($plugin->slug_override) ? $plugin->slug_override : $plugin->slug;
                    $is_custom_slug = !empty($plugin->slug_override);
                ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($plugin->name); ?></strong>
                            <?php if (!empty($plugin->description)): ?>
                                <br><small><?php echo esc_html($plugin->description); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code><?php echo esc_html($effective_slug); ?></code>
                            <?php if ($is_custom_slug): ?>
                                <span class="dashicons dashicons-edit" style="color: #2271b1;" title="<?php esc_attr_e('Slug personalizado', 'imagina-updater-server'); ?>"></span>
                            <?php endif; ?>
                            <br>
                            <small style="color: #666;">
                                <?php _e('Auto:', 'imagina-updater-server'); ?> <code><?php echo esc_html($plugin->slug); ?></code>
                            </small>
                            <br>
                            <a href="#" class="edit-slug-link" data-plugin-id="<?php echo esc_attr($plugin->id); ?>" data-current-slug="<?php echo esc_attr($effective_slug); ?>" style="font-size: 11px;">
                                <?php _e('Editar slug', 'imagina-updater-server'); ?>
                            </a>
                            <div class="slug-edit-form" id="slug-edit-<?php echo $plugin->id; ?>" style="display:none; margin-top:5px;">
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('update_slug_' . $plugin->id); ?>
                                    <input type="hidden" name="plugin_id" value="<?php echo esc_attr($plugin->id); ?>">
                                    <input type="text" name="new_slug" value="<?php echo esc_attr($effective_slug); ?>" style="width:150px;" placeholder="<?php echo esc_attr($plugin->slug); ?>">
                                    <button type="submit" name="imagina_update_slug" class="button button-small"><?php _e('Guardar', 'imagina-updater-server'); ?></button>
                                    <button type="button" class="button button-small cancel-slug-edit"><?php _e('Cancelar', 'imagina-updater-server'); ?></button>
                                    <br><small><?php _e('Deja vacío para usar el slug auto-generado', 'imagina-updater-server'); ?></small>
                                </form>
                            </div>
                        </td>
                        <td><strong><?php echo esc_html($plugin->current_version); ?></strong></td>
                        <td><?php echo esc_html($plugin->author); ?></td>
                        <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $plugin->uploaded_at)); ?></td>
                        <td><?php echo size_format($plugin->file_size); ?></td>
                        <td>
                            <a href="<?php echo esc_url(rest_url('imagina-updater/v1/download/' . $plugin->slug)); ?>" class="button button-small">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Descargar', 'imagina-updater-server'); ?>
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-plugins&action=delete_plugin&id=' . $plugin->id), 'delete_plugin_' . $plugin->id)); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('¿Estás seguro de eliminar este plugin?', 'imagina-updater-server'); ?>');">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Eliminar', 'imagina-updater-server'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
