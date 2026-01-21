<?php
/**
 * Vista: Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Imagina Updater Server - Dashboard', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <!-- Estadísticas Principales -->
    <div class="imagina-stats-grid">
        <div class="imagina-stat-card">
            <div class="imagina-stat-icon">
                <span class="dashicons dashicons-admin-plugins"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($total_plugins); ?></h3>
                <p><?php esc_html_e('Plugins Gestionados', 'imagina-updater-server'); ?></p>
                <?php if (!empty($license_stats['premium_plugins'])): ?>
                    <small style="color:#646970;">
                        <?php echo intval($license_stats['premium_plugins']); ?> premium,
                        <?php echo intval($license_stats['free_plugins']); ?> gratuitos
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <div class="imagina-stat-card">
            <div class="imagina-stat-icon">
                <span class="dashicons dashicons-admin-network"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($total_api_keys); ?></h3>
                <p><?php esc_html_e('API Keys Activas', 'imagina-updater-server'); ?></p>
                <?php if ($total_api_keys_inactive > 0): ?>
                    <small style="color:#646970;">
                        <?php echo intval($total_api_keys_inactive); ?> inactivas
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <div class="imagina-stat-card">
            <div class="imagina-stat-icon">
                <span class="dashicons dashicons-download"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($total_downloads); ?></h3>
                <p><?php esc_html_e('Descargas Totales', 'imagina-updater-server'); ?></p>
                <small style="color:#646970;">
                    <?php echo intval($downloads_week); ?> esta semana,
                    <?php echo intval($downloads_month); ?> este mes
                </small>
            </div>
        </div>

        <div class="imagina-stat-card">
            <div class="imagina-stat-icon">
                <span class="dashicons dashicons-location"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($total_activations); ?></h3>
                <p><?php esc_html_e('Sitios Activos', 'imagina-updater-server'); ?></p>
                <small style="color:#646970;">
                    <?php esc_html_e('con plugins instalados', 'imagina-updater-server'); ?>
                </small>
            </div>
        </div>
    </div>

    <?php if (!empty($license_stats) && !empty($license_stats['total_licenses'])): ?>
    <!-- Estadísticas de Licencias -->
    <h2 style="margin-top: 30px;">
        <span class="dashicons dashicons-lock" style="vertical-align: middle;"></span>
        <?php esc_html_e('Sistema de Licencias', 'imagina-updater-server'); ?>
    </h2>
    <div class="imagina-stats-grid">
        <div class="imagina-stat-card" style="border-left-color: #00a32a;">
            <div class="imagina-stat-icon" style="color: #00a32a;">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($license_stats['active_licenses']); ?></h3>
                <p><?php esc_html_e('Licencias Activas', 'imagina-updater-server'); ?></p>
            </div>
        </div>

        <div class="imagina-stat-card" style="border-left-color: #dba617;">
            <div class="imagina-stat-icon" style="color: #dba617;">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($license_stats['expired_licenses']); ?></h3>
                <p><?php esc_html_e('Licencias Expiradas', 'imagina-updater-server'); ?></p>
            </div>
        </div>

        <div class="imagina-stat-card" style="border-left-color: #d63638;">
            <div class="imagina-stat-icon" style="color: #d63638;">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($license_stats['revoked_licenses']); ?></h3>
                <p><?php esc_html_e('Licencias Revocadas', 'imagina-updater-server'); ?></p>
            </div>
        </div>

        <?php if (isset($license_stats['license_activations'])): ?>
        <div class="imagina-stat-card" style="border-left-color: #8c5cb7;">
            <div class="imagina-stat-icon" style="color: #8c5cb7;">
                <span class="dashicons dashicons-laptop"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($license_stats['license_activations']); ?></h3>
                <p><?php esc_html_e('Activaciones de Licencia', 'imagina-updater-server'); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Información Adicional -->
    <div class="imagina-info-boxes">
        <div class="imagina-info-box">
            <h2><?php esc_html_e('API Endpoint', 'imagina-updater-server'); ?></h2>
            <p><?php esc_html_e('URL base para las peticiones de los clientes:', 'imagina-updater-server'); ?></p>
            <code class="imagina-code-block"><?php echo esc_html(rest_url('imagina-updater/v1')); ?></code>
        </div>

        <div class="imagina-info-box">
            <h2><?php esc_html_e('Resumen Rápido', 'imagina-updater-server'); ?></h2>
            <table class="widefat" style="border: none; box-shadow: none;">
                <tr>
                    <td><span class="dashicons dashicons-category" style="color: #2271b1;"></span> <?php esc_html_e('Grupos de Plugins', 'imagina-updater-server'); ?></td>
                    <td style="text-align: right;"><strong><?php echo intval($total_groups); ?></strong></td>
                </tr>
                <?php if ($most_downloaded): ?>
                <tr>
                    <td><span class="dashicons dashicons-star-filled" style="color: #dba617;"></span> <?php esc_html_e('Plugin más descargado', 'imagina-updater-server'); ?></td>
                    <td style="text-align: right;"><strong><?php echo esc_html($most_downloaded->name); ?></strong> (<?php echo intval($most_downloaded->downloads); ?>)</td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($license_stats['premium_plugins'])): ?>
                <tr>
                    <td><span class="dashicons dashicons-awards" style="color: #00a32a;"></span> <?php esc_html_e('Plugins Premium', 'imagina-updater-server'); ?></td>
                    <td style="text-align: right;"><strong><?php echo intval($license_stats['premium_plugins']); ?></strong></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div class="imagina-info-box">
            <h2><?php esc_html_e('Inicio Rápido', 'imagina-updater-server'); ?></h2>
            <ol>
                <li><?php esc_html_e('Crea una API Key desde la sección "API Keys"', 'imagina-updater-server'); ?></li>
                <li><?php esc_html_e('Sube tus plugins desde la sección "Plugins"', 'imagina-updater-server'); ?></li>
                <li><?php esc_html_e('Instala el plugin cliente en tus sitios hijo', 'imagina-updater-server'); ?></li>
                <li><?php esc_html_e('Configura el plugin cliente con la URL del servidor y la API Key', 'imagina-updater-server'); ?></li>
            </ol>
        </div>
    </div>
</div>
