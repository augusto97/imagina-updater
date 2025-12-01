<?php
/**
 * Imagina License SDK - License Validator
 *
 * Validador principal de licencias con múltiples capas de seguridad.
 * CRÍTICO: No modificar este archivo - La integridad se verifica automáticamente.
 *
 * @package Imagina_License_SDK
 * @version 1.0.0
 * @checksum WILL_BE_CALCULATED_ON_BUILD
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase principal de validación de licencias
 */
class Imagina_License_Validator {

	/**
	 * Slug del plugin que está siendo validado
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Nombre del plugin
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Archivo principal del plugin
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Período de gracia en segundos
	 *
	 * @var int
	 */
	private $grace_period;

	/**
	 * Clave de opción para almacenar datos de licencia
	 *
	 * @var string
	 */
	private $option_key;

	/**
	 * Estado de la licencia (caché en memoria)
	 *
	 * @var array|null
	 */
	private $license_state = null;

	/**
	 * Flag de verificación de integridad
	 *
	 * @var bool
	 */
	private $integrity_checked = false;

	/**
	 * Timestamp de la última verificación remota
	 *
	 * @var int
	 */
	private $last_remote_check = 0;

	/**
	 * Intervalo mínimo entre verificaciones remotas (12 horas)
	 *
	 * @var int
	 */
	const REMOTE_CHECK_INTERVAL = 43200;

	/**
	 * Versión del SDK
	 *
	 * @var string
	 */
	const SDK_VERSION = '1.0.0';

	/**
	 * Constructor
	 *
	 * @param array $args Argumentos de configuración
	 */
	public function __construct( $args = array() ) {
		// Validar argumentos requeridos
		if ( empty( $args['plugin_slug'] ) || empty( $args['plugin_name'] ) || empty( $args['plugin_file'] ) ) {
			wp_die( 'Imagina License SDK: Configuración inválida.' );
		}

		$this->plugin_slug  = sanitize_key( $args['plugin_slug'] );
		$this->plugin_name  = sanitize_text_field( $args['plugin_name'] );
		$this->plugin_file  = $args['plugin_file'];
		$this->grace_period = isset( $args['grace_period'] ) ? absint( $args['grace_period'] ) : 3 * DAY_IN_SECONDS;
		$this->option_key   = 'imagina_license_' . $this->plugin_slug;

		// Verificar integridad del SDK al instanciar
		$this->verify_sdk_integrity();

		// Cargar estado de licencia
		$this->load_license_state();

		// Registrar hooks
		$this->register_hooks();
	}

	/**
	 * Registra los hooks necesarios
	 */
	private function register_hooks() {
		// Verificación al iniciar el admin
		add_action( 'admin_init', array( $this, 'validate_on_admin_init' ) );

		// Desactivación al desactivar el plugin
		add_action( 'deactivate_' . plugin_basename( $this->plugin_file ), array( $this, 'on_plugin_deactivation' ) );

		// Proteger endpoints AJAX y REST API
		add_action( 'wp_ajax_' . $this->plugin_slug . '_*', array( $this, 'validate_before_ajax' ), 0 );
		add_filter( 'rest_pre_dispatch', array( $this, 'validate_before_rest' ), 10, 3 );
	}

	/**
	 * Verifica la integridad del SDK
	 *
	 * CAPA DE SEGURIDAD #5: Detecta modificaciones del código
	 */
	private function verify_sdk_integrity() {
		// Evitar verificación múltiple
		if ( $this->integrity_checked ) {
			return;
		}

		// Calcular checksum del archivo actual
		$current_file = __FILE__;
		$checksum     = Imagina_License_Crypto::file_checksum( $current_file );

		if ( ! $checksum ) {
			$this->trigger_integrity_failure( 'checksum_error' );
			return;
		}

		// Obtener checksum esperado (almacenado por el servidor)
		$expected_checksum = $this->get_expected_checksum();

		// Si no hay checksum esperado, generar uno
		// (Primera vez que se ejecuta)
		if ( ! $expected_checksum ) {
			$this->store_expected_checksum( $checksum );
			$this->integrity_checked = true;
			return;
		}

		// Verificar integridad
		if ( ! hash_equals( $expected_checksum, $checksum ) ) {
			$this->trigger_integrity_failure( 'modified_code' );
			return;
		}

		$this->integrity_checked = true;
	}

