<?php
/**
 * Plugin Name: Example Premium Plugin
 * Plugin URI: https://example.com/premium-plugin
 * Description: Plugin premium de ejemplo. La protección de licencias se inyecta automáticamente al subir al servidor.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://example.com
 * Requires PHP: 7.4
 * Text Domain: example-premium
 * Domain Path: /languages
 *
 * @package Example_Premium_Plugin
 *
 * NOTA IMPORTANTE:
 * ================
 * Este plugin es un ejemplo de cómo desarrollar un plugin premium.
 * NO necesitas agregar código de protección manualmente.
 *
 * Cuando subas este plugin al servidor Imagina Updater y lo marques como "Premium",
 * el sistema inyectará automáticamente el código de protección de licencias.
 *
 * El código de protección:
 * - Verifica la licencia al cargar el plugin
 * - Bloquea funcionalidades si no hay licencia válida
 * - Tiene período de gracia de 7 días
 * - Hace heartbeat cada 12 horas
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// NOTA: El código de protección de licencias se inyectará aquí automáticamente
// cuando subas el plugin al servidor y lo marques como premium.
// NO necesitas agregar nada manualmente.
// ============================================================================

// Constantes del plugin
define('EXAMPLE_PREMIUM_VERSION', '1.0.0');
define('EXAMPLE_PREMIUM_PLUGIN_FILE', __FILE__);
define('EXAMPLE_PREMIUM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EXAMPLE_PREMIUM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EXAMPLE_PREMIUM_SLUG', 'example-premium');

/**
 * Clase principal del plugin
 */
