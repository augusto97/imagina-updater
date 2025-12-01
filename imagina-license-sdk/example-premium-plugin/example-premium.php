<?php
/**
 * Plugin Name: Example Premium Plugin
 * Plugin URI: https://example.com/premium-plugin
 * Description: Plugin premium de ejemplo con integración del sistema de licencias Imagina.
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://example.com
 * Requires PHP: 7.4
 * Requires Plugins: imagina-updater-client
 * Text Domain: example-premium
 * Domain Path: /languages
 *
 * @package Example_Premium_Plugin
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constantes del plugin
define( 'EXAMPLE_PREMIUM_VERSION', '1.0.0' );
define( 'EXAMPLE_PREMIUM_PLUGIN_FILE', __FILE__ );
define( 'EXAMPLE_PREMIUM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EXAMPLE_PREMIUM_PLUGIN_URL', plugin_url( __FILE__ ) );
define( 'EXAMPLE_PREMIUM_SLUG', 'example-premium' );

/**
 * Verificar que el plugin de licencias esté activo
 */
function example_premium_check_dependencies() {
	// Verificar que Imagina Updater Client esté activo
	if ( ! class_exists( 'Imagina_Updater_License_Manager' ) ) {
		add_action( 'admin_notices', 'example_premium_missing_license_manager_notice' );
		return false;
	}

	return true;
}

/**
 * Aviso si falta el gestor de licencias
 */
function example_premium_missing_license_manager_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Example Premium Plugin', 'example-premium' ); ?>:</strong>
			<?php
			echo wp_kses_post(
				__( 'Este plugin requiere que <strong>Imagina Updater Client</strong> esté instalado y activado.', 'example-premium' )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Cargar el SDK de licencias
 */
function example_premium_load_license_sdk() {
	// Verificar dependencias
	if ( ! example_premium_check_dependencies() ) {
		return;
	}

	// Cargar el SDK
	$sdk_path = EXAMPLE_PREMIUM_PLUGIN_DIR . 'vendor/imagina-license-sdk/loader.php';

	if ( ! file_exists( $sdk_path ) ) {
		add_action( 'admin_notices', 'example_premium_missing_sdk_notice' );
		return;
	}

	require_once $sdk_path;

	// Inicializar validación de licencia
	$license = Imagina_License_SDK::init( array(
		'plugin_slug'  => EXAMPLE_PREMIUM_SLUG,
		'plugin_name'  => 'Example Premium Plugin',
		'plugin_file'  => EXAMPLE_PREMIUM_PLUGIN_FILE,
		'grace_period' => 3 * DAY_IN_SECONDS, // 3 días de gracia
	) );

	// Verificar licencia
	if ( ! $license->is_valid() ) {
		// La licencia no es válida
		// El SDK ya se encargará de mostrar el aviso
		// Solo cargamos la funcionalidad básica (admin)

		// Hook para que otros plugins puedan reaccionar
		do_action( 'example_premium_license_invalid' );

		// No cargar funcionalidades principales
		return;
	}

	// Licencia válida, cargar el plugin completo
	example_premium_load_plugin();
}
add_action( 'plugins_loaded', 'example_premium_load_license_sdk' );

/**
 * Aviso si falta el SDK
 */
function example_premium_missing_sdk_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Example Premium Plugin', 'example-premium' ); ?>:</strong>
			<?php esc_html_e( 'El SDK de licencias no está instalado correctamente.', 'example-premium' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Carga el plugin completo (solo si la licencia es válida)
 */
function example_premium_load_plugin() {
	// Cargar clases principales
	require_once EXAMPLE_PREMIUM_PLUGIN_DIR . 'includes/class-license-integration.php';
	require_once EXAMPLE_PREMIUM_PLUGIN_DIR . 'includes/class-main.php';

	// Inicializar el plugin
	Example_Premium_Main::init();

	// Hook de inicialización
	do_action( 'example_premium_loaded' );
}

/**
 * Activación del plugin
 */
function example_premium_activate() {
	// Verificar dependencias
	if ( ! class_exists( 'Imagina_Updater_License_Manager' ) ) {
		wp_die(
			wp_kses_post(
				__( '<h1>Error de Dependencias</h1><p>Este plugin requiere que <strong>Imagina Updater Client</strong> esté instalado y activado.</p>', 'example-premium' )
			)
		);
	}

	// Crear opciones por defecto
	add_option(
		'example_premium_settings',
		array(
			'enabled' => true,
		)
	);
}
register_activation_hook( __FILE__, 'example_premium_activate' );

/**
 * Desactivación del plugin
 */
function example_premium_deactivate() {
	// Limpiar cron jobs si los hubiera
	// No eliminar datos por si reactivan el plugin
}
register_deactivation_hook( __FILE__, 'example_premium_deactivate' );

/**
 * Desinstalación del plugin
 */
function example_premium_uninstall() {
	// Eliminar opciones
	delete_option( 'example_premium_settings' );

	// Eliminar datos de licencia
	delete_option( 'imagina_license_' . EXAMPLE_PREMIUM_SLUG );
}
// register_uninstall_hook se debe llamar sin estar en una función
register_uninstall_hook( __FILE__, 'example_premium_uninstall' );