	/**
	 * Obtiene el checksum esperado del servidor
	 *
	 * @return string|false
	 */
	private function get_expected_checksum() {
		$checksums = get_option( 'imagina_sdk_checksums', array() );
		return isset( $checksums[ $this->plugin_slug ] ) ? $checksums[ $this->plugin_slug ] : false;
	}

	/**
	 * Almacena el checksum esperado
	 *
	 * @param string $checksum
	 */
	private function store_expected_checksum( $checksum ) {
		$checksums                         = get_option( 'imagina_sdk_checksums', array() );
		$checksums[ $this->plugin_slug ]   = $checksum;
		update_option( 'imagina_sdk_checksums', $checksums );
	}

	/**
	 * Trigger cuando falla la verificación de integridad
	 *
	 * @param string $reason Razón del fallo
	 */
	private function trigger_integrity_failure( $reason ) {
		// Log del incidente
		error_log( sprintf(
			'[Imagina License SDK] Integridad comprometida para %s. Razón: %s',
			$this->plugin_slug,
			$reason
		) );

		// Invalidar licencia
		$this->invalidate_license( 'integrity_failure' );

		// Desactivar plugin
		if ( is_admin() ) {
			deactivate_plugins( plugin_basename( $this->plugin_file ) );
			wp_die(
				sprintf(
					'<h1>Error de Seguridad</h1><p>El plugin <strong>%s</strong> ha detectado una modificación no autorizada en su código de licenciamiento.</p><p>Por favor, reinstala el plugin desde la fuente oficial.</p>',
					esc_html( $this->plugin_name )
				)
			);
		}
	}

	/**
	 * Carga el estado de la licencia desde la base de datos
	 */
	private function load_license_state() {
		$state = get_option( $this->option_key );

		if ( ! $state || ! is_array( $state ) ) {
			$state = array(
				'is_valid'           => false,
				'last_check'         => 0,
				'license_token'      => '',
				'license_data'       => array(),
				'failure_count'      => 0,
				'grace_period_start' => 0,
			);
		}

		$this->license_state = $state;
		$this->last_remote_check = absint( $state['last_check'] );
	}

	/**
	 * Guarda el estado de la licencia en la base de datos
	 */
	private function save_license_state() {
		update_option( $this->option_key, $this->license_state );
	}

	/**
	 * Verifica si la licencia es válida
	 *
	 * PUNTO DE ENTRADA PRINCIPAL
	 *
	 * @param bool $force_remote_check Forzar verificación remota
	 * @return bool True si la licencia es válida
	 */
	public function is_valid( $force_remote_check = false ) {
		// CAPA #1: Verificar integridad del SDK
		if ( ! $this->integrity_checked ) {
			return false;
		}

		// CAPA #2: Verificar que existe el plugin cliente
		if ( ! $this->is_license_manager_available() ) {
			return false;
		}

		// CAPA #3: Verificar caché local (si no se fuerza verificación remota)
		if ( ! $force_remote_check && $this->is_cache_valid() ) {
			return $this->license_state['is_valid'];
		}

		// CAPA #4: Verificación remota con el servidor
		$result = $this->verify_with_server();

		// Si falla la verificación remota
		if ( ! $result ) {
			return $this->handle_verification_failure();
		}

		// Actualizar estado local
		$this->update_license_state( $result );

		return true;
	}

	/**
	 * Verifica si el gestor de licencias está disponible
	 *
	 * @return bool
	 */
	private function is_license_manager_available() {
		return class_exists( 'Imagina_Updater_License_Manager' );
	}

