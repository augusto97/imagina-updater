<?php
/**
 * Vista: Configuración del Cliente
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Imagina Updater Client - Configuración', 'imagina-updater-client'); ?></h1>

    <?php settings_errors('imagina_updater_client'); ?>

    <div class="imagina-client-container">
        <!-- Configuración de Conexión -->
        <div class="imagina-card">
            <h2 class="imagina-card-title">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Configuración de Conexión', 'imagina-updater-client'); ?>
            </h2>

            <form method="post">
                <?php wp_nonce_field('imagina_save_config'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="server_url"><?php _e('URL del Servidor', 'imagina-updater-client'); ?></label>
                        </th>
                        <td>
                            <input type="url"
                                   name="server_url"
                                   id="server_url"
                                   class="regular-text"
                                   value="<?php echo esc_attr($config['server_url']); ?>"
                                   placeholder="https://tu-servidor.com"
                                   required>
                            <p class="description">
                                <?php _e('URL completa del sitio donde está instalado el plugin servidor (sin /wp-json al final).', 'imagina-updater-client'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e('API Key', 'imagina-updater-client'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   name="api_key"
                                   id="api_key"
                                   class="regular-text"
                                   value="<?php echo esc_attr($config['api_key']); ?>"
                                   placeholder="ius_..."
                                   required>
                            <p class="description">
                                <?php _e('API Key proporcionada por el administrador del servidor central.', 'imagina-updater-client'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="imagina_save_config" class="button button-primary">
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('Guardar Configuración', 'imagina-updater-client'); ?>
                    </button>
                </p>
            </form>

            <?php if ($is_configured): ?>
                <hr>
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('imagina_test_connection'); ?>
                    <button type="submit" name="imagina_test_connection" class="button">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Probar Conexión', 'imagina-updater-client'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($is_configured): ?>
            <!-- Gestión de Plugins -->
            <div class="imagina-card">
                <h2 class="imagina-card-title">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php _e('Gestión de Plugins', 'imagina-updater-client'); ?>
                </h2>

                <p class="description">
                    <?php _e('Selecciona los plugins que deseas actualizar desde el servidor central. Solo los plugins marcados recibirán actualizaciones.', 'imagina-updater-client'); ?>
                </p>

                <form method="post">
                    <?php wp_nonce_field('imagina_save_plugins'); ?>

                    <?php if (empty($server_plugins)): ?>
                        <div class="imagina-notice-info">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php _e('No hay plugins disponibles en el servidor o no se pudo conectar.', 'imagina-updater-client'); ?></p>
                        </div>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" id="select_all_plugins">
                                    </th>
                                    <th><?php _e('Plugin', 'imagina-updater-client'); ?></th>
                                    <th><?php _e('Slug', 'imagina-updater-client'); ?></th>
                                    <th><?php _e('Versión Disponible', 'imagina-updater-client'); ?></th>
                                    <th><?php _e('Versión Instalada', 'imagina-updater-client'); ?></th>
                                    <th><?php _e('Estado', 'imagina-updater-client'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($server_plugins as $plugin): ?>
                                    <?php
                                    $is_enabled = in_array($plugin['slug'], $config['enabled_plugins']);
                                    $is_installed = isset($installed_plugins[$plugin['slug']]);
                                    $installed_version = $is_installed ? $installed_plugins[$plugin['slug']]['version'] : '-';
                                    $needs_update = $is_installed && version_compare($plugin['version'], $installed_version, '>');

                                    // Verificar si el plugin puede ser detectado por el updater
                                    $updater = class_exists('Imagina_Updater_Client_Updater') ? Imagina_Updater_Client_Updater::get_instance() : null;
                                    $can_detect = false;
                                    if ($updater) {
                                        $reflection = new ReflectionClass($updater);
                                        $method = $reflection->getMethod('find_plugin_file');
                                        $method->setAccessible(true);
                                        $detected_file = $method->invoke($updater, $plugin['slug']);
                                        $can_detect = !empty($detected_file);
                                    }
                                    ?>
                                    <tr>
                                        <td class="check-column">
                                            <input type="checkbox"
                                                   name="enabled_plugins[]"
                                                   value="<?php echo esc_attr($plugin['slug']); ?>"
                                                   <?php checked($is_enabled); ?>
                                                   <?php disabled(!$can_detect && !$is_enabled); ?>
                                                   class="plugin_checkbox">
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($plugin['name']); ?></strong>
                                            <br>
                                            <small><?php echo esc_html($plugin['description']); ?></small>
                                        </td>
                                        <td>
                                            <code><?php echo esc_html($plugin['slug']); ?></code>
                                            <?php if (!$can_detect && $is_installed): ?>
                                                <br><span class="imagina-badge imagina-badge-warning" style="font-size: 10px;">
                                                    <?php _e('No detectado', 'imagina-updater-client'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($plugin['version']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($is_installed): ?>
                                                <code><?php echo esc_html($installed_version); ?></code>
                                            <?php else: ?>
                                                <span class="description"><?php _e('No instalado', 'imagina-updater-client'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$is_installed): ?>
                                                <span class="imagina-badge imagina-badge-gray">
                                                    <?php _e('No instalado', 'imagina-updater-client'); ?>
                                                </span>
                                            <?php elseif ($needs_update): ?>
                                                <span class="imagina-badge imagina-badge-warning">
                                                    <?php _e('Actualización disponible', 'imagina-updater-client'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="imagina-badge imagina-badge-success">
                                                    <?php _e('Actualizado', 'imagina-updater-client'); ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($is_enabled): ?>
                                                <span class="imagina-badge imagina-badge-info">
                                                    <?php _e('Habilitado', 'imagina-updater-client'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <p class="submit">
                            <button type="submit" name="imagina_save_plugins" class="button button-primary">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Guardar Selección', 'imagina-updater-client'); ?>
                            </button>
                        </p>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Información -->
            <div class="imagina-card">
                <h2 class="imagina-card-title">
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Cómo Funciona', 'imagina-updater-client'); ?>
                </h2>

                <ol class="imagina-info-list">
                    <li><?php _e('Selecciona los plugins que deseas gestionar desde el servidor central.', 'imagina-updater-client'); ?></li>
                    <li><?php _e('El sistema verificará automáticamente si hay nuevas versiones disponibles.', 'imagina-updater-client'); ?></li>
                    <li><?php _e('Las actualizaciones aparecerán en la página de Plugins como cualquier otra actualización de WordPress.', 'imagina-updater-client'); ?></li>
                    <li><?php _e('Puedes actualizar los plugins de forma individual o en lote desde el panel de Plugins.', 'imagina-updater-client'); ?></li>
                </ol>
            </div>
        <?php endif; ?>
    </div>
</div>
