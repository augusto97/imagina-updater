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

                <hr>
                <h3><?php _e('Configuración de Logs', 'imagina-updater-client'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_logging"><?php _e('Habilitar Logging', 'imagina-updater-client'); ?></label>
                        </th>
                        <td>
                            <label for="enable_logging">
                                <input type="checkbox"
                                       name="enable_logging"
                                       id="enable_logging"
                                       value="1"
                                       <?php checked(isset($config['enable_logging']) && $config['enable_logging']); ?>>
                                <?php _e('Activar sistema de logs', 'imagina-updater-client'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Los logs te ayudarán a diagnosticar problemas con las actualizaciones. Se guardan en un archivo independiente.', 'imagina-updater-client'); ?>
                                <?php if ($is_configured): ?>
                                    <br>
                                    <a href="<?php echo admin_url('options-general.php?page=imagina-updater-client-logs'); ?>"><?php _e('Ver logs', 'imagina-updater-client'); ?></a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="log_level"><?php _e('Nivel de Log', 'imagina-updater-client'); ?></label>
                        </th>
                        <td>
                            <select name="log_level" id="log_level">
                                <option value="DEBUG" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'DEBUG'); ?>>
                                    <?php _e('DEBUG - Todos los mensajes', 'imagina-updater-client'); ?>
                                </option>
                                <option value="INFO" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'INFO'); ?>>
                                    <?php _e('INFO - Información general', 'imagina-updater-client'); ?>
                                </option>
                                <option value="WARNING" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'WARNING'); ?>>
                                    <?php _e('WARNING - Solo advertencias y errores', 'imagina-updater-client'); ?>
                                </option>
                                <option value="ERROR" <?php selected(isset($config['log_level']) ? $config['log_level'] : 'INFO', 'ERROR'); ?>>
                                    <?php _e('ERROR - Solo errores', 'imagina-updater-client'); ?>
                                </option>
                            </select>
                            <p class="description">
                                <?php _e('Selecciona qué nivel de detalle quieres en los logs. DEBUG es muy detallado, INFO es recomendado.', 'imagina-updater-client'); ?>
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
                <form method="post" style="display: inline; margin-right: 10px;">
                    <?php wp_nonce_field('imagina_test_connection'); ?>
                    <button type="submit" name="imagina_test_connection" class="button">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Probar Conexión', 'imagina-updater-client'); ?>
                    </button>
                </form>
                <form method="post" style="display: inline;">
                    <?php wp_nonce_field('imagina_refresh_plugins'); ?>
                    <button type="submit" name="imagina_refresh_plugins" class="button">
                        <span class="dashicons dashicons-update-alt"></span>
                        <?php _e('Actualizar Lista de Plugins', 'imagina-updater-client'); ?>
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

                <form method="post" style="margin-bottom: 15px;">
                    <?php wp_nonce_field('imagina_save_display_mode'); ?>

                    <table class="form-table" style="margin-top: 0;">
                        <tr>
                            <th scope="row">
                                <?php _e('Modo de Visualización', 'imagina-updater-client'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio"
                                               name="plugin_display_mode"
                                               value="installed_only"
                                               <?php checked(isset($config['plugin_display_mode']) ? $config['plugin_display_mode'] : 'installed_only', 'installed_only'); ?>>
                                        <?php _e('Mostrar solo plugins instalados', 'imagina-updater-client'); ?>
                                    </label>
                                    <br>
                                    <label>
                                        <input type="radio"
                                               name="plugin_display_mode"
                                               value="all_with_install"
                                               <?php checked(isset($config['plugin_display_mode']) ? $config['plugin_display_mode'] : 'installed_only', 'all_with_install'); ?>>
                                        <?php _e('Mostrar todos los plugins con opción de instalar', 'imagina-updater-client'); ?>
                                    </label>
                                    <p class="description">
                                        <?php _e('Elige si quieres ver todos los plugins disponibles en el servidor o solo los que ya tienes instalados.', 'imagina-updater-client'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <p class="submit" style="margin-top: 0;">
                        <button type="submit" name="imagina_save_display_mode" class="button">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php _e('Actualizar Vista', 'imagina-updater-client'); ?>
                        </button>
                    </p>
                </form>

                <hr>

                <form method="post">
                    <?php wp_nonce_field('imagina_save_plugins'); ?>

                    <?php if (empty($server_plugins)): ?>
                        <div class="imagina-notice-info">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php _e('No hay plugins disponibles en el servidor o no se pudo conectar.', 'imagina-updater-client'); ?></p>
                        </div>
                    <?php else:
                        // Obtener modo de visualización
                        $display_mode = isset($config['plugin_display_mode']) ? $config['plugin_display_mode'] : 'installed_only';

                        // Obtener término de búsqueda
                        $search_term = isset($_GET['plugin_search']) ? sanitize_text_field($_GET['plugin_search']) : '';

                        // Separar plugins en instalados y no instalados, y aplicar filtros
                        $installed_list = array();
                        $not_installed_list = array();

                        foreach ($server_plugins as $plugin) {
                            $is_installed = isset($installed_plugins[$plugin['slug']]);

                            // Aplicar filtro de modo de visualización
                            if ($display_mode === 'installed_only' && !$is_installed) {
                                continue;
                            }

                            // Aplicar filtro de búsqueda
                            if (!empty($search_term)) {
                                $matches = stripos($plugin['name'], $search_term) !== false ||
                                          stripos($plugin['slug'], $search_term) !== false ||
                                          stripos($plugin['description'], $search_term) !== false;
                                if (!$matches) {
                                    continue;
                                }
                            }

                            // Separar en listas
                            if ($is_installed) {
                                $is_enabled = in_array($plugin['slug'], $config['enabled_plugins']);
                                // Ordenar: habilitados primero
                                if ($is_enabled) {
                                    array_unshift($installed_list, $plugin);
                                } else {
                                    $installed_list[] = $plugin;
                                }
                            } else {
                                $not_installed_list[] = $plugin;
                            }
                        }

                        // Combinar listas: instalados primero, luego no instalados
                        $filtered_plugins = array_merge($installed_list, $not_installed_list);
                        $total_plugins = count($filtered_plugins);

                        // Configuración de paginación
                        $per_page = 20;
                        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
                        $total_pages = max(1, ceil($total_plugins / $per_page));
                        $current_page = min($current_page, $total_pages);
                        $offset = ($current_page - 1) * $per_page;
                        $plugins_to_show = array_slice($filtered_plugins, $offset, $per_page);
                        ?>

                        <!-- Buscador -->
                        <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <div style="flex: 1;">
                                <form method="get" action="" style="display: flex; gap: 10px; max-width: 500px;">
                                    <input type="hidden" name="page" value="imagina-updater-client">
                                    <?php if (isset($_GET['paged'])): ?>
                                        <input type="hidden" name="paged" value="<?php echo esc_attr($_GET['paged']); ?>">
                                    <?php endif; ?>
                                    <input type="text"
                                           name="plugin_search"
                                           placeholder="<?php _e('Buscar por nombre, slug o descripción...', 'imagina-updater-client'); ?>"
                                           value="<?php echo esc_attr($search_term); ?>"
                                           style="flex: 1; padding: 6px 10px;">
                                    <button type="submit" class="button">
                                        <span class="dashicons dashicons-search"></span>
                                        <?php _e('Buscar', 'imagina-updater-client'); ?>
                                    </button>
                                    <?php if (!empty($search_term)): ?>
                                        <a href="<?php echo admin_url('options-general.php?page=imagina-updater-client'); ?>" class="button">
                                            <?php _e('Limpiar', 'imagina-updater-client'); ?>
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div>
                                <span class="description">
                                    <?php printf(__('Mostrando %d de %d plugins', 'imagina-updater-client'), count($plugins_to_show), $total_plugins); ?>
                                </span>
                            </div>
                        </div>

                        <?php if (empty($plugins_to_show)): ?>
                            <div class="imagina-notice-info">
                                <span class="dashicons dashicons-info"></span>
                                <p><?php _e('No se encontraron plugins con ese criterio de búsqueda.', 'imagina-updater-client'); ?></p>
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
                                <?php
                                foreach ($plugins_to_show as $plugin):
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
                                            <?php if ($is_installed): ?>
                                                <input type="checkbox"
                                                       name="enabled_plugins[]"
                                                       value="<?php echo esc_attr($plugin['slug']); ?>"
                                                       <?php checked($is_enabled); ?>
                                                       <?php disabled(!$can_detect && !$is_enabled); ?>
                                                       class="plugin_checkbox">
                                            <?php else: ?>
                                                <span class="dashicons dashicons-download" style="color: #999; font-size: 16px;"></span>
                                            <?php endif; ?>
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
                                                <br>
                                                <form method="post" style="display: inline; margin-top: 5px;">
                                                    <?php wp_nonce_field('imagina_install_plugin'); ?>
                                                    <input type="hidden" name="plugin_slug" value="<?php echo esc_attr($plugin['slug']); ?>">
                                                    <button type="submit" name="imagina_install_plugin" class="button button-small">
                                                        <span class="dashicons dashicons-download" style="font-size: 14px; margin-top: 3px;"></span>
                                                        <?php _e('Instalar Plugin', 'imagina-updater-client'); ?>
                                                    </button>
                                                </form>
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

                        <!-- Paginación -->
                        <?php if ($total_pages > 1): ?>
                            <div class="tablenav" style="margin-top: 15px;">
                                <div class="tablenav-pages">
                                    <span class="displaying-num">
                                        <?php printf(__('%s elementos', 'imagina-updater-client'), number_format_i18n($total_plugins)); ?>
                                    </span>
                                    <span class="pagination-links">
                                        <?php
                                        $base_url = admin_url('options-general.php?page=imagina-updater-client');
                                        if (!empty($search_term)) {
                                            $base_url = add_query_arg('plugin_search', urlencode($search_term), $base_url);
                                        }

                                        // Primera página
                                        if ($current_page == 1) {
                                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                                        } else {
                                            echo '<a class="first-page button" href="' . esc_url($base_url) . '"><span aria-hidden="true">«</span></a>';
                                            echo '<a class="prev-page button" href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '"><span aria-hidden="true">‹</span></a>';
                                        }

                                        // Números de página
                                        echo '<span class="paging-input">';
                                        echo '<span class="tablenav-paging-text">';
                                        printf(__('%1$s de %2$s', 'imagina-updater-client'),
                                            '<span class="current-page">' . number_format_i18n($current_page) . '</span>',
                                            '<span class="total-pages">' . number_format_i18n($total_pages) . '</span>'
                                        );
                                        echo '</span>';
                                        echo '</span>';

                                        // Última página
                                        if ($current_page == $total_pages) {
                                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                                            echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                                        } else {
                                            echo '<a class="next-page button" href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '"><span aria-hidden="true">›</span></a>';
                                            echo '<a class="last-page button" href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '"><span aria-hidden="true">»</span></a>';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <p class="submit">
                            <button type="submit" name="imagina_save_plugins" class="button button-primary">
                                <span class="dashicons dashicons-yes"></span>
                                <?php _e('Guardar Selección', 'imagina-updater-client'); ?>
                            </button>
                        </p>
                        <?php endif; ?>
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
