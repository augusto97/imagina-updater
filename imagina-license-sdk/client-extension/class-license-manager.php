<?php
/**
 * Imagina License SDK - Client Extension - License Manager
 *
 * Gestor de licencias para el plugin cliente.
 * Maneja la comunicación con el servidor para validar licencias de plugins.
 *
 * INSTALACIÓN:
 * 1. Copiar este archivo a: imagina-updater-client/includes/
 * 2. En imagina-updater-client.php añadir:
 *    require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';
 *    add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ) );
 *
 * @package Imagina_License_SDK
 * @version 1.0.0
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase de gestión de licencias
 */
class Imagina_Updater_License_Manager {

	/**
	 * Instancia única (Singleton)
	 *
	 * @var Imagina_Updater_License_Manager
	 */
	private static $instance = null;

	/**
	 * Configuración del plugin cliente
	 *
	 * @var array
	 */
	private $config;

	/**
	 * Cliente API
	 *
	 * @var Imagina_Updater_API_Client
	 */
	private $api_client;

	/**
	 * Caché de validaciones en memoria
	 *
	 * @var array
	 */
	private $validation_cache = array();

	/**
	 * Tiempo de caché de validaciones (6 horas)
	 *
	 * @var int
	 */
	const CACHE_EXPIRATION = 21600;

	/**
	 * Constructor privado (Singleton)
	 */
	private function __construct() {
		// Cargar configuración
		$this->config = get_option( 'imagina_updater_client_config', array() );

		// Cargar cliente API si está disponible
		if ( class_exists( 'Imagina_Updater_API_Client' ) ) {
			$this->api_client = new Imagina_Updater_API_Client();
		}
	}

	/**
	 * Inicializa el gestor de licencias
	 */
	public static function init() {
		self::get_instance();
	}

	/**
	 * Obtiene la instancia única
	 *
	 * @return Imagina_Updater_License_Manager
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Verifica la licencia de un plugin
	 *
	 * @param string $plugin_slug Slug del plugin
	 * @return array|false Datos de la licencia si es válida, false si no
	 */
	public function verify_plugin_license( $plugin_slug ) {
		// Verificar que esté configurado
		if ( ! $this->is_configured() ) {
			return $this->error_response( 'not_configured' );
		}

		// Verificar caché en memoria
		if ( isset( $this->validation_cache[ $plugin_slug ] ) ) {
			return $this->validation_cache[ $plugin_slug ];
		}

		// Verificar caché persistente
		$cached = $this->get_cached_validation( $plugin_slug );
		if ( $cached !== false ) {
			$this->validation_cache[ $plugin_slug ] = $cached;
			return $cached;
		}

		// Verificar con el servidor
		$result = $this->verify_with_server( $plugin_slug );

		// Si falla, retornar error
		if ( is_wp_error( $result ) ) {
			$error_data = $this->error_response( $result->get_error_code() );
			$this->validation_cache[ $plugin_slug ] = $error_data;
			return $error_data;
		}

		// Verificar la firma de la respuesta
		if ( ! $this->verify_response_signature( $result ) ) {
			$error_data = $this->error_response( 'invalid_signature' );
			$this->validation_cache[ $plugin_slug ] = $error_data;
			return $error_data;
		}

		// Guardar en caché
		$this->cache_validation( $plugin_slug, $result );
		$this->validation_cache[ $plugin_slug ] = $result;

		return $result;
	}