	/**
	 * Verifica si el caché local es válido
	 *
	 * @return bool
	 */
	private function is_cache_valid() {
		// Si nunca se ha verificado
		if ( empty( $this->license_state['last_check'] ) ) {
			return false;
		}

		// Si pasó el intervalo de verificación
		$time_since_check = time() - $this->license_state['last_check'];
		if ( $time_since_check > self::REMOTE_CHECK_INTERVAL ) {
			return false;
		}

		// Si está en período de gracia, siempre verificar
		if ( ! empty( $this->license_state['grace_period_start'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Verifica la licencia con el servidor
	 *
	 * CAPA DE SEGURIDAD #2: Validación remota obligatoria
	 *
	 * @return array|false Datos de la licencia si es válida
	 */
	private function verify_with_server() {
		// Verificar que el gestor de licencias esté disponible
		if ( ! class_exists( 'Imagina_Updater_License_Manager' ) ) {
			return false;
		}

		// Obtener instancia del gestor
		$manager = Imagina_Updater_License_Manager::get_instance();

		// Verificar licencia para este plugin
		$result = $manager->verify_plugin_license( $this->plugin_slug );

		// Actualizar timestamp
		$this->last_remote_check = time();
		$this->license_state['last_check'] = time();

		return $result;
	}

	/**
	 * Maneja el fallo de verificación
	 *
	 * CAPA DE SEGURIDAD #7: Grace period configurable
	 *
	 * @return bool
	 */
	private function handle_verification_failure() {
		// Incrementar contador de fallos
		$this->license_state['failure_count']++;

		// Si es el primer fallo, iniciar grace period
		if ( empty( $this->license_state['grace_period_start'] ) ) {
			$this->license_state['grace_period_start'] = time();
		}

		// Verificar si aún está en grace period
		$time_in_grace = time() - $this->license_state['grace_period_start'];

		if ( $time_in_grace < $this->grace_period ) {
			// Aún en grace period, permitir funcionamiento
			$this->save_license_state();
			return true;
		}

		// Grace period expirado, invalidar licencia
		$this->invalidate_license( 'grace_period_expired' );
		return false;
	}

	/**
	 * Actualiza el estado de la licencia
	 *
	 * @param array $license_data Datos de la licencia
	 */
	private function update_license_state( $license_data ) {
		$this->license_state = array(
			'is_valid'           => true,
			'last_check'         => time(),
			'license_token'      => isset( $license_data['token'] ) ? $license_data['token'] : '',
			'license_data'       => $license_data,
			'failure_count'      => 0,
			'grace_period_start' => 0, // Reset grace period
		);

		$this->save_license_state();
	}

	/**
	 * Invalida la licencia
	 *
	 * @param string $reason Razón de la invalidación
	 */
	private function invalidate_license( $reason = 'unknown' ) {
		$this->license_state['is_valid']     = false;
		$this->license_state['last_check']   = time();
		$this->license_state['license_data'] = array( 'error' => $reason );

		$this->save_license_state();

		// Log del evento
		error_log( sprintf(
			'[Imagina License SDK] Licencia invalidada para %s. Razón: %s',
			$this->plugin_slug,
			$reason
		) );
	}

	/**
	 * Validación en admin_init
	 *
	 * CAPA DE SEGURIDAD #7: Múltiples puntos de verificación
	 */
	public function validate_on_admin_init() {
		// Solo verificar en el admin
		if ( ! is_admin() ) {
			return;
		}

		// Verificar licencia (usa caché)
		if ( ! $this->is_valid() ) {
			// Desactivar funcionalidades
			$this->disable_plugin_functionality();
		}
	}

	/**
	 * Validación antes de AJAX
	 *
	 * @param mixed $args Argumentos del hook
	 */
	public function validate_before_ajax( $args = null ) {
		if ( ! $this->is_valid() ) {
			wp_send_json_error( array(
				'message' => sprintf(
					__( '%s requiere una licencia válida.', 'imagina-license' ),
					$this->plugin_name
				),
			) );
		}
	}

	/**
	 * Validación antes de REST API
	 *
	 * @param mixed           $result  Resultado
	 * @param WP_REST_Server  $server  Servidor REST
	 * @param WP_REST_Request $request Request
	 * @return mixed
	 */
	public function validate_before_rest( $result, $server, $request ) {
		// Verificar si es una ruta de este plugin
		$route = $request->get_route();
		if ( strpos( $route, '/' . $this->plugin_slug . '/' ) === false ) {
			return $result;
		}

		// Validar licencia
		if ( ! $this->is_valid() ) {
			return new WP_Error(
				'license_invalid',
				sprintf(
					__( '%s requiere una licencia válida.', 'imagina-license' ),
					$this->plugin_name
				),
				array( 'status' => 403 )
			);
		}

		return $result;
	}

	/**
	 * Desactiva la funcionalidad del plugin
	 */
	private function disable_plugin_functionality() {
		// Mostrar aviso de licencia
		add_action( 'admin_notices', array( $this, 'show_license_notice' ) );

		// Trigger hook para que el plugin desactive sus funcionalidades
		do_action( 'imagina_license_invalid_' . $this->plugin_slug );
	}

	/**
	 * Muestra aviso de licencia inválida
	 */
	public function show_license_notice() {
		$reason = isset( $this->license_state['license_data']['error'] )
			? $this->license_state['license_data']['error']
			: 'unknown';

		$messages = array(
			'not_configured'       => __( 'El sistema de licencias no está configurado.', 'imagina-license' ),
			'invalid_license'      => __( 'La licencia no es válida para este plugin.', 'imagina-license' ),
			'license_expired'      => __( 'La licencia ha expirado.', 'imagina-license' ),
			'license_deactivated'  => __( 'La licencia ha sido desactivada.', 'imagina-license' ),
			'grace_period_expired' => __( 'No se pudo verificar la licencia. El período de gracia ha expirado.', 'imagina-license' ),
			'integrity_failure'    => __( 'Se detectó una modificación no autorizada en el código.', 'imagina-license' ),
			'unknown'              => __( 'Error desconocido al validar la licencia.', 'imagina-license' ),
		);

		$message = isset( $messages[ $reason ] ) ? $messages[ $reason ] : $messages['unknown'];

		// Información del grace period
		$grace_info = '';
		if ( ! empty( $this->license_state['grace_period_start'] ) ) {
			$time_in_grace = time() - $this->license_state['grace_period_start'];
			$time_remaining = $this->grace_period - $time_in_grace;

			if ( $time_remaining > 0 ) {
				$days_remaining = ceil( $time_remaining / DAY_IN_SECONDS );
				$grace_info = sprintf(
					__( ' Quedan %d días antes de que el plugin se desactive.', 'imagina-license' ),
					$days_remaining
				);
			}
		}

		?>
		<div class="notice notice-error">
			<p>
				<strong><?php echo esc_html( $this->plugin_name ); ?>:</strong>
				<?php echo esc_html( $message ); ?>
				<?php echo esc_html( $grace_info ); ?>
			</p>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<p>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=imagina-updater-client' ) ); ?>" class="button button-primary">
						<?php esc_html_e( 'Configurar Licencia', 'imagina-license' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Al desactivar el plugin
	 */
	public function on_plugin_deactivation() {
		// Limpiar estado de licencia
		delete_option( $this->option_key );
	}

	/**
	 * Obtiene datos de la licencia
	 *
	 * @return array
	 */
	public function get_license_data() {
		return $this->license_state['license_data'];
	}

	/**
	 * Verifica si está en grace period
	 *
	 * @return bool
	 */
	public function is_in_grace_period() {
		return ! empty( $this->license_state['grace_period_start'] );
	}

	/**
	 * Obtiene el tiempo restante de grace period
	 *
	 * @return int Segundos restantes, 0 si no está en grace period
	 */
	public function get_grace_period_remaining() {
		if ( ! $this->is_in_grace_period() ) {
			return 0;
		}

		$time_in_grace = time() - $this->license_state['grace_period_start'];
		$time_remaining = $this->grace_period - $time_in_grace;

		return max( 0, $time_remaining );
	}

	/**
	 * Fuerza una verificación remota inmediata
	 *
	 * @return bool
	 */
	public function force_check() {
		return $this->is_valid( true );
	}
}
