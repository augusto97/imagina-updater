<?php
/**
 * Vista: Gestión de API Keys
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Gestión de API Keys', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <?php if ($new_api_key): ?>
        <div class="notice notice-success">
            <h3><?php esc_html_e('¡API Key Creada Exitosamente!', 'imagina-updater-server'); ?></h3>
            <p><?php esc_html_e('Guarda esta API Key en un lugar seguro. No podrás verla nuevamente.', 'imagina-updater-server'); ?></p>
            <p class="imagina-api-key-display">
                <code><?php echo esc_html($new_api_key); ?></code>
                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($new_api_key); ?>'); this.textContent='<?php esc_attr_e('¡Copiado!', 'imagina-updater-server'); ?>'">
                    <?php esc_html_e('Copiar', 'imagina-updater-server'); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>

    <?php
    $regenerated_api_key = get_transient('imagina_regenerated_api_key');
    if ($regenerated_api_key) {
        delete_transient('imagina_regenerated_api_key');
    ?>
        <div class="notice notice-warning">
            <h3><?php esc_html_e('¡API Key Regenerada Exitosamente!', 'imagina-updater-server'); ?></h3>
            <p><?php esc_html_e('Se ha generado una nueva API Key. La anterior ya no es válida. Guarda esta clave en un lugar seguro y actualiza la configuración en el cliente.', 'imagina-updater-server'); ?></p>
            <p class="imagina-api-key-display">
                <code><?php echo esc_html($regenerated_api_key); ?></code>
                <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo esc_js($regenerated_api_key); ?>'); this.textContent='<?php esc_attr_e('¡Copiado!', 'imagina-updater-server'); ?>'">
                    <?php esc_html_e('Copiar', 'imagina-updater-server'); ?>
                </button>
            </p>
        </div>
    <?php } ?>

    <div class="imagina-create-section">
        <h2><?php esc_html_e('Crear Nueva API Key', 'imagina-updater-server'); ?></h2>

        <form method="post" class="imagina-create-form">
            <?php wp_nonce_field('imagina_create_api_key'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="site_name"><?php esc_html_e('Cliente / Descripción', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <input type="text" name="site_name" id="site_name" class="regular-text" required>
                        <p class="description">
                            <?php esc_html_e('Nombre identificativo del cliente o propósito de la licencia (ej: "Juan Pérez - Paquete Premium", "Licencia Agencia XYZ").', 'imagina-updater-server'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="max_activations"><?php esc_html_e('Límite de Activaciones', 'imagina-updater-server'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="max_activations" id="max_activations" class="small-text" value="1" min="0" step="1">
                        <p class="description">
                            <?php esc_html_e('Número máximo de sitios donde se puede activar esta licencia. Usa 0 para activaciones ilimitadas.', 'imagina-updater-server'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php esc_html_e('Permisos de Acceso', 'imagina-updater-server'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="access_type" value="all" checked>
                                <strong><?php esc_html_e('Todos los plugins', 'imagina-updater-server'); ?></strong>
                                <p class="description"><?php esc_html_e('Acceso completo a todos los plugins disponibles', 'imagina-updater-server'); ?></p>
                            </label>
                            <br><br>

                            <label>
                                <input type="radio" name="access_type" value="specific">
                                <strong><?php esc_html_e('Plugins específicos', 'imagina-updater-server'); ?></strong>
                                <p class="description"><?php esc_html_e('Seleccionar plugins individuales', 'imagina-updater-server'); ?></p>
                            </label>
                            <div id="specific-plugins-box" style="display: none; margin: 10px 0 0 25px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                <?php if (empty($all_plugins)): ?>
                                    <p class="description"><?php esc_html_e('No hay plugins disponibles', 'imagina-updater-server'); ?></p>
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
                                <strong><?php esc_html_e('Grupos de plugins', 'imagina-updater-server'); ?></strong>
                                <p class="description"><?php esc_html_e('Seleccionar grupos completos de plugins', 'imagina-updater-server'); ?></p>
                            </label>
                            <div id="groups-box" style="display: none; margin: 10px 0 0 25px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                                <?php if (empty($all_groups)): ?>
                                    <p class="description">
                                        <?php esc_html_e('No hay grupos creados.', 'imagina-updater-server'); ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-plugin-groups&action=new')); ?>">
                                            <?php esc_html_e('Crear grupo', 'imagina-updater-server'); ?>
                                        </a>
                                    </p>
                                <?php else: ?>
                                    <?php foreach ($all_groups as $group): ?>
                                        <?php $count = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_count($group->id); ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox" name="allowed_groups[]" value="<?php echo esc_attr($group->id); ?>">
                                            <?php echo esc_html($group->name); ?> <span class="description">(<?php echo intval($count); ?> plugins)</span>
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
                    <?php esc_html_e('Crear API Key', 'imagina-updater-server'); ?>
                </button>
            </p>
        </form>
    </div>

    <hr>

    <h2><?php esc_html_e('API Keys Existentes', 'imagina-updater-server'); ?></h2>

    <?php if (empty($api_keys)): ?>
        <div class="imagina-empty-state">
            <span class="dashicons dashicons-admin-network"></span>
            <p><?php esc_html_e('No hay API Keys creadas aún. Crea una usando el formulario de arriba.', 'imagina-updater-server'); ?></p>
        </div>
    <?php else: ?>
        <!-- Toolbar: Búsqueda y Columnas -->
        <div class="imagina-table-toolbar">
            <div class="imagina-table-search">
                <input type="text" placeholder="<?php esc_attr_e('Buscar cliente, sitio...', 'imagina-updater-server'); ?>">
            </div>

            <div class="imagina-column-toggle">
                <button type="button" class="imagina-column-toggle-btn">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e('Columnas', 'imagina-updater-server'); ?>
                </button>
                <div class="imagina-column-dropdown">
                    <label><input type="checkbox" data-col="1" checked> <?php esc_html_e('Cliente', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="2" checked> <?php esc_html_e('Estado', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="3" checked> <?php esc_html_e('Activaciones', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="4" checked> <?php esc_html_e('Permisos', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="5"> <?php esc_html_e('Creada', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="6"> <?php esc_html_e('Último Uso', 'imagina-updater-server'); ?></label>
                    <label><input type="checkbox" data-col="7"> <?php esc_html_e('Estadísticas', 'imagina-updater-server'); ?></label>
                </div>
            </div>

            <span class="imagina-table-count"><?php echo count($api_keys); ?> registros</span>
        </div>

        <table id="apikeys-table" class="wp-list-table widefat fixed striped imagina-table-enhanced">
            <thead>
                <tr>
                    <th><?php esc_html_e('Cliente / Licencia', 'imagina-updater-server'); ?></th>
                    <th style="width: 75px;"><?php esc_html_e('Estado', 'imagina-updater-server'); ?></th>
                    <th style="width: 90px;"><?php esc_html_e('Activaciones', 'imagina-updater-server'); ?></th>
                    <th style="width: 140px;"><?php esc_html_e('Permisos', 'imagina-updater-server'); ?></th>
                    <th style="width: 85px;"><?php esc_html_e('Creada', 'imagina-updater-server'); ?></th>
                    <th style="width: 85px;"><?php esc_html_e('Último Uso', 'imagina-updater-server'); ?></th>
                    <th style="width: 90px;"><?php esc_html_e('Estadísticas', 'imagina-updater-server'); ?></th>
                    <th style="width: 90px;"><?php esc_html_e('Acciones', 'imagina-updater-server'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($api_keys as $key): ?>
                    <?php
                    $stats = Imagina_Updater_Server_API_Keys::get_usage_stats($key->id);

                    // Obtener activaciones
                    $active_count = Imagina_Updater_Server_Activations::count_active_activations($key->id);
                    $max_activations = isset($key->max_activations) ? (int)$key->max_activations : 1;

                    // Determinar permisos
                    $access_type = isset($key->access_type) ? $key->access_type : 'all';
                    $permission_text = '';
                    $permission_detail = '';

                    if ($access_type === 'all') {
                        $permission_text = __('Todos', 'imagina-updater-server');
                        $permission_detail = __('Acceso completo', 'imagina-updater-server');
                    } elseif ($access_type === 'specific' && !empty($key->allowed_plugins)) {
                        $allowed_ids = json_decode($key->allowed_plugins, true);
                        $count = is_array($allowed_ids) ? count($allowed_ids) : 0;
                        /* translators: %d: number of plugins */
                        $permission_text = sprintf(_n('%d plugin', '%d plugins', $count, 'imagina-updater-server'), $count);
                        $permission_detail = __('Específicos', 'imagina-updater-server');
                    } elseif ($access_type === 'groups' && !empty($key->allowed_groups)) {
                        $allowed_group_ids = json_decode($key->allowed_groups, true);
                        $count = is_array($allowed_group_ids) ? count($allowed_group_ids) : 0;
                        /* translators: %d: number of groups */
                        $permission_text = sprintf(_n('%d grupo', '%d grupos', $count, 'imagina-updater-server'), $count);
                        $permission_detail = __('Por grupos', 'imagina-updater-server');
                    } else {
                        $permission_text = __('Ninguno', 'imagina-updater-server');
                        $permission_detail = __('Sin acceso', 'imagina-updater-server');
                    }
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($key->site_name); ?></strong></td>
                        <td>
                            <?php if ($key->is_active): ?>
                                <span class="imagina-status imagina-status-active" style="font-size: 11px; padding: 2px 6px;">
                                    <?php esc_html_e('Activa', 'imagina-updater-server'); ?>
                                </span>
                            <?php else: ?>
                                <span class="imagina-status imagina-status-inactive" style="font-size: 11px; padding: 2px 6px;">
                                    <?php esc_html_e('Inactiva', 'imagina-updater-server'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <strong><?php echo esc_html($active_count); ?></strong> / <?php echo $max_activations == 0 ? '∞' : esc_html($max_activations); ?>
                            <?php if ($active_count > 0): ?>
                                <br><a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-activations&api_key_id=' . $key->id)); ?>" style="font-size: 11px;"><?php esc_html_e('ver', 'imagina-updater-server'); ?></a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="font-size: 12px;"><?php echo esc_html($permission_text); ?></strong><br>
                            <small class="description"><?php echo esc_html($permission_detail); ?></small>
                        </td>
                        <td style="font-size: 11px;"><?php echo esc_html(mysql2date('d/m/Y', $key->created_at)); ?></td>
                        <td style="font-size: 11px;">
                            <?php if ($key->last_used): ?>
                                <?php echo esc_html(mysql2date('d/m/Y', $key->last_used)); ?>
                            <?php else: ?>
                                <span class="description"><?php esc_html_e('Nunca', 'imagina-updater-server'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 11px;">
                            <strong><?php echo esc_html($stats['total_downloads']); ?></strong> <?php esc_html_e('desc.', 'imagina-updater-server'); ?>
                            <br><small><?php echo esc_html($stats['last_30_days']); ?> (30d)</small>
                        </td>
                        <td>
                            <div class="imagina-actions-dropdown">
                                <button type="button" class="imagina-actions-btn">
                                    <?php esc_html_e('Acciones', 'imagina-updater-server'); ?>
                                    <span class="dashicons dashicons-arrow-down-alt2" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                </button>
                                <div class="imagina-actions-menu">
                                    <a href="#" class="edit-permissions-btn" data-key-id="<?php echo esc_attr($key->id); ?>">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                        <?php esc_html_e('Editar Permisos', 'imagina-updater-server'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-api-keys&action=regenerate_api_key&id=' . $key->id), 'regenerate_api_key_' . $key->id)); ?>" onclick="return confirm('<?php esc_attr_e('¿Regenerar esta API Key?', 'imagina-updater-server'); ?>');">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php esc_html_e('Regenerar Key', 'imagina-updater-server'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-api-keys&action=toggle_api_key&id=' . $key->id), 'toggle_api_key_' . $key->id)); ?>">
                                        <span class="dashicons dashicons-<?php echo $key->is_active ? 'hidden' : 'visibility'; ?>"></span>
                                        <?php echo $key->is_active ? esc_html__('Desactivar', 'imagina-updater-server') : esc_html__('Activar', 'imagina-updater-server'); ?>
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-api-keys&action=delete_api_key&id=' . $key->id), 'delete_api_key_' . $key->id)); ?>" class="action-delete" onclick="return confirm('<?php esc_attr_e('¿Eliminar esta API Key?', 'imagina-updater-server'); ?>');">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php esc_html_e('Eliminar', 'imagina-updater-server'); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <!-- Row expandable para editar permisos -->
                    <tr id="permissions-row-<?php echo esc_attr($key->id); ?>" class="permissions-edit-row" style="display: none;">
                        <td colspan="7" style="background: #f9f9f9; padding: 20px;">
                            <h3><?php esc_html_e('Editar Permisos', 'imagina-updater-server'); ?> - <?php echo esc_html($key->site_name); ?></h3>
                            <form method="post">
                                <?php wp_nonce_field('imagina_update_api_permissions'); ?>
                                <input type="hidden" name="api_key_id" value="<?php echo esc_attr($key->id); ?>">

                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e('Tipo de Acceso', 'imagina-updater-server'); ?></th>
                                        <td>
                                            <fieldset>
                                                <label>
                                                    <input type="radio" name="access_type" value="all" <?php checked($access_type, 'all'); ?>>
                                                    <strong><?php esc_html_e('Todos los plugins', 'imagina-updater-server'); ?></strong>
                                                </label>
                                                <br><br>

                                                <label>
                                                    <input type="radio" name="access_type" value="specific" <?php checked($access_type, 'specific'); ?>>
                                                    <strong><?php esc_html_e('Plugins específicos', 'imagina-updater-server'); ?></strong>
                                                </label>
                                                <div class="specific-plugins-box-edit" style="<?php echo $access_type === 'specific' ? '' : 'display: none;'; ?> margin: 10px 0 0 25px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                                    <?php
                                                    $current_allowed_plugins = !empty($key->allowed_plugins) ? json_decode($key->allowed_plugins, true) : array();
                                                    if (empty($all_plugins)): ?>
                                                        <p class="description"><?php esc_html_e('No hay plugins disponibles', 'imagina-updater-server'); ?></p>
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
                                                    <strong><?php esc_html_e('Grupos de plugins', 'imagina-updater-server'); ?></strong>
                                                </label>
                                                <div class="groups-box-edit" style="<?php echo $access_type === 'groups' ? '' : 'display: none;'; ?> margin: 10px 0 0 25px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
                                                    <?php
                                                    $current_allowed_groups = !empty($key->allowed_groups) ? json_decode($key->allowed_groups, true) : array();
                                                    if (empty($all_groups)): ?>
                                                        <p class="description"><?php esc_html_e('No hay grupos creados', 'imagina-updater-server'); ?></p>
                                                    <?php else: ?>
                                                        <?php foreach ($all_groups as $group): ?>
                                                            <?php $count = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_count($group->id); ?>
                                                            <label style="display: block; margin-bottom: 5px;">
                                                                <input type="checkbox" name="allowed_groups[]" value="<?php echo esc_attr($group->id); ?>" <?php checked(in_array($group->id, $current_allowed_groups)); ?>>
                                                                <?php echo esc_html($group->name); ?> <span class="description">(<?php echo intval($count); ?> plugins)</span>
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
                                        <?php esc_html_e('Guardar Permisos', 'imagina-updater-server'); ?>
                                    </button>
                                    <button type="button" class="button cancel-permissions-btn" data-key-id="<?php echo esc_attr($key->id); ?>">
                                        <?php esc_html_e('Cancelar', 'imagina-updater-server'); ?>
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
                $('.permissions-edit-row').hide();
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

