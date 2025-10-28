<?php
/**
 * Vista: Logs del Sistema
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>
        <?php _e('Imagina Updater - Logs del Sistema', 'imagina-updater-client'); ?>
    </h1>

    <?php settings_errors('imagina_updater_client', false); ?>

    <?php if (!$is_enabled): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('Logging Desactivado', 'imagina-updater-client'); ?></strong>
                <?php _e('El sistema de logs está desactivado. Actívalo en la ', 'imagina-updater-client'); ?>
                <a href="<?php echo admin_url('options-general.php?page=imagina-updater-client'); ?>">
                    <?php _e('página de configuración', 'imagina-updater-client'); ?>
                </a>.
            </p>
        </div>
    <?php endif; ?>

    <div class="imagina-logs-container">
        <!-- Información del Log -->
        <div class="imagina-card" style="margin-bottom: 20px;">
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-info"></span>
                <?php _e('Información', 'imagina-updater-client'); ?>
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <strong><?php _e('Estado:', 'imagina-updater-client'); ?></strong>
                    <span style="color: <?php echo $is_enabled ? '#46b450' : '#dc3232'; ?>;">
                        <?php echo $is_enabled ? __('Activado', 'imagina-updater-client') : __('Desactivado', 'imagina-updater-client'); ?>
                    </span>
                </div>

                <div>
                    <strong><?php _e('Tamaño del Log:', 'imagina-updater-client'); ?></strong>
                    <?php echo size_format($log_size); ?>
                </div>

                <div>
                    <strong><?php _e('Archivos:', 'imagina-updater-client'); ?></strong>
                    <?php echo count($log_files); ?>
                </div>

                <div>
                    <strong><?php _e('Ubicación:', 'imagina-updater-client'); ?></strong>
                    <code>wp-content/uploads/imagina-updater-logs/</code>
                </div>
            </div>

            <div style="margin-top: 15px;">
                <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=imagina-updater-client-logs&action=download_log'), 'download_log', 'nonce'); ?>"
                   class="button"
                   <?php disabled(empty($log_content)); ?>>
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Descargar Log', 'imagina-updater-client'); ?>
                </a>

                <form method="post" style="display: inline-block; margin-left: 10px;">
                    <?php wp_nonce_field('imagina_clear_logs'); ?>
                    <button type="submit"
                            name="imagina_clear_logs"
                            class="button button-link-delete"
                            onclick="return confirm('<?php esc_attr_e('¿Estás seguro de eliminar todos los logs?', 'imagina-updater-client'); ?>');"
                            <?php disabled(empty($log_content)); ?>>
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Limpiar Logs', 'imagina-updater-client'); ?>
                    </button>
                </form>

                <button type="button"
                        class="button"
                        onclick="location.reload();"
                        style="margin-left: 10px;">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refrescar', 'imagina-updater-client'); ?>
                </button>
            </div>
        </div>

        <!-- Lista de Archivos de Log -->
        <?php if (!empty($log_files)): ?>
            <div class="imagina-card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0;">
                    <span class="dashicons dashicons-media-document"></span>
                    <?php _e('Archivos de Log', 'imagina-updater-client'); ?>
                </h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Archivo', 'imagina-updater-client'); ?></th>
                            <th><?php _e('Tamaño', 'imagina-updater-client'); ?></th>
                            <th><?php _e('Última Modificación', 'imagina-updater-client'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log_files as $file): ?>
                            <tr>
                                <td><code><?php echo esc_html($file['name']); ?></code></td>
                                <td><?php echo size_format($file['size']); ?></td>
                                <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $file['modified']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p class="description">
                    <?php _e('Los archivos se rotan automáticamente cuando superan los 5MB. Se mantienen máximo 5 archivos.', 'imagina-updater-client'); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Visualizador de Log -->
        <div class="imagina-card">
            <h2 style="margin-top: 0;">
                <span class="dashicons dashicons-media-text"></span>
                <?php _e('Contenido del Log', 'imagina-updater-client'); ?>
                <span style="font-size: 13px; font-weight: normal; color: #666;">
                    (<?php _e('Últimas 1000 líneas', 'imagina-updater-client'); ?>)
                </span>
            </h2>

            <?php if (empty($log_content)): ?>
                <div style="padding: 40px; text-align: center; background: #f9f9f9; border: 1px dashed #ccc; border-radius: 4px;">
                    <span class="dashicons dashicons-admin-page" style="font-size: 48px; color: #ddd; width: 48px; height: 48px;"></span>
                    <p style="color: #666; margin: 10px 0 0 0;">
                        <?php _e('No hay logs registrados aún.', 'imagina-updater-client'); ?>
                    </p>
                    <?php if (!$is_enabled): ?>
                        <p style="color: #666; margin: 5px 0 0 0;">
                            <?php _e('Activa el logging para comenzar a registrar eventos.', 'imagina-updater-client'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 10px;">
                    <label>
                        <input type="checkbox" id="auto-scroll" checked>
                        <?php _e('Auto-scroll al final', 'imagina-updater-client'); ?>
                    </label>
                    <span style="margin-left: 20px;">
                        <label for="filter-level"><?php _e('Filtrar por nivel:', 'imagina-updater-client'); ?></label>
                        <select id="filter-level" style="margin-left: 5px;">
                            <option value=""><?php _e('Todos', 'imagina-updater-client'); ?></option>
                            <option value="DEBUG">DEBUG</option>
                            <option value="INFO">INFO</option>
                            <option value="WARNING">WARNING</option>
                            <option value="ERROR">ERROR</option>
                        </select>
                    </span>
                </div>

                <div id="log-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; line-height: 1.6; max-height: 600px; overflow-y: auto;">
                    <?php
                    $lines = explode("\n", trim($log_content));
                    foreach ($lines as $line) {
                        if (empty(trim($line))) continue;

                        // Colorear según nivel
                        $color = '#d4d4d4';
                        if (strpos($line, '[ERROR]') !== false) {
                            $color = '#f48771';
                        } elseif (strpos($line, '[WARNING]') !== false) {
                            $color = '#dcdcaa';
                        } elseif (strpos($line, '[INFO]') !== false) {
                            $color = '#4ec9b0';
                        } elseif (strpos($line, '[DEBUG]') !== false) {
                            $color = '#9cdcfe';
                        }

                        // Determinar clase de nivel para filtrado
                        $level_class = '';
                        if (preg_match('/\[(DEBUG|INFO|WARNING|ERROR)\]/', $line, $matches)) {
                            $level_class = ' data-level="' . $matches[1] . '"';
                        }

                        echo '<div class="log-line"' . $level_class . ' style="color: ' . $color . '; margin-bottom: 2px;">';
                        echo esc_html($line);
                        echo '</div>';
                    }
                    ?>
                </div>

                <script>
                jQuery(document).ready(function($) {
                    // Auto-scroll
                    var logViewer = $('#log-viewer');
                    if ($('#auto-scroll').is(':checked')) {
                        logViewer.scrollTop(logViewer[0].scrollHeight);
                    }

                    $('#auto-scroll').on('change', function() {
                        if ($(this).is(':checked')) {
                            logViewer.scrollTop(logViewer[0].scrollHeight);
                        }
                    });

                    // Filtro por nivel
                    $('#filter-level').on('change', function() {
                        var level = $(this).val();
                        if (level === '') {
                            $('.log-line').show();
                        } else {
                            $('.log-line').hide();
                            $('.log-line[data-level="' + level + '"]').show();
                        }

                        // Auto-scroll si está activado
                        if ($('#auto-scroll').is(':checked')) {
                            logViewer.scrollTop(logViewer[0].scrollHeight);
                        }
                    });
                });
                </script>
            <?php endif; ?>
        </div>
    </div>

    <style>
        .imagina-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin-top: 20px;
        }

        #log-viewer::-webkit-scrollbar {
            width: 10px;
        }

        #log-viewer::-webkit-scrollbar-track {
            background: #2d2d2d;
        }

        #log-viewer::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 5px;
        }

        #log-viewer::-webkit-scrollbar-thumb:hover {
            background: #777;
        }
    </style>
</div>
