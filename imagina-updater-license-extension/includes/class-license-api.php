<?php
/**
 * Imagina License SDK - Server Extension - REST API
 *
 * Extiende la API REST del servidor para añadir endpoints de validación de licencias.
 *
 * INSTALACIÓN:
 * 1. Copiar este archivo a: imagina-updater-server/api/
 * 2. En imagina-updater-server.php añadir:
 *    require_once plugin_dir_path( __FILE__ ) . 'api/class-license-api.php';
 *    add_action( 'rest_api_init', array( 'Imagina_License_API', 'register_routes' ) );
 *
 * @package Imagina_License_SDK
 * @version 1.0.0
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase de API REST para licencias
 */
class Imagina_License_API {

	/**
	 * Namespace de la API
	 */
	const NAMESPACE = 'imagina-updater/v1';

	/**
	 * Registra las rutas de la API
	 */
	public static function register_routes() {
		// Endpoint: Verificar licencia de un plugin específico
		register_rest_route(
			self::NAMESPACE,
			'/license/verify',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'verify_plugin_license' ),
				'permission_callback' => array( __CLASS__, 'check_activation_token' ),
			)
		);

		// Endpoint: Obtener información de licencia
		register_rest_route(
			self::NAMESPACE,
			'/license/info',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'get_license_info' ),
				'permission_callback' => array( __CLASS__, 'check_activation_token' ),
			)
		);

		// Endpoint: Verificar múltiples licencias (para heartbeat)
		register_rest_route(
			self::NAMESPACE,
			'/license/verify-batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'verify_batch' ),
				'permission_callback' => array( __CLASS__, 'check_activation_token' ),
			)
		);

		// Endpoint: Kill Switch - Verificar si una instalación está bloqueada
		register_rest_route(
			'imagina-license/v1',
			'/killswitch',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'check_killswitch' ),
				'permission_callback' => '__return_true', // Público pero requiere activation_token
			)
		);

		// Endpoint: Telemetry - Recibir datos de telemetría de instalaciones
		register_rest_route(
			'imagina-license/v1',
			'/telemetry',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'receive_telemetry' ),
				'permission_callback' => '__return_true', // Público pero requiere activation_token
			)
		);

		// ===============================================
		// NUEVOS ENDPOINTS - Sistema de License Keys v5.0
		// ===============================================

		// Endpoint: Activar licencia con License Key
		register_rest_route(
			'imagina-license/v1',
			'/activate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'activate_license_key' ),
				'permission_callback' => '__return_true',
			)
		);

		// Endpoint: Desactivar licencia
		register_rest_route(
			'imagina-license/v1',
			'/deactivate',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'deactivate_license_key' ),
				'permission_callback' => '__return_true',
			)
		);

		// Endpoint: Verificar licencia con License Key
		register_rest_route(
			'imagina-license/v1',
			'/check',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'check_license_key' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verifica la licencia de un plugin específico
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function verify_plugin_license( $request ) {
		$plugin_slug = sanitize_key( $request->get_param( 'plugin_slug' ) );

		if ( empty( $plugin_slug ) ) {
			return new WP_Error(
				'missing_plugin_slug',
				__( 'Plugin slug es requerido.', 'imagina-updater' ),
				array( 'status' => 400 )
			);
		}

		// Obtener datos de activación desde el header
		$activation_data = $request->get_attribute( 'activation_data' );

		if ( ! $activation_data ) {
			return new WP_Error(
				'invalid_activation',
				__( 'Datos de activación inválidos.', 'imagina-updater' ),
				array( 'status' => 401 )
			);
		}

		// Obtener el plugin desde la base de datos
		global $wpdb;
		$table_name = $wpdb->prefix . 'imagina_updater_plugins';

		$plugin = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE slug = %s OR slug_override = %s LIMIT 1",
				$plugin_slug,
				$plugin_slug
			)
		);

		if ( ! $plugin ) {
			return new WP_Error(
				'plugin_not_found',
				__( 'Plugin no encontrado en el servidor.', 'imagina-updater' ),
				array( 'status' => 404 )
			);
		}

		// Verificar si el cliente tiene permiso para este plugin
		$has_access = self::verify_plugin_access(
			$activation_data['api_key_id'],
			$plugin->id
		);

		if ( ! $has_access ) {
			return new WP_Error(
				'no_access',
				__( 'No tienes acceso a este plugin.', 'imagina-updater' ),
				array( 'status' => 403 )
			);
		}

		// Generar license token firmado
		$license_token = self::generate_license_token(
			$activation_data,
			$plugin_slug
		);

		// Preparar respuesta
		$response_data = array(
			'is_valid'      => true,
			'plugin_slug'   => $plugin_slug,
			'plugin_name'   => $plugin->name,
			'license_token' => $license_token,
			'expires_at'    => time() + ( 24 * HOUR_IN_SECONDS ),
			'site_domain'   => $activation_data['site_domain'],
			'verified_at'   => current_time( 'mysql' ),
		);

		// Firmar la respuesta
		$signature = self::sign_response( $response_data, $activation_data['activation_token'] );
		$response_data['signature'] = $signature;

		return rest_ensure_response( $response_data );
	}

	/**
	 * Obtiene información de la licencia
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_license_info( $request ) {
		$activation_data = $request->get_attribute( 'activation_data' );

		// Obtener datos de la API key
		global $wpdb;
		$api_keys_table = $wpdb->prefix . 'imagina_updater_api_keys';

		$api_key_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$api_keys_table} WHERE id = %d",
				$activation_data['api_key_id']
			)
		);

		if ( ! $api_key_data ) {
			return new WP_Error(
				'api_key_not_found',
				__( 'API Key no encontrada.', 'imagina-updater' ),
				array( 'status' => 404 )
			);
		}

		// Contar activaciones
		$activations_table = $wpdb->prefix . 'imagina_updater_activations';
		$activation_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$activations_table} WHERE api_key_id = %d AND is_active = 1",
				$api_key_data->id
			)
		);

		// Preparar respuesta
		$response_data = array(
			'site_name'        => $api_key_data->site_name,
			'site_url'         => $api_key_data->site_url,
			'access_type'      => $api_key_data->access_type,
			'max_activations'  => (int) $api_key_data->max_activations,
			'current_activations' => (int) $activation_count,
			'is_active'        => (bool) $api_key_data->is_active,
			'created_at'       => $api_key_data->created_at,
		);

		// Firmar la respuesta
		$signature = self::sign_response( $response_data, $activation_data['activation_token'] );
		$response_data['signature'] = $signature;

		return rest_ensure_response( $response_data );
	}

	/**
	 * Verifica múltiples licencias en batch (para heartbeat)
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function verify_batch( $request ) {
		$plugin_slugs = $request->get_param( 'plugin_slugs' );

		if ( empty( $plugin_slugs ) || ! is_array( $plugin_slugs ) ) {
			return new WP_Error(
				'invalid_plugins',
				__( 'Lista de plugins inválida.', 'imagina-updater' ),
				array( 'status' => 400 )
			);
		}

		$activation_data = $request->get_attribute( 'activation_data' );
		$results = array();

		foreach ( $plugin_slugs as $plugin_slug ) {
			$plugin_slug = sanitize_key( $plugin_slug );

			// Verificar acceso
			global $wpdb;
			$table_name = $wpdb->prefix . 'imagina_updater_plugins';

			$plugin = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE slug = %s OR slug_override = %s LIMIT 1",
					$plugin_slug,
					$plugin_slug
				)
			);

			if ( ! $plugin ) {
				$results[ $plugin_slug ] = array(
					'is_valid' => false,
					'error'    => 'plugin_not_found',
				);
				continue;
			}

			$has_access = self::verify_plugin_access(
				$activation_data['api_key_id'],
				$plugin->id
			);

			if ( ! $has_access ) {
				$results[ $plugin_slug ] = array(
					'is_valid' => false,
					'error'    => 'no_access',
				);
				continue;
			}

			// Generar token
			$license_token = self::generate_license_token(
				$activation_data,
				$plugin_slug
			);

			$results[ $plugin_slug ] = array(
				'is_valid'      => true,
				'license_token' => $license_token,
				'expires_at'    => time() + ( 24 * HOUR_IN_SECONDS ),
			);
		}

		// Firmar respuesta
		$signature = self::sign_response( $results, $activation_data['activation_token'] );

		return rest_ensure_response( array(
			'results'   => $results,
			'signature' => $signature,
		) );
	}

	/**
	 * Verifica el activation token (permission callback)
	 *
	 * @param WP_REST_Request $request Request
	 * @return bool|WP_Error
	 */
	public static function check_activation_token( $request ) {
		// Obtener el token del header Authorization
		$auth_header = $request->get_header( 'Authorization' );

		if ( ! $auth_header ) {
			return new WP_Error(
				'missing_authorization',
				__( 'Header Authorization es requerido.', 'imagina-updater' ),
				array( 'status' => 401 )
			);
		}

		// Extraer el token (formato: "Bearer iat_xxx")
		if ( ! preg_match( '/Bearer\s+(\S+)/', $auth_header, $matches ) ) {
			return new WP_Error(
				'invalid_authorization',
				__( 'Formato de Authorization inválido.', 'imagina-updater' ),
				array( 'status' => 401 )
			);
		}

		$activation_token = $matches[1];

		// Verificar que sea un activation token
		if ( strpos( $activation_token, 'iat_' ) !== 0 ) {
			return new WP_Error(
				'invalid_token_type',
				__( 'Se requiere un activation token.', 'imagina-updater' ),
				array( 'status' => 401 )
			);
		}

		// Obtener el dominio del sitio
		$site_domain = $request->get_header( 'X-Site-Domain' );

		if ( ! $site_domain ) {
			return new WP_Error(
				'missing_domain',
				__( 'Header X-Site-Domain es requerido.', 'imagina-updater' ),
				array( 'status' => 401 )
			);
		}

		// Validar el token en la base de datos
		global $wpdb;
		$table_name = $wpdb->prefix . 'imagina_updater_activations';

		$activation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE activation_token = %s AND is_active = 1",
				$activation_token
			)
		);

		if ( ! $activation ) {
			return new WP_Error(
				'invalid_activation_token',
				__( 'Token de activación inválido o inactivo.', 'imagina-updater' ),
				array( 'status' => 401 )
			);
		}

		// Verificar que el dominio coincida
		if ( $activation->site_domain !== $site_domain ) {
			return new WP_Error(
				'domain_mismatch',
				__( 'El dominio no coincide con el token de activación.', 'imagina-updater' ),
				array( 'status' => 403 )
			);
		}

		// Verificar que la API key padre esté activa
		$api_keys_table = $wpdb->prefix . 'imagina_updater_api_keys';
		$api_key = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$api_keys_table} WHERE id = %d",
				$activation->api_key_id
			)
		);

		if ( ! $api_key || ! $api_key->is_active ) {
			return new WP_Error(
				'api_key_inactive',
				__( 'La API Key asociada está inactiva.', 'imagina-updater' ),
				array( 'status' => 403 )
			);
		}

		// Guardar datos de activación en el request para uso posterior
		$request->set_attribute( 'activation_data', array(
			'activation_id'    => $activation->id,
			'api_key_id'       => $activation->api_key_id,
			'site_domain'      => $activation->site_domain,
			'activation_token' => $activation_token,
			'access_type'      => $api_key->access_type,
			'allowed_plugins'  => $api_key->allowed_plugins,
			'allowed_groups'   => $api_key->allowed_groups,
		) );

		// Actualizar last_verified
		$wpdb->update(
			$table_name,
			array( 'last_verified' => current_time( 'mysql' ) ),
			array( 'id' => $activation->id ),
			array( '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Verifica si un cliente tiene acceso a un plugin
	 *
	 * @param int $api_key_id ID de la API key
	 * @param int $plugin_id  ID del plugin
	 * @return bool
	 */
	private static function verify_plugin_access( $api_key_id, $plugin_id ) {
		global $wpdb;
		$api_keys_table = $wpdb->prefix . 'imagina_updater_api_keys';

		$api_key = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$api_keys_table} WHERE id = %d",
				$api_key_id
			)
		);

		if ( ! $api_key ) {
			return false;
		}

		// Acceso total
		if ( $api_key->access_type === 'all' ) {
			return true;
		}

		// Acceso específico
		if ( $api_key->access_type === 'specific' ) {
			$allowed_plugins = json_decode( $api_key->allowed_plugins, true );
			return in_array( $plugin_id, $allowed_plugins, true );
		}

		// Acceso por grupos
		if ( $api_key->access_type === 'groups' ) {
			$allowed_groups = json_decode( $api_key->allowed_groups, true );

			// Verificar si el plugin pertenece a algún grupo permitido
			$group_items_table = $wpdb->prefix . 'imagina_updater_plugin_group_items';

			$in_group = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$group_items_table}
					WHERE plugin_id = %d AND group_id IN (" . implode( ',', array_map( 'absint', $allowed_groups ) ) . ')',
					$plugin_id
				)
			);

			return $in_group > 0;
		}

		return false;
	}

	/**
	 * Genera un license token firmado
	 *
	 * @param array  $activation_data Datos de activación
	 * @param string $plugin_slug     Plugin slug
	 * @return string License token
	 */
	private static function generate_license_token( $activation_data, $plugin_slug ) {
		// Cargar la clase de criptografía
		if ( ! class_exists( 'Imagina_License_Crypto' ) ) {
			require_once dirname( __FILE__ ) . '/../includes/class-license-crypto-server.php';
		}

		$token_data = array(
			'plugin_slug'      => $plugin_slug,
			'site_domain'      => $activation_data['site_domain'],
			'activation_id'    => $activation_data['activation_id'],
			'api_key_id'       => $activation_data['api_key_id'],
		);

		// Usar el activation token como secreto
		$secret = $activation_data['activation_token'];

		return Imagina_License_Crypto::generate_license_token( $token_data, $secret );
	}

	/**
	 * Firma una respuesta
	 *
	 * @param array  $data  Datos a firmar
	 * @param string $secret Secreto (activation token)
	 * @return string Firma
	 */
	private static function sign_response( $data, $secret ) {
		if ( ! class_exists( 'Imagina_License_Crypto' ) ) {
			require_once dirname( __FILE__ ) . '/../includes/class-license-crypto-server.php';
		}

		$data_string = wp_json_encode( $data );
		return Imagina_License_Crypto::generate_signature( $data_string, $secret );
	}

	/**
	 * Kill Switch - Verificar si una instalación específica está bloqueada
	 *
	 * Este endpoint permite al servidor bloquear remotamente instalaciones específicas
	 * de plugins premium si se detecta uso no autorizado o piratería.
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response
	 */
	public static function check_killswitch( $request ) {
		$plugin_slug = sanitize_key( $request->get_param( 'plugin_slug' ) );
		$activation_token = sanitize_text_field( $request->get_param( 'activation_token' ) );
		$site_url = esc_url_raw( $request->get_param( 'site_url' ) );

		// Validar parámetros
		if ( empty( $plugin_slug ) || empty( $activation_token ) || empty( $site_url ) ) {
			return new WP_REST_Response(
				array(
					'blocked' => false,
					'message' => 'Invalid parameters'
				),
				200
			);
		}

		global $wpdb;

		// Verificar que el activation_token es válido
		$table_activations = $wpdb->prefix . 'imagina_updater_activations';
		$activation = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_activations WHERE activation_token = %s AND is_active = 1",
			$activation_token
		) );

		if ( ! $activation ) {
			// Activation token inválido o desactivado
			return new WP_REST_Response(
				array(
					'blocked' => true,
					'message' => 'Invalid or inactive activation token'
				),
				200
			);
		}

		// Verificar si el plugin existe y es premium
		$table_plugins = $wpdb->prefix . 'imagina_updater_plugins';
		$plugin = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_plugins WHERE slug = %s AND is_premium = 1",
			$plugin_slug
		) );

		if ( ! $plugin ) {
			// Plugin no existe o no es premium
			return new WP_REST_Response(
				array(
					'blocked' => false,
					'message' => 'Plugin not found or not premium'
				),
				200
			);
		}

		// Verificar si hay una blacklist de sitios para este plugin
		// Por ahora, usar una opción de WordPress para almacenar blacklist
		$blacklist_key = 'imagina_license_blacklist_' . $plugin_slug;
		$blacklist = get_option( $blacklist_key, array() );

		// Normalizar URL del sitio para comparación
		$normalized_url = trailingslashit( strtolower( parse_url( $site_url, PHP_URL_HOST ) ) );

		// Verificar si el sitio está en la blacklist
		$is_blocked = false;
		foreach ( $blacklist as $blocked_site ) {
			$blocked_normalized = trailingslashit( strtolower( $blocked_site ) );
			if ( strpos( $normalized_url, $blocked_normalized ) !== false ) {
				$is_blocked = true;
				break;
			}
		}

		// También verificar si la activación está marcada como bloqueada
		if ( isset( $activation->is_blocked ) && $activation->is_blocked == 1 ) {
			$is_blocked = true;
		}

		// Log de la verificación
		imagina_license_log(
			sprintf(
				'Kill switch check: %s from %s - %s',
				$plugin_slug,
				$site_url,
				$is_blocked ? 'BLOCKED' : 'Allowed'
			),
			$is_blocked ? 'warning' : 'info'
		);

		return new WP_REST_Response(
			array(
				'blocked' => $is_blocked,
				'message' => $is_blocked ? 'This installation has been blocked' : 'Active'
			),
			200
		);
	}

	/**
	 * Recibir datos de telemetría de una instalación
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response
	 */
	public static function receive_telemetry( $request ) {
		$plugin_slug       = sanitize_key( $request->get_param( 'plugin_slug' ) );
		$activation_token  = sanitize_text_field( $request->get_param( 'activation_token' ) );
		$site_url          = esc_url_raw( $request->get_param( 'site_url' ) );
		$site_name         = sanitize_text_field( $request->get_param( 'site_name' ) );
		$wp_version        = sanitize_text_field( $request->get_param( 'wp_version' ) );
		$php_version       = sanitize_text_field( $request->get_param( 'php_version' ) );
		$is_multisite      = (bool) $request->get_param( 'is_multisite' );
		$locale            = sanitize_text_field( $request->get_param( 'locale' ) );
		$timestamp         = sanitize_text_field( $request->get_param( 'timestamp' ) );

		// Validar datos requeridos
		if ( empty( $plugin_slug ) || empty( $activation_token ) || empty( $site_url ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing required parameters'
				),
				400
			);
		}

		// Verificar que el activation_token sea válido
		global $wpdb;
		$table = $wpdb->prefix . 'imagina_updater_activations';

		$activation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE activation_token = %s AND site_url = %s",
				$activation_token,
				$site_url
			)
		);

		if ( ! $activation ) {
			imagina_license_log(
				sprintf(
					'Telemetry rejected: Invalid activation token for %s from %s',
					$plugin_slug,
					$site_url
				),
				'warning'
			);

			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid activation token'
				),
				403
			);
		}

		// Guardar telemetría en wp_options con clave única por instalación
		$telemetry_key = 'imagina_telemetry_' . md5( $plugin_slug . $site_url );

		$telemetry_data = array(
			'plugin_slug'      => $plugin_slug,
			'activation_token' => $activation_token,
			'activation_id'    => $activation->id,
			'site_url'         => $site_url,
			'site_name'        => $site_name,
			'wp_version'       => $wp_version,
			'php_version'      => $php_version,
			'is_multisite'     => $is_multisite,
			'locale'           => $locale,
			'last_seen'        => current_time( 'mysql' ),
			'client_timestamp' => $timestamp,
			'reports_count'    => 1
		);

		// Si ya existe telemetría previa, actualizar el contador
		$existing = get_option( $telemetry_key );
		if ( $existing && is_array( $existing ) ) {
			$telemetry_data['reports_count'] = isset( $existing['reports_count'] ) ? $existing['reports_count'] + 1 : 1;
			$telemetry_data['first_seen'] = $existing['first_seen'] ?? $telemetry_data['last_seen'];
		} else {
			$telemetry_data['first_seen'] = $telemetry_data['last_seen'];
		}

		update_option( $telemetry_key, $telemetry_data, false );

		// Log de telemetría recibida
		imagina_license_log(
			sprintf(
				'Telemetry received: %s from %s (WP %s, PHP %s) - Report #%d',
				$plugin_slug,
				$site_name ? $site_name : $site_url,
				$wp_version,
				$php_version,
				$telemetry_data['reports_count']
			),
			'info'
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => 'Telemetry received'
			),
			200
		);
	}

	// ===============================================
	// NUEVOS MÉTODOS - Sistema de License Keys v5.0
	// ===============================================

	/**
	 * Activar licencia usando License Key
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response
	 */
	public static function activate_license_key( $request ) {
		$license_key = sanitize_text_field( $request->get_param( 'license_key' ) );
		$site_url    = esc_url_raw( $request->get_param( 'site_url' ) );
		$site_name   = sanitize_text_field( $request->get_param( 'site_name' ) );
		$plugin_slug = sanitize_key( $request->get_param( 'plugin_slug' ) );

		// Validar parámetros requeridos
		if ( empty( $license_key ) || empty( $site_url ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'missing_parameters',
					'message' => __( 'License key y site URL son requeridos.', 'imagina-updater-license' )
				),
				400
			);
		}

		// Obtener licencia
		$license = Imagina_License_Database::get_license_by_key( $license_key );

		if ( ! $license ) {
			imagina_license_log( 'Intento de activación con licencia inválida: ' . $license_key, 'warning' );
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'invalid_license_key',
					'message' => __( 'La license key no es válida.', 'imagina-updater-license' )
				),
				404
			);
		}

		// Si se especificó plugin_slug, verificar que coincida
		if ( ! empty( $plugin_slug ) ) {
			global $wpdb;
			$table_plugins = $wpdb->prefix . 'imagina_updater_plugins';
			$plugin = $wpdb->get_row( $wpdb->prepare(
				"SELECT id FROM $table_plugins WHERE (slug = %s OR slug_override = %s) AND id = %d",
				$plugin_slug,
				$plugin_slug,
				$license->plugin_id
			) );

			if ( ! $plugin ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'error'   => 'wrong_plugin',
						'message' => __( 'Esta licencia no es válida para este plugin.', 'imagina-updater-license' )
					),
					403
				);
			}
		}

		// Intentar activar
		$result = Imagina_License_Database::activate_license( $license->id, $site_url, $site_name );

		if ( ! $result['success'] ) {
			$error_messages = array(
				'license_not_found'      => __( 'Licencia no encontrada.', 'imagina-updater-license' ),
				'license_not_active'     => __( 'Esta licencia no está activa.', 'imagina-updater-license' ),
				'license_expired'        => __( 'Esta licencia ha expirado.', 'imagina-updater-license' ),
				'max_activations_reached' => __( 'Has alcanzado el límite máximo de activaciones para esta licencia.', 'imagina-updater-license' ),
				'database_error'         => __( 'Error de base de datos.', 'imagina-updater-license' ),
			);

			$message = isset( $error_messages[ $result['error'] ] ) ? $error_messages[ $result['error'] ] : $result['error'];

			imagina_license_log( 'Error en activación: ' . $result['error'] . ' para ' . $site_url, 'warning' );

			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result['error'],
					'message' => $message
				),
				403
			);
		}

		// Obtener información del plugin para la respuesta
		global $wpdb;
		$table_plugins = $wpdb->prefix . 'imagina_updater_plugins';
		$plugin = $wpdb->get_row( $wpdb->prepare(
			"SELECT name, slug, slug_override FROM $table_plugins WHERE id = %d",
			$license->plugin_id
		) );

		imagina_license_log( sprintf(
			'Licencia activada: %s en %s (%s)',
			$license_key,
			$site_url,
			$result['message']
		), 'info' );

		return new WP_REST_Response(
			array(
				'success'        => true,
				'message'        => $result['message'] === 'already_active'
					? __( 'La licencia ya estaba activa en este sitio.', 'imagina-updater-license' )
					: __( 'Licencia activada correctamente.', 'imagina-updater-license' ),
				'license_key'    => $license_key,
				'site_url'       => $site_url,
				'plugin_name'    => $plugin ? $plugin->name : '',
				'plugin_slug'    => $plugin ? ( $plugin->slug_override ?: $plugin->slug ) : '',
				'expires_at'     => $license->expires_at,
				'activations'    => array(
					'current' => $license->activations_count + ( $result['message'] === 'activated' ? 1 : 0 ),
					'max'     => $license->max_activations
				),
				'site_local_key' => isset( $result['site_local_key'] ) ? $result['site_local_key'] : null
			),
			200
		);
	}

	/**
	 * Desactivar licencia
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response
	 */
	public static function deactivate_license_key( $request ) {
		$license_key = sanitize_text_field( $request->get_param( 'license_key' ) );
		$site_url    = esc_url_raw( $request->get_param( 'site_url' ) );

		// Validar parámetros
		if ( empty( $license_key ) || empty( $site_url ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'missing_parameters',
					'message' => __( 'License key y site URL son requeridos.', 'imagina-updater-license' )
				),
				400
			);
		}

		// Obtener licencia
		$license = Imagina_License_Database::get_license_by_key( $license_key );

		if ( ! $license ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => 'invalid_license_key',
					'message' => __( 'La license key no es válida.', 'imagina-updater-license' )
				),
				404
			);
		}

		// Desactivar
		$result = Imagina_License_Database::deactivate_license( $license->id, $site_url );

		if ( ! $result['success'] ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'error'   => $result['error'],
					'message' => __( 'No se encontró una activación activa en este sitio.', 'imagina-updater-license' )
				),
				404
			);
		}

		imagina_license_log( sprintf( 'Licencia desactivada: %s de %s', $license_key, $site_url ), 'info' );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Licencia desactivada correctamente.', 'imagina-updater-license' )
			),
			200
		);
	}

	/**
	 * Verificar estado de licencia
	 *
	 * @param WP_REST_Request $request Request
	 * @return WP_REST_Response
	 */
	public static function check_license_key( $request ) {
		$license_key = sanitize_text_field( $request->get_param( 'license_key' ) );
		$site_url    = esc_url_raw( $request->get_param( 'site_url' ) );
		$plugin_slug = sanitize_key( $request->get_param( 'plugin_slug' ) );

		// Validar parámetros
		if ( empty( $license_key ) || empty( $site_url ) ) {
			return new WP_REST_Response(
				array(
					'valid'   => false,
					'error'   => 'missing_parameters',
					'message' => __( 'License key y site URL son requeridos.', 'imagina-updater-license' )
				),
				400
			);
		}

		// Obtener plugin_id si se especificó slug
		$plugin_id = null;
		if ( ! empty( $plugin_slug ) ) {
			global $wpdb;
			$table_plugins = $wpdb->prefix . 'imagina_updater_plugins';
			$plugin = $wpdb->get_row( $wpdb->prepare(
				"SELECT id FROM $table_plugins WHERE slug = %s OR slug_override = %s",
				$plugin_slug,
				$plugin_slug
			) );
			$plugin_id = $plugin ? $plugin->id : null;
		}

		// Verificar licencia
		$result = Imagina_License_Database::verify_license( $license_key, $site_url, $plugin_id );

		if ( ! $result['valid'] ) {
			$error_messages = array(
				'invalid_license_key'    => __( 'La license key no es válida.', 'imagina-updater-license' ),
				'wrong_plugin'           => __( 'Esta licencia no es válida para este plugin.', 'imagina-updater-license' ),
				'license_inactive'       => __( 'Esta licencia está inactiva.', 'imagina-updater-license' ),
				'license_expired'        => __( 'Esta licencia ha expirado.', 'imagina-updater-license' ),
				'license_revoked'        => __( 'Esta licencia ha sido revocada.', 'imagina-updater-license' ),
				'not_activated_on_site'  => __( 'Esta licencia no está activada en este sitio.', 'imagina-updater-license' ),
			);

			$message = isset( $error_messages[ $result['error'] ] ) ? $error_messages[ $result['error'] ] : $result['error'];

			return new WP_REST_Response(
				array(
					'valid'   => false,
					'error'   => $result['error'],
					'message' => $message
				),
				200 // Retornamos 200 porque la verificación fue exitosa, solo la licencia no es válida
			);
		}

		// Obtener información adicional de la licencia
		$license = Imagina_License_Database::get_license_by_key( $license_key );

		return new WP_REST_Response(
			array(
				'valid'         => true,
				'license_id'    => $result['license_id'],
				'expires_at'    => $result['expires_at'],
				'customer_email' => $result['customer_email'],
				'activations'   => array(
					'current' => $license->activations_count,
					'max'     => $license->max_activations
				)
			),
			200
		);
	}
}
