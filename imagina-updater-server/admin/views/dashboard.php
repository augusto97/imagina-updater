<?php
/**
 * Vista: Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Imagina Updater Server - Dashboard', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <div class="imagina-stats-grid">
        <div class="imagina-stat-card">
            <div class="imagina-stat-icon">
                <span class="dashicons dashicons-admin-plugins"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($total_plugins); ?></h3>
                <p><?php _e('Plugins Gestionados', 'imagina-updater-server'); ?></p>
            </div>
        </div>

        <div class="imagina-stat-card">
            <div class="imagina-stat-icon">
                <span class="dashicons dashicons-admin-network"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($total_api_keys); ?></h3>
                <p><?php _e('Sitios Conectados', 'imagina-updater-server'); ?></p>
            </div>
        </div>

        <div class="imagina-stat-card">
            <div class="imagina-stat-icon">
                <span class="dashicons dashicons-download"></span>
            </div>
            <div class="imagina-stat-content">
                <h3><?php echo esc_html($total_downloads); ?></h3>
                <p><?php _e('Descargas Totales', 'imagina-updater-server'); ?></p>
            </div>
        </div>
    </div>

    <div class="imagina-info-boxes">
        <div class="imagina-info-box">
            <h2><?php _e('API Endpoint', 'imagina-updater-server'); ?></h2>
            <p><?php _e('URL base para las peticiones de los clientes:', 'imagina-updater-server'); ?></p>
            <code class="imagina-code-block"><?php echo esc_html(rest_url('imagina-updater/v1')); ?></code>
        </div>

        <div class="imagina-info-box">
            <h2><?php _e('Inicio Rápido', 'imagina-updater-server'); ?></h2>
            <ol>
                <li><?php _e('Crea una API Key desde la sección "API Keys"', 'imagina-updater-server'); ?></li>
                <li><?php _e('Sube tus plugins desde la sección "Plugins"', 'imagina-updater-server'); ?></li>
                <li><?php _e('Instala el plugin cliente en tus sitios hijo', 'imagina-updater-server'); ?></li>
                <li><?php _e('Configura el plugin cliente con la URL del servidor y la API Key', 'imagina-updater-server'); ?></li>
            </ol>
        </div>
    </div>
</div>
