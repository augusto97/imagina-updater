<?php
/**
 * Integración con el sistema de licencias
 *
 * Esta clase proporciona utilidades adicionales para trabajar con el sistema de licencias.
 *
 * @package Example_Premium_Plugin
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase de integración con licencias
 */
class Example_Premium_License_Integration {

	/**
	 * Instancia del validador de licencias
	 *
	 * @var Imagina_License_Validator
	 */
	private static $validator = null;

	/**
	 * Obtiene el validador de licencias
	 *
	 * @return Imagina_License_Validator|null
	 */
	public static function get_validator() {
		if ( null === self::$validator ) {
			self::$validator = Imagina_License_SDK::get_validator( EXAMPLE_PREMIUM_SLUG );
		}

		return self::$validator;
	}

	/**
	 * Verifica si la licencia es válida
	 *
	 * @param bool $force_check Forzar verificación remota
	 * @return bool
	 */
	public static function is_valid( $force_check = false ) {
		$validator = self::get_validator();

		if ( ! $validator ) {
			return false;
		}

		return $validator->is_valid( $force_check );
	}

	/**
	 * Obtiene datos de la licencia
	 *
	 * @return array
	 */
	public static function get_license_data() {
		$validator = self::get_validator();

		if ( ! $validator ) {
			return array();
		}

		return $validator->get_license_data();
	}

	/**
	 * Verifica si está en período de gracia
	 *
	 * @return bool
	 */
	public static function is_in_grace_period() {
		$validator = self::get_validator();

		if ( ! $validator ) {
			return false;
		}

		return $validator->is_in_grace_period();
	}

	/**
	 * Obtiene el tiempo restante de grace period
	 *
	 * @return int Segundos restantes
	 */
	public static function get_grace_period_remaining() {
		$validator = self::get_validator();

		if ( ! $validator ) {
			return 0;
		}

		return $validator->get_grace_period_remaining();
	}

	/**
	 * Fuerza una verificación remota de la licencia
	 *
	 * @return bool
	 */
	public static function force_check() {
		$validator = self::get_validator();

		if ( ! $validator ) {
			return false;
		}

		return $validator->force_check();
	}

	/**
	 * Hook cuando la licencia se invalida
	 *
	 * Puedes usar esto para limpiar datos, notificar al usuario, etc.
	 */
	public static function on_license_invalid() {
		// Log del evento
		error_log( '[Example Premium] Licencia invalidada.' );

		// Notificar al admin (opcional)
		// self::notify_admin_license_invalid();

		// Limpiar datos sensibles (opcional)
		// self::cleanup_premium_data();
	}

	/**
	 * Notifica al administrador sobre licencia inválida
	 */
	private static function notify_admin_license_invalid() {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_option( 'blogname' );

		$subject = sprintf(
			__( '[%s] Licencia del plugin inválida', 'example-premium' ),
			$site_name
		);

		$message = sprintf(
			__( "La licencia del plugin Example Premium ha sido invalidada.\n\nPor favor, verifica la configuración de tu licencia en:\n%s\n\nSi el problema persiste, contacta con soporte.", 'example-premium' ),
			admin_url( 'options-general.php?page=imagina-updater-client' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Limpia datos premium cuando la licencia se invalida
	 */
	private static function cleanup_premium_data() {
		// Ejemplo: Limpiar caché de datos premium
		delete_transient( 'example_premium_cached_data' );

		// Ejemplo: Resetear configuración premium
		$settings = get_option( 'example_premium_settings', array() );
		$settings['premium_feature_enabled'] = false;
		update_option( 'example_premium_settings', $settings );
	}

	/**
	 * Muestra un widget de estado de licencia en el admin
	 */
	public static function render_license_widget() {
		$validator = self::get_validator();

		if ( ! $validator ) {
			return;
		}

		$license_data = $validator->get_license_data();
		$is_in_grace = $validator->is_in_grace_period();
		$grace_remaining = $validator->get_grace_period_remaining();

		?>
		<div class="example-premium-license-widget postbox">
			<h3 class="hndle">
				<span><?php esc_html_e( 'Estado de la Licencia', 'example-premium' ); ?></span>
			</h3>
			<div class="inside">
				<?php if ( $is_in_grace ) : ?>
					<div class="notice notice-warning inline">
						<p>
							<strong><?php esc_html_e( 'Período de Gracia', 'example-premium' ); ?></strong><br>
							<?php
							printf(
								/* translators: %d: días restantes */
								esc_html__( 'Tiempo restante: %d días', 'example-premium' ),
								ceil( $grace_remaining / DAY_IN_SECONDS )
							);
							?>
						</p>
					</div>
				<?php else : ?>
					<p>
						<span class="dashicons dashicons-yes-alt" style="color: green;"></span>
						<?php esc_html_e( 'Licencia activa y válida', 'example-premium' ); ?>
					</p>
				<?php endif; ?>

				<?php if ( isset( $license_data['site_domain'] ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Dominio:', 'example-premium' ); ?></strong>
						<?php echo esc_html( $license_data['site_domain'] ); ?>
					</p>
				<?php endif; ?>

				<?php if ( isset( $license_data['verified_at'] ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Última verificación:', 'example-premium' ); ?></strong>
						<?php echo esc_html( $license_data['verified_at'] ); ?>
					</p>
				<?php endif; ?>

				<p>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=imagina-updater-client' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Gestionar Licencia', 'example-premium' ); ?>
					</a>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Helper: Verifica licencia antes de ejecutar una función
	 *
	 * Uso:
	 * if ( ! Example_Premium_License_Integration::require_license() ) {
	 *     return;
	 * }
	 *
	 * @return bool
	 */
	public static function require_license() {
		if ( ! self::is_valid() ) {
			// En admin, mostrar aviso
			if ( is_admin() ) {
				add_action( 'admin_notices', array( __CLASS__, 'show_license_required_notice' ) );
			}

			return false;
		}

		return true;
	}

	/**
	 * Muestra aviso de licencia requerida
	 */
	public static function show_license_required_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Example Premium Plugin', 'example-premium' ); ?>:</strong>
				<?php esc_html_e( 'Esta funcionalidad requiere una licencia válida.', 'example-premium' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=imagina-updater-client' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Configurar Licencia', 'example-premium' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

// Registrar hook cuando la licencia se invalida
add_action(
	'imagina_license_invalid_' . EXAMPLE_PREMIUM_SLUG,
	array( 'Example_Premium_License_Integration', 'on_license_invalid' )
);
