<?php
/**
 * Interfaz de administración del plugin cliente
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Client_Admin {

    /**
     * Instancia única
     */
    private static $instance = null;

    /**
     * Obtener instancia
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'handle_actions'));
        add_action('admin_notices', array($this, 'show_config_notice'));
    }

    /**
     * Agregar páginas al menú
     */
    public function add_menu_pages() {
        add_options_page(
            __('Imagina Updater', 'imagina-updater-client'),
            __('Imagina Updater', 'imagina-updater-client'),
            'manage_options',
            'imagina-updater-client',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'settings_page_imagina-updater-client') {
            return;
        }

        wp_enqueue_style(
            'imagina-updater-client-admin',
            IMAGINA_UPDATER_CLIENT_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            IMAGINA_UPDATER_CLIENT_VERSION
        );

        wp_enqueue_script(
            'imagina-updater-client-admin',
            IMAGINA_UPDATER_CLIENT_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            IMAGINA_UPDATER_CLIENT_VERSION,
            true
        );
    }

    /**
     * Mostrar aviso si no está configurado
     */
    public function show_config_notice() {
        if (!imagina_updater_client()->is_configured()) {
            $screen = get_current_screen();

            if ($screen->id !== 'settings_page_imagina-updater-client') {
                ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php _e('Imagina Updater Client:', 'imagina-updater-client'); ?></strong>
                        <?php _e('El plugin aún no está configurado.', 'imagina-updater-client'); ?>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=imagina-updater-client')); ?>">
                            <?php _e('Configurar ahora', 'imagina-updater-client'); ?>
                        </a>
                    </p>
                </div>
                <?php
            }
        }
    }

    /**
     * Manejar acciones
     */
    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Guardar configuración
        if (isset($_POST['imagina_save_config']) && check_admin_referer('imagina_save_config')) {
            $server_url = esc_url_raw($_POST['server_url']);
            $enable_logging = isset($_POST['enable_logging']) ? true : false;
            $log_level = isset($_POST['log_level']) ? sanitize_text_field($_POST['log_level']) : 'INFO';

            // Obtener configuración actual
            $current_config = imagina_updater_client()->get_config();
            $has_api_key = !empty($current_config['api_key']);
            $change_api_key = isset($_POST['change_api_key']) && $_POST['change_api_key'] === '1';

            // Determinar qué API Key usar
            $activation_token = isset($current_config['activation_token']) ? $current_config['activation_token'] : '';
            $api_key = '';

            // Caso 1: Primera vez (no hay API key) O usuario marcó cambiar API key
            if (!$has_api_key || $change_api_key) {
                // Obtener API key del formulario
                $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';

                if (empty($api_key)) {
                    add_settings_error('imagina_updater_client', 'missing_api_key', __('Debes ingresar una API Key', 'imagina-updater-client'), 'error');
                    return;
                }

                // Validar la API Key
                $api_client = new Imagina_Updater_Client_API($server_url, $api_key);
                $validation = $api_client->validate();

                if (is_wp_error($validation)) {
                    add_settings_error('imagina_updater_client', 'connection_error', sprintf(
                        __('Error de conexión: %s', 'imagina-updater-client'),
                        $validation->get_error_message()
                    ), 'error');
                    return;
                }

                // Activar sitio para obtener activation token
                $activation_result = $api_client->activate_site();

                if (is_wp_error($activation_result)) {
                    add_settings_error('imagina_updater_client', 'activation_error', sprintf(
                        __('Error al activar el sitio: %s', 'imagina-updater-client'),
                        $activation_result->get_error_message()
                    ), 'error');
                    return;
                }

                // Guardar nuevo activation token
                $activation_token = $activation_result['activation_token'];
            } else {
                // Caso 2: Ya hay API key guardada y NO se está cambiando
                // Mantener configuración actual
                $api_key = $current_config['api_key'];
                // No revalidar ni reactivar
            }

            // Validar server_url
            if (empty($server_url)) {
                add_settings_error('imagina_updater_client', 'missing_server_url', __('URL del servidor es requerida', 'imagina-updater-client'), 'error');
                return;
            }

            // Guardar configuración
            imagina_updater_client()->update_config(array(
                'server_url' => $server_url,
                'api_key' => $api_key,
                'activation_token' => $activation_token,
                'enable_logging' => $enable_logging,
                'log_level' => $log_level
            ));

            add_settings_error('imagina_updater_client', 'config_saved', __('Configuración guardada exitosamente', 'imagina-updater-client'), 'success');

            // Limpiar cache de actualizaciones y plugins del servidor
            delete_site_transient('update_plugins');
            delete_transient('imagina_updater_cached_updates');
            delete_transient('imagina_updater_server_plugins_' . md5($server_url));
        }

        // Desactivar licencia
        if (isset($_GET['action']) && $_GET['action'] === 'deactivate_license' && check_admin_referer('deactivate_license')) {
            // Primero intentar desactivar en el servidor
            $api_client = imagina_updater_client()->get_api_client();
            if ($api_client) {
                $deactivation = $api_client->deactivate_license();
                // Ignorar errores, desactivar localmente de todas formas
            }

            // Limpiar toda la configuración local
            imagina_updater_client()->update_config(array(
                'server_url' => '',
                'api_key' => '',
                'activation_token' => '',
                'enabled_plugins' => array(),
                'plugin_display_mode' => 'installed_only'
            ));

            // Limpiar cachés
            delete_site_transient('update_plugins');
            delete_transient('imagina_updater_cached_updates');
            $config = imagina_updater_client()->get_config();
            if (!empty($config['server_url'])) {
                delete_transient('imagina_updater_server_plugins_' . md5($config['server_url']));
            }

            add_settings_error('imagina_updater_client', 'license_deactivated', __('Licencia desactivada exitosamente', 'imagina-updater-client'), 'success');

            wp_redirect(admin_url('options-general.php?page=imagina-updater-client'));
            exit;
        }

        // Instalar plugin desde servidor (GET con nonce en URL)
        if (isset($_GET['action']) && $_GET['action'] === 'install_plugin' && isset($_GET['plugin_slug'])) {
            $plugin_slug = sanitize_text_field($_GET['plugin_slug']);

            // Verificar nonce específico del plugin
            if (!wp_verify_nonce($_GET['_wpnonce'], 'imagina_install_plugin_' . $plugin_slug)) {
                wp_die(__('Error de seguridad: Nonce inválido', 'imagina-updater-client'));
            }

            if (empty($plugin_slug)) {
                add_settings_error('imagina_updater_client', 'install_error', __('Slug de plugin no válido', 'imagina-updater-client'), 'error');
            } else {
                $result = $this->install_plugin_from_server($plugin_slug);

                if (is_wp_error($result)) {
                    add_settings_error('imagina_updater_client', 'install_error', sprintf(
                        __('Error al instalar plugin: %s', 'imagina-updater-client'),
                        $result->get_error_message()
                    ), 'error');
                } else {
                    // Guardar mensaje en transient para mostrarlo después del redirect
                    set_transient('imagina_updater_install_success', array(
                        'name' => $result['name'],
                        'version' => $result['version']
                    ), 30);

                    // Redirect para limpiar la URL
                    wp_redirect(admin_url('options-general.php?page=imagina-updater-client'));
                    exit;
                }
            }
        }

        // Guardar plugins habilitados
        if (isset($_POST['imagina_save_plugins']) && check_admin_referer('imagina_save_plugins')) {
            // Obtener selección del formulario
            $enabled_plugins = isset($_POST['enabled_plugins']) && is_array($_POST['enabled_plugins'])
                ? array_map('sanitize_text_field', $_POST['enabled_plugins'])
                : array();

            // Guardar configuración
            imagina_updater_client()->update_config(array(
                'enabled_plugins' => array_values(array_unique($enabled_plugins))
            ));

            add_settings_error('imagina_updater_client', 'plugins_saved', __('Plugins actualizados exitosamente', 'imagina-updater-client'), 'success');

            // Limpiar cache de actualizaciones
            delete_site_transient('update_plugins');
            delete_transient('imagina_updater_cached_updates');
        }

        // Guardar modo de visualización
        if (isset($_POST['imagina_save_display_mode']) && check_admin_referer('imagina_save_display_mode')) {
            $display_mode = isset($_POST['plugin_display_mode'])
                ? sanitize_text_field($_POST['plugin_display_mode'])
                : 'installed_only';

            // Validar que sea un valor permitido
            if (!in_array($display_mode, array('all_with_install', 'installed_only'))) {
                $display_mode = 'installed_only';
            }

            imagina_updater_client()->update_config(array(
                'plugin_display_mode' => $display_mode
            ));

            add_settings_error('imagina_updater_client', 'display_mode_saved', __('Modo de visualización actualizado', 'imagina-updater-client'), 'success');
        }

        // Mostrar mensaje de instalación exitosa (después del redirect)
        $install_success = get_transient('imagina_updater_install_success');
        if ($install_success) {
            delete_transient('imagina_updater_install_success');
            add_settings_error('imagina_updater_client', 'install_success', sprintf(
                __('Plugin "%s" v%s instalado exitosamente. Ahora puedes habilitarlo para recibir actualizaciones.', 'imagina-updater-client'),
                $install_success['name'],
                $install_success['version']
            ), 'success');
        }

        // Test de conexión
        if (isset($_POST['imagina_test_connection']) && check_admin_referer('imagina_test_connection')) {
            $api_client = imagina_updater_client()->get_api_client();

            if (!$api_client) {
                add_settings_error('imagina_updater_client', 'test_failed', __('Plugin no configurado. Activa el sitio primero.', 'imagina-updater-client'), 'error');
                return;
            }

            // Probar conexión obteniendo lista de plugins (usa activation_token)
            $result = $api_client->get_plugins();

            if (is_wp_error($result)) {
                add_settings_error('imagina_updater_client', 'test_failed', sprintf(
                    __('Test fallido: %s', 'imagina-updater-client'),
                    $result->get_error_message()
                ), 'error');
            } else {
                $plugin_count = is_array($result) ? count($result) : 0;
                add_settings_error('imagina_updater_client', 'test_success', sprintf(
                    __('Conexión exitosa. %d plugin(s) disponible(s).', 'imagina-updater-client'),
                    $plugin_count
                ), 'success');
            }
        }

        // Refrescar lista de plugins desde servidor
        if (isset($_POST['imagina_refresh_plugins']) && check_admin_referer('imagina_refresh_plugins')) {
            $config = imagina_updater_client()->get_config();

            // Limpiar todos los cachés relacionados
            $cache_key = 'imagina_updater_server_plugins_' . md5($config['server_url']);
            delete_transient($cache_key);
            delete_transient('imagina_updater_cached_updates');
            delete_site_transient('update_plugins');

            // Consultar servidor directamente
            $api_client = imagina_updater_client()->get_api_client();

            if (!$api_client) {
                add_settings_error('imagina_updater_client', 'refresh_failed', __('Plugin no configurado', 'imagina-updater-client'), 'error');
                return;
            }

            $result = $api_client->get_plugins();

            if (is_wp_error($result)) {
                add_settings_error('imagina_updater_client', 'refresh_failed', sprintf(
                    __('Error al refrescar: %s', 'imagina-updater-client'),
                    $result->get_error_message()
                ), 'error');
            } else {
                // Guardar en caché
                set_transient($cache_key, $result, 24 * HOUR_IN_SECONDS);

                // Contar plugins
                $count = count($result);

                add_settings_error('imagina_updater_client', 'refresh_success', sprintf(
                    __('Lista actualizada: %d plugin(s) disponibles en el servidor', 'imagina-updater-client'),
                    $count
                ), 'success');

                // Redirect para recargar página con nuevo caché
                wp_redirect(admin_url('options-general.php?page=imagina-updater-client'));
                exit;
            }
        }
    }

    /**
     * Instalar plugin desde servidor Imagina
     */
    private function install_plugin_from_server($plugin_slug) {
        $config = imagina_updater_client()->get_config();

        if (!imagina_updater_client()->is_configured()) {
            return new WP_Error('not_configured', __('El plugin no está configurado', 'imagina-updater-client'));
        }

        // Obtener información del plugin desde el servidor
        $api_client = imagina_updater_client()->get_api_client();

        if (!$api_client) {
            return new WP_Error('not_configured', __('El plugin no está configurado', 'imagina-updater-client'));
        }

        $plugins = $api_client->get_plugins();

        if (is_wp_error($plugins)) {
            return $plugins;
        }

        // Buscar el plugin en la lista
        $plugin_info = null;
        foreach ($plugins as $plugin) {
            if ($plugin['slug'] === $plugin_slug) {
                $plugin_info = $plugin;
                break;
            }
        }

        if (!$plugin_info) {
            return new WP_Error('plugin_not_found', __('Plugin no encontrado en el servidor', 'imagina-updater-client'));
        }

        // Descargar el plugin
        $download_url = trailingslashit($config['server_url']) . 'wp-json/imagina-updater/v1/download/' . $plugin_slug;

        // Usar WordPress HTTP API para descargar
        $response = wp_remote_get($download_url, array(
            'timeout' => 300,
            'headers' => array(
                'X-API-Key' => $config['api_key']
            )
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('download_failed', sprintf(
                __('Error al descargar plugin (código %d)', 'imagina-updater-client'),
                $response_code
            ));
        }

        // Guardar archivo temporal
        $temp_file = wp_tempnam($plugin_slug . '.zip');
        file_put_contents($temp_file, wp_remote_retrieve_body($response));

        // Instalar usando Plugin_Upgrader
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($temp_file);

        // Limpiar archivo temporal
        @unlink($temp_file);

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result === false) {
            return new WP_Error('install_failed', __('Error al instalar el plugin', 'imagina-updater-client'));
        }

        // Limpiar cache
        delete_transient('imagina_updater_server_plugins_' . md5($config['server_url']));

        return array(
            'name' => $plugin_info['name'],
            'version' => $plugin_info['version'],
            'slug' => $plugin_slug
        );
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        $config = imagina_updater_client()->get_config();
        $is_configured = imagina_updater_client()->is_configured();

        // Obtener plugins disponibles del servidor con caché
        $server_plugins = array();
        if ($is_configured) {
            // Intentar obtener de caché (24 horas)
            $cache_key = 'imagina_updater_server_plugins_' . md5($config['server_url']);
            $server_plugins = get_transient($cache_key);

            if ($server_plugins === false) {
                // No hay caché, consultar servidor
                $api_client = imagina_updater_client()->get_api_client();

                if (!$api_client) {
                    $server_plugins = array();
                } else {
                    $result = $api_client->get_plugins();

                    if (!is_wp_error($result)) {
                        $server_plugins = $result;
                        // Guardar en caché por 24 horas
                        set_transient($cache_key, $server_plugins, 24 * HOUR_IN_SECONDS);
                    } else {
                        $server_plugins = array();
                    }
                }
            }

            // Limpiar plugins habilitados que ya no están disponibles en el servidor
            if (!empty($server_plugins) && !empty($config['enabled_plugins'])) {
                $available_slugs = array_column($server_plugins, 'slug');
                $original_count = count($config['enabled_plugins']);
                $cleaned_plugins = array_intersect($config['enabled_plugins'], $available_slugs);

                // Si se removieron plugins, actualizar configuración
                if (count($cleaned_plugins) < $original_count) {
                    $removed = array_diff($config['enabled_plugins'], $cleaned_plugins);
                    imagina_updater_client()->update_config(array(
                        'enabled_plugins' => array_values($cleaned_plugins)
                    ));

                    // Recargar configuración para reflejar los cambios
                    $config = imagina_updater_client()->get_config();
                }
            }
        }

        // Obtener plugins instalados
        $installed_plugins = array();
        if ($is_configured && class_exists('Imagina_Updater_Client_Updater')) {
            $updater = Imagina_Updater_Client_Updater::get_instance();
            $installed_plugins = $updater->get_installed_plugins();
        }

        include IMAGINA_UPDATER_CLIENT_PLUGIN_DIR . 'admin/views/settings.php';
    }

}
