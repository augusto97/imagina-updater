<?php
/**
 * Vista: Logs del Servidor
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Logs del Servidor', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater'); ?>

    <?php if (!$is_enabled): ?>
        <div class="notice notice-warning">
            <p>
                <?php _e('El sistema de logs está desactivado.', 'imagina-updater-server'); ?>
                <a href="<?php echo admin_url('admin.php?page=imagina-updater-settings'); ?>">
                    <?php _e('Activar logging', 'imagina-updater-server'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <div class="imagina-logs-container">
        <div class="tablenav top">
            <div class="alignleft actions">
                <?php if (!empty($logs)): ?>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=imagina-updater-logs&action=download_log'), 'download_log'); ?>" class="button">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Descargar Log', 'imagina-updater-server'); ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="alignright">
                <?php if (!empty($logs)): ?>
                    <form method="post" style="display:inline;">
                        <?php wp_nonce_field('imagina_clear_logs'); ?>
                        <button type="submit" name="imagina_clear_logs" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e('¿Estás seguro de eliminar todos los logs?', 'imagina-updater-server'); ?>');">
                            <span class="dashicons dashicons-trash"></span>
                            <?php _e('Limpiar Logs', 'imagina-updater-server'); ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($logs)): ?>
            <div class="imagina-empty-state">
                <span class="dashicons dashicons-media-text"></span>
                <p><?php _e('No hay logs disponibles', 'imagina-updater-server'); ?></p>
            </div>
        <?php else: ?>
            <div class="imagina-logs-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 15px; font-family: 'Courier New', monospace; font-size: 13px; border-radius: 3px; max-height: 600px; overflow-y: auto;">
                <?php foreach ($logs as $log_line): ?>
                    <?php
                    // Colorear según nivel de log
                    $color = '#d4d4d4'; // Default
                    if (strpos($log_line, '[ERROR]') !== false) {
                        $color = '#f48771';
                    } elseif (strpos($log_line, '[WARNING]') !== false) {
                        $color = '#dcdcaa';
                    } elseif (strpos($log_line, '[INFO]') !== false) {
                        $color = '#4ec9b0';
                    } elseif (strpos($log_line, '[DEBUG]') !== false) {
                        $color = '#9cdcfe';
                    }
                    ?>
                    <div style="color: <?php echo esc_attr($color); ?>; margin-bottom: 2px; word-wrap: break-word;">
                        <?php echo esc_html($log_line); ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="description" style="margin-top: 15px;">
            <?php printf(
                __('Mostrando las últimas %d líneas de log.', 'imagina-updater-server'),
                count($logs)
            ); ?>
            <?php if ($is_enabled): ?>
                <?php _e('Los logs se rotan automáticamente cuando alcanzan 5MB.', 'imagina-updater-server'); ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<style>
.imagina-logs-container {
    margin-top: 20px;
}

.imagina-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 3px;
}

.imagina-empty-state .dashicons {
    font-size: 64px;
    width: 64px;
    height: 64px;
    color: #ddd;
    margin-bottom: 10px;
}

.imagina-empty-state p {
    color: #666;
    font-size: 16px;
}

.imagina-logs-viewer {
    border: 1px solid #ddd;
}

.imagina-logs-viewer::-webkit-scrollbar {
    width: 10px;
}

.imagina-logs-viewer::-webkit-scrollbar-track {
    background: #2d2d30;
}

.imagina-logs-viewer::-webkit-scrollbar-thumb {
    background: #555;
    border-radius: 5px;
}

.imagina-logs-viewer::-webkit-scrollbar-thumb:hover {
    background: #777;
}
</style>
