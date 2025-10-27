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

    <?php settings_errors('imagina_updater'); ?>

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
                    <th><?php _e('Sitio', 'imagina-updater-server'); ?></th>
                    <th><?php _e('URL', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Estado', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Creada', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Último Uso', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Estadísticas', 'imagina-updater-server'); ?></th>
                    <th><?php _e('Acciones', 'imagina-updater-server'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($api_keys as $key): ?>
                    <?php $stats = Imagina_Updater_Server_API_Keys::get_usage_stats($key->id); ?>
                    <tr>
                        <td><strong><?php echo esc_html($key->site_name); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url($key->site_url); ?>" target="_blank">
                                <?php echo esc_html($key->site_url); ?>
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
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-api-keys&action=toggle_api_key&id=' . $key->id), 'toggle_api_key_' . $key->id)); ?>" class="button button-small">
                                <?php if ($key->is_active): ?>
                                    <span class="dashicons dashicons-dismiss"></span>
                                    <?php _e('Desactivar', 'imagina-updater-server'); ?>
                                <?php else: ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Activar', 'imagina-updater-server'); ?>
                                <?php endif; ?>
                            </a>
                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-api-keys&action=delete_api_key&id=' . $key->id), 'delete_api_key_' . $key->id)); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('¿Estás seguro de eliminar esta API Key?', 'imagina-updater-server'); ?>');">
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
