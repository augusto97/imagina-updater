<?php
/**
 * Vista: Gestión de Activaciones
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Gestión de Activaciones', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <div class="imagina-activations-container">
        <!-- Filtros -->
        <div class="imagina-card">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-filter"></span>
                <?php esc_html_e('Filtros', 'imagina-updater-server'); ?>
            </h2>

            <form method="get" style="padding: 20px;">
                <input type="hidden" name="page" value="imagina-updater-activations">

                <table class="form-table" style="margin-top: 0;">
                    <tr>
                        <th scope="row">
                            <label for="api_key_id"><?php esc_html_e('Filtrar por Licencia', 'imagina-updater-server'); ?></label>
                        </th>
                        <td>
                            <select name="api_key_id" id="api_key_id">
                                <option value=""><?php esc_html_e('Todas las licencias', 'imagina-updater-server'); ?></option>
                                <?php foreach ($api_keys as $key): ?>
                                    <option value="<?php echo esc_attr($key->id); ?>" <?php selected($api_key_id, $key->id); ?>>
                                        <?php echo esc_html($key->site_name); ?> (<?php echo esc_html(substr($key->api_key, 0, 15)); ?>...)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="button">
                                <?php esc_html_e('Filtrar', 'imagina-updater-server'); ?>
                            </button>
                            <?php if ($api_key_id): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-activations')); ?>" class="button">
                                    <?php esc_html_e('Limpiar filtros', 'imagina-updater-server'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </form>
        </div>

        <!-- Lista de activaciones -->
        <div class="imagina-card" style="margin-top: 20px;">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-admin-site-alt3"></span>
                <?php esc_html_e('Sitios Activados', 'imagina-updater-server'); ?>
            </h2>

            <?php if (empty($activations)): ?>
                <div class="imagina-empty-state">
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <p><?php esc_html_e('No hay activaciones registradas.', 'imagina-updater-server'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?php esc_html_e('Licencia', 'imagina-updater-server'); ?></th>
                            <th style="width: 20%;"><?php esc_html_e('Dominio', 'imagina-updater-server'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Estado', 'imagina-updater-server'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Activado', 'imagina-updater-server'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Última Verificación', 'imagina-updater-server'); ?></th>
                            <th style="width: 15%;"><?php esc_html_e('Desactivado', 'imagina-updater-server'); ?></th>
                            <th style="width: 10%;"><?php esc_html_e('Acciones', 'imagina-updater-server'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activations as $activation): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($activation->site_name); ?></strong>
                                    <br>
                                    <small class="description"><?php echo esc_html(substr($activation->api_key, 0, 15)); ?>...</small>
                                </td>
                                <td>
                                    <code><?php echo esc_html($activation->site_domain); ?></code>
                                </td>
                                <td>
                                    <?php if ($activation->is_active): ?>
                                        <span class="imagina-status imagina-status-active">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Activo', 'imagina-updater-server'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="imagina-status imagina-status-inactive">
                                            <span class="dashicons dashicons-dismiss"></span>
                                            <?php esc_html_e('Inactivo', 'imagina-updater-server'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $activation->activated_at)); ?>
                                </td>
                                <td>
                                    <?php if ($activation->last_verified): ?>
                                        <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $activation->last_verified)); ?>
                                    <?php else: ?>
                                        <span class="description"><?php esc_html_e('Nunca', 'imagina-updater-server'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($activation->deactivated_at): ?>
                                        <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $activation->deactivated_at)); ?>
                                    <?php else: ?>
                                        <span class="description">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($activation->is_active): ?>
                                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=imagina-updater-activations&action=deactivate_activation&id=' . $activation->id . ($api_key_id ? '&api_key_id=' . $api_key_id : '')), 'deactivate_activation_' . $activation->id)); ?>"
                                           class="button button-small button-link-delete"
                                           onclick="return confirm('<?php esc_attr_e('¿Estás seguro de desactivar este sitio? El cliente necesitará reactivar con la API key.', 'imagina-updater-server'); ?>');">
                                            <span class="dashicons dashicons-dismiss"></span>
                                            <?php esc_html_e('Desactivar', 'imagina-updater-server'); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="description"><?php esc_html_e('Desactivado', 'imagina-updater-server'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.imagina-activations-container {
    max-width: 1200px;
}

.imagina-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 0;
}

.imagina-card-title {
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #ccd0d4;
    font-size: 16px;
    font-weight: 600;
}

.imagina-card-title .dashicons {
    color: #2271b1;
    margin-right: 5px;
}

.imagina-empty-state {
    text-align: center;
    padding: 60px 20px;
}

.imagina-empty-state .dashicons {
    font-size: 80px;
    width: 80px;
    height: 80px;
    color: #c3c4c7;
    margin-bottom: 20px;
}

.imagina-empty-state p {
    color: #646970;
    font-size: 16px;
}

.imagina-status {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 13px;
    font-weight: 500;
}

.imagina-status-active {
    background: #d4edda;
    color: #155724;
}

.imagina-status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.imagina-status .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.imagina-card table.wp-list-table {
    margin: 0;
    border: 0;
}
</style>
