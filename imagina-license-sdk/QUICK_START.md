# 🚀 Quick Start - Sistema de Licencias para Plugins Premium

Guía de inicio rápido para poner en marcha el sistema de licencias.

## 📋 ¿Qué Tengo Ahora?

Has recibido un sistema completo de licenciamiento con **7 capas de seguridad** que incluye:

✅ **SDK de Licencias** - Para integrar en tus plugins premium
✅ **Extensión del Servidor** - Nuevos endpoints de validación
✅ **Extensión del Cliente** - Gestor de licencias local
✅ **Plugin de Ejemplo** - Implementación completa de referencia
✅ **Documentación Completa** - Guías de integración, seguridad y API

## ⚡ Inicio Rápido en 5 Pasos

### Paso 1: Instalar Extensión del Servidor (5 minutos)

```bash
cd imagina-updater-server

# Copiar archivos
cp ../imagina-license-sdk/server-extension/class-license-api.php api/
cp ../imagina-license-sdk/server-extension/class-license-crypto-server.php includes/
```

Editar `imagina-updater-server.php`:

```php
// Al final del archivo, añadir:
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-server.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-license-api.php';
add_action( 'rest_api_init', array( 'Imagina_Updater_License_API', 'register_routes' ) );
```

### Paso 2: Instalar Extensión del Cliente (5 minutos)

```bash
cd imagina-updater-client

# Copiar archivos
cp ../imagina-license-sdk/client-extension/class-license-manager.php includes/
cp ../imagina-license-sdk/client-extension/class-license-crypto-client.php includes/
```

Editar `imagina-updater-client.php`:

```php
// Después de cargar otras clases, añadir:
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';
add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ), 5 );
```

### Paso 3: Probar con el Plugin de Ejemplo (10 minutos)

```bash
cd imagina-license-sdk/example-premium-plugin

# Copiar el SDK
mkdir -p vendor
cp -r ../sdk vendor/imagina-license-sdk

# Crear ZIP
zip -r example-premium-plugin.zip . -x "*.git*"
```

**En el servidor:**
1. Ve a Plugins > Añadir Plugin
2. Sube `example-premium-plugin.zip`
3. El servidor detectará automáticamente los metadatos

**Configurar permisos:**
1. Ve a API Keys
2. Edita/crea una API Key
3. En "Tipo de Acceso", selecciona "All" o añade `example-premium` a permisos específicos

**En un sitio cliente:**
1. Asegúrate que `imagina-updater-client` esté configurado
2. Instala el plugin desde la sección de Actualizaciones
3. ¡Debería funcionar automáticamente!

### Paso 4: Crear Tu Primer Plugin Premium (30 minutos)

```bash
# Copiar el ejemplo como plantilla
cp -r example-premium-plugin mi-plugin-premium
cd mi-plugin-premium

# Buscar y reemplazar en todos los archivos:
# - example-premium → mi-plugin-premium
# - Example_Premium → Mi_Plugin_Premium
# - EXAMPLE_PREMIUM → MI_PLUGIN_PREMIUM
# - Example Premium → Mi Plugin Premium

# Copiar el SDK
mkdir -p vendor
cp -r ../sdk vendor/imagina-license-sdk

# Desarrollar tu funcionalidad en includes/class-main.php
```

### Paso 5: Distribuir y Gestionar (Continuo)

**Subir al servidor:**
```bash
zip -r mi-plugin-premium-1.0.0.zip mi-plugin-premium -x "*.git*"
# Subir en el panel del servidor
```

**Configurar licencias:**
- Crea API Keys para tus clientes
- Asigna permisos por plugin o por grupos
- Configura límites de activación (max_activations)

**Control remoto:**
- Desactiva API Keys para revocar acceso
- Los plugins se desactivarán en máximo 12 horas (heartbeat)
- Puedes ver estadísticas de uso en el servidor

## 🎯 Opciones de Integración

### Opción A: Plugin 100% Premium (Requiere Licencia)

