# 📖 API Reference - Imagina License SDK

Referencia completa de todas las clases, métodos y funciones del SDK.

## 📚 Índice

- [Imagina_License_SDK](#imagina_license_sdk) - Clase principal
- [Imagina_License_Validator](#imagina_license_validator) - Validador de licencias
- [Imagina_License_Crypto](#imagina_license_crypto) - Criptografía
- [Imagina_License_Heartbeat](#imagina_license_heartbeat) - Verificación periódica
- [Imagina_Updater_License_Manager](#imagina_updater_license_manager) - Gestor (Cliente)
- [Imagina_Updater_License_API](#imagina_updater_license_api) - API REST (Servidor)
- [Helper Functions](#helper-functions) - Funciones auxiliares

---

## Imagina_License_SDK

Clase principal del SDK. Punto de entrada para inicializar y gestionar licencias.

### Métodos Estáticos

#### `init( array $args ): Imagina_License_Validator`

Inicializa el SDK para un plugin y retorna el validador.

**Parámetros:**
- `$args['plugin_slug']` (string) **Requerido** - Slug del plugin
- `$args['plugin_name']` (string) **Requerido** - Nombre del plugin
- `$args['plugin_file']` (string) **Requerido** - Archivo principal del plugin (`__FILE__`)
- `$args['grace_period']` (int) Opcional - Período de gracia en segundos (default: 3 días)

**Retorna:** `Imagina_License_Validator` - Instancia del validador

**Ejemplo:**
```php
$license = Imagina_License_SDK::init( array(
    'plugin_slug'  => 'mi-plugin-premium',
    'plugin_name'  => 'Mi Plugin Premium',
    'plugin_file'  => __FILE__,
    'grace_period' => 7 * DAY_IN_SECONDS,
) );
```

---

#### `get_validator( string $plugin_slug ): Imagina_License_Validator|null`

Obtiene un validador existente.

**Parámetros:**
- `$plugin_slug` (string) - Slug del plugin

**Retorna:** `Imagina_License_Validator|null` - Validador o null si no existe

**Ejemplo:**
```php
$validator = Imagina_License_SDK::get_validator( 'mi-plugin-premium' );
if ( $validator ) {
    $is_valid = $validator->is_valid();
}
```

---

#### `is_licensed( string $plugin_slug ): bool`

Método de conveniencia para verificar rápidamente si un plugin tiene licencia válida.

**Parámetros:**
- `$plugin_slug` (string) - Slug del plugin

**Retorna:** `bool` - True si la licencia es válida

**Ejemplo:**
```php
if ( Imagina_License_SDK::is_licensed( 'mi-plugin-premium' ) ) {
    // Ejecutar código premium
}
```

---

#### `get_version(): string`

Obtiene la versión del SDK.

**Retorna:** `string` - Versión del SDK (ej: "1.0.0")

---

#### `is_license_manager_available(): bool`

Verifica si el gestor de licencias (plugin cliente) está disponible.

**Retorna:** `bool` - True si está disponible

**Ejemplo:**
```php
if ( ! Imagina_License_SDK::is_license_manager_available() ) {
    add_action( 'admin_notices', 'mostrar_aviso_falta_cliente' );
}
```

---

#### `show_manager_required_notice()`

Muestra un aviso de admin indicando que se requiere el plugin cliente.

**Ejemplo:**
```php
if ( ! Imagina_License_SDK::is_license_manager_available() ) {
    add_action( 'admin_notices', array( 'Imagina_License_SDK', 'show_manager_required_notice' ) );
}
```

---

## Imagina_License_Validator

Clase de validación de licencias. Cada plugin tiene su propia instancia.

### Métodos Públicos

#### `is_valid( bool $force_remote_check = false ): bool`

Verifica si la licencia es válida.

**Parámetros:**
- `$force_remote_check` (bool) Opcional - Forzar verificación remota (default: false)

**Retorna:** `bool` - True si la licencia es válida

**Comportamiento:**
1. Verifica integridad del SDK
2. Verifica que el gestor de licencias esté disponible
3. Si `$force_remote_check` es false, usa caché (si válido)
4. Si es true o caché inválido, verifica con el servidor
5. Maneja grace period si la verificación falla

**Ejemplo:**
```php
// Verificación normal (usa caché)
if ( $validator->is_valid() ) {
    // Licencia válida
}

// Forzar verificación remota
if ( $validator->is_valid( true ) ) {
    // Licencia válida (verificado con servidor)
}
```

---

#### `get_license_data(): array`

Obtiene los datos de la licencia.

**Retorna:** `array` - Datos de la licencia

**Estructura:**
```php
array(
    'is_valid'      => true,
    'plugin_slug'   => 'mi-plugin',
    'plugin_name'   => 'Mi Plugin',
    'license_token' => 'ey...',
    'expires_at'    => 1234567890,
    'site_domain'   => 'mi-sitio.com',
    'verified_at'   => '2025-01-15 10:30:00',
)
```

**Ejemplo:**
```php
$data = $validator->get_license_data();
echo 'Licencia verificada el: ' . $data['verified_at'];
```

---

#### `is_in_grace_period(): bool`

Verifica si está activo el período de gracia.

**Retorna:** `bool` - True si está en grace period

**Ejemplo:**
```php
if ( $validator->is_in_grace_period() ) {
    $remaining = $validator->get_grace_period_remaining();
    echo "Grace period activo. Quedan: " . ceil( $remaining / DAY_IN_SECONDS ) . " días";
}
```

---

#### `get_grace_period_remaining(): int`

Obtiene el tiempo restante del grace period.

**Retorna:** `int` - Segundos restantes, 0 si no está en grace period

**Ejemplo:**
```php
$remaining = $validator->get_grace_period_remaining();
if ( $remaining > 0 ) {
    $days = ceil( $remaining / DAY_IN_SECONDS );
    echo "Quedan {$days} días de grace period";
}
```

---

#### `force_check(): bool`

Fuerza una verificación remota inmediata (ignora caché).

**Retorna:** `bool` - True si la licencia es válida

**Ejemplo:**
```php
// Al hacer clic en "Verificar licencia ahora"
$is_valid = $validator->force_check();
if ( $is_valid ) {
    echo 'Licencia verificada correctamente';
} else {
    echo 'Licencia inválida';
}
```

---

#### `show_license_notice()`

Muestra un aviso de admin con el estado de la licencia.

**Uso:**
```php
add_action( 'admin_notices', array( $validator, 'show_license_notice' ) );
```

---

## Imagina_License_Crypto

Clase de criptografía y firma digital. Todos los métodos son estáticos.

### Métodos Estáticos

#### `generate_license_token( array $data, string $secret ): string|false`

Genera un token de licencia firmado (formato JWT-like).

**Parámetros:**
- `$data` (array) - Datos a incluir en el token
- `$secret` (string) - Clave secreta para firmar (mín. 32 bytes)

**Retorna:** `string|false` - Token firmado o false en error

**Ejemplo:**
```php
$token = Imagina_License_Crypto::generate_license_token(
    array(
        'plugin_slug' => 'mi-plugin',
        'site_domain' => 'ejemplo.com',
    ),
    'mi-secreto-muy-largo-y-seguro-de-mas-de-32-caracteres'
);
```

---

#### `verify_license_token( string $token, string $secret ): array|false`

Verifica y decodifica un token de licencia.

**Parámetros:**
- `$token` (string) - Token a verificar
- `$secret` (string) - Clave secreta para verificar

**Retorna:** `array|false` - Datos del token si es válido, false si no

**Ejemplo:**
```php
$data = Imagina_License_Crypto::verify_license_token( $token, $secret );
if ( $data ) {
    echo 'Token válido para: ' . $data['plugin_slug'];
} else {
    echo 'Token inválido o expirado';
}
```

---

#### `generate_signature( string $data, string $secret ): string`

Genera una firma HMAC-SHA256.

**Parámetros:**
- `$data` (string) - Datos a firmar
- `$secret` (string) - Clave secreta

**Retorna:** `string` - Firma en base64url

---

#### `verify_signature( string $data, string $signature, string $secret ): bool`

Verifica una firma HMAC.

**Parámetros:**
- `$data` (string) - Datos originales
- `$signature` (string) - Firma a verificar
- `$secret` (string) - Clave secreta

**Retorna:** `bool` - True si la firma es válida

---

#### `file_checksum( string $file_path ): string|false`

Calcula el checksum SHA-256 de un archivo.

**Parámetros:**
- `$file_path` (string) - Ruta del archivo

**Retorna:** `string|false` - Checksum o false en error

---

#### `verify_file_integrity( string $file_path, string $expected_checksum ): bool`

Verifica la integridad de un archivo.

**Parámetros:**
- `$file_path` (string) - Ruta del archivo
- `$expected_checksum` (string) - Checksum esperado

**Retorna:** `bool` - True si coincide

---

#### `encrypt( string $data, string $key ): string|false`

Encripta datos usando AES-256.

**Parámetros:**
- `$data` (string) - Datos a encriptar
- `$key` (string) - Clave de encriptación

**Retorna:** `string|false` - Datos encriptados (base64) o false

---

#### `decrypt( string $encrypted_data, string $key ): string|false`

Desencripta datos encriptados con `encrypt()`.

**Parámetros:**
- `$encrypted_data` (string) - Datos encriptados
- `$key` (string) - Clave de encriptación

**Retorna:** `string|false` - Datos desencriptados o false

---

## Imagina_License_Heartbeat

Sistema de verificación periódica en background.

### Métodos Estáticos

#### `get_instance(): Imagina_License_Heartbeat`

Obtiene la instancia única (Singleton).

---

#### `get_logs( int $limit = 50 ): array`

Obtiene los logs del heartbeat.

**Parámetros:**
- `$limit` (int) - Número máximo de logs

**Retorna:** `array` - Logs

**Estructura:**
```php
array(
    array(
        'timestamp' => '2025-01-15 10:30:00',
        'level'     => 'info',
        'message'   => 'Verificación completada. 3 plugins verificados.',
    ),
    // ...
)
```

**Ejemplo:**
```php
$logs = Imagina_License_Heartbeat::get_logs( 20 );
foreach ( $logs as $log ) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}<br>";
}
```

---

#### `run_manual_check(): array`

Ejecuta una verificación manual inmediata de todas las licencias.

**Retorna:** `array` - Resultados indexados por slug

**Ejemplo:**
```php
$results = Imagina_License_Heartbeat::run_manual_check();
foreach ( $results as $slug => $result ) {
    echo "{$slug}: " . ( $result['is_valid'] ? 'Válido' : 'Inválido' ) . "<br>";
}
```

---

#### `cleanup()`

Limpia todos los datos del heartbeat (logs, cron jobs).

**Uso:** Al desinstalar el plugin

---

### Métodos de Instancia

#### `register_plugin( string $plugin_slug, Imagina_License_Validator $validator )`

Registra un plugin para verificación periódica.

**Nota:** Esto se hace automáticamente al llamar `Imagina_License_SDK::init()`.

---

## Imagina_Updater_License_Manager

Gestor de licencias en el cliente. Maneja la comunicación con el servidor.

### Métodos Estáticos

#### `init()`

Inicializa el gestor de licencias.

**Uso:** En `imagina-updater-client.php`

---

#### `get_instance(): Imagina_Updater_License_Manager`

Obtiene la instancia única.

---

### Métodos de Instancia

#### `verify_plugin_license( string $plugin_slug ): array|false`

Verifica la licencia de un plugin con el servidor.

**Parámetros:**
- `$plugin_slug` (string) - Slug del plugin

**Retorna:** `array|false` - Datos de la licencia o false

**Uso:** Llamado automáticamente por `Imagina_License_Validator`

---

#### `is_plugin_licensed( string $plugin_slug ): bool`

Verifica si un plugin tiene licencia válida.

**Parámetros:**
- `$plugin_slug` (string) - Slug del plugin

**Retorna:** `bool` - True si es válido

---

#### `verify_batch( array $plugin_slugs ): array`

Verifica múltiples licencias en batch.

**Parámetros:**
- `$plugin_slugs` (array) - Lista de slugs

**Retorna:** `array` - Resultados indexados por slug

---

#### `get_license_info(): array|WP_Error`

Obtiene información de la licencia actual (API Key).

**Retorna:** `array|WP_Error` - Información o error

**Estructura:**
```php
array(
    'site_name'           => 'Mi Sitio',
    'site_url'            => 'https://mi-sitio.com',
    'access_type'         => 'all',
    'max_activations'     => 5,
    'current_activations' => 2,
    'is_active'           => true,
    'created_at'          => '2025-01-01 00:00:00',
)
```

---

#### `invalidate_cache( string $plugin_slug )`

Invalida la caché de un plugin.

---

#### `invalidate_all_cache()`

Invalida toda la caché de licencias.

---

#### `get_stats(): array`

Obtiene estadísticas de licencias.

**Retorna:** `array` - Estadísticas

---

## Imagina_Updater_License_API

API REST del servidor para validación de licencias.

### Endpoints

#### `POST /wp-json/imagina-updater/v1/license/verify`

Verifica la licencia de un plugin.

**Headers:**
- `Authorization: Bearer {activation_token}`
- `X-Site-Domain: {dominio}`

**Body:**
```json
{
    "plugin_slug": "mi-plugin-premium"
}
```

**Respuesta:**
```json
{
    "is_valid": true,
    "plugin_slug": "mi-plugin-premium",
    "plugin_name": "Mi Plugin Premium",
    "license_token": "eyJ...",
    "expires_at": 1234567890,
    "site_domain": "mi-sitio.com",
    "verified_at": "2025-01-15 10:30:00",
    "signature": "abc123..."
}
```

---

#### `POST /wp-json/imagina-updater/v1/license/info`

Obtiene información de la licencia.

**Headers:**
- `Authorization: Bearer {activation_token}`
- `X-Site-Domain: {dominio}`

**Respuesta:**
```json
{
    "site_name": "Mi Sitio",
    "access_type": "all",
    "max_activations": 5,
    "current_activations": 2,
    "is_active": true,
    "signature": "abc123..."
}
```

---

#### `POST /wp-json/imagina-updater/v1/license/verify-batch`

Verifica múltiples licencias.

**Headers:**
- `Authorization: Bearer {activation_token}`
- `X-Site-Domain: {dominio}`

**Body:**
```json
{
    "plugin_slugs": ["plugin-1", "plugin-2", "plugin-3"]
}
```

**Respuesta:**
```json
{
    "results": {
        "plugin-1": {
            "is_valid": true,
            "license_token": "eyJ...",
            "expires_at": 1234567890
        },
        "plugin-2": {
            "is_valid": false,
            "error": "no_access"
        }
    },
    "signature": "abc123..."
}
```

---

## Helper Functions

Funciones auxiliares globales.

### `imagina_license_init( array $args ): Imagina_License_Validator`

Alias de `Imagina_License_SDK::init()`.

**Ejemplo:**
```php
$license = imagina_license_init( array(
    'plugin_slug' => 'mi-plugin',
    'plugin_name' => 'Mi Plugin',
    'plugin_file' => __FILE__,
) );
```

---

### `imagina_is_licensed( string $plugin_slug ): bool`

Alias de `Imagina_License_SDK::is_licensed()`.

**Ejemplo:**
```php
if ( imagina_is_licensed( 'mi-plugin' ) ) {
    // Código premium
}
```

---

## 🎯 Hooks y Actions

### Actions

#### `imagina_license_invalid_{plugin_slug}`

Se ejecuta cuando una licencia se invalida.

**Parámetros:** Ninguno

**Ejemplo:**
```php
add_action( 'imagina_license_invalid_mi-plugin', function() {
    // Limpiar datos
    delete_option( 'mi_plugin_premium_data' );

    // Notificar
    error_log( '[Mi Plugin] Licencia invalidada' );
} );
```

---

## 📊 Constantes

### SDK

- `IMAGINA_LICENSE_SDK_LOADED` - True si el SDK está cargado
- `IMAGINA_LICENSE_SDK_VERSION` - Versión del SDK
- `IMAGINA_LICENSE_SDK_PATH` - Ruta del SDK

---

## 🔍 Debugging

### Habilitar Logs de Debug

```php
// En wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

// Ver logs
tail -f wp-content/debug.log | grep "Imagina License"
```

### Ver Estado de Validación

```php
$validator = Imagina_License_SDK::get_validator( 'mi-plugin' );
if ( $validator ) {
    error_log( 'License Data: ' . print_r( $validator->get_license_data(), true ) );
    error_log( 'Is valid: ' . ( $validator->is_valid() ? 'YES' : 'NO' ) );
    error_log( 'In grace period: ' . ( $validator->is_in_grace_period() ? 'YES' : 'NO' ) );
}
```

### Ver Logs del Heartbeat

```php
$logs = Imagina_License_Heartbeat::get_logs( 50 );
error_log( 'Heartbeat Logs: ' . print_r( $logs, true ) );
```

---

## 📚 Más Información

- [INTEGRATION.md](INTEGRATION.md) - Guía de integración
- [SECURITY.md](SECURITY.md) - Explicación de seguridad
- [README.md](../README.md) - Visión general del sistema
