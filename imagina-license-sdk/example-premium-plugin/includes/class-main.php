<?php
/**
 * Clase principal del plugin premium de ejemplo
 *
 * @package Example_Premium_Plugin
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase principal
 */
class Example_Premium_Main {

	/**
	 * Instancia única
	 *
	 * @var Example_Premium_Main
	 */
	private static $instance = null;

	/**
	 * Inicializa el plugin
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Registrar hooks
		$this->register_hooks();
	}

	/**
	 * Registra los hooks del plugin
	 */
	private function register_hooks() {
		// Admin
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}

		// Frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// REST API
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX
		add_action( 'wp_ajax_example_premium_action', array( $this, 'handle_ajax' ) );

		// Shortcodes
		add_shortcode( 'example_premium', array( $this, 'shortcode_example' ) );
	}

	/**
	 * Añade menú de administración
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Example Premium', 'example-premium' ),
			__( 'Example Premium', 'example-premium' ),
			'manage_options',
			'example-premium',
			array( $this, 'render_admin_page' ),
			'dashicons-star-filled',
			30
		);
	}

	/**
	 * Renderiza la página de administración
	 */
	public function render_admin_page() {
		// Verificar permisos
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Obtener información de la licencia
		$license_validator = Imagina_License_SDK::get_validator( EXAMPLE_PREMIUM_SLUG );
		$license_data = $license_validator ? $license_validator->get_license_data() : array();
		$is_in_grace = $license_validator ? $license_validator->is_in_grace_period() : false;
		$grace_remaining = $license_validator ? $license_validator->get_grace_period_remaining() : 0;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Example Premium Plugin', 'example-premium' ); ?></h1>

			<?php if ( $is_in_grace ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Período de Gracia Activo', 'example-premium' ); ?></strong>
					</p>
					<p>
						<?php
						printf(
							/* translators: %s: días restantes */
							esc_html__( 'El plugin está funcionando en modo de gracia. Tiempo restante: %s días.', 'example-premium' ),
							ceil( $grace_remaining / DAY_IN_SECONDS )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="card">
				<h2><?php esc_html_e( 'Estado de la Licencia', 'example-premium' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Estado', 'example-premium' ); ?></th>
						<td>
							<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
							<?php esc_html_e( 'Licencia Válida', 'example-premium' ); ?>
						</td>
					</tr>
					<?php if ( isset( $license_data['plugin_name'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Plugin', 'example-premium' ); ?></th>
							<td><?php echo esc_html( $license_data['plugin_name'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( isset( $license_data['site_domain'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Dominio', 'example-premium' ); ?></th>
							<td><?php echo esc_html( $license_data['site_domain'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( isset( $license_data['verified_at'] ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'Última Verificación', 'example-premium' ); ?></th>
							<td><?php echo esc_html( $license_data['verified_at'] ); ?></td>
						</tr>
					<?php endif; ?>
				</table>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Funcionalidades del Plugin', 'example-premium' ); ?></h2>
				<p>
					<?php esc_html_e( 'Este es un plugin premium de ejemplo. Aquí irían las funcionalidades reales de tu plugin.', 'example-premium' ); ?>
				</p>

				<h3><?php esc_html_e( 'Características:', 'example-premium' ); ?></h3>
				<ul>
					<li>✓ <?php esc_html_e( 'Funcionalidad premium 1', 'example-premium' ); ?></li>
					<li>✓ <?php esc_html_e( 'Funcionalidad premium 2', 'example-premium' ); ?></li>
					<li>✓ <?php esc_html_e( 'Funcionalidad premium 3', 'example-premium' ); ?></li>
				</ul>
			</div>

			<div class="card">
				<h2><?php esc_html_e( 'Configuración', 'example-premium' ); ?></h2>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'example_premium_settings' );
					do_settings_sections( 'example_premium_settings' );
					submit_button();
					?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Registra los ajustes del plugin
	 */
	public function register_settings() {
		register_setting(
			'example_premium_settings',
			'example_premium_settings',
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'example_premium_general',
			__( 'Configuración General', 'example-premium' ),
			null,
			'example_premium_settings'
		);

		add_settings_field(
			'enabled',
			__( 'Habilitar funcionalidad', 'example-premium' ),
			array( $this, 'render_enabled_field' ),
			'example_premium_settings',
			'example_premium_general'
		);
	}

	/**
	 * Renderiza el campo de habilitación
	 */
	public function render_enabled_field() {
		$settings = get_option( 'example_premium_settings', array() );
		$enabled = isset( $settings['enabled'] ) ? $settings['enabled'] : true;
		?>
		<label>
			<input type="checkbox" name="example_premium_settings[enabled]" value="1" <?php checked( $enabled, true ); ?>>
			<?php esc_html_e( 'Habilitar las funcionalidades premium', 'example-premium' ); ?>
		</label>
		<?php
	}

	/**
	 * Sanitiza los ajustes
	 *
	 * @param array $input Input data
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['enabled'] ) ) {
			$sanitized['enabled'] = (bool) $input['enabled'];
		}

		return $sanitized;
	}

	/**
	 * Encola scripts y estilos
	 */
	public function enqueue_scripts() {
		// Solo en páginas donde se usa el plugin
		if ( ! $this->is_plugin_active_on_page() ) {
			return;
		}

		wp_enqueue_style(
			'example-premium-style',
			EXAMPLE_PREMIUM_PLUGIN_URL . 'assets/css/style.css',
			array(),
			EXAMPLE_PREMIUM_VERSION
		);

		wp_enqueue_script(
			'example-premium-script',
			EXAMPLE_PREMIUM_PLUGIN_URL . 'assets/js/script.js',
			array( 'jquery' ),
			EXAMPLE_PREMIUM_VERSION,
			true
		);
	}

	/**
	 * Verifica si el plugin está activo en la página actual
	 *
	 * @return bool
	 */
	private function is_plugin_active_on_page() {
		// Lógica para determinar si el plugin debe cargarse
		return true;
	}

	/**
	 * Registra rutas REST API
	 */
	public function register_rest_routes() {
		register_rest_route(
			'example-premium/v1',
			'/data',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_data' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);
	}

	/**
	 * Endpoint REST API
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response
	 */
	public function rest_get_data( $request ) {
		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Esta es una respuesta de ejemplo del plugin premium.',
			'data'    => array(
				'version' => EXAMPLE_PREMIUM_VERSION,
			),
		) );
	}

	/**
	 * Verifica permisos para REST API
	 *
	 * IMPORTANTE: La licencia ya se verificó al cargar el plugin,
	 * pero puedes añadir verificaciones adicionales aquí.
	 *
	 * @return bool
	 */
	public function rest_permission_check() {
		// Verificar que la licencia sigue siendo válida
		$license_validator = Imagina_License_SDK::get_validator( EXAMPLE_PREMIUM_SLUG );

		if ( ! $license_validator || ! $license_validator->is_valid() ) {
			return false;
		}

		return true;
	}

	/**
	 * Maneja peticiones AJAX
	 */
	public function handle_ajax() {
		// Verificar nonce
		check_ajax_referer( 'example_premium_nonce', 'nonce' );

		// Verificar licencia (doble verificación)
		if ( ! Imagina_License_SDK::is_licensed( EXAMPLE_PREMIUM_SLUG ) ) {
			wp_send_json_error( array(
				'message' => __( 'Licencia inválida.', 'example-premium' ),
			) );
		}

		// Procesar acción
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';

		switch ( $action ) {
			case 'test':
				wp_send_json_success( array(
					'message' => __( 'Acción de prueba ejecutada correctamente.', 'example-premium' ),
				) );
				break;

			default:
				wp_send_json_error( array(
					'message' => __( 'Acción no válida.', 'example-premium' ),
				) );
		}
	}

	/**
	 * Shortcode de ejemplo
	 *
	 * @param array $atts Atributos
	 * @return string
	 */
	public function shortcode_example( $atts ) {
		// Verificar licencia antes de renderizar
		if ( ! Imagina_License_SDK::is_licensed( EXAMPLE_PREMIUM_SLUG ) ) {
			return '<p>' . esc_html__( 'Este contenido requiere una licencia válida.', 'example-premium' ) . '</p>';
		}

		$atts = shortcode_atts(
			array(
				'title' => __( 'Contenido Premium', 'example-premium' ),
			),
			$atts,
			'example_premium'
		);

		ob_start();
		?>
		<div class="example-premium-shortcode">
			<h3><?php echo esc_html( $atts['title'] ); ?></h3>
			<p><?php esc_html_e( 'Este es contenido premium protegido por licencia.', 'example-premium' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}
}
