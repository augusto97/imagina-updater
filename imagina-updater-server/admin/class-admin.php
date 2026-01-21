<?php
/**
 * Interfaz de administración del plugin servidor
 */

if (!defined('ABSPATH')) {
    exit;
}

class Imagina_Updater_Server_Admin {

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
    }

    /**
     * Agregar páginas al menú de administración
     */
    public function add_menu_pages() {
        add_menu_page(
            __('Imagina Updater', 'imagina-updater-server'),
            __('Imagina Updater', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-server',
            array($this, 'render_dashboard_page'),
            'dashicons-update',
            30
        );

        add_submenu_page(
            'imagina-updater-server',
            __('Dashboard', 'imagina-updater-server'),
            __('Dashboard', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-server',
            array($this, 'render_dashboard_page')
        );

        add_submenu_page(
            'imagina-updater-server',
            __('Plugins', 'imagina-updater-server'),
            __('Plugins', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-plugins',
            array($this, 'render_plugins_page')
        );

        add_submenu_page(
            'imagina-updater-server',
            __('Grupos de Plugins', 'imagina-updater-server'),
            __('Grupos de Plugins', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-plugin-groups',
            array($this, 'render_plugin_groups_page')
        );

        add_submenu_page(
            'imagina-updater-server',
            __('API Keys', 'imagina-updater-server'),
            __('API Keys', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-api-keys',
            array($this, 'render_api_keys_page')
        );

        add_submenu_page(
            'imagina-updater-server',
            __('Activaciones', 'imagina-updater-server'),
            __('Activaciones', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-activations',
            array($this, 'render_activations_page')
        );

        add_submenu_page(
            'imagina-updater-server',
            __('Logs', 'imagina-updater-server'),
            __('Logs', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-logs',
            array($this, 'render_logs_page')
        );

        add_submenu_page(
            'imagina-updater-server',
            __('Configuración', 'imagina-updater-server'),
            __('Configuración', 'imagina-updater-server'),
            'manage_options',
            'imagina-updater-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Cargar scripts y estilos
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'imagina-updater') === false) {
            return;
        }

        wp_enqueue_style(
            'imagina-updater-server-admin',
            IMAGINA_UPDATER_SERVER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            IMAGINA_UPDATER_SERVER_VERSION
        );

        // Script principal para todas las páginas de Imagina Updater
        wp_add_inline_script('jquery', $this->get_admin_js());
    }

    /**
     * Obtener JavaScript para administración
     */
    private function get_admin_js() {
        return '
jQuery(document).ready(function($) {
    // ============================================
    // SLUG EDIT FUNCTIONALITY
    // ============================================
    $(".edit-slug-link").on("click", function(e) {
        e.preventDefault();
        var pluginId = $(this).data("plugin-id");
        $("#slug-edit-" + pluginId).slideToggle();
    });

    $(".cancel-slug-edit").on("click", function(e) {
        e.preventDefault();
        $(this).closest(".slug-edit-form").slideUp();
    });

    // ============================================
    // ENHANCED TABLE SYSTEM
    // ============================================

    // Column Toggle Dropdown
    $(document).on("click", ".imagina-column-toggle-btn", function(e) {
        e.stopPropagation();
        var $toggle = $(this).closest(".imagina-column-toggle");
        $(".imagina-column-toggle").not($toggle).removeClass("open");
        $toggle.toggleClass("open");
    });

    // Column visibility change
    $(document).on("change", ".imagina-column-dropdown input[type=checkbox]", function() {
        var $table = $(this).closest(".imagina-table-toolbar").next(".imagina-table-enhanced");
        if (!$table.length) {
            $table = $(this).closest(".imagina-table-toolbar").nextAll(".imagina-table-enhanced").first();
        }
        var colIndex = $(this).data("col");
        var isVisible = $(this).is(":checked");
        var tableId = $table.attr("id") || "default";

        // Toggle column visibility
        $table.find("th:nth-child(" + colIndex + "), td:nth-child(" + colIndex + ")").toggleClass("column-hidden", !isVisible);

        // Save preference to localStorage
        var prefs = JSON.parse(localStorage.getItem("imagina_table_cols_" + tableId) || "{}");
        prefs[colIndex] = isVisible;
        localStorage.setItem("imagina_table_cols_" + tableId, JSON.stringify(prefs));

        updateRowCount($table);
    });

    // Load saved column preferences
    $(".imagina-table-enhanced").each(function() {
        var $table = $(this);
        var tableId = $table.attr("id") || "default";
        var prefs = JSON.parse(localStorage.getItem("imagina_table_cols_" + tableId) || "{}");

        for (var colIndex in prefs) {
            if (!prefs[colIndex]) {
                $table.find("th:nth-child(" + colIndex + "), td:nth-child(" + colIndex + ")").addClass("column-hidden");
                $(".imagina-column-dropdown input[data-col=" + colIndex + "]").prop("checked", false);
            }
        }
    });

    // Table Search
    $(document).on("input", ".imagina-table-search input", function() {
        var searchTerm = $(this).val().toLowerCase().trim();
        var $toolbar = $(this).closest(".imagina-table-toolbar");
        var $table = $toolbar.next(".imagina-table-enhanced");
        if (!$table.length) {
            $table = $toolbar.nextAll(".imagina-table-enhanced").first();
        }

        // Obtener filtro premium si existe
        var $premiumFilter = $toolbar.find(".imagina-filter-select[data-filter=premium]");
        var premiumFilterValue = $premiumFilter.length ? $premiumFilter.val() : "";

        var visibleCount = 0;
        $table.find("tbody tr").not(".permissions-edit-row").each(function() {
            var $row = $(this);
            var rowText = $row.text().toLowerCase();
            var matchesSearch = searchTerm === "" || rowText.indexOf(searchTerm) !== -1;

            // Aplicar filtro premium
            var matchesPremium = true;
            if (premiumFilterValue !== "" && $row.data("premium") !== undefined) {
                var isPremium = $row.data("premium") == 1;
                if (premiumFilterValue === "premium" && !isPremium) {
                    matchesPremium = false;
                } else if (premiumFilterValue === "free" && isPremium) {
                    matchesPremium = false;
                }
            }

            var matches = matchesSearch && matchesPremium;
            $row.toggle(matches);
            if (matches) visibleCount++;
        });

        // Update row count
        var $count = $toolbar.find(".imagina-table-count");
        if ($count.length) {
            var total = $table.find("tbody tr").not(".permissions-edit-row").length;
            if (searchTerm !== "") {
                $count.text(visibleCount + " de " + total + " resultados");
            } else {
                $count.text(total + " registros");
            }
        }

        // Show/hide no results message
        var $noResults = $table.find(".imagina-no-results-row");
        if (visibleCount === 0 && searchTerm !== "") {
            if (!$noResults.length) {
                var cols = $table.find("thead th").length;
                $table.find("tbody").append("<tr class=\"imagina-no-results-row\"><td colspan=\"" + cols + "\" class=\"imagina-no-results\">No se encontraron resultados para \"" + searchTerm + "\"</td></tr>");
            }
        } else {
            $noResults.remove();
        }
    });

    function updateRowCount($table) {
        var $toolbar = $table.prevAll(".imagina-table-toolbar").first();
        var $count = $toolbar.find(".imagina-table-count");
        if ($count.length) {
            var total = $table.find("tbody tr:visible").not(".permissions-edit-row, .imagina-no-results-row").length;
            $count.text(total + " registros");
        }
    }

    // Actions Dropdown
    $(document).on("click", ".imagina-actions-btn", function(e) {
        e.stopPropagation();
        var $dropdown = $(this).closest(".imagina-actions-dropdown");
        $(".imagina-actions-dropdown").not($dropdown).removeClass("open");
        $dropdown.toggleClass("open");
    });

    // Close dropdowns on outside click
    $(document).on("click", function(e) {
        if (!$(e.target).closest(".imagina-column-toggle").length) {
            $(".imagina-column-toggle").removeClass("open");
        }
        if (!$(e.target).closest(".imagina-actions-dropdown").length) {
            $(".imagina-actions-dropdown").removeClass("open");
        }
    });

    // Description expand/collapse (new block style)
    $(document).on("click", ".desc-toggle-link", function(e) {
        e.preventDefault();
        var $block = $(this).closest(".imagina-desc-block");
        var $content = $block.find(".desc-content");
        $content.slideToggle(200);
        $(this).text($content.is(":visible") ? "ocultar descripción" : "ver descripción");
    });

    // Premium filter dropdown
    $(document).on("change", ".imagina-filter-select[data-filter=premium]", function() {
        var filterValue = $(this).val();
        var $toolbar = $(this).closest(".imagina-table-toolbar");
        var $table = $toolbar.next(".imagina-table-enhanced");
        if (!$table.length) {
            $table = $toolbar.nextAll(".imagina-table-enhanced").first();
        }

        var visibleCount = 0;
        $table.find("tbody tr").not(".permissions-edit-row, .imagina-no-results-row").each(function() {
            var $row = $(this);
            var isPremium = $row.data("premium") == 1;
            var show = true;

            if (filterValue === "premium" && !isPremium) {
                show = false;
            } else if (filterValue === "free" && isPremium) {
                show = false;
            }

            // También respetar la búsqueda activa
            var searchTerm = $toolbar.find(".imagina-table-search input").val().toLowerCase().trim();
            if (show && searchTerm !== "") {
                var rowText = $row.text().toLowerCase();
                show = rowText.indexOf(searchTerm) !== -1;
            }

            $row.toggle(show);
            if (show) visibleCount++;
        });

        updateRowCount($table);
    });

    // Initialize row counts
    $(".imagina-table-enhanced").each(function() {
        updateRowCount($(this));
    });
});
        ';
    }

    /**
     * Manejar acciones de formularios
     */
    public function handle_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Crear API Key
        if (isset($_POST['imagina_create_api_key']) && check_admin_referer('imagina_create_api_key')) {
            $site_name = isset($_POST['site_name']) ? sanitize_text_field(wp_unslash($_POST['site_name'])) : '';
            $site_url = '-'; // Ya no se usa site_url, solo site_name (cliente/descripción)
            $max_activations = isset($_POST['max_activations']) ? max(0, intval($_POST['max_activations'])) : 1;
            $access_type = isset($_POST['access_type']) ? sanitize_text_field(wp_unslash($_POST['access_type'])) : 'all';
            $allowed_plugins = isset($_POST['allowed_plugins']) ? array_map('intval', $_POST['allowed_plugins']) : array();
            $allowed_groups = isset($_POST['allowed_groups']) ? array_map('intval', $_POST['allowed_groups']) : array();

            $result = Imagina_Updater_Server_API_Keys::create($site_name, $site_url, $access_type, $allowed_plugins, $allowed_groups, $max_activations);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al crear API Key: ' . $result->get_error_message(), 'error');
                set_transient('imagina_updater_api_error', $result->get_error_message(), 30);
            } else {
                imagina_updater_server_log('API Key creada exitosamente para: ' . $site_name . ' con acceso: ' . $access_type, 'info');
                set_transient('imagina_updater_api_success', true, 30);
                set_transient('imagina_new_api_key', $result['api_key'], 60);
            }

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-api-keys'));
            exit;
        }

        // Mostrar mensajes de API Keys
        if (get_transient('imagina_updater_api_success')) {
            delete_transient('imagina_updater_api_success');
            add_settings_error('imagina_updater', 'create_success', __('API Key creada exitosamente', 'imagina-updater-server'), 'success');
        }
        if ($api_error = get_transient('imagina_updater_api_error')) {
            delete_transient('imagina_updater_api_error');
            add_settings_error('imagina_updater', 'create_error', $api_error, 'error');
        }

        // Eliminar API Key
        if (isset($_GET['action']) && $_GET['action'] === 'delete_api_key' && isset($_GET['id']) && check_admin_referer('delete_api_key_' . intval($_GET['id']))) {
            $id = intval($_GET['id']);
            Imagina_Updater_Server_API_Keys::delete($id);
            imagina_updater_server_log('API Key eliminada: ID ' . $id, 'info');
            set_transient('imagina_updater_api_deleted', true, 30);

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-api-keys'));
            exit;
        }

        // Mostrar mensaje de eliminación de API Key
        if (get_transient('imagina_updater_api_deleted')) {
            delete_transient('imagina_updater_api_deleted');
            add_settings_error('imagina_updater', 'delete_success', __('API Key eliminada', 'imagina-updater-server'), 'success');
        }

        // Activar/Desactivar API Key
        if (isset($_GET['action']) && $_GET['action'] === 'toggle_api_key' && isset($_GET['id']) && check_admin_referer('toggle_api_key_' . intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $key = Imagina_Updater_Server_API_Keys::get_by_id($id);
            if ($key) {
                $new_status = !$key->is_active;
                Imagina_Updater_Server_API_Keys::set_active($id, $new_status);
                imagina_updater_server_log('API Key ID ' . $id . ' ' . ($new_status ? 'activada' : 'desactivada'), 'info');
                set_transient('imagina_updater_api_toggled', true, 30);
            }

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-api-keys'));
            exit;
        }

        // Mostrar mensaje de cambio de estado
        if (get_transient('imagina_updater_api_toggled')) {
            delete_transient('imagina_updater_api_toggled');
            add_settings_error('imagina_updater', 'toggle_success', __('Estado actualizado', 'imagina-updater-server'), 'success');
        }

        // Regenerar API Key
        if (isset($_GET['action']) && $_GET['action'] === 'regenerate_api_key' && isset($_GET['id']) && check_admin_referer('regenerate_api_key_' . intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $result = Imagina_Updater_Server_API_Keys::regenerate_key($id);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al regenerar API Key ID ' . $id . ': ' . $result->get_error_message(), 'error');
                set_transient('imagina_updater_api_error', $result->get_error_message(), 30);
            } else {
                imagina_updater_server_log('API Key regenerada exitosamente para: ' . $result['site_name'] . ' (ID: ' . $id . ')', 'info');
                set_transient('imagina_updater_api_regenerated', true, 30);
                set_transient('imagina_regenerated_api_key', $result['api_key'], 60);
            }

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-api-keys'));
            exit;
        }

        // Mostrar mensaje de regeneración de API Key
        if (get_transient('imagina_updater_api_regenerated')) {
            delete_transient('imagina_updater_api_regenerated');
            add_settings_error('imagina_updater', 'regenerate_success', __('API Key regenerada exitosamente', 'imagina-updater-server'), 'success');
        }

        // Actualizar información del sitio (nombre y URL)
        if (isset($_POST['imagina_update_site_info']) && check_admin_referer('imagina_update_site_info')) {
            $api_key_id = isset($_POST['api_key_id']) ? intval($_POST['api_key_id']) : 0;
            $site_name = isset($_POST['site_name']) ? sanitize_text_field(wp_unslash($_POST['site_name'])) : '';
            $site_url = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';

            $result = Imagina_Updater_Server_API_Keys::update_site_info($api_key_id, $site_name, $site_url);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al actualizar información del sitio API Key ID ' . $api_key_id . ': ' . $result->get_error_message(), 'error');
                set_transient('imagina_updater_api_error', $result->get_error_message(), 30);
            } else {
                imagina_updater_server_log('Información del sitio actualizada exitosamente para API Key ID ' . $api_key_id, 'info');
                set_transient('imagina_updater_site_info_success', true, 30);
            }

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-api-keys'));
            exit;
        }

        // Mostrar mensaje de actualización de información del sitio
        if (get_transient('imagina_updater_site_info_success')) {
            delete_transient('imagina_updater_site_info_success');
            add_settings_error('imagina_updater', 'site_info_success', __('Información del sitio actualizada', 'imagina-updater-server'), 'success');
        }

        // Actualizar permisos de API Key
        if (isset($_POST['imagina_update_api_permissions']) && check_admin_referer('imagina_update_api_permissions')) {
            $api_key_id = isset($_POST['api_key_id']) ? intval($_POST['api_key_id']) : 0;
            $access_type = isset($_POST['access_type']) ? sanitize_text_field(wp_unslash($_POST['access_type'])) : 'all';
            $allowed_plugins = isset($_POST['allowed_plugins']) ? array_map('intval', $_POST['allowed_plugins']) : array();
            $allowed_groups = isset($_POST['allowed_groups']) ? array_map('intval', $_POST['allowed_groups']) : array();

            $result = Imagina_Updater_Server_API_Keys::update_permissions($api_key_id, $access_type, $allowed_plugins, $allowed_groups);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al actualizar permisos API Key ID ' . $api_key_id . ': ' . $result->get_error_message(), 'error');
                set_transient('imagina_updater_api_error', $result->get_error_message(), 30);
            } else {
                imagina_updater_server_log('Permisos actualizados exitosamente para API Key ID ' . $api_key_id . ': ' . $access_type, 'info');
                set_transient('imagina_updater_api_permissions_success', true, 30);
            }

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-api-keys'));
            exit;
        }

        // Desactivar activación de sitio
        if (isset($_GET['action']) && $_GET['action'] === 'deactivate_activation' && isset($_GET['id']) && check_admin_referer('deactivate_activation_' . intval($_GET['id']))) {
            $activation_id = intval($_GET['id']);
            $result = Imagina_Updater_Server_Activations::deactivate_site($activation_id);

            if ($result) {
                imagina_updater_server_log('Activación ID ' . $activation_id . ' desactivada', 'info');
                set_transient('imagina_updater_activation_deactivated', true, 30);
            } else {
                imagina_updater_server_log('Error al desactivar activación ID ' . $activation_id, 'error');
                set_transient('imagina_updater_activation_error', __('Error al desactivar sitio', 'imagina-updater-server'), 30);
            }

            // Redirigir a la página de activaciones
            $redirect_url = admin_url('admin.php?page=imagina-updater-activations');
            if (isset($_GET['api_key_id'])) {
                $redirect_url = add_query_arg('api_key_id', intval($_GET['api_key_id']), $redirect_url);
            }

            wp_safe_redirect($redirect_url);
            exit;
        }

        // Mostrar mensajes de activaciones
        if (get_transient('imagina_updater_activation_deactivated')) {
            delete_transient('imagina_updater_activation_deactivated');
            add_settings_error('imagina_updater', 'activation_deactivated', __('Sitio desactivado exitosamente', 'imagina-updater-server'), 'success');
        }
        if ($activation_error = get_transient('imagina_updater_activation_error')) {
            delete_transient('imagina_updater_activation_error');
            add_settings_error('imagina_updater', 'activation_error', $activation_error, 'error');
        }

        // Mostrar mensaje de actualización de permisos
        if (get_transient('imagina_updater_api_permissions_success')) {
            delete_transient('imagina_updater_api_permissions_success');
            add_settings_error('imagina_updater', 'permissions_success', __('Permisos actualizados', 'imagina-updater-server'), 'success');
        }

        // Detectar si hubo un intento de subida que falló por límites de PHP
        // Cuando el archivo excede post_max_size, PHP descarta TODO el POST silenciosamente
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_SERVER['CONTENT_LENGTH']) && empty($_POST) && empty($_FILES)) {

            $post_max = $this->return_bytes(ini_get('post_max_size'));
            $content_length = (int) $_SERVER['CONTENT_LENGTH'];

            if ($content_length > $post_max) {
                add_settings_error('imagina_updater', 'upload_size_exceeded',
                    sprintf(
                        /* translators: %1$s: file size attempted, %2$s: maximum allowed size */
                        __('Error: El archivo que intentaste subir (%1$s) excede el límite permitido por el servidor (%2$s). Contacta a tu proveedor de hosting para aumentar el valor de post_max_size en PHP.', 'imagina-updater-server'),
                        size_format($content_length),
                        size_format($post_max)
                    ),
                    'error'
                );
                imagina_updater_server_log(sprintf('Subida rechazada: archivo de %s excede post_max_size de %s', size_format($content_length), size_format($post_max)), 'warning');
            }
        }

        // Subir plugin
        if (isset($_POST['imagina_upload_plugin'])) {
            // Verificar nonce con mejor manejo de errores
            if (!check_admin_referer('imagina_upload_plugin', '_wpnonce', false)) {
                imagina_updater_server_log('Error de seguridad: Nonce inválido al subir plugin', 'warning');
                add_settings_error('imagina_updater', 'nonce_error',
                    __('Error de seguridad: El formulario ha caducado. Por favor, recarga la página e intenta de nuevo.', 'imagina-updater-server'),
                    'error'
                );
                return;
            }

            // Verificar que se subió un archivo
            if (!isset($_FILES['plugin_file']) || $_FILES['plugin_file']['error'] !== UPLOAD_ERR_OK) {
                $error_message = '';

                if (!isset($_FILES['plugin_file'])) {
                    $error_message = __('No se seleccionó ningún archivo', 'imagina-updater-server');
                } else {
                    // Mensajes de error más descriptivos
                    switch ($_FILES['plugin_file']['error']) {
                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $error_message = __('El archivo es demasiado grande. Tamaño máximo: ', 'imagina-updater-server') . ini_get('upload_max_filesize');
                            break;
                        case UPLOAD_ERR_PARTIAL:
                            $error_message = __('El archivo se subió parcialmente. Intenta de nuevo.', 'imagina-updater-server');
                            break;
                        case UPLOAD_ERR_NO_FILE:
                            $error_message = __('No se seleccionó ningún archivo', 'imagina-updater-server');
                            break;
                        case UPLOAD_ERR_NO_TMP_DIR:
                        case UPLOAD_ERR_CANT_WRITE:
                        case UPLOAD_ERR_EXTENSION:
                            $error_message = __('Error del servidor al procesar el archivo. Contacta al administrador.', 'imagina-updater-server');
                            break;
                        default:
                            $error_message = __('Error desconocido al subir el archivo', 'imagina-updater-server');
                    }
                }

                imagina_updater_server_log('Error al subir archivo: ' . $error_message, 'error');
                add_settings_error('imagina_updater', 'upload_error', $error_message, 'error');
                return;
            }

            $changelog = isset($_POST['changelog']) ? sanitize_textarea_field(wp_unslash($_POST['changelog'])) : '';
            $plugin_groups = isset($_POST['plugin_groups']) ? array_map('intval', $_POST['plugin_groups']) : array();

            $plugin_filename = isset($_FILES['plugin_file']['name']) ? sanitize_file_name($_FILES['plugin_file']['name']) : '';
            imagina_updater_server_log('Iniciando subida de plugin: ' . $plugin_filename, 'info');
            $result = Imagina_Updater_Server_Plugin_Manager::upload_plugin($_FILES['plugin_file'], $changelog);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al subir plugin: ' . $result->get_error_message(), 'error');
                add_settings_error('imagina_updater', 'upload_error', $result->get_error_message(), 'error');
            } else {
                imagina_updater_server_log(sprintf('Plugin subido exitosamente: %s v%s', $result['name'], $result['version']), 'info');

                // Asignar plugin a los grupos seleccionados
                if (!empty($plugin_groups) && isset($result['id'])) {
                    $this->add_plugin_to_groups($result['id'], $plugin_groups);
                    imagina_updater_server_log(sprintf('Plugin ID %d asignado a %d grupo(s)', $result['id'], count($plugin_groups)), 'info');
                }

                // Guardar mensaje de éxito en transient para mostrarlo después del redirect
                set_transient('imagina_updater_upload_success', array(
                    'name' => $result['name'],
                    'version' => $result['version']
                ), 30);

                // Redirect limpio para evitar reenvío de formulario
                wp_safe_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
                exit;
            }
        }

        // Mostrar mensaje de éxito guardado en transient
        $upload_success = get_transient('imagina_updater_upload_success');
        if ($upload_success) {
            delete_transient('imagina_updater_upload_success');
            add_settings_error('imagina_updater', 'upload_success', sprintf(
                /* translators: %1$s: plugin name, %2$s: plugin version */
                __('Plugin "%1$s" versión %2$s subido exitosamente', 'imagina-updater-server'),
                $upload_success['name'],
                $upload_success['version']
            ), 'success');
        }

        // Eliminar plugin
        if (isset($_GET['action']) && $_GET['action'] === 'delete_plugin' && isset($_GET['id']) && check_admin_referer('delete_plugin_' . intval($_GET['id']))) {
            $id = intval($_GET['id']);
            $result = Imagina_Updater_Server_Plugin_Manager::delete_plugin($id);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al eliminar plugin ID ' . $id . ': ' . $result->get_error_message(), 'error');
                set_transient('imagina_updater_delete_error', $result->get_error_message(), 30);
            } else {
                imagina_updater_server_log('Plugin eliminado exitosamente: ID ' . $id, 'info');
                set_transient('imagina_updater_delete_success', true, 30);
            }

            // Redirect limpio
            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
            exit;
        }

        // Mostrar mensajes de eliminación
        if (get_transient('imagina_updater_delete_success')) {
            delete_transient('imagina_updater_delete_success');
            add_settings_error('imagina_updater', 'delete_success', __('Plugin eliminado', 'imagina-updater-server'), 'success');
        }
        if ($delete_error = get_transient('imagina_updater_delete_error')) {
            delete_transient('imagina_updater_delete_error');
            add_settings_error('imagina_updater', 'delete_error', $delete_error, 'error');
        }

        // Descargar plugin directamente (sin REST API)
        if (isset($_GET['action']) && $_GET['action'] === 'download_plugin' && isset($_GET['slug'])) {
            $slug = sanitize_text_field(wp_unslash($_GET['slug']));

            // Verificar nonce
            if (!check_admin_referer('download_plugin_' . $slug)) {
                wp_die(esc_html__('Error de seguridad: Nonce inválido', 'imagina-updater-server'));
            }

            // Verificar permisos de administrador
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('No tienes permisos para descargar plugins', 'imagina-updater-server'));
            }

            // Obtener información del plugin
            $plugin = Imagina_Updater_Server_Plugin_Manager::get_plugin_by_slug($slug);

            if (!$plugin) {
                wp_die(esc_html__('Plugin no encontrado', 'imagina-updater-server'));
            }

            // Verificar que el archivo existe
            if (!file_exists($plugin->file_path)) {
                imagina_updater_server_log('Archivo de plugin no encontrado: ' . $plugin->file_path, 'error');
                wp_die(esc_html__('Archivo de plugin no encontrado', 'imagina-updater-server'));
            }

            // Registrar la descarga
            imagina_updater_server_log(sprintf('Admin descarga plugin: %s v%s', $plugin->name, $plugin->current_version), 'info');

            // Enviar archivo usando WP_Filesystem
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . '/wp-admin/includes/file.php';
                WP_Filesystem();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($plugin->file_path) . '"');
            header('Content-Length: ' . filesize($plugin->file_path));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');

            // Limpiar buffer de salida antes de enviar archivo
            if (ob_get_level()) {
                ob_end_clean();
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Using for file download to browser
            echo $wp_filesystem->get_contents($plugin->file_path);
            exit;
        }

        // Actualizar slug del plugin
        if (isset($_POST['imagina_update_slug'])) {
            $plugin_id = isset($_POST['plugin_id']) ? intval($_POST['plugin_id']) : 0;

            if (check_admin_referer('update_slug_' . $plugin_id)) {
                $new_slug = isset($_POST['new_slug']) ? sanitize_title(wp_unslash($_POST['new_slug'])) : '';

                // Si está vacío, pasar null para usar el auto-generado
                if (empty($new_slug)) {
                    $new_slug = null;
                }

                $result = Imagina_Updater_Server_Plugin_Manager::update_plugin_slug($plugin_id, $new_slug);

                if (is_wp_error($result)) {
                    imagina_updater_server_log('Error al actualizar slug: ' . $result->get_error_message(), 'error');
                    set_transient('imagina_updater_slug_error', $result->get_error_message(), 30);
                } else {
                    imagina_updater_server_log('Slug actualizado exitosamente para plugin ID ' . $plugin_id, 'info');
                    set_transient('imagina_updater_slug_success', true, 30);
                }

                // Redirect limpio
                wp_safe_redirect(admin_url('admin.php?page=imagina-updater-plugins'));
                exit;
            }
        }

        // Mostrar mensajes de actualización de slug
        if (get_transient('imagina_updater_slug_success')) {
            delete_transient('imagina_updater_slug_success');
            add_settings_error('imagina_updater', 'slug_success', __('Slug actualizado exitosamente', 'imagina-updater-server'), 'success');
        }
        if ($slug_error = get_transient('imagina_updater_slug_error')) {
            delete_transient('imagina_updater_slug_error');
            add_settings_error('imagina_updater', 'slug_error', sprintf(
                /* translators: %s: error message */
                __('Error al actualizar slug: %s', 'imagina-updater-server'),
                $slug_error
            ), 'error');
        }

        // Crear grupo de plugins
        if (isset($_POST['imagina_create_group']) && check_admin_referer('imagina_create_group')) {
            $name = isset($_POST['group_name']) ? sanitize_text_field(wp_unslash($_POST['group_name'])) : '';
            $description = isset($_POST['group_description']) ? sanitize_textarea_field(wp_unslash($_POST['group_description'])) : '';
            $plugin_ids = isset($_POST['plugin_ids']) ? array_map('intval', $_POST['plugin_ids']) : array();

            $result = Imagina_Updater_Server_Plugin_Groups::create_group($name, $description, $plugin_ids);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al crear grupo: ' . $result->get_error_message(), 'error');
                set_transient('imagina_updater_group_error', $result->get_error_message(), 30);
            } else {
                imagina_updater_server_log('Grupo creado exitosamente: ' . $name, 'info');
                set_transient('imagina_updater_group_success', __('Grupo creado exitosamente', 'imagina-updater-server'), 30);
            }

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-plugin-groups'));
            exit;
        }

        // Actualizar grupo de plugins
        if (isset($_POST['imagina_update_group']) && check_admin_referer('imagina_update_group')) {
            $group_id = isset($_POST['group_id']) ? intval($_POST['group_id']) : 0;
            $name = isset($_POST['group_name']) ? sanitize_text_field(wp_unslash($_POST['group_name'])) : '';
            $description = isset($_POST['group_description']) ? sanitize_textarea_field(wp_unslash($_POST['group_description'])) : '';
            $plugin_ids = isset($_POST['plugin_ids']) ? array_map('intval', $_POST['plugin_ids']) : array();

            $result = Imagina_Updater_Server_Plugin_Groups::update_group($group_id, $name, $description, $plugin_ids);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al actualizar grupo ID ' . $group_id . ': ' . $result->get_error_message(), 'error');
                set_transient('imagina_updater_group_error', $result->get_error_message(), 30);
            } else {
                imagina_updater_server_log('Grupo actualizado exitosamente: ID ' . $group_id, 'info');
                set_transient('imagina_updater_group_success', __('Grupo actualizado exitosamente', 'imagina-updater-server'), 30);
            }

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-plugin-groups'));
            exit;
        }

        // Eliminar grupo de plugins
        if (isset($_GET['action']) && $_GET['action'] === 'delete_group' && isset($_GET['group_id']) && check_admin_referer('delete_group_' . intval($_GET['group_id']))) {
            $group_id = intval($_GET['group_id']);
            $result = Imagina_Updater_Server_Plugin_Groups::delete_group($group_id);

            if (is_wp_error($result)) {
                imagina_updater_server_log('Error al eliminar grupo ID ' . $group_id . ': ' . $result->get_error_message(), 'error');
                set_transient('imagina_updater_group_error', $result->get_error_message(), 30);
            } else {
                imagina_updater_server_log('Grupo eliminado exitosamente: ID ' . $group_id, 'info');
                set_transient('imagina_updater_group_success', __('Grupo eliminado', 'imagina-updater-server'), 30);
            }

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-plugin-groups'));
            exit;
        }

        // Mostrar mensajes de grupos
        if ($group_success = get_transient('imagina_updater_group_success')) {
            delete_transient('imagina_updater_group_success');
            add_settings_error('imagina_updater', 'group_success', $group_success, 'success');
        }
        if ($group_error = get_transient('imagina_updater_group_error')) {
            delete_transient('imagina_updater_group_error');
            add_settings_error('imagina_updater', 'group_error', $group_error, 'error');
        }

        // Ejecutar migración manualmente
        if (isset($_POST['imagina_run_migration']) && check_admin_referer('imagina_run_migration')) {
            // Primero crear/actualizar tablas
            Imagina_Updater_Server_Database::create_tables();
            // Luego ejecutar migraciones de campos
            Imagina_Updater_Server_Database::run_migrations();
            add_settings_error('imagina_updater', 'migration_success', __('Migración ejecutada exitosamente. Todas las tablas están actualizadas.', 'imagina-updater-server'), 'success');
        }

        // Guardar configuración
        if (isset($_POST['imagina_save_settings']) && check_admin_referer('imagina_save_settings')) {
            $enable_logging = isset($_POST['enable_logging']) ? true : false;
            $log_level = isset($_POST['log_level']) ? sanitize_text_field(wp_unslash($_POST['log_level'])) : 'INFO';

            update_option('imagina_updater_server_config', array(
                'enable_logging' => $enable_logging,
                'log_level' => $log_level
            ));

            imagina_updater_server_log('Configuración actualizada: logging=' . ($enable_logging ? 'enabled' : 'disabled') . ', level=' . $log_level, 'info');
            set_transient('imagina_updater_settings_saved', true, 30);

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-settings'));
            exit;
        }

        // Mostrar mensaje de configuración guardada
        if (get_transient('imagina_updater_settings_saved')) {
            delete_transient('imagina_updater_settings_saved');
            add_settings_error('imagina_updater', 'settings_saved', __('Configuración guardada', 'imagina-updater-server'), 'success');
        }

        // Limpiar logs
        if (isset($_POST['imagina_clear_logs']) && check_admin_referer('imagina_clear_logs')) {
            Imagina_Updater_Server_Logger::get_instance()->clear_logs();
            imagina_updater_server_log('Logs limpiados manualmente', 'info');
            set_transient('imagina_updater_logs_cleared', true, 30);

            wp_safe_redirect(admin_url('admin.php?page=imagina-updater-logs'));
            exit;
        }

        // Mostrar mensaje de logs limpiados
        if (get_transient('imagina_updater_logs_cleared')) {
            delete_transient('imagina_updater_logs_cleared');
            add_settings_error('imagina_updater', 'logs_cleared', __('Logs eliminados', 'imagina-updater-server'), 'success');
        }

        // Descargar logs
        if (isset($_GET['action']) && $_GET['action'] === 'download_log' && check_admin_referer('download_log')) {
            $this->download_log();
        }
    }

    /**
     * Renderizar página de dashboard
     */
    public function render_dashboard_page() {
        global $wpdb;

        // Estadísticas básicas
        $total_plugins = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_plugins");
        $total_api_keys = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_api_keys WHERE is_active = 1");
        $total_api_keys_inactive = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_api_keys WHERE is_active = 0");
        $total_downloads = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_downloads");

        // Estadísticas adicionales
        $total_groups = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_plugin_groups");
        $total_activations = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_activations WHERE is_active = 1");

        // Descargas recientes (últimos 7 días)
        $downloads_week = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_downloads WHERE downloaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $downloads_month = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_downloads WHERE downloaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

        // Plugin más descargado
        $most_downloaded = $wpdb->get_row("
            SELECT p.name, COUNT(d.id) as downloads
            FROM {$wpdb->prefix}imagina_updater_downloads d
            INNER JOIN {$wpdb->prefix}imagina_updater_plugins p ON d.plugin_id = p.id
            GROUP BY d.plugin_id
            ORDER BY downloads DESC
            LIMIT 1
        ");

        // Verificar si la extensión de licencias está activa
        $has_license_extension = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}imagina_updater_plugins LIKE 'is_premium'");
        $license_stats = array();

        if ($has_license_extension) {
            // Plugins premium vs gratuitos
            $license_stats['premium_plugins'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}imagina_updater_plugins WHERE is_premium = 1");
            $license_stats['free_plugins'] = $total_plugins - $license_stats['premium_plugins'];

            // Estadísticas de licencias (si la tabla existe)
            $table_licenses = $wpdb->prefix . 'imagina_license_keys';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_licenses'")) {
                $license_stats['total_licenses'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_licenses");
                $license_stats['active_licenses'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'active'");
                $license_stats['expired_licenses'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'expired'");
                $license_stats['revoked_licenses'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_licenses WHERE status = 'revoked'");

                // Activaciones de licencias
                $table_activations = $wpdb->prefix . 'imagina_license_activations';
                if ($wpdb->get_var("SHOW TABLES LIKE '$table_activations'")) {
                    $license_stats['license_activations'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_activations WHERE is_active = 1");
                }
            }
        }

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Renderizar página de plugins
     */
    public function render_plugins_page() {
        $plugins = Imagina_Updater_Server_Plugin_Manager::get_all_plugins();
        $all_groups = Imagina_Updater_Server_Plugin_Groups::get_all_groups();

        // Obtener grupos para cada plugin
        $plugin_groups = array();
        foreach ($plugins as $plugin) {
            $plugin_groups[$plugin->id] = $this->get_plugin_groups($plugin->id);
        }

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/plugins.php';
    }

    /**
     * Obtener grupos de un plugin específico
     */
    private function get_plugin_groups($plugin_id) {
        global $wpdb;

        $table_items = $wpdb->prefix . 'imagina_updater_plugin_group_items';
        $table_groups = $wpdb->prefix . 'imagina_updater_plugin_groups';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT g.*
            FROM $table_groups g
            INNER JOIN $table_items gi ON g.id = gi.group_id
            WHERE gi.plugin_id = %d
            ORDER BY g.name ASC",
            $plugin_id
        ));
    }

    /**
     * Agregar un plugin a múltiples grupos
     */
    private function add_plugin_to_groups($plugin_id, $group_ids) {
        global $wpdb;

        $table_items = $wpdb->prefix . 'imagina_updater_plugin_group_items';

        // Eliminar relaciones existentes para este plugin
        $wpdb->delete($table_items, array('plugin_id' => $plugin_id), array('%d'));

        // Agregar nuevas relaciones
        foreach ($group_ids as $group_id) {
            $wpdb->insert(
                $table_items,
                array(
                    'group_id' => intval($group_id),
                    'plugin_id' => intval($plugin_id)
                ),
                array('%d', '%d')
            );
        }

        return true;
    }

    /**
     * Renderizar página de grupos de plugins
     */
    public function render_plugin_groups_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        $group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;

        // Si estamos editando o creando, cargar datos necesarios
        $editing_group = null;
        if ($action === 'edit' && $group_id > 0) {
            $editing_group = Imagina_Updater_Server_Plugin_Groups::get_group($group_id);
            if ($editing_group) {
                $editing_group->plugin_ids = Imagina_Updater_Server_Plugin_Groups::get_group_plugin_ids($group_id);
            }
        }

        $groups = Imagina_Updater_Server_Plugin_Groups::get_all_groups();
        $all_plugins = Imagina_Updater_Server_Plugin_Manager::get_all_plugins();

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/plugin-groups.php';
    }

    /**
     * Renderizar página de API Keys
     */
    public function render_api_keys_page() {
        $api_keys = Imagina_Updater_Server_API_Keys::get_all();
        $new_api_key = get_transient('imagina_new_api_key');

        if ($new_api_key) {
            delete_transient('imagina_new_api_key');
        }

        // Cargar plugins y grupos para los permisos
        $all_plugins = Imagina_Updater_Server_Plugin_Manager::get_all_plugins();
        $all_groups = Imagina_Updater_Server_Plugin_Groups::get_all_groups();

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/api-keys.php';
    }

    /**
     * Renderizar página de activaciones
     */
    public function render_activations_page() {
        global $wpdb;

        // Filtrar por API key si se especifica
        $api_key_id = isset($_GET['api_key_id']) ? intval($_GET['api_key_id']) : null;

        // Obtener todas las activaciones con información de API key
        $table_activations = $wpdb->prefix . 'imagina_updater_activations';
        $table_api_keys = $wpdb->prefix . 'imagina_updater_api_keys';

        $query = "SELECT a.*, k.site_name, k.api_key
                  FROM $table_activations a
                  INNER JOIN $table_api_keys k ON a.api_key_id = k.id";

        if ($api_key_id) {
            $query .= $wpdb->prepare(" WHERE a.api_key_id = %d", $api_key_id);
        }

        $query .= " ORDER BY a.is_active DESC, a.activated_at DESC";

        $activations = $wpdb->get_results($query);

        // Obtener todas las API keys para el filtro
        $api_keys = Imagina_Updater_Server_API_Keys::get_all();

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/activations.php';
    }

    /**
     * Renderizar página de logs
     */
    public function render_logs_page() {
        $logger = Imagina_Updater_Server_Logger::get_instance();
        $logs = $logger->read_logs(200);
        $is_enabled = $logger->is_enabled();

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/logs.php';
    }

    /**
     * Renderizar página de configuración
     */
    public function render_settings_page() {
        $config = get_option('imagina_updater_server_config', array(
            'enable_logging' => false,
            'log_level' => 'INFO'
        ));

        include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Descargar archivo de log
     */
    private function download_log() {
        $logger = Imagina_Updater_Server_Logger::get_instance();
        $log_file = $logger->get_log_file();

        if (!file_exists($log_file)) {
            wp_die(esc_html__('No hay logs disponibles para descargar', 'imagina-updater-server'));
        }

        // Usar WP_Filesystem para leer el archivo
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="imagina-updater-server-' . gmdate('Y-m-d-His') . '.log"');
        header('Content-Length: ' . filesize($log_file));

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Using for file download to browser
        echo $wp_filesystem->get_contents($log_file);
        exit;
    }

    /**
     * Convertir valores de PHP ini (como "8M", "128K") a bytes
     *
     * @param string $val Valor de PHP ini
     * @return int Valor en bytes
     */
    private function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}
