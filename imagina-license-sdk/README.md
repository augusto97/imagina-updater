# Imagina License SDK v4.0

Sistema de licenciamiento con **protección automática** para plugins premium de WordPress.

## Descripcion General

**Ya no necesitas integrar el SDK manualmente.** A partir de la version 4.0, el sistema de proteccion se inyecta automaticamente cuando subes un plugin al servidor y lo marcas como "Premium".

## Como Funciona (v4.0)

### Para el Desarrollador del Plugin Premium:

1. **Desarrolla tu plugin normalmente** - Sin codigo de proteccion
2. **Sube el plugin al servidor** Imagina Updater
3. **Marca el plugin como "Premium"** en la interfaz de administracion
4. **El sistema inyecta la proteccion automaticamente**

### Para el Usuario Final (Cliente):

1. Instala `Imagina Updater Client` y configura la conexion al servidor
2. Los plugins premium se actualizan normalmente
3. Si no tiene licencia valida, el plugin muestra un aviso y se desactiva

## Caracteristicas de la Proteccion v4.0

### Arquitectura Hibrida
- **Verificacion via License Manager** del cliente (si esta disponible)
- **Verificacion directa al servidor** (fallback)
- **Multiples capas de seguridad**

### Puntos de Verificacion
- `plugins_loaded` - Al cargar WordPress
- `admin_init` - Al cargar el admin
- REST API - Antes de procesar requests
- AJAX - Antes de procesar requests

### Grace Period
- 7 dias sin conexion al servidor
- Permite funcionamiento temporal si hay problemas de red
- Solo aplica si ya tenia una licencia valida previamente

### Heartbeat
- Verificacion automatica cada 12 horas
- Detecta licencias revocadas
- Envia telemetria opcional (version WP, PHP, etc.)

## Estructura del Codigo Inyectado

El codigo de proteccion se inyecta automaticamente en el archivo principal del plugin:

```php
<?php
/**
 * Plugin Name: Tu Plugin Premium
 * ...
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// IMAGINA LICENSE PROTECTION v4.0.0
// Plugin: Tu Plugin Premium
// Generated: 2025-01-08 12:00:00
// DO NOT MODIFY THIS CODE - Integrity verification is active
// ============================================================================

// ... codigo de proteccion inyectado automaticamente ...

// ============================================================================
// END IMAGINA LICENSE PROTECTION
// ============================================================================

// Tu codigo del plugin continua aqui normalmente
```

## API del Sistema de Proteccion

Si necesitas interactuar con el sistema de proteccion desde tu plugin:

```php
// Obtener el nombre de la clase de proteccion
$class_name = 'ILP_' . substr(md5('tu-plugin-slug' . 'imagina_license'), 0, 8);

// Verificar si esta licenciado
if (class_exists($class_name)) {
    $is_licensed = call_user_func(array($class_name, 'is_licensed'));

    if ($is_licensed) {
        // Plugin licenciado, ejecutar funcionalidades premium
    } else {
        // Plugin no licenciado - las funciones premium estan deshabilitadas
        // IMPORTANTE: Los avisos SOLO se muestran en el admin, NUNCA en el frontend
    }
}
```

### Metodos Disponibles

| Metodo | Descripcion |
|--------|-------------|
| `is_licensed()` | Retorna `true` si la licencia es valida |
| `get_license_data()` | Retorna array con datos de la licencia |
| `recheck()` | Fuerza una verificacion remota inmediata |
| `deactivate()` | Limpia los datos de licencia locales |

## Flujo de Verificacion

```
PLUGIN PREMIUM                    CLIENTE                     SERVIDOR
     |                               |                            |
     | 1. plugins_loaded             |                            |
     |------------------------------>|                            |
     |                               |                            |
     | 2. Verificar licencia         |                            |
     |   (via License Manager)       |                            |
     |------------------------------>|                            |
     |                               |                            |
     |                               | 3. Cache valido?           |
     |                               |    SI -> Retornar cache    |
     |                               |    NO -> Verificar servidor|
     |                               |--------------------------->|
     |                               |                            |
     |                               |         4. Verificar:      |
     |                               |         - activation_token |
     |                               |         - plugin_slug      |
     |                               |         - dominio          |
     |                               |         - permisos         |
     |                               |                            |
     |                               |   5. Respuesta + firma     |
     |                               |<---------------------------|
     |                               |                            |
     |                               | 6. Guardar en cache        |
     |                               |    (6 horas)               |
     |                               |                            |
     | 7. Resultado                  |                            |
     |<------------------------------|                            |
     |                               |                            |
     | 8. SI valido: cargar plugin   |                            |
     |    NO valido: mostrar aviso   |                            |
     |                               |                            |
```

## Componentes del Sistema

### En el Servidor
- `imagina-updater-server` - Plugin base
- `imagina-updater-license-extension` - Extension de licencias
  - `class-protection-generator.php` - Genera el codigo de proteccion
  - `class-sdk-injector.php` - Inyecta el codigo en los plugins
  - `class-license-api.php` - Endpoints REST para verificacion
  - `class-admin.php` - Interfaz de administracion

### En el Cliente
- `imagina-updater-client` - Plugin cliente
  - `class-license-manager.php` - Gestor de licencias
  - Verificacion y cache de licencias

## Ejemplo de Plugin Premium

Ver `/example-premium-plugin/` para un ejemplo completo de como desarrollar un plugin premium compatible con este sistema.

**Nota:** El plugin de ejemplo NO tiene codigo de proteccion incluido. Este se inyecta automaticamente cuando se sube al servidor y se marca como premium.

## Seguridad

### Que protege:
- Uso sin licencia
- Uso en dominios no autorizados
- Uso despues de cancelar la licencia

### Limitaciones (PHP es interpretado):
- Un usuario tecnico podria modificar el codigo
- Pero tendria que hacerlo en CADA actualizacion
- Y perderia soporte oficial
- Y seria detectable por telemetria

**El objetivo es hacer que sea mas facil pagar que hackear.**

## Migracion desde v3.x

Si tienes plugins con el sistema anterior (SDK embebido manual):

1. Actualiza `imagina-updater-license-extension` a v4.0
2. Vuelve a subir el plugin premium al servidor
3. El sistema inyectara la nueva proteccion automaticamente
4. Los usuarios recibirán la actualizacion normalmente

## Soporte

Para soporte tecnico, contacta al equipo de desarrollo de Imagina.