class Example_Premium_Plugin {

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
        $this->init_hooks();
    }

    /**
     * Inicializar hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));

        // Shortcode de ejemplo
        add_shortcode('example_premium', array($this, 'shortcode_output'));

        // REST API de ejemplo
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }

    /**
     * Agregar menú de administración
     */
    public function add_admin_menu() {
        add_options_page(
            __('Example Premium', 'example-premium'),
            __('Example Premium', 'example-premium'),
            'manage_options',
            'example-premium',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Registrar configuración
     */
    public function register_settings() {
        register_setting('example_premium_settings', 'example_premium_options');
    }

    /**
     * Renderizar página de administración
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Example Premium Plugin', 'example-premium'); ?></h1>

            <div class="notice notice-success">
                <p>
                    <strong><?php _e('El plugin está funcionando correctamente.', 'example-premium'); ?></strong>
                </p>
            </div>

            <h2><?php _e('Información de Licencia', 'example-premium'); ?></h2>

            <?php $this->show_license_info(); ?>

            <h2><?php _e('Configuración', 'example-premium'); ?></h2>

            <form method="post" action="options.php">
                <?php
                settings_fields('example_premium_settings');
                $options = get_option('example_premium_options', array());
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Activar funcionalidad', 'example-premium'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="example_premium_options[enabled]" value="1"
                                    <?php checked(!empty($options['enabled'])); ?>>
                                <?php _e('Activar las funciones premium', 'example-premium'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Mensaje personalizado', 'example-premium'); ?></th>
                        <td>
                            <input type="text" name="example_premium_options[message]" class="regular-text"
                                value="<?php echo esc_attr($options['message'] ?? ''); ?>">
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <h2><?php _e('Uso del Shortcode', 'example-premium'); ?></h2>
            <p><?php _e('Usa el shortcode [example_premium] para mostrar contenido premium en tu sitio.', 'example-premium'); ?></p>
            <code>[example_premium message="Hola Mundo"]</code>
        </div>
        <?php
    }

    /**
     * Mostrar información de licencia
     */
    private function show_license_info() {
        // Verificar si hay un sistema de licencias activo
        $license_class = 'ILP_' . substr(md5(EXAMPLE_PREMIUM_SLUG . 'imagina_license'), 0, 8);

        if (class_exists($license_class)) {
            $is_licensed = call_user_func(array($license_class, 'is_licensed'));
            $license_data = call_user_func(array($license_class, 'get_license_data'));

            if ($is_licensed) {
                ?>
                <div class="notice notice-success inline" style="margin: 10px 0;">
                    <p>
                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                        <strong><?php _e('Licencia Activa', 'example-premium'); ?></strong>
                    </p>
                </div>
                <?php
            } else {
                ?>
                <div class="notice notice-warning inline" style="margin: 10px 0;">
                    <p>
                        <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                        <strong><?php _e('Licencia No Válida', 'example-premium'); ?></strong>
                        <?php if (!empty($license_data['message'])): ?>
                            - <?php echo esc_html($license_data['message']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php
            }
        } else {
            ?>
            <div class="notice notice-info inline" style="margin: 10px 0;">
                <p>
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Sistema de licencias no detectado. Este plugin está funcionando en modo de desarrollo.', 'example-premium'); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Salida del shortcode
     */
    public function shortcode_output($atts) {
        $atts = shortcode_atts(array(
            'message' => __('Contenido Premium', 'example-premium'),
        ), $atts);

        $options = get_option('example_premium_options', array());

        if (empty($options['enabled'])) {
            return '<p>' . __('La funcionalidad premium está desactivada.', 'example-premium') . '</p>';
        }

        ob_start();
        ?>
        <div class="example-premium-content" style="padding: 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">
            <h3 style="margin-top: 0;"><?php echo esc_html($atts['message']); ?></h3>
            <p><?php _e('Este es contenido premium generado por el plugin de ejemplo.', 'example-premium'); ?></p>
            <p><small><?php printf(__('Version: %s', 'example-premium'), EXAMPLE_PREMIUM_VERSION); ?></small></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Registrar rutas REST API
     */
    public function register_rest_routes() {
        register_rest_route('example-premium/v1', '/data', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_data'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Endpoint REST de ejemplo
     */
    public function rest_get_data($request) {
        return rest_ensure_response(array(
            'success' => true,
            'message' => __('API Premium funcionando', 'example-premium'),
            'version' => EXAMPLE_PREMIUM_VERSION,
            'timestamp' => current_time('mysql'),
        ));
    }
}

/**
 * Inicializar el plugin
 *
 * NOTA: Si el plugin tiene protección de licencias inyectada,
 * esta función solo se ejecutará si la licencia es válida.
 */
function example_premium_init() {
    // Verificar si la protección de licencias bloqueó la ejecución
    $license_class = 'ILP_' . substr(md5(EXAMPLE_PREMIUM_SLUG . 'imagina_license'), 0, 8);

    if (class_exists($license_class)) {
        // Hay protección de licencias, verificar si está licenciado
        if (!call_user_func(array($license_class, 'is_licensed'))) {
            // No está licenciado, no cargar el plugin
            // El sistema de protección ya mostrará el aviso
            return;
        }
    }

    // Cargar el plugin
    Example_Premium_Plugin::get_instance();
}
add_action('plugins_loaded', 'example_premium_init', 20);

/**
 * Activación del plugin
 */
function example_premium_activate() {
    // Crear opciones por defecto
    add_option('example_premium_options', array(
        'enabled' => true,
        'message' => '',
    ));
}
register_activation_hook(__FILE__, 'example_premium_activate');

/**
 * Desactivación del plugin
 */
function example_premium_deactivate() {
    // Limpiar caché si es necesario
}
register_deactivation_hook(__FILE__, 'example_premium_deactivate');

/**
 * Desinstalación del plugin
 */
function example_premium_uninstall() {
    // Eliminar opciones
    delete_option('example_premium_options');

    // Eliminar datos de licencia
    delete_option('ilp_status_' . substr(md5(EXAMPLE_PREMIUM_SLUG . 'imagina_license'), 0, 8));
    delete_option('ilp_grace_' . substr(md5(EXAMPLE_PREMIUM_SLUG . 'imagina_license'), 0, 8));
    delete_option('ilp_valid_' . substr(md5(EXAMPLE_PREMIUM_SLUG . 'imagina_license'), 0, 8));
}
register_uninstall_hook(__FILE__, 'example_premium_uninstall');