```php
add_action( 'plugins_loaded', 'mi_plugin_init' );

function mi_plugin_init() {
    $license = Imagina_License_SDK::init( array(
        'plugin_slug' => 'mi-plugin-premium',
        'plugin_name' => 'Mi Plugin Premium',
        'plugin_file' => __FILE__,
    ) );

    if ( ! $license->is_valid() ) {
        return; // No cargar nada sin licencia
    }

    // Cargar plugin completo
    require_once 'includes/class-main.php';
    Mi_Plugin_Main::init();
}
```

### Opción B: Plugin Freemium (Básico Gratis + Premium de Pago)

```php
function mi_plugin_init() {
    // SIEMPRE cargar funcionalidad básica
    require_once 'includes/class-basic.php';
    Mi_Plugin_Basic::init();

    // Verificar licencia para premium
    $license = Imagina_License_SDK::init( array(
        'plugin_slug' => 'mi-plugin-pro',
        'plugin_name' => 'Mi Plugin Pro',
        'plugin_file' => __FILE__,
    ) );

    if ( $license->is_valid() ) {
        // Cargar funcionalidad premium
        require_once 'includes/class-premium.php';
        Mi_Plugin_Premium::init();
    }
}
```

### Opción C: Múltiples Niveles (Basic / Pro / Enterprise)

```php
// Basic (gratis)
require_once 'includes/class-basic.php';

// Pro (licencia "mi-plugin-pro")
$pro_license = Imagina_License_SDK::init( array( 'plugin_slug' => 'mi-plugin-pro', ... ) );
if ( $pro_license->is_valid() ) {
    require_once 'includes/class-pro.php';
}

// Enterprise (licencia "mi-plugin-enterprise")
$enterprise_license = Imagina_License_SDK::init( array( 'plugin_slug' => 'mi-plugin-enterprise', ... ) );
if ( $enterprise_license->is_valid() ) {
    require_once 'includes/class-enterprise.php';
}
```

## 🛡️ Niveles de Seguridad

El sistema implementa **7 capas de protección**:

| # | Capa | Descripción | Bypass Difícil |
|---|------|-------------|----------------|
| 1 | **Validación Remota** | El servidor decide si es válida | ⭐⭐⭐⭐⭐ |
| 2 | **Heartbeat (12h)** | Verificación automática periódica | ⭐⭐⭐⭐ |
| 3 | **Firma Digital** | Respuestas firmadas con HMAC-SHA256 | ⭐⭐⭐⭐⭐ |
| 4 | **Tokens Cortos** | Tokens JWT que expiran cada 24h | ⭐⭐⭐⭐ |
| 5 | **Integridad SDK** | Detecta modificación del código | ⭐⭐⭐ |
| 6 | **Ofuscación** | Código difícil de leer/modificar | ⭐⭐ |
| 7 | **Múltiples Puntos** | Verifica en varios momentos | ⭐⭐⭐⭐ |

**Grace Period:** 3 días por defecto (configurable)
- Permite funcionamiento temporal si el servidor no responde
- Evita desactivaciones por problemas de conectividad
- Se resetea al verificar exitosamente

## 📊 Gestión de Licencias

### Escenarios Comunes

**1. Cliente nuevo:**
```
1. Crear API Key en el servidor
2. Cliente activa sitio con la API Key
3. Recibe activation_token único
4. Plugins premium verifican automáticamente
```

**2. Limitar sitios por licencia:**
```
API Key con max_activations = 5
→ Cliente puede activar hasta 5 sitios
→ Intento #6 recibe error
```

**3. Revocar acceso:**
```
Desactivar API Key en el servidor
→ En máximo 12h (heartbeat) plugins se desactivan
→ Cliente ve aviso de licencia inválida
```

**4. Downgrade de licencia:**
```
Cambiar access_type de "all" a "specific"
Seleccionar solo plugins básicos
→ Plugins premium se desactivan en siguiente verificación
```

**5. Cliente cambia de dominio:**
```
Desactivar activación antigua en el servidor
Cliente activa nuevo dominio con la misma API Key
→ Libera slot de activación
```

## 🔍 Debugging y Logs

### Ver estado de licencia:

