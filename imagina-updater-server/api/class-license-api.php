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
 *    add_action( 'rest_api_init', array( 'Imagina_Updater_License_API', 'register_routes' ) );
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
class Imagina_Updater_License_API {

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
}
