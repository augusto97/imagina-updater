<?php
/**
 * Vista: Gestión de API Keys
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Gestión de API Keys', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <?php if ($new_api_key): ?>
        <div class="notice notice-success">
            <h3><?php _e('¡API Key Creada Exitosamente!', 'imagina-updater-server'); ?></h3>
            <p><?php _e('Guarda esta API Key en un lugar seguro. No podrás verla nuevamente.', 'imagina-updater-server'); ?></p>
            <p class="imagina-api-key-display">
                <code><?php echo esc_html($new_api_key); ?></code>
                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($new_api_key); ?>'); this.textContent='<?php esc_attr_e('¡Copiado!', 'imagina-updater-server'); ?>'">
                    <?php _e('Copiar', 'imagina-updater-server'); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>

    <div class="imagina-create-section">
        <h2><?php _e('Crear Nueva API Key', 'imagina-updater-server'); ?></h2>

        <form method="post" class="imagina-create-form">
            <?php wp_nonce_field('imagina_create_api_key'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="site_name"><?php _e('Nombre del Sitio', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="site_name" id="site_name" class="regular-text" required>
                        <p class="description">
                            <?php _e('Nombre identificativo del sitio cliente (ej: "Sitio de Juan Pérez").', 'imagina-updater-server'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="site_url"><?php _e('URL del Sitio', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <input type="url" name="site_url" id="site_url" class="regular-text" placeholder="https://ejemplo.com" required>
                        <p class="description">
                            <?php _e('URL completa del sitio cliente.', 'imagina-updater-server'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php _e('Permisos de Acceso', 'imagina-updater-server'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="access_type" value="all" checked>
                                <strong><?php _e('Todos los plugins', 'imagina-updater-server'); ?></strong>
                                <p class="description"><?php _e('Acceso completo a todos los plugins disponibles', 'imagina-updater-server'); ?></p>
                            </label>
                            <br><br>

                            <label>
                                <input type="radio" name="access_type" value="specific">
                                <strong><?php _e('Plugins específicos', 'imagina-updater-server'); ?></strong>
                                <p class="description"><?php _e('Seleccionar plugins individuales', 'imagina-updater-server'); ?></p>
                            </label>
                            <div id="specific-plugins-box" style="display: none; margin: 10px 0 0 25px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                <?php if (empty($all_plugins)): ?>
                                    <p class="description"><?php _e('No hay plugins disponibles', 'imagina-updater-server'); ?></p>
                                <?php else: ?>
                                    <?php foreach ($all_plugins as $plugin): ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="allowed_plugins[]" value="<?php echo esc_attr($plugin->id); ?>">
                                            <?php echo esc_html($plugin->name); ?> <span class="description">(v<?php echo esc_html($plugin->current_version); ?>)</span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <br>

                            <label>
                                <input type="radio" name="access_type" value="groups">
                                <strong><?php _e('Grupos de plugins', 'imagina-updater-server'); ?></strong>
                                <p class="description"><?php _e('Seleccionar grupos completos de plugins', 'imagina-updater-server'); ?></p>
                            </label>
                            <div id="groups-box" style="display: none; margin: 10px 0 0 25px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                <?php if (empty($all_groups)): ?>
                                    <p class="description">
                                        <?php _e('No hay grupos creados.', 'imagina-updater-server'); ?>
                                        <a href="<?php echo admin_url('admin.php?page=imagina-updater-plugin-groups&action=new'); ?>">
                                            <?php _e('Crear grupo', 'imagina-updater-server'); ?>
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($all_groups as $group): ?>
                                        <?php $count = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_count($group->id); ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="allowed_groups[]" value="<?php echo esc_attr($group->id); ?>">
                                            <?php echo esc_html($group->name); ?> <span class="description">(<?php echo $count; ?> plugins)</span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </fieldset>

                        <script>
                        jQuery(document).ready(function($) {
                            $('input[name="access_type"]').on('change', function() {
                                $('#specific-plugins-box, #groups-box').hide();
                                if ($(this).val() === 'specific') {
                                    $('#specific-plugins-box').show();
                                } else if ($(this).val() === 'groups') {
                                    $('#groups-box').show();
                                }
                            });
                        });
                        </script>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="imagina_create_api_key" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Crear API Key', 'imagina-updater-server'); ?>
                </button>
            </p>
        </form>
    </div>

    <hr>

    <h2><?php _e('API Keys Existentes', 'imagina-updater-server'); ?></h2>

    <?php if (empty($api_keys)): ?>
        <div class="imagina-empty-state">
            <span class="dashicons dashicons-admin-network"></span>
            <p><?php _e('No hay API Keys creadas aún. Crea una usando el formulario de arriba.', 'imagina-updater-server'); ?></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 15%;"><?php _e('Sitio', 'imagina-updater-server'); ?></th>
                    <th style="width: 15%;"><?php _e('URL', 'imagina-updater-server'); ?></th>
                    <th style="width: 8%;"><?php _e('Estado', 'imagina-updater-server'); ?></th>
                    <th style="width: 18%;"><?php _e('Permisos', 'imagina-updater-server'); ?></th>
                    <th style="width: 8%;"><?php _e('Creada', 'imagina-updater-server'); ?></th>
                    <th style="width: 10%;"><?php _e('Último Uso', 'imagina-updater-server'); ?></th>
                    <th style="width: 10%;"><?php _e('Estadísticas', 'imagina-updater-server'); ?></th>
                    <th style="width: 16%;"><?php _e('Acciones', 'imagina-updater-server'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($api_keys as $key): ?>
                    <?php
                    $stats = Imagina_Updater_Server_API_Keys::get_usage_stats($key->id);

                    // Determinar permisos
                    $access_type = isset($key->access_type) ? $key->access_type : 'all';
                    $permission_text = '';
                    $permission_detail = '';

                    if ($access_type === 'all') {
                        $permission_text = __('Todos los plugins', 'imagina-updater-server');
                        $permission_detail = __('Acceso completo', 'imagina-updater-server');
                    } elseif ($access_type === 'specific' && !empty($key->allowed_plugins)) {
                        $allowed_ids = json_decode($key->allowed_plugins, true);
                        $count = is_array($allowed_ids) ? count($allowed_ids) : 0;
                        $permission_text = __('Plugins específicos', 'imagina-updater-server');
                        $permission_detail = sprintf(_n('%d plugin', '%d plugins', $count, 'imagina-updater-server'), $count);
                    } elseif ($access_type === 'groups' && !empty($key->allowed_groups)) {
                        $allowed_group_ids = json_decode($key->allowed_groups, true);
                        $count = is_array($allowed_group_ids) ? count($allowed_group_ids) : 0;
                        $permission_text = __('Grupos de plugins', 'imagina-updater-server');
                        $permission_detail = sprintf(_n('%d grupo', '%d grupos', $count, 'imagina-updater-server'), $count);
                    } else {
                        $permission_text = __('Sin permisos', 'imagina-updater-server');
                        $permission_detail = __('Ningún plugin asignado', 'imagina-updater-server');
                    }
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($key->site_name); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url($key->site_url); ?>" target="_blank" title="<?php echo esc_attr($key->site_url); ?>">
                                <?php echo esc_html(wp_trim_words($key->site_url, 3, '...')); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($key->is_active): ?>
                                <span class="imagina-status imagina-status-active">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Activa', 'imagina-updater-server'); ?>
                                </span>
                            <?php else: ?>
                                <span class="imagina-status imagina-status-inactive">
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Inactiva', 'imagina-updater-server'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($permission_text); ?></strong><br>
                            <small class="description"><?php echo esc_html($permission_detail); ?></small>
                        </td>
                        <td><?php echo esc_html(mysql2date(get_option('date_format'), $key->created_at)); ?></td>
                        <td>
                            <?php if ($key->last_used): ?>
                                <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $key->last_used)); ?>
                            <?php else: ?>
                                <span class="description"><?php _e('Nunca', 'imagina-updater-server'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($stats['total_downloads']); ?></strong> <?php _e('descargas', 'imagina-updater-server'); ?>
                            <br>
                            <small><?php echo esc_html($stats['last_30_days']); ?> <?php _e('últimos 30 días', 'imagina-updater-server'); ?></small>
                        </td>
                        <td>
                            <a href="#" class="button button-small edit-permissions-btn" data-key-id="<?php echo esc_attr($key->id); ?>">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php _e('Permisos', 'imagina-updater-server'); ?>
                            </a>
                            <br>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-api-keys&action=toggle_api_key&id=' . $key->id), 'toggle_api_key_' . $key->id)); ?>" class="button button-small">
                                <?php if ($key->is_active): ?>
                                    <?php _e('Desactivar', 'imagina-updater-server'); ?>
                                <?php else: ?>
                                    <?php _e('Activar', 'imagina-updater-server'); ?>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-api-keys&action=delete_api_key&id=' . $key->id), 'delete_api_key_' . $key->id)); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('¿Estás seguro de eliminar esta API Key?', 'imagina-updater-server'); ?>');">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('Eliminar', 'imagina-updater-server'); ?>
                            </a>
                        </td>
                    </tr>
                    <!-- Row expandable para editar permisos -->
                    <tr id="permissions-row-<?php echo esc_attr($key->id); ?>" class="permissions-edit-row" style="display: none;">
                        <td colspan="8" style="background: #f9f9f9; padding: 20px;">
                            <h3><?php _e('Editar Permisos', 'imagina-updater-server'); ?> - <?php echo esc_html($key->site_name); ?></h3>
                            <form method="post">
                                <?php wp_nonce_field('imagina_update_api_permissions'); ?>
                                <input type="hidden" name="api_key_id" value="<?php echo esc_attr($key->id); ?>">

                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php _e('Tipo de Acceso', 'imagina-updater-server'); ?></th>
                                        <td>
                                            <fieldset>
                                                <label>
                                                    <input type="radio" name="access_type" value="all" <?php checked($access_type, 'all'); ?>>
                                                    <strong><?php _e('Todos los plugins', 'imagina-updater-server'); ?></strong>
                                                </label>
                                                <br><br>

                                                <label>
                                                    <input type="radio" name="access_type" value="specific" <?php checked($access_type, 'specific'); ?>>
                                                    <strong><?php _e('Plugins específicos', 'imagina-updater-server'); ?></strong>
                                                </label>
                                                <div class="specific-plugins-box-edit" style="<?php echo $access_type === 'specific' ? '' : 'display: none;'; ?> margin: 10px 0 0 25px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                                    <?php
                                                    $current_allowed_plugins = !empty($key->allowed_plugins) ? json_decode($key->allowed_plugins, true) : array();
                                                    if (empty($all_plugins)): ?>
                                                        <p class="description"><?php _e('No hay plugins disponibles', 'imagina-updater-server'); ?></p>
                                                    <?php else: ?>
                                                        <?php foreach ($all_plugins as $plugin): ?>
                                                            <label style="display: block; margin-bottom: 5px;">
                                                                <input type="checkbox" name="allowed_plugins[]" value="<?php echo esc_attr($plugin->id); ?>" <?php checked(in_array($plugin->id, $current_allowed_plugins)); ?>>
                                                                <?php echo esc_html($plugin->name); ?> <span class="description">(v<?php echo esc_html($plugin->current_version); ?>)</span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <br>

                                                <label>
                                                    <input type="radio" name="access_type" value="groups" <?php checked($access_type, 'groups'); ?>>
                                                    <strong><?php _e('Grupos de plugins', 'imagina-updater-server'); ?></strong>
                                                </label>
                                                <div class="groups-box-edit" style="<?php echo $access_type === 'groups' ? '' : 'display: none;'; ?> margin: 10px 0 0 25px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                                    <?php
                                                    $current_allowed_groups = !empty($key->allowed_groups) ? json_decode($key->allowed_groups, true) : array();
                                                    if (empty($all_groups)): ?>
                                                        <p class="description"><?php _e('No hay grupos creados', 'imagina-updater-server'); ?></p>
                                                    <?php else: ?>
                                                        <?php foreach ($all_groups as $group): ?>
                                                            <?php $count = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_count($group->id); ?>
                                                            <label style="display: block; margin-bottom: 5px;">
                                                                <input type="checkbox" name="allowed_groups[]" value="<?php echo esc_attr($group->id); ?>" <?php checked(in_array($group->id, $current_allowed_groups)); ?>>
                                                                <?php echo esc_html($group->name); ?> <span class="description">(<?php echo $count; ?> plugins)</span>
                                                            </label>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </fieldset>
                                        </td>
                                    </tr>
                                </table>

                                <p class="submit">
                                    <button type="submit" name="imagina_update_api_permissions" class="button button-primary">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Guardar Permisos', 'imagina-updater-server'); ?>
                                    </button>
                                    <button type="button" class="button cancel-permissions-btn" data-key-id="<?php echo esc_attr($key->id); ?>">
                                        <?php _e('Cancelar', 'imagina-updater-server'); ?>
                                    </button>
                                </p>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
        jQuery(document).ready(function($) {
            // Toggle inline edit form for permissions
            $('.edit-permissions-btn').on('click', function(e) {
                e.preventDefault();
                var keyId = $(this).data('key-id');
                $('.permissions-edit-row').not('#permissions-row-' + keyId).hide();
                $('#permissions-row-' + keyId).toggle();
            });

            $('.cancel-permissions-btn').on('click', function(e) {
                e.preventDefault();
                var keyId = $(this).data('key-id');
                $('#permissions-row-' + keyId).hide();
            });

            // Handle access type change in edit forms
            $('.permissions-edit-row input[name="access_type"]').on('change', function() {
                var form = $(this).closest('form');
                form.find('.specific-plugins-box-edit, .groups-box-edit').hide();
                if ($(this).val() === 'specific') {
                    form.find('.specific-plugins-box-edit').show();
                } else if ($(this).val() === 'groups') {
                    form.find('.groups-box-edit').show();
                }
            });
        });
        </script>
    <?php endif; ?>
</div>

