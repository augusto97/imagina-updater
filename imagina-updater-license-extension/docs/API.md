# API Reference — Imagina Updater License Extension

Referencia de los endpoints REST y de las clases públicas que componen la extensión de licencias.

> **Nota sobre el origen:** parte de este contenido se rescató del antiguo `imagina-license-sdk/docs/API.md` (eliminado en la Fase 0) y se adaptó al modelo actual. Se descartó todo lo que era específico del SDK manual (clases `Imagina_License_SDK`, `Imagina_License_Validator`, helpers `imagina_license_init()`, constantes `IMAGINA_LICENSE_SDK_*`) porque el sistema actual auto-inyecta el código de protección y no requiere integración manual por parte del desarrollador del plugin premium.

---

## Índice

- [Endpoints REST](#endpoints-rest)
  - [Verificación de licencias por activation token](#verificación-de-licencias-por-activation-token)
  - [License keys (sistema v5.0)](#license-keys-sistema-v50)
  - [Killswitch y telemetría](#killswitch-y-telemetría)
- [Clases públicas — servidor](#clases-públicas--servidor)
  - [Imagina_License_Crypto](#imagina_license_crypto)
- [Clases públicas — cliente](#clases-públicas--cliente)
  - [Imagina_Updater_License_Manager](#imagina_updater_license_manager)
- [Hooks y actions](#hooks-y-actions)

---

## Endpoints REST

> Todos los endpoints se registran en `imagina-updater-license-extension/includes/class-license-api.php` durante el hook `rest_api_init`.

### Verificación de licencias por activation token

**Namespace:** `imagina-updater/v1`

Usados por el cliente para verificar licencias en el flujo normal. Requieren `activation_token` (no API key) — ver [modelo de doble token en CLAUDE.md §1.3].

#### `POST /wp-json/imagina-updater/v1/license/verify`

Verifica la licencia de un plugin específico.

**Headers:**

```
Authorization: Bearer {activation_token}
X-Site-Domain: {dominio}
```

**Body:**

```json
{
  "plugin_slug": "mi-plugin-premium"
}
```

**Respuesta 200:**

```json
{
  "is_valid": true,
  "plugin_slug": "mi-plugin-premium",
  "plugin_name": "Mi Plugin Premium",
  "license_token": "eyJ...",
  "expires_at": 1234567890,
  "site_domain": "mi-sitio.com",
  "verified_at": "2026-01-15 10:30:00",
  "signature": "abc123..."
}
```

---

#### `POST /wp-json/imagina-updater/v1/license/info`

Obtiene información general de la licencia (API key) del sitio que llama.

**Headers:**

```
Authorization: Bearer {activation_token}
X-Site-Domain: {dominio}
```

**Respuesta 200:**

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

Verifica múltiples licencias en una sola llamada (lo usa el heartbeat).

**Headers:**

```
Authorization: Bearer {activation_token}
X-Site-Domain: {dominio}
```

**Body:**

```json
{
  "plugin_slugs": ["plugin-1", "plugin-2", "plugin-3"]
}
```

**Respuesta 200:**

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

### License keys (sistema v5.0)

**Namespace:** `imagina-license/v1`

Usados para gestionar license keys (`ILK-XXXX-XXXX-XXXX-XXXX`) — un mecanismo de licenciamiento adicional al de API keys. Permission callback público (la propia license key actúa como secreto).

#### `POST /wp-json/imagina-license/v1/activate`

Activa una license key en un sitio.

**Body:**

```json
{
  "license_key": "ILK-XXXX-XXXX-XXXX-XXXX",
  "site_url": "https://mi-sitio.com",
  "site_name": "Mi Sitio"
}
```

#### `POST /wp-json/imagina-license/v1/deactivate`

Desactiva una license key en un sitio.

**Body:**

```json
{
  "license_key": "ILK-XXXX-XXXX-XXXX-XXXX",
  "site_url": "https://mi-sitio.com"
}
```

#### `POST /wp-json/imagina-license/v1/check`

Verifica el estado de una license key en un sitio.

**Body:**

```json
{
  "license_key": "ILK-XXXX-XXXX-XXXX-XXXX",
  "site_url": "https://mi-sitio.com"
}
```

---

### Killswitch y telemetría

**Namespace:** `imagina-license/v1`

#### `POST /wp-json/imagina-license/v1/killswitch`

Permite al servidor indicar que una instalación específica debe bloquearse.

#### `POST /wp-json/imagina-license/v1/telemetry`

Recibe datos de telemetría de las instalaciones (versiones de WP/PHP, info de site).

---

## Clases públicas — servidor

### `Imagina_License_Crypto`

Archivo: `imagina-updater-license-extension/includes/class-license-crypto-server.php`

Clase de criptografía y firma digital. Todos los métodos son estáticos.

> **Regla crítica:** los métodos de firma y verificación NO deben cambiar de algoritmo sin migración planificada (ver CLAUDE.md §4 regla 9). Sitios cliente con tokens viejos se romperían.

#### `generate_license_token( array $data, string $secret ): string|false`

Genera un token de licencia firmado (formato JWT-like).

- `$data` — datos a incluir en el token.
- `$secret` — clave secreta para firmar (mínimo 32 bytes).

#### `verify_license_token( string $token, string $secret ): array|false`

Verifica y decodifica un token de licencia. Retorna los datos si es válido, `false` si no.

#### `generate_signature( string $data, string $secret ): string`

Genera una firma HMAC-SHA256 en base64url.

#### `verify_signature( string $data, string $signature, string $secret ): bool`

Verifica una firma HMAC. Usa `hash_equals()` para evitar timing attacks.

#### `generate_site_secret( string $activation_token ): string`

Deriva un secreto específico de sitio a partir del `activation_token`.

#### `file_checksum( string $file_path ): string|false`

Calcula el checksum SHA-256 de un archivo.

#### `verify_file_integrity( string $file_path, string $expected_checksum ): bool`

Verifica la integridad de un archivo comparando su checksum.

#### `encrypt( string $data, string $key ): string|false`

Encripta datos usando AES-256.

#### `decrypt( string $encrypted_data, string $key ): string|false`

Desencripta datos encriptados con `encrypt()`.

#### `hash( string $data ): string` / `verify_hash( string $data, string $hash ): bool`

Hashing genérico y verificación.

---

## Clases públicas — cliente

### `Imagina_Updater_License_Manager`

Archivo: `imagina-updater-client/includes/class-license-manager.php`

Singleton que maneja la comunicación con el servidor para validación de licencias.

#### `is_configured(): bool`

Verifica que el cliente tiene `activation_token` configurado y server URL.

#### `verify_plugin_license( string $plugin_slug, bool $force_check = false ): array|false`

Verifica la licencia de un plugin con el servidor. Cachea 6 h salvo `force_check = true`.

#### `verify_batch( array $plugin_slugs ): array`

Verifica múltiples licencias en una sola llamada.

#### `is_plugin_licensed( string $plugin_slug ): bool`

Atajo booleano sobre `verify_plugin_license()`.

#### `get_license_info(): array|WP_Error`

Información general de la API key del sitio.

#### `force_check( string $plugin_slug ): array|false`

Verificación forzada (ignora caché).

#### `invalidate_cache( string $plugin_slug ): void` / `invalidate_all_cache(): void`

Invalidan caché local. Usado al cambiar la configuración del cliente.

#### `get_stats(): array`

Estadísticas de licencias verificadas.

#### `get_server_url(): string` / `get_activation_token(): string`

Getters de configuración.

---

## Hooks y actions

### `imagina_license_invalid_{plugin_slug}` (action)

Se ejecuta cuando una licencia se invalida (tras grace period o por revocación remota).

**Parámetros:** ninguno.

**Ejemplo de uso:**

```php
add_action( 'imagina_license_invalid_mi-plugin', function () {
    // Limpiar datos premium del plugin
    delete_option( 'mi_plugin_premium_data' );

    // Loggear (con WP_DEBUG_LOG activo)
    error_log( '[Mi Plugin] Licencia invalidada' );
} );
```

### Hooks del servidor (no romper)

Estos hooks expone `imagina-updater-server` y son consumidos por la extensión de licencias. Ver CLAUDE.md §1.5 — están protegidos por la regla crítica nº 2 (no eliminar/renombrar):

- `imagina_updater_after_upload_form`
- `imagina_updater_after_move_plugin_file` ← aquí se inyecta la protección
- `imagina_updater_after_upload_plugin`
- `imagina_updater_plugins_table_header`
- `imagina_updater_plugins_table_row`

---

## Debugging

### Habilitar logs

```php
// En wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

```bash
tail -f wp-content/debug.log | grep -i "imagina\|license"
```

### Diagnóstico rápido

El repositorio incluye `diagnostico-licencias.php` (en la raíz) — script standalone que verifica:

1. Plugins activos.
2. Estructura de la tabla `wp_imagina_updater_plugins` (campo `is_premium`).
3. Plugins en BD y su estado premium.
4. Clases de licencias cargadas.
5. Hooks registrados.
6. Archivos de la extensión.
7. Último plugin subido y verificación del ZIP.
8. Logs relacionados.

**Uso:**

1. Sube `diagnostico-licencias.php` a la raíz de WordPress.
2. Accede como admin a `https://tu-sitio.com/diagnostico-licencias.php`.
3. Copia el output.

---

## Referencias

- [SECURITY.md](./SECURITY.md) — descripción detallada de las 7 capas de seguridad.
- [CLAUDE.md](../../CLAUDE.md) §1.3 — modelo de doble token (API key + activation_token).
- [CLAUDE.md](../../CLAUDE.md) §1.5 — hooks expuestos por el servidor.
- [CLAUDE.md](../../CLAUDE.md) §4 — reglas críticas que protegen este sistema.
