<?php
/**
 * Vista: Configuración del Servidor
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Configuración de Imagina Updater Server', 'imagina-updater-server'); ?></h1>

    <?php settings_errors('imagina_updater', false); ?>

    <div class="imagina-settings-container">
        <div class="imagina-card">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-media-text"></span>
                <?php _e('Configuración de Logs', 'imagina-updater-server'); ?>
            </h2>

            <form method="post">
                <?php wp_nonce_field('imagina_save_settings'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_logging"><?php _e('Habilitar Logging', 'imagina-updater-server'); ?></label>
                        </th>
                        <td>
                            <label for="enable_logging">
                                <input type="checkbox"
                                       name="enable_logging"
                                       id="enable_logging"
                                       value="1"
                                       <?php checked(isset($config['enable_logging']) && $config['enable_logging']); ?>>
                                <?php _e('Activar sistema de logs', 'imagina-updater-server'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Los logs te ayudarán a diagnosticar problemas con las subidas de plugins y las solicitudes de los clientes.', 'imagina-updater-server'); ?>
                                <?php if (isset($config['enable_logging']) && $config['enable_logging']): ?>
                                    <br>
                                    <a href="<?php echo admin_url('admin.php?page=imagina-updater-logs'); ?>"><?php _e('Ver logs', 'imagina-updater-server'); ?></a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="log_level"><?php _e('Nivel de Log', 'imagina-updater-server'); ?></label>
                        </th>
                        <td>
                            <select name="log_level" id="log_level">
                                <option value="DEBUG" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'DEBUG'); ?>>
                                    <?php _e('DEBUG - Todos los mensajes', 'imagina-updater-server'); ?>
                                </option>
                                <option value="INFO" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'INFO'); ?>>
                                    <?php _e('INFO - Información y superiores', 'imagina-updater-server'); ?>
                                </option>
                                <option value="WARNING" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'WARNING'); ?>>
                                    <?php _e('WARNING - Advertencias y errores', 'imagina-updater-server'); ?>
                                </option>
                                <option value="ERROR" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'ERROR'); ?>>
                                    <?php _e('ERROR - Solo errores', 'imagina-updater-server'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Selecciona qué nivel de mensajes quieres registrar. DEBUG genera muchos logs, úsalo solo para diagnóstico.', 'imagina-updater-server'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h3 style="margin: 20px 20px 0 20px; padding-top: 20px; border-top: 1px solid #ccd0d4;">
                    <span class="dashicons dashicons-shield" style="color: #2271b1;"></span>
                    <?php _e('Seguridad', 'imagina-updater-server'); ?>
                </h3>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="validate_domain"><?php _e('Validar Dominio', 'imagina-updater-server'); ?></label>
                        </th>
                        <td>
                            <label for="validate_domain">
                                <input type="checkbox"
                                       name="validate_domain"
                                       id="validate_domain"
                                       value="1"
                                       <?php checked(isset($config['validate_domain']) ? $config['validate_domain'] : true); ?>>
                                <?php _e('Validar que el dominio del cliente coincida con el registrado en la API Key', 'imagina-updater-server'); ?>
                            </label>
                            <p class="description">
                                <strong><?php _e('Recomendado: Habilitado', 'imagina-updater-server'); ?></strong><br>
                                <?php _e('Cuando está habilitado, el sistema verifica que las peticiones provengan del dominio registrado en la licencia.', 'imagina-updater-server'); ?>
                                <?php _e('Esto previene que un API Key robado sea usado desde otro dominio.', 'imagina-updater-server'); ?><br>
                                <strong style="color: #d63638;"><?php _e('Deshabilitar solo para pruebas o si tienes problemas con múltiples subdominios.', 'imagina-updater-server'); ?></strong>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="imagina_save_settings" class="button button-primary">
                        <?php _e('Guardar Configuración', 'imagina-updater-server'); ?>
                    </button>
                </p>
            </form>
        </div>

        <div class="imagina-card" style="margin-top: 20px;">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-database"></span>
                <?php _e('Mantenimiento de Base de Datos', 'imagina-updater-server'); ?>
            </h2>

            <div style="padding: 20px;">
                <p><?php _e('Si has actualizado el plugin y experimentas errores, ejecuta las migraciones de base de datos para actualizar las tablas.', 'imagina-updater-server'); ?></p>

                <form method="post">
                    <?php wp_nonce_field('imagina_run_migration'); ?>
                    <button type="submit" name="imagina_run_migration" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Ejecutar Migraciones de Base de Datos', 'imagina-updater-server'); ?>
                    </button>
                </form>
            </div>
        </div>

        <div class="imagina-card" style="margin-top: 20px;">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-info"></span>
                <?php _e('Información del Sistema', 'imagina-updater-server'); ?>
            </h2>

            <table class="widefat">
                <tbody>
                    <tr>
                        <td style="width: 250px;"><strong><?php _e('Versión del Plugin:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo IMAGINA_UPDATER_SERVER_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Tamaño máximo de subida:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo ini_get('upload_max_filesize'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Tamaño máximo de POST:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo ini_get('post_max_size'); ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Tiempo máximo de ejecución:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo ini_get('max_execution_time'); ?>s</td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Versión de PHP:', 'imagina-updater-server'); ?></strong></td>
                        <td><?php echo PHP_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td><strong><?php _e('Directorio de plugins:', 'imagina-updater-server'); ?></strong></td>
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
