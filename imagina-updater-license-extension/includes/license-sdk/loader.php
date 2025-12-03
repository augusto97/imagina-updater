<?php
/**
 * Imagina License SDK - Loader
 *
 * Punto de entrada del SDK. Este archivo debe ser incluido en los plugins premium.
 *
 * Uso:
 * require_once plugin_dir_path( __FILE__ ) . 'vendor/imagina-license-sdk/loader.php';
 *
 * @package Imagina_License_SDK
 * @version 1.0.0
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevenir carga múltiple
if ( defined( 'IMAGINA_LICENSE_SDK_LOADED' ) ) {
	return;
}

define( 'IMAGINA_LICENSE_SDK_LOADED', true );
define( 'IMAGINA_LICENSE_SDK_VERSION', '1.0.0' );
define( 'IMAGINA_LICENSE_SDK_PATH', dirname( __FILE__ ) . '/' );

/**
 * Autoloader del SDK
 */
spl_autoload_register( function( $class ) {
	// Prefijo de las clases del SDK
	$prefix = 'Imagina_License_';

	// Verificar si la clase pertenece al SDK
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	// Mapeo de clases a archivos
	$class_map = array(
		'Imagina_License_Crypto'     => IMAGINA_LICENSE_SDK_PATH . 'class-crypto.php',
		'Imagina_License_Validator'  => IMAGINA_LICENSE_SDK_PATH . 'class-license-validator.php',
		'Imagina_License_Heartbeat'  => IMAGINA_LICENSE_SDK_PATH . 'class-heartbeat.php',
	);

	if ( isset( $class_map[ $class ] ) && file_exists( $class_map[ $class ] ) ) {
		require_once $class_map[ $class ];
	}
} );

/**
 * Clase principal del SDK
 */
class Imagina_License_SDK {

	/**
	 * Instancias de validadores activos
	 *
	 * @var array
	 */
	private static $validators = array();

	/**
	 * Inicializa el SDK para un plugin
	 *
	 * @param array $args Argumentos de configuración
	 *                    - plugin_slug (string): Slug del plugin (requerido)
	 *                    - plugin_name (string): Nombre del plugin (requerido)
	 *                    - plugin_file (string): Archivo principal del plugin (requerido)
	 *                    - grace_period (int): Período de gracia en segundos (opcional, default: 3 días)
	 *
	 * @return Imagina_License_Validator Instancia del validador
	 */
	public static function init( $args ) {
		// Validar argumentos requeridos
		$required = array( 'plugin_slug', 'plugin_name', 'plugin_file' );
		foreach ( $required as $key ) {
			if ( empty( $args[ $key ] ) ) {
				wp_die(
					sprintf(
						'Imagina License SDK: Falta el parámetro requerido "%s" en init().',
						$key
					)
				);
			}
		}

		$plugin_slug = $args['plugin_slug'];

		// Si ya existe un validador para este plugin, retornarlo
		if ( isset( self::$validators[ $plugin_slug ] ) ) {
			return self::$validators[ $plugin_slug ];
		}

		// Crear nuevo validador
		$validator = new Imagina_License_Validator( $args );

		// Guardar instancia
		self::$validators[ $plugin_slug ] = $validator;

		// Registrar en el heartbeat
		$heartbeat = Imagina_License_Heartbeat::get_instance();
		$heartbeat->register_plugin( $plugin_slug, $validator );

		return $validator;
	}

	/**
	 * Obtiene un validador existente
	 *
	 * @param string $plugin_slug Slug del plugin
	 * @return Imagina_License_Validator|null
	 */
	public static function get_validator( $plugin_slug ) {
		return isset( self::$validators[ $plugin_slug ] ) ? self::$validators[ $plugin_slug ] : null;
	}

	/**
	 * Verifica si un plugin tiene licencia válida
	 *
	 * Método de conveniencia para verificación rápida
	 *
	 * @param string $plugin_slug Slug del plugin
	 * @return bool
	 */
	public static function is_licensed( $plugin_slug ) {
		$validator = self::get_validator( $plugin_slug );

		if ( ! $validator ) {
			return false;
		}

		return $validator->is_valid();
	}

	/**
	 * Obtiene la versión del SDK
	 *
	 * @return string
	 */
	public static function get_version() {
		return IMAGINA_LICENSE_SDK_VERSION;
	}

	/**
	 * Verifica si el gestor de licencias está disponible
	 *
	 * @return bool
	 */
	public static function is_license_manager_available() {
		return class_exists( 'Imagina_Updater_License_Manager' );
	}

	/**
	 * Muestra un mensaje de error si el gestor de licencias no está disponible
	 */
	public static function show_manager_required_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Plugin de Licencias Requerido', 'imagina-license' ); ?></strong>
			</p>
			<p>
				<?php
				echo wp_kses_post(
					__( 'Este plugin requiere que <strong>Imagina Updater Client</strong> esté instalado y activado para funcionar.', 'imagina-license' )
				);
				?>
			</p>
			<p>
				<?php esc_html_e( 'Por favor, instala y activa el plugin Imagina Updater Client.', 'imagina-license' ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * Helper function para inicializar el SDK
 *
 * @param array $args Argumentos de configuración
 * @return Imagina_License_Validator
 */
function imagina_license_init( $args ) {
	return Imagina_License_SDK::init( $args );
}

/**
 * Helper function para verificar si un plugin está licenciado
 *
 * @param string $plugin_slug Slug del plugin
 * @return bool
 */
function imagina_is_licensed( $plugin_slug ) {
	return Imagina_License_SDK::is_licensed( $plugin_slug );
}
