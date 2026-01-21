<?php
/**
 * Vista: Configuración del Servidor
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Configuración de Imagina Updater Server', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <div class="imagina-settings-container">
        <div class="imagina-card">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-media-text"></span>
                <?php esc_html_e('Configuración de Logs', 'imagina-updater-server'); ?>
            </h2>

            <form method="post">
                <?php wp_nonce_field('imagina_save_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_logging"><?php esc_html_e('Habilitar Logging', 'imagina-updater-server'); ?></label>
                        </th>
                        <td>
                            <label for="enable_logging">
                                <input type="checkbox"
                                       name="enable_logging"
                                       id="enable_logging"
                                       value="1"
                                       <?php checked(isset($config['enable_logging']) && $config['enable_logging']); ?>>
                                <?php esc_html_e('Activar sistema de logs', 'imagina-updater-server'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Los logs te ayudarán a diagnosticar problemas con las subidas de plugins y las solicitudes de los clientes.', 'imagina-updater-server'); ?>
                                <?php if (isset($config['enable_logging']) && $config['enable_logging']): ?>
                                    <br>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=imagina-updater-logs')); ?>"><?php esc_html_e('Ver logs', 'imagina-updater-server'); ?></a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="log_level"><?php esc_html_e('Nivel de Log', 'imagina-updater-server'); ?></label>
                        </th>
                        <td>
                            <select name="log_level" id="log_level">
                                <option value="DEBUG" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'DEBUG'); ?>>
                                    <?php esc_html_e('DEBUG - Todos los mensajes', 'imagina-updater-server'); ?>
                                </option>
                                <option value="INFO" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'INFO'); ?>>
                                    <?php esc_html_e('INFO - Información y superiores', 'imagina-updater-server'); ?>
                                </option>
                                <option value="WARNING" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'WARNING'); ?>>
                                    <?php esc_html_e('WARNING - Advertencias y errores', 'imagina-updater-server'); ?>
                                </option>
                                <option value="ERROR" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'ERROR'); ?>>
                                    <?php esc_html_e('ERROR - Solo errores', 'imagina-updater-server'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Selecciona qué nivel de mensajes quieres registrar. DEBUG genera muchos logs, úsalo solo para diagnóstico.', 'imagina-updater-server'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="imagina_save_settings" class="button button-primary">
                        <?php esc_html_e('Guardar Configuración', 'imagina-updater-server'); ?>
                    </button>
                </p>
            </form>
        </div>

        <div class="imagina-card" style="margin-top: 20px;">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-database"></span>
                <?php esc_html_e('Mantenimiento de Base de Datos', 'imagina-updater-server'); ?>
            </h2>

            <div style="padding: 20px;">
                <p><?php esc_html_e('Si has actualizado el plugin y experimentas errores, ejecuta las migraciones de base de datos para actualizar las tablas.', 'imagina-updater-server'); ?></p>

                <form method="post">
                    <?php wp_nonce_field('imagina_run_migration'); ?>
                    <button type="submit" name="imagina_run_migration" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e('Ejecutar Migraciones de Base de Datos', 'imagina-updater-server'); ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="imagina-card" style="margin-top: 20px;">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-info"></span>
                <?php esc_html_e('Información del Sistema', 'imagina-updater-server'); ?>
            </h2>

            <table class="widefat">
                <tbody>
                    <tr>
                        <td style="width: 250px;"><strong><?php esc_html_e('Versión del Plugin:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo esc_html(IMAGINA_UPDATER_SERVER_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Tamaño máximo de subida:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Tamaño máximo de POST:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo esc_html(ini_get('post_max_size')); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Tiempo máximo de ejecución:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo esc_html(ini_get('max_execution_time')); ?>s</td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Versión de PHP:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Directorio de plugins:', 'imagina-updater-server'); ?></strong></td>
                        <td>
                            <?php
                            $upload_dir = wp_upload_dir();
                            echo esc_html($upload_dir['basedir'] . '/imagina-updater-plugins');
                            ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.imagina-settings-container {
    max-width: 900px;
}

.imagina-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    padding: 0;
    margin-top: 20px;
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

.imagina-card .form-table {
    margin-top: 0;
    padding: 20px;
}

.imagina-card p.submit {
    margin: 0;
    padding: 0 20px 20px 20px;
}

.imagina-card table.widefat {
    margin: 20px;
    width: calc(100% - 40px);
    border: 1px solid #ccd0d4;
}

.imagina-card table.widefat td {
    padding: 12px 15px;
    border-bottom: 1px solid #f0f0f1;
}

.imagina-card table.widefat tr:last-child td {
    border-bottom: none;
}
</style>
