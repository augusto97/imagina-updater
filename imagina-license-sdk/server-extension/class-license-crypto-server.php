<?php
/**
 * Imagina License SDK - Cryptography Handler
 *
 * Maneja toda la criptografía, firma digital y verificación de tokens.
 * CRÍTICO: No modificar este archivo - La integridad se verifica automáticamente.
 *
 * @package Imagina_License_SDK
 * @version 1.0.0
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase de criptografía para el SDK de licencias
 */
class Imagina_License_Crypto {

	/**
	 * Versión del protocolo de firma
	 */
	const SIGNATURE_VERSION = 'v1';

	/**
	 * Algoritmo de hash para HMAC
	 */
	const HASH_ALGO = 'sha256';

	/**
	 * Longitud mínima de la clave secreta (bytes)
	 */
	const MIN_SECRET_LENGTH = 32;

	/**
	 * Tiempo de expiración de tokens (24 horas)
	 */
	const TOKEN_EXPIRATION = 86400;

	/**
	 * Genera un token de licencia firmado
	 *
	 * @param array  $data Datos del token
	 * @param string $secret Clave secreta para firmar
	 * @return string Token firmado en formato JWT-like
	 */
	public static function generate_license_token( $data, $secret ) {
		// Validar entrada
		if ( empty( $data ) || empty( $secret ) ) {
			return false;
		}

		if ( strlen( $secret ) < self::MIN_SECRET_LENGTH ) {
			return false;
		}

		// Añadir metadata de seguridad
		$payload = array_merge( $data, array(
			'iat' => time(), // Issued at
			'exp' => time() + self::TOKEN_EXPIRATION, // Expiration
			'v'   => self::SIGNATURE_VERSION, // Version
			'jti' => self::generate_jti(), // Unique ID
		) );

		// Codificar payload
		$encoded_payload = self::base64url_encode( wp_json_encode( $payload ) );

		// Generar firma HMAC
		$signature = self::generate_signature( $encoded_payload, $secret );

		// Formato: payload.signature
		return $encoded_payload . '.' . $signature;
	}

	/**
	 * Verifica y decodifica un token de licencia
	 *
	 * @param string $token Token a verificar
	 * @param string $secret Clave secreta para verificar
	 * @return array|false Datos del token si es válido, false si no
	 */
	public static function verify_license_token( $token, $secret ) {
		// Validar entrada
		if ( empty( $token ) || empty( $secret ) ) {
			return false;
		}

		// Separar payload y firma
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		list( $encoded_payload, $signature ) = $parts;

		// Verificar firma
		$expected_signature = self::generate_signature( $encoded_payload, $secret );
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			return false;
		}

		// Decodificar payload
		$payload = json_decode( self::base64url_decode( $encoded_payload ), true );
		if ( ! $payload ) {
			return false;
		}

		// Verificar versión
		if ( ! isset( $payload['v'] ) || $payload['v'] !== self::SIGNATURE_VERSION ) {
			return false;
		}

		// Verificar expiración
		if ( ! isset( $payload['exp'] ) || $payload['exp'] < time() ) {
			return false;
		}

		// Verificar fecha de emisión
		if ( ! isset( $payload['iat'] ) || $payload['iat'] > time() ) {
			return false;
		}

