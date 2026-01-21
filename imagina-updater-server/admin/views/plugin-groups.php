<?php
/**
 * Vista: Gestión de Grupos de Plugins
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>
        <?php esc_html_e('Grupos de Plugins', 'imagina-updater-server'); ?>
        <?php if ($action !== 'edit' && $action !== 'new'): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups&action=new')); ?>" class="page-title-action">
                <?php esc_html_e('Añadir Nuevo', 'imagina-updater-server'); ?>
            </a>
        <?php endif; ?>
    </h1>

    <?php settings_errors('imagina_updater', false); ?>

    <?php if ($action === 'new' || $action === 'edit'): ?>
        <!-- Formulario de crear/editar grupo -->
        <div class="imagina-create-section">
            <h2>
                <?php if ($action === 'edit'): ?>
                    <?php esc_html_e('Editar Grupo', 'imagina-updater-server'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups')); ?>" class="button">
                        <?php esc_html_e('← Volver a la lista', 'imagina-updater-server'); ?>
                    </a>
                <?php else: ?>
                    <?php esc_html_e('Crear Nuevo Grupo', 'imagina-updater-server'); ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups')); ?>" class="button">
                        <?php esc_html_e('← Cancelar', 'imagina-updater-server'); ?>
                    </a>
                <?php endif; ?>
            </h2>

            <form method="post" class="imagina-create-form">
                <?php if ($action === 'edit'): ?>
                    <?php wp_nonce_field('imagina_update_group'); ?>
                    <input type="hidden" name="group_id" value="<?php echo esc_attr($editing_group->id); ?>">
                <?php else: ?>
                    <?php wp_nonce_field('imagina_create_group'); ?>
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="group_name"><?php esc_html_e('Nombre del Grupo', 'imagina-updater-server'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="group_name" id="group_name" class="regular-text"
                                   value="<?php echo $editing_group ? esc_attr($editing_group->name) : ''; ?>" required>
                            <p class="description">
                                <?php esc_html_e('Nombre identificativo del grupo (ej: "Plugins Premium", "Plugins Gratuitos").', 'imagina-updater-server'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="group_description"><?php esc_html_e('Descripción', 'imagina-updater-server'); ?></label>
                        </th>
                        <td>
                            <textarea name="group_description" id="group_description" class="large-text" rows="3"><?php echo $editing_group ? esc_textarea($editing_group->description) : ''; ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Descripción opcional del grupo.', 'imagina-updater-server'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Plugins', 'imagina-updater-server'); ?>
                        </th>
                        <td>
                            <?php if (empty($all_plugins)): ?>
                                <p class="description">
                                    <?php esc_html_e('No hay plugins disponibles. Sube plugins primero.', 'imagina-updater-server'); ?>
                                </p>
                            <?php else: ?>
                                <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                    <?php
                                    $selected_ids = $editing_group ? $editing_group->plugin_ids : array();
                                    foreach ($all_plugins as $plugin):
                                        $is_selected = in_array($plugin->id, $selected_ids);
                                    ?>
                                        <label style="display: block; margin-bottom: 8px;">
                                            <input type="checkbox" name="plugin_ids[]" value="<?php echo esc_attr($plugin->id); ?>" <?php checked($is_selected); ?>>
                                            <strong><?php echo esc_html($plugin->name); ?></strong>
                                            <span class="description">(v<?php echo esc_html($plugin->current_version); ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">
                                    <?php esc_html_e('Selecciona los plugins que pertenecen a este grupo.', 'imagina-updater-server'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php if ($action === 'edit'): ?>
                        <button type="submit" name="imagina_update_group" class="button button-primary">
                            <span class="dashicons dashicons-yes"></span>
                            <?php esc_html_e('Actualizar Grupo', 'imagina-updater-server'); ?>
                        </button>
                    <?php else: ?>
                        <button type="submit" name="imagina_create_group" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php esc_html_e('Crear Grupo', 'imagina-updater-server'); ?>
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups')); ?>" class="button">
                        <?php esc_html_e('Cancelar', 'imagina-updater-server'); ?>
                    </a>
                </p>
            </form>
        </div>

    <?php else: ?>
        <!-- Lista de grupos existentes -->
        <p><?php esc_html_e('Organiza tus plugins en grupos para facilitar su gestión y asignación a API Keys.', 'imagina-updater-server'); ?></p>

        <?php if (empty($groups)): ?>
            <div class="imagina-empty-state">
                <span class="dashicons dashicons-category"></span>
                <p><?php esc_html_e('No hay grupos creados aún.', 'imagina-updater-server'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups&action=new')); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Crear Primer Grupo', 'imagina-updater-server'); ?>
                </a>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 25%;"><?php esc_html_e('Nombre', 'imagina-updater-server'); ?></th>
                        <th style="width: 40%;"><?php esc_html_e('Descripción', 'imagina-updater-server'); ?></th>
                        <th style="width: 15%;"><?php esc_html_e('Plugins', 'imagina-updater-server'); ?></th>
                        <th style="width: 20%;"><?php esc_html_e('Acciones', 'imagina-updater-server'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <?php $plugin_count = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_count($group->id); ?>
                        <tr>
                            <td><strong><?php echo esc_html($group->name); ?></strong></td>
                            <td>
                                <?php if (!empty($group->description)): ?>
                                    <?php echo esc_html($group->description); ?>
                                <?php else: ?>
                                    <span class="description"><?php esc_html_e('Sin descripción', 'imagina-updater-server'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="dashicons dashicons-admin-plugins"></span>
                                <strong><?php echo esc_html($plugin_count); ?></strong>
                                <?php echo $plugin_count === 1 ? esc_html__('plugin', 'imagina-updater-server') : esc_html__('plugins', 'imagina-updater-server'); ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups&action=edit&group_id=' . $group->id)); ?>" class="button button-small">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e('Editar', 'imagina-updater-server'); ?>
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-plugin-groups&action=delete_group&group_id=' . $group->id), 'delete_group_' . $group->id)); ?>"
                                   class="button button-small button-link-delete"
                                   onclick="return confirm('<?php esc_attr_e('¿Estás seguro de eliminar este grupo? Esta acción no se puede deshacer.', 'imagina-updater-server'); ?>');">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e('Eliminar', 'imagina-updater-server'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
