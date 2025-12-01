# 🔐 Imagina License SDK

Sistema de licenciamiento seguro y robusto para plugins premium de WordPress.

## 📋 Descripción

Este SDK proporciona un sistema de validación de licencias de **múltiples capas de seguridad** que protege tus plugins premium contra:

- ✅ Uso no autorizado
- ✅ Modificación del código de validación
- ✅ Bypass por comentar código
- ✅ Clonación a otros sitios
- ✅ Uso después de cancelar la licencia

## 🛡️ Capas de Seguridad

### 1. **Validación Remota Obligatoria**
- El servidor es la única fuente de verdad
- Sin conexión al servidor = sin funcionalidad (después del grace period)

### 2. **Heartbeat Constante**
- Verificación automática cada 12-24 horas
- Detección de licencias desactivadas en tiempo real
- Grace period configurable para problemas temporales de conectividad

### 3. **Firma Digital Criptográfica**
- Todas las respuestas del servidor están firmadas con HMAC-SHA256
- Imposible falsificar respuestas del servidor
- Cada sitio tiene una clave secreta única

### 4. **License Tokens de Corta Duración**
- Tokens JWT que expiran cada 24-48 horas
- Deben renovarse constantemente
- Almacenados encriptados en la base de datos

### 5. **Verificación de Integridad del SDK**
- El SDK verifica su propio checksum
- Detecta modificaciones en el código de validación
- Auto-desactivación si detecta manipulación

### 6. **Ofuscación de Código Crítico**
- Variables y funciones con nombres aleatorios
- Código crítico ofuscado
- Dificulta la lectura y modificación

### 7. **Múltiples Puntos de Verificación**
- Validación al activar el plugin
- Validación en admin_init
- Validación antes de ejecutar funcionalidades críticas
- Validación en AJAX/REST API endpoints

## 📦 Componentes

### SDK (`/sdk/`)
- **class-license-validator.php**: Validador principal (código ofuscado)
- **class-heartbeat.php**: Sistema de verificación periódica
- **class-crypto.php**: Criptografía y firma digital
- **loader.php**: Cargador del SDK

### Extensión del Servidor (`/server-extension/`)
- Nuevos endpoints REST API para validación de licencias
- Generación de tokens firmados
- Control de licencias por plugin

### Extensión del Cliente (`/client-extension/`)
- Gestor de licencias local
- Caché de validaciones
- Heartbeat client-side

### Plugin de Ejemplo (`/example-premium-plugin/`)
- Plugin premium completo con integración del SDK
- Ejemplos de uso en diferentes contextos
- UI de gestión de licencia

## 🚀 Instalación

### Paso 1: Instalar Extensiones

```bash
# Copiar extensión del servidor
cp server-extension/class-license-api.php imagina-updater-server/api/
cp server-extension/class-license-validator.php imagina-updater-server/includes/

# Copiar extensión del cliente
cp client-extension/class-license-manager.php imagina-updater-client/includes/
```

### Paso 2: Integrar en el Servidor

Editar `imagina-updater-server/imagina-updater-server.php`:

```php
// Cargar la extensión de licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-validator.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-license-api.php';

// Registrar la API de licencias
add_action( 'rest_api_init', array( 'Imagina_Updater_License_API', 'register_routes' ) );
```

### Paso 3: Integrar en el Cliente

Editar `imagina-updater-client/imagina-updater-client.php`:

```php
// Cargar el gestor de licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';

// Inicializar el gestor
add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ) );
```

### Paso 4: Integrar en tu Plugin Premium

```bash
# Copiar el SDK a tu plugin
cp -r sdk/ tu-plugin-premium/vendor/imagina-license-sdk/
```

En tu plugin principal:

```php
/**
 * Plugin Name: Tu Plugin Premium
 * Requires Plugins: imagina-updater-client
 */

// Cargar el SDK
require_once plugin_dir_path( __FILE__ ) . 'vendor/imagina-license-sdk/loader.php';

// Inicializar validación
$license = Imagina_License_SDK::init( array(
    'plugin_slug' => 'tu-plugin-premium',
    'plugin_name' => 'Tu Plugin Premium',
    'plugin_file' => __FILE__,
    'grace_period' => 3 * DAY_IN_SECONDS, // 3 días
) );

// Verificar licencia antes de cargar funcionalidades
if ( ! $license->is_valid() ) {
    // Mostrar aviso de licencia
    add_action( 'admin_notices', array( $license, 'show_notice' ) );
    return; // No cargar funcionalidades
}

// Cargar plugin normalmente
require_once 'includes/class-main.php';
```

## 📖 Documentación

- **[INTEGRATION.md](docs/INTEGRATION.md)** - Guía de integración completa
- **[SECURITY.md](docs/SECURITY.md)** - Explicación detallada de seguridad
- **[API.md](docs/API.md)** - Referencia de la API del SDK

## 🔒 Cómo Funciona

### Flujo de Validación

```
PLUGIN PREMIUM                    CLIENTE                     SERVIDOR
     |                               |                            |
     | 1. Verificar licencia         |                            |
     |------------------------------>|                            |
     |                               |                            |
     |                               | 2. ¿Tiene license_token    |
     |                               |    válido en caché?        |
     |                               |                            |
     |                               | NO → Solicitar validación  |
     |                               |--------------------------->|
     |                               |                            |
     |                               |         3. Verificar:      |
     |                               |         - activation_token |
     |                               |         - plugin_slug      |
     |                               |         - dominio          |
     |                               |         - permisos         |
     |                               |                            |
     |                               |   4. Generar license_token |
     |                               |      firmado (24h)         |
     |                               |   + firma HMAC             |
     |                               |<---------------------------|
     |                               |                            |
     |                               | 5. Verificar firma         |
     |                               | 6. Guardar en caché        |
     |                               |                            |
     | 7. Licencia válida ✓          |                            |
     |<------------------------------|                            |
     |                               |                            |
     | 8. Ejecutar funcionalidades   |                            |
     |                               |                            |
```

### Heartbeat (Verificación Periódica)

```
HEARTBEAT (WP-Cron)               CLIENTE                     SERVIDOR
     |                               |                            |
     | Cada 12 horas                 |                            |
     |------------------------------>|                            |
     |                               |                            |
     |                               | Verificar todas las        |
     |                               | licencias activas          |
     |                               |--------------------------->|
     |                               |                            |
     |                               |    Validar cada una        |
     |                               |<---------------------------|
     |                               |                            |
     |                               | Actualizar caché           |
     |                               | Si inválida: marcar        |
     |                               |                            |
```

## ⚠️ Limitaciones Conocidas

**PHP no puede ser 100% seguro contra reverse engineering**, pero este SDK implementa:

- ✅ Múltiples capas que dificultan el bypass
- ✅ Validación constante con el servidor (no solo una vez)
- ✅ Detección de modificaciones del código
- ✅ Control total desde el servidor para desactivar licencias

**Un usuario muy técnico podría**:
- Modificar el código del plugin para eliminar las verificaciones
- Pero tendría que hacerlo en CADA actualización
- Y tendría que modificar múltiples archivos
- Y perder soporte oficial

**Este SDK hace que sea más fácil pagar la licencia que hackearla.**

## 📝 Licencia

Este SDK es de código cerrado y solo puede ser usado en plugins autorizados por Imagina.

## 🤝 Soporte

Para soporte técnico, contacta al equipo de desarrollo.