		return $payload;
	}

	/**
	 * Genera una firma HMAC para los datos
	 *
	 * @param string $data Datos a firmar
	 * @param string $secret Clave secreta
	 * @return string Firma en base64url
	 */
	public static function generate_signature( $data, $secret ) {
		$hash = hash_hmac( self::HASH_ALGO, $data, $secret, true );
		return self::base64url_encode( $hash );
	}

	/**
	 * Verifica una firma HMAC
	 *
	 * @param string $data Datos originales
	 * @param string $signature Firma a verificar
	 * @param string $secret Clave secreta
	 * @return bool True si la firma es válida
	 */
	public static function verify_signature( $data, $signature, $secret ) {
		$expected_signature = self::generate_signature( $data, $secret );
		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Genera un JTI (JWT ID) único
	 *
	 * @return string ID único
	 */
	private static function generate_jti() {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Codifica en base64url (URL-safe base64)
	 *
	 * @param string $data Datos a codificar
	 * @return string Datos codificados
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Decodifica desde base64url
	 *
	 * @param string $data Datos codificados
	 * @return string Datos decodificados
	 */
	private static function base64url_decode( $data ) {
		return base64_decode( strtr( $data, '-_', '+/' ) );
	}

	/**
	 * Genera una clave secreta única para un sitio
	 *
	 * Se basa en constantes de WordPress que son únicas por instalación
	 *
	 * @param string $activation_token Token de activación del sitio
	 * @return string Clave secreta de 64 caracteres
	 */
	public static function generate_site_secret( $activation_token ) {
		// Usar constantes únicas de WordPress + activation token
		$unique_data = array(
			defined( 'AUTH_KEY' ) ? AUTH_KEY : '',
			defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '',
			defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '',
			defined( 'NONCE_KEY' ) ? NONCE_KEY : '',
			$activation_token,
			home_url(),
			time(), // Añadir timestamp para variabilidad
		);

		// Generar hash SHA-256
		return hash( 'sha256', implode( '|', $unique_data ) );
	}

	/**
	 * Calcula el checksum de un archivo
	 *
	 * Se usa para verificar la integridad del SDK
	 *
	 * @param string $file_path Ruta del archivo
	 * @return string|false Checksum SHA-256 o false en error
	 */
	public static function file_checksum( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		return hash_file( 'sha256', $file_path );
	}

	/**
	 * Verifica la integridad de un archivo contra un checksum esperado
	 *
	 * @param string $file_path Ruta del archivo
	 * @param string $expected_checksum Checksum esperado
	 * @return bool True si coincide
	 */
	public static function verify_file_integrity( $file_path, $expected_checksum ) {
		$actual_checksum = self::file_checksum( $file_path );

		if ( ! $actual_checksum ) {
			return false;
		}

		return hash_equals( $expected_checksum, $actual_checksum );
	}

	/**
	 * Encripta datos usando AES-256-GCM (si disponible) o AES-256-CBC
	 *
	 * @param string $data Datos a encriptar
	 * @param string $key Clave de encriptación
	 * @return string|false Datos encriptados (base64) o false
	 */
	public static function encrypt( $data, $key ) {
		// Intentar usar GCM (más seguro)
		if ( in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			$cipher = 'aes-256-gcm';
			$iv_length = openssl_cipher_iv_length( $cipher );
			$iv = openssl_random_pseudo_bytes( $iv_length );
			$tag = '';

			$encrypted = openssl_encrypt(
				$data,
				$cipher,
				substr( hash( 'sha256', $key, true ), 0, 32 ),
				OPENSSL_RAW_DATA,
				$iv,
				$tag,
				'',
				16
			);

			if ( $encrypted === false ) {
				return false;
			}

			// Formato: iv + tag + encrypted_data
			$result = $iv . $tag . $encrypted;
			return base64_encode( $result );
		}

		// Fallback a CBC
		$cipher = 'aes-256-cbc';
		$iv_length = openssl_cipher_iv_length( $cipher );
		$iv = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt(
			$data,
			$cipher,
			substr( hash( 'sha256', $key, true ), 0, 32 ),
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( $encrypted === false ) {
			return false;
		}

		// Formato: iv + encrypted_data
		$result = $iv . $encrypted;
		return base64_encode( $result );
	}

	/**
	 * Desencripta datos encriptados con encrypt()
	 *
	 * @param string $encrypted_data Datos encriptados (base64)
	 * @param string $key Clave de encriptación
	 * @return string|false Datos desencriptados o false
	 */
	public static function decrypt( $encrypted_data, $key ) {
		$data = base64_decode( $encrypted_data );
		if ( ! $data ) {
			return false;
		}

		// Intentar GCM primero
		if ( in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) {
			$cipher = 'aes-256-gcm';
			$iv_length = openssl_cipher_iv_length( $cipher );
			$tag_length = 16;

			if ( strlen( $data ) < $iv_length + $tag_length ) {
				// No es GCM, intentar CBC
				return self::decrypt_cbc( $data, $key );
			}

			$iv = substr( $data, 0, $iv_length );
			$tag = substr( $data, $iv_length, $tag_length );
			$encrypted = substr( $data, $iv_length + $tag_length );

			$decrypted = openssl_decrypt(
				$encrypted,
				$cipher,
				substr( hash( 'sha256', $key, true ), 0, 32 ),
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			return $decrypted !== false ? $decrypted : false;
		}

		// Fallback a CBC
		return self::decrypt_cbc( $data, $key );
	}

	/**
	 * Desencripta datos usando AES-256-CBC
	 *
	 * @param string $data Datos encriptados (raw)
	 * @param string $key Clave de encriptación
	 * @return string|false Datos desencriptados o false
	 */
	private static function decrypt_cbc( $data, $key ) {
		$cipher = 'aes-256-cbc';
		$iv_length = openssl_cipher_iv_length( $cipher );

		if ( strlen( $data ) < $iv_length ) {
			return false;
		}

		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		$decrypted = openssl_decrypt(
			$encrypted,
			$cipher,
			substr( hash( 'sha256', $key, true ), 0, 32 ),
			OPENSSL_RAW_DATA,
			$iv
		);

		return $decrypted !== false ? $decrypted : false;
	}

	/**
	 * Genera un hash seguro para almacenar
	 *
	 * @param string $data Datos a hashear
	 * @return string Hash SHA-256
	 */
	public static function hash( $data ) {
		return hash( 'sha256', $data );
	}

	/**
	 * Verifica si un hash coincide con los datos
	 *
	 * @param string $data Datos originales
	 * @param string $hash Hash a verificar
	 * @return bool True si coincide
	 */
	public static function verify_hash( $data, $hash ) {
		return hash_equals( self::hash( $data ), $hash );
	}
}
