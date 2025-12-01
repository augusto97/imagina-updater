<?php
/**
 * Imagina License SDK - Heartbeat System
 *
 * Sistema de verificación periódica de licencias en background.
 * Verifica automáticamente las licencias cada 12 horas usando WP-Cron.
 *
 * @package Imagina_License_SDK
 * @version 1.0.0
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase de heartbeat para verificación periódica
 */
class Imagina_License_Heartbeat {

	/**
	 * Intervalo de verificación (12 horas)
	 *
	 * @var int
	 */
	const CHECK_INTERVAL = 43200; // 12 horas

	/**
	 * Hook del cron
	 *
	 * @var string
	 */
	const CRON_HOOK = 'imagina_license_heartbeat';

	/**
	 * Instancia única
	 *
	 * @var Imagina_License_Heartbeat
	 */
	private static $instance = null;

	/**
	 * Plugins registrados para verificación
	 *
	 * @var array
	 */
	private $registered_plugins = array();

	/**
	 * Constructor privado (Singleton)
	 */
	private function __construct() {
		// Registrar el intervalo de cron personalizado
		add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );

		// Registrar el hook del cron
		add_action( self::CRON_HOOK, array( $this, 'run_heartbeat' ) );

		// Programar el cron si no existe
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'imagina_license_12hours', self::CRON_HOOK );
		}
	}

	/**
	 * Obtiene la instancia única
	 *
	 * @return Imagina_License_Heartbeat
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Añade intervalo personalizado de 12 horas
	 *
	 * @param array $schedules Schedules existentes
	 * @return array
	 */
	public function add_cron_interval( $schedules ) {
		$schedules['imagina_license_12hours'] = array(
			'interval' => self::CHECK_INTERVAL,
			'display'  => __( 'Cada 12 horas (Imagina License)', 'imagina-license' ),
		);

		return $schedules;
	}

	/**
	 * Registra un plugin para verificación periódica
	 *
	 * @param string                      $plugin_slug Plugin slug
	 * @param Imagina_License_Validator   $validator   Instancia del validador
	 */
	public function register_plugin( $plugin_slug, $validator ) {
		$this->registered_plugins[ $plugin_slug ] = $validator;
	}

	/**
	 * Ejecuta el heartbeat (verificación de todas las licencias)
	 */
	public function run_heartbeat() {
		// Log del inicio
		$this->log( 'Iniciando verificación periódica de licencias.' );

		// Verificar cada plugin registrado
		foreach ( $this->registered_plugins as $slug => $validator ) {
			$this->verify_plugin_license( $slug, $validator );
		}

		// Log del final
		$this->log( sprintf(
			'Verificación completada. %d plugins verificados.',
			count( $this->registered_plugins )
		) );

		// Limpiar logs antiguos
		$this->cleanup_old_logs();
	}

	/**
	 * Verifica la licencia de un plugin
	 *
	 * @param string                    $slug      Plugin slug
	 * @param Imagina_License_Validator $validator Validador
	 */
	private function verify_plugin_license( $slug, $validator ) {
		$this->log( sprintf( 'Verificando licencia de %s...', $slug ) );

		// Forzar verificación remota
		$is_valid = $validator->force_check();

		if ( $is_valid ) {
			$this->log( sprintf( '%s: Licencia válida ✓', $slug ) );
		} else {
			$this->log( sprintf( '%s: Licencia inválida ✗', $slug ), 'warning' );

			// Notificar al administrador
			$this->notify_admin_invalid_license( $slug, $validator );
		}
	}

	/**
	 * Notifica al administrador sobre una licencia inválida
	 *
	 * @param string                    $slug      Plugin slug
	 * @param Imagina_License_Validator $validator Validador
	 */
	private function notify_admin_invalid_license( $slug, $validator ) {
		// Obtener datos de la licencia
		$license_data = $validator->get_license_data();
		$error_reason = isset( $license_data['error'] ) ? $license_data['error'] : 'unknown';

		// Verificar si ya se notificó recientemente
		$notification_key = 'imagina_license_notification_' . $slug;
		$last_notification = get_transient( $notification_key );

		if ( $last_notification ) {
			return; // Ya se notificó en las últimas 24 horas
		}

		// Obtener email del admin
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_option( 'blogname' );

		// Preparar el mensaje
		$subject = sprintf(
			__( '[%s] Licencia inválida detectada', 'imagina-license' ),
			$site_name
		);

		$message = sprintf(
			__( "Se ha detectado que la licencia del plugin '%s' no es válida.\n\nRazón: %s\n\nPor favor, revisa la configuración de licencias en:\n%s\n\nEste es un mensaje automático del sistema de licencias.", 'imagina-license' ),
			$slug,
			$error_reason,
			admin_url( 'options-general.php?page=imagina-updater-client' )
		);

		// Enviar email
		wp_mail( $admin_email, $subject, $message );

		// Marcar como notificado (24 horas)
		set_transient( $notification_key, true, DAY_IN_SECONDS );

		$this->log( sprintf( 'Notificación enviada a %s', $admin_email ) );
	}

	/**
	 * Registra un mensaje en el log
	 *
	 * @param string $message Mensaje
	 * @param string $level   Nivel (info, warning, error)
	 */
	private function log( $message, $level = 'info' ) {
		// Obtener logs existentes
		$logs = get_option( 'imagina_license_heartbeat_logs', array() );

		// Añadir nuevo log
		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
		);

		// Mantener solo los últimos 100 logs
		if ( count( $logs ) > 100 ) {
			$logs = array_slice( $logs, -100 );
		}

		// Guardar
		update_option( 'imagina_license_heartbeat_logs', $logs );

		// Log también en error_log si es warning o error
		if ( in_array( $level, array( 'warning', 'error' ), true ) ) {
			error_log( sprintf( '[Imagina License Heartbeat] %s: %s', strtoupper( $level ), $message ) );
		}
	}

	/**
	 * Limpia logs antiguos (mayores a 30 días)
	 */
	private function cleanup_old_logs() {
		$logs = get_option( 'imagina_license_heartbeat_logs', array() );

		if ( empty( $logs ) ) {
			return;
		}

		$cutoff_date = strtotime( '-30 days' );
		$cleaned_logs = array();

		foreach ( $logs as $log ) {
			$log_timestamp = strtotime( $log['timestamp'] );
			if ( $log_timestamp > $cutoff_date ) {
				$cleaned_logs[] = $log;
			}
		}

		update_option( 'imagina_license_heartbeat_logs', $cleaned_logs );
	}

	/**
	 * Obtiene los logs del heartbeat
	 *
	 * @param int $limit Número máximo de logs a retornar
	 * @return array
	 */
	public static function get_logs( $limit = 50 ) {
		$logs = get_option( 'imagina_license_heartbeat_logs', array() );

		// Retornar los más recientes
		return array_slice( array_reverse( $logs ), 0, $limit );
	}

	/**
	 * Ejecuta una verificación manual inmediata
	 *
	 * @return array Resultados de la verificación
	 */
	public static function run_manual_check() {
		$instance = self::get_instance();
		$results = array();

		foreach ( $instance->registered_plugins as $slug => $validator ) {
			$is_valid = $validator->force_check();
			$results[ $slug ] = array(
				'is_valid' => $is_valid,
				'data'     => $validator->get_license_data(),
			);
		}

		return $results;
	}

	/**
	 * Limpia todos los datos del heartbeat
	 */
	public static function cleanup() {
		// Desactivar el cron
		wp_clear_scheduled_hook( self::CRON_HOOK );

		// Limpiar logs
		delete_option( 'imagina_license_heartbeat_logs' );

		// Limpiar notificaciones
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_imagina_license_notification_%'"
		);
	}
}