```php
$validator = Imagina_License_SDK::get_validator( 'mi-plugin' );
$data = $validator->get_license_data();
error_log( print_r( $data, true ) );
```

### Forzar verificación:

```php
$validator->force_check(); // Ignora caché, verifica con servidor
```

### Ver logs del heartbeat:

```php
$logs = Imagina_License_Heartbeat::get_logs( 50 );
foreach ( $logs as $log ) {
    echo "[{$log['timestamp']}] {$log['level']}: {$log['message']}<br>";
}
```

## 📚 Documentación Completa

- **[README.md](README.md)** - Visión general del sistema
- **[INTEGRATION.md](docs/INTEGRATION.md)** - Guía completa de integración
- **[SECURITY.md](docs/SECURITY.md)** - Explicación de las 7 capas de seguridad
- **[API.md](docs/API.md)** - Referencia completa de la API
- **[server-extension/INSTALL.md](server-extension/INSTALL.md)** - Instalación del servidor
- **[client-extension/INSTALL.md](client-extension/INSTALL.md)** - Instalación del cliente
- **[example-premium-plugin/README.md](example-premium-plugin/README.md)** - Guía del ejemplo

## ⚙️ Configuración Recomendada

### Para Desarrollo/Testing:

```php
$license = Imagina_License_SDK::init( array(
    'plugin_slug'  => 'mi-plugin',
    'plugin_name'  => 'Mi Plugin',
    'plugin_file'  => __FILE__,
    'grace_period' => DAY_IN_SECONDS, // 1 día
) );
```

### Para Producción:

```php
$license = Imagina_License_SDK::init( array(
    'plugin_slug'  => 'mi-plugin',
    'plugin_name'  => 'Mi Plugin',
    'plugin_file'  => __FILE__,
    'grace_period' => 7 * DAY_IN_SECONDS, // 7 días (recomendado)
) );
```

### Para Máxima Seguridad:

```php
$license = Imagina_License_SDK::init( array(
    'plugin_slug'  => 'mi-plugin',
    'plugin_name'  => 'Mi Plugin',
    'plugin_file'  => __FILE__,
    'grace_period' => 0, // Sin grace period
) );
```

## ✅ Checklist de Implementación

- [ ] Extensión del servidor instalada
- [ ] Extensión del cliente instalada
- [ ] Plugin de ejemplo probado
- [ ] Primer plugin premium creado
- [ ] SDK copiado al plugin (`vendor/imagina-license-sdk/`)
- [ ] Código de validación implementado
- [ ] Plugin subido al servidor
- [ ] Permisos configurados en API Key
- [ ] Probado en sitio cliente
- [ ] Documentación leída

## 🎉 ¡Listo!

Ya tienes un sistema completo de licenciamiento para tus plugins premium que:

✅ Es **muy difícil de hackear** (7 capas de seguridad)
✅ Te da **control total** sobre las licencias
✅ **Valida constantemente** con el servidor
✅ Detecta y **bloquea licencias desactivadas**
✅ Es **fácil de integrar** en tus plugins
✅ Tiene **grace period** para mejor UX
✅ Incluye **plugin de ejemplo** funcional
✅ Está **completamente documentado**

## 💡 Próximos Pasos

1. **Instala las extensiones** en servidor y cliente
2. **Prueba con el plugin de ejemplo**
3. **Crea tu primer plugin premium**
4. **Lee la documentación completa** para features avanzados
5. **Desarrolla y distribuye** tus plugins premium

## 🆘 Soporte

Si tienes problemas:

1. **Lee la documentación** correspondiente
2. **Revisa el plugin de ejemplo** para ver la implementación correcta
3. **Verifica los logs** de WordPress y del heartbeat
4. **Revisa los checklist** de instalación

## 🔐 Recuerda

> **El objetivo no es ser 100% inquebrantable (imposible en PHP), sino hacer que sea más fácil pagar la licencia que hackearla.**

El sistema implementa suficiente seguridad para el 99% de los casos de uso, con control total desde tu servidor.

---

**¡Buena suerte con tus plugins premium!** 🚀
