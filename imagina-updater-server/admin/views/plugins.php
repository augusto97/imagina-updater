<?php
/**
 * Vista: Gestión de Plugins
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Gestión de Plugins', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <div class="imagina-upload-section">
        <h2><?php esc_html_e('Subir Nuevo Plugin o Actualización', 'imagina-updater-server'); ?></h2>

        <form method="post" enctype="multipart/form-data" class="imagina-upload-form">
            <?php wp_nonce_field('imagina_upload_plugin'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="plugin_file"><?php esc_html_e('Archivo ZIP del Plugin', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <input type="file" name="plugin_file" id="plugin_file" accept=".zip" required>
                        <p class="description">
                            <?php esc_html_e('Sube el archivo ZIP del plugin. Si el plugin ya existe, se actualizará a la nueva versión.', 'imagina-updater-server'); ?>
                            <br>
                            <strong><?php esc_html_e('Tamaño máximo:', 'imagina-updater-server'); ?></strong>
                            <?php echo esc_html(min((int)ini_get('upload_max_filesize'), (int)ini_get('post_max_size'))); ?>
                            <span style="color: #646970; font-size: 11px;">
                                (upload_max_filesize: <?php echo esc_html(ini_get('upload_max_filesize')); ?>, post_max_size: <?php echo esc_html(ini_get('post_max_size')); ?>)
                            </span>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="changelog"><?php esc_html_e('Notas de la Versión (Opcional)', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <textarea name="changelog" id="changelog" rows="5" class="large-text" placeholder="<?php esc_attr_e('Describe los cambios en esta versión...', 'imagina-updater-server'); ?>"></textarea>
                        <p class="description">
                            <?php esc_html_e('Changelog o notas de la versión que se mostrarán a los clientes.', 'imagina-updater-server'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="plugin_groups"><?php esc_html_e('Categoría / Grupo (Opcional)', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <?php if (empty($all_groups)): ?>
                            <p class="description">
                                <?php esc_html_e('No hay grupos creados. ', 'imagina-updater-server'); ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups&action=new')); ?>" target="_blank">
                                    <?php esc_html_e('Crear primer grupo', 'imagina-updater-server'); ?>
                                </a>
                            </p>
                        <?php else: ?>
                            <select name="plugin_groups[]" id="plugin_groups" class="regular-text" multiple size="5" style="height: auto;">
                                <?php foreach ($all_groups as $group): ?>
                                    <option value="<?php echo esc_attr($group->id); ?>">
                                        <?php echo esc_html($group->name); ?>
                                        <?php if (!empty($group->description)): ?>
                                            - <?php echo esc_html($group->description); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Selecciona uno o más grupos a los que pertenece este plugin. Mantén presionado Ctrl (o Cmd en Mac) para seleccionar múltiples.', 'imagina-updater-server'); ?>
                                <br>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups')); ?>" target="_blank">
                                    <?php esc_html_e('Gestionar grupos', 'imagina-updater-server'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php do_action('imagina_updater_after_upload_form'); ?>
            </table>

            <p class="submit">
                <button type="submit" name="imagina_upload_plugin" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Subir Plugin', 'imagina-updater-server'); ?>
                </button>
            </p>
        </form>
    </div>

    <hr>

    <h2><?php esc_html_e('Plugins Gestionados', 'imagina-updater-server'); ?></h2>

    <?php if (empty($plugins)): ?>
        <div class="imagina-empty-state">
            <span class="dashicons dashicons-admin-plugins"></span>
            <p><?php esc_html_e('No hay plugins subidos aún. Sube tu primer plugin usando el formulario de arriba.', 'imagina-updater-server'); ?></p>
        </div>
    <?php else: ?>
        <!-- Toolbar: Búsqueda, Filtros y Columnas -->
        <div class="imagina-table-toolbar">
            <div class="imagina-table-search">
                <input type="text" placeholder="<?php esc_attr_e('Buscar plugins...', 'imagina-updater-server'); ?>">
            </div>

            <?php
            // Verificar si la extensión de licencias está activa (existe el campo is_premium)
            global $wpdb;
            $has_license_extension = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}imagina_updater_plugins LIKE 'is_premium'");
            if ($has_license_extension):
            ?>
            <div class="imagina-filter-dropdown">
                <select class="imagina-filter-select" data-filter="premium">
                    <option value=""><?php esc_html_e('Todos los plugins', 'imagina-updater-server'); ?></option>
                    <option value="premium"><?php esc_html_e('Solo Premium', 'imagina-updater-server'); ?></option>
                    <option value="free"><?php esc_html_e('Solo Gratuitos', 'imagina-updater-server'); ?></option>
                </select>
            </div>
            <?php endif; ?>

            <div class="imagina-column-toggle">
                <button type="button" class="imagina-column-toggle-btn">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e('Columnas', 'imagina-updater-server'); ?>
                </button>
                <div class="imagina-column-dropdown">
                    <label><input type="checkbox" data-col="1" checked> <?php esc_html_e('Plugin', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="2" checked> <?php esc_html_e('Slug', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="3" checked> <?php esc_html_e('Versión', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="4" checked> <?php esc_html_e('Categorías', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="5"> <?php esc_html_e('Autor', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="6"> <?php esc_html_e('Actualización', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="7"> <?php esc_html_e('Tamaño', 'imagina-updater-server'); ?></label>
                    <?php do_action('imagina_updater_plugins_column_toggles'); ?>
                </div>
            </div>

            <span class="imagina-table-count"><?php echo count($plugins); ?> registros</span>
        </div>

        <table id="plugins-table" class="wp-list-table widefat fixed striped imagina-table-enhanced">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'imagina-updater-server'); ?></th>
                    <th style="width: 180px;"><?php esc_html_e('Slug', 'imagina-updater-server'); ?></th>
                    <th style="width: 70px;"><?php esc_html_e('Versión', 'imagina-updater-server'); ?></th>
                    <th style="width: 130px;"><?php esc_html_e('Categorías', 'imagina-updater-server'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Autor', 'imagina-updater-server'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('Actualización', 'imagina-updater-server'); ?></th>
                    <th style="width: 70px;"><?php esc_html_e('Tamaño', 'imagina-updater-server'); ?></th>
                    <?php do_action('imagina_updater_plugins_table_header'); ?>
                    <th style="width: 90px;"><?php esc_html_e('Acciones', 'imagina-updater-server'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plugins as $plugin):
                    $effective_slug = !empty($plugin->slug_override) ? $plugin->slug_override : $plugin->slug;
                    $is_custom_slug = !empty($plugin->slug_override);
                    $is_premium = isset($plugin->is_premium) && $plugin->is_premium == 1;
                ?>
                    <tr data-premium="<?php echo $is_premium ? '1' : '0'; ?>">
                        <td>
                            <strong><?php echo esc_html($plugin->name); ?></strong>
                            <?php if (!empty($plugin->description)): ?>
                                <div class="imagina-desc-block">
                                    <a href="#" class="desc-toggle-link">ver descripción</a>
                                    <div class="desc-content" style="display:none;">
                                        <small><?php echo esc_html($plugin->description); ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <code style="font-size: 11px;"><?php echo esc_html($effective_slug); ?></code>
                            <?php if ($is_custom_slug): ?>
                                <span class="dashicons dashicons-edit" style="color: #2271b1; font-size: 12px;" title="<?php esc_attr_e('Slug personalizado', 'imagina-updater-server'); ?>"></span>
                            <?php endif; ?>
                            <br>
                            <a href="#" class="edit-slug-link" data-plugin-id="<?php echo esc_attr($plugin->id); ?>" data-current-slug="<?php echo esc_attr($effective_slug); ?>" style="font-size: 11px;">
                                <?php esc_html_e('editar', 'imagina-updater-server'); ?>
                            </a>
                            <div class="slug-edit-form" id="slug-edit-<?php echo $plugin->id; ?>" style="display:none; margin-top:5px;">
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field('update_slug_' . $plugin->id); ?>
                                    <input type="hidden" name="plugin_id" value="<?php echo esc_attr($plugin->id); ?>">
                                    <input type="text" name="new_slug" value="<?php echo esc_attr($effective_slug); ?>" style="width:120px; font-size: 11px;" placeholder="<?php echo esc_attr($plugin->slug); ?>">
                                    <button type="submit" name="imagina_update_slug" class="button button-small"><?php esc_html_e('OK', 'imagina-updater-server'); ?></button>
                                    <button type="button" class="button button-small cancel-slug-edit">✕</button>
                                </form>
                            </div>
                        </td>
                        <td><strong><?php echo esc_html($plugin->current_version); ?></strong></td>
                        <td>
                            <?php
                            $groups = isset($plugin_groups[$plugin->id]) ? $plugin_groups[$plugin->id] : array();
                            if (!empty($groups)):
                                foreach ($groups as $group):
                                    ?>
                                    <span class="imagina-group-badge" style="display: inline-block; background: #2271b1; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin: 1px;">
                                        <?php echo esc_html($group->name); ?>
                                    </span>
                                    <?php
                                endforeach;
                            else:
                                ?>
                                <span class="description" style="font-size: 11px;"><?php esc_html_e('Sin categoría', 'imagina-updater-server'); ?></span>
                                <?php
                            endif;
                            ?>
                        </td>
                        <td style="font-size: 12px;"><?php echo esc_html($plugin->author); ?></td>
                        <td style="font-size: 11px;"><?php echo esc_html(mysql2date('d/m/Y', $plugin->uploaded_at)); ?></td>
                        <td style="font-size: 11px;"><?php echo size_format($plugin->file_size); ?></td>
                        <?php do_action('imagina_updater_plugins_table_row', $plugin); ?>
                        <td>
                            <div class="imagina-actions-dropdown">
                                <button type="button" class="imagina-actions-btn">
                                    <?php esc_html_e('Acciones', 'imagina-updater-server'); ?>
                                    <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                </button>
                                <div class="imagina-actions-menu">
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-plugins&action=download_plugin&slug=' . urlencode($effective_slug)), 'download_plugin_' . $effective_slug)); ?>">
                                        <span class="dashicons dashicons-download"></span>
                                        <?php esc_html_e('Descargar', 'imagina-updater-server'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-plugins&action=delete_plugin&id=' . $plugin->id), 'delete_plugin_' . $plugin->id)); ?>" class="action-delete" onclick="return confirm('<?php esc_attr_e('¿Estás seguro de eliminar este plugin?', 'imagina-updater-server'); ?>');">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php esc_html_e('Eliminar', 'imagina-updater-server'); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