	/**
	 * Verifica la licencia con el servidor
	 *
	 * @param string $plugin_slug Plugin slug
	 * @return array|WP_Error
	 */
	private function verify_with_server( $plugin_slug ) {
		if ( ! $this->api_client ) {
			return new WP_Error( 'no_api_client', 'API Client no disponible.' );
		}

		// Construir la URL del endpoint
		$endpoint = '/license/verify';

		// Datos de la petición
		$body = array(
			'plugin_slug' => $plugin_slug,
		);

		// Hacer la petición
		$response = $this->api_client->request( 'POST', $endpoint, $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Verifica la firma de una respuesta del servidor
	 *
	 * @param array $response Respuesta del servidor
	 * @return bool
	 */
	private function verify_response_signature( $response ) {
		// Verificar que tenga firma
		if ( ! isset( $response['signature'] ) ) {
			return false;
		}

		$signature = $response['signature'];
		unset( $response['signature'] );

		// Cargar clase de criptografía
		if ( ! class_exists( 'Imagina_License_Crypto' ) ) {
			require_once dirname( __FILE__ ) . '/class-license-crypto-client.php';
		}

		// Verificar firma usando el activation token como secreto
		$secret = $this->get_activation_token();
		if ( ! $secret ) {
			return false;
		}

		$data_string = wp_json_encode( $response );
		return Imagina_License_Crypto::verify_signature( $data_string, $signature, $secret );
	}

	/**
	 * Obtiene el activation token
	 *
	 * @return string|false
	 */
	private function get_activation_token() {
		return isset( $this->config['activation_token'] ) ? $this->config['activation_token'] : false;
	}

	/**
	 * Verifica si el gestor está configurado
	 *
	 * @return bool
	 */
	private function is_configured() {
		return ! empty( $this->config['activation_token'] ) && ! empty( $this->config['server_url'] );
	}

	/**
	 * Obtiene validación desde la caché
	 *
	 * @param string $plugin_slug Plugin slug
	 * @return array|false
	 */
	private function get_cached_validation( $plugin_slug ) {
		$cache_key = 'imagina_license_validation_' . md5( $plugin_slug );
		$cached = get_transient( $cache_key );

		if ( ! $cached || ! is_array( $cached ) ) {
			return false;
		}

		// Verificar expiración
		if ( isset( $cached['expires_at'] ) && $cached['expires_at'] < time() ) {
			delete_transient( $cache_key );
			return false;
		}

		return $cached;
	}

	/**
	 * Guarda validación en caché
	 *
	 * @param string $plugin_slug Plugin slug
	 * @param array  $data        Datos de validación
	 */
	private function cache_validation( $plugin_slug, $data ) {
		$cache_key = 'imagina_license_validation_' . md5( $plugin_slug );
		set_transient( $cache_key, $data, self::CACHE_EXPIRATION );
	}

	/**
	 * Invalida la caché de un plugin
	 *
	 * @param string $plugin_slug Plugin slug
	 */
	public function invalidate_cache( $plugin_slug ) {
		$cache_key = 'imagina_license_validation_' . md5( $plugin_slug );
		delete_transient( $cache_key );
		unset( $this->validation_cache[ $plugin_slug ] );
	}

	/**
	 * Invalida toda la caché de licencias
	 */
	public function invalidate_all_cache() {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_imagina_license_validation_%'
			OR option_name LIKE '_transient_timeout_imagina_license_validation_%'"
		);

		$this->validation_cache = array();
	}

	/**
	 * Genera una respuesta de error
	 *
	 * @param string $error_code Código de error
	 * @return array
	 */
	private function error_response( $error_code ) {
		return array(
			'is_valid' => false,
			'error'    => $error_code,
		);
	}

	/**
	 * Verifica múltiples licencias en batch
	 *
	 * @param array $plugin_slugs Lista de plugin slugs
	 * @return array Resultados indexados por slug
	 */
	public function verify_batch( $plugin_slugs ) {
		if ( ! $this->is_configured() ) {
			return array();
		}

		// Construir la URL del endpoint
		$endpoint = '/license/verify-batch';

		// Datos de la petición
		$body = array(
			'plugin_slugs' => $plugin_slugs,
		);

		// Hacer la petición
		$response = $this->api_client->request( 'POST', $endpoint, $body );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		// Verificar firma
		if ( ! $this->verify_response_signature( $response ) ) {
			return array();
		}

		$results = isset( $response['results'] ) ? $response['results'] : array();

		// Cachear los resultados
		foreach ( $results as $slug => $data ) {
			if ( isset( $data['is_valid'] ) && $data['is_valid'] ) {
				$this->cache_validation( $slug, $data );
				$this->validation_cache[ $slug ] = $data;
			}
		}

		return $results;
	}

	/**
	 * Obtiene información de la licencia actual
	 *
	 * @return array|WP_Error
	 */
	public function get_license_info() {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'not_configured', 'Licencia no configurada.' );
		}

		// Construir la URL del endpoint
		$endpoint = '/license/info';

		// Hacer la petición
		$response = $this->api_client->request( 'POST', $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Verificar firma
		if ( ! $this->verify_response_signature( $response ) ) {
			return new WP_Error( 'invalid_signature', 'Firma inválida.' );
		}

		return $response;
	}

	/**
	 * Verifica si un plugin tiene licencia válida (método público para SDK)
	 *
	 * @param string $plugin_slug Plugin slug
	 * @return bool
	 */
	public function is_plugin_licensed( $plugin_slug ) {
		$result = $this->verify_plugin_license( $plugin_slug );

		return isset( $result['is_valid'] ) && $result['is_valid'] === true;
	}

	/**
	 * Obtiene estadísticas de licencias
	 *
	 * @return array
	 */
	public function get_stats() {
		// Obtener todas las validaciones en caché
		global $wpdb;

		$cached_validations = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_imagina_license_validation_%'
			AND option_name NOT LIKE '_transient_timeout_%'",
			ARRAY_A
		);

		$stats = array(
			'total_cached'    => count( $cached_validations ),
			'valid_licenses'  => 0,
			'invalid_licenses' => 0,
			'plugins'         => array(),
		);

		foreach ( $cached_validations as $row ) {
			$data = maybe_unserialize( $row['option_value'] );

			if ( ! is_array( $data ) ) {
				continue;
			}

			$plugin_slug = isset( $data['plugin_slug'] ) ? $data['plugin_slug'] : 'unknown';
			$is_valid = isset( $data['is_valid'] ) && $data['is_valid'];

			$stats['plugins'][ $plugin_slug ] = array(
				'is_valid'   => $is_valid,
				'expires_at' => isset( $data['expires_at'] ) ? $data['expires_at'] : null,
			);

			if ( $is_valid ) {
				$stats['valid_licenses']++;
			} else {
				$stats['invalid_licenses']++;
			}
		}

		return $stats;
	}
}
