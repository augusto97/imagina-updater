# 📘 Guía de Integración - Imagina License SDK

Esta guía te llevará paso a paso para integrar el sistema de licencias en tus plugins premium.

## 📋 Requisitos Previos

- ✅ Imagina Updater Server instalado y configurado
- ✅ Imagina Updater Client instalado en los sitios cliente
- ✅ PHP 7.4 o superior
- ✅ WordPress 5.8 o superior

## 🚀 Instalación Rápida

### Paso 1: Instalar Extensiones del Sistema

#### A) Extensión del Servidor

```bash
cd /ruta/a/imagina-updater-server

# Copiar archivos de la extensión
cp ../imagina-license-sdk/server-extension/class-license-api.php api/
cp ../imagina-license-sdk/server-extension/class-license-crypto-server.php includes/
```

Editar `imagina-updater-server.php` y añadir:

```php
// Cargar extensión de licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-server.php';
require_once plugin_dir_path( __FILE__ ) . 'api/class-license-api.php';

// Registrar rutas de la API de licencias
add_action( 'rest_api_init', array( 'Imagina_Updater_License_API', 'register_routes' ) );
```

#### B) Extensión del Cliente

```bash
cd /ruta/a/imagina-updater-client

# Copiar archivos de la extensión
cp ../imagina-license-sdk/client-extension/class-license-manager.php includes/
cp ../imagina-license-sdk/client-extension/class-license-crypto-client.php includes/
```

Editar `imagina-updater-client.php` y añadir:

```php
// Cargar gestor de licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-client.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';

// Inicializar gestor de licencias
add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ), 5 );
```

### Paso 2: Integrar el SDK en tu Plugin Premium

#### A) Copiar el SDK a tu Plugin

```bash
cd /ruta/a/tu-plugin-premium

# Crear directorio vendor
mkdir -p vendor

# Copiar el SDK
cp -r ../imagina-license-sdk/sdk vendor/imagina-license-sdk
```

#### B) Modificar el Archivo Principal del Plugin

Editar `tu-plugin-premium.php`:

```php
<?php
/**
 * Plugin Name: Tu Plugin Premium
 * Plugin URI: https://tu-sitio.com/plugin
 * Description: Tu plugin premium con licenciamiento
 * Version: 1.0.0
 * Author: Tu Nombre
 * Requires Plugins: imagina-updater-client
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definir constantes
define( 'TU_PLUGIN_VERSION', '1.0.0' );
define( 'TU_PLUGIN_FILE', __FILE__ );
define( 'TU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TU_PLUGIN_SLUG', 'tu-plugin-premium' );

/**
 * Inicializar el plugin con verificación de licencia
 */
function tu_plugin_init() {
    // 1. Verificar que el gestor de licencias esté disponible
    if ( ! class_exists( 'Imagina_Updater_License_Manager' ) ) {
        add_action( 'admin_notices', 'tu_plugin_missing_license_manager' );
        return;
    }

    // 2. Cargar el SDK de licencias
    require_once TU_PLUGIN_DIR . 'vendor/imagina-license-sdk/loader.php';

    // 3. Inicializar validación de licencia
    $license = Imagina_License_SDK::init( array(
        'plugin_slug'  => TU_PLUGIN_SLUG,
        'plugin_name'  => 'Tu Plugin Premium',
        'plugin_file'  => TU_PLUGIN_FILE,
        'grace_period' => 3 * DAY_IN_SECONDS, // 3 días de gracia
    ) );

    // 4. Verificar licencia
    if ( ! $license->is_valid() ) {
        // Licencia inválida - el SDK mostrará el aviso
        // Solo cargar funcionalidades básicas si es necesario
        return;
    }

    // 5. Licencia válida - cargar el plugin completo
    require_once TU_PLUGIN_DIR . 'includes/class-main.php';
    Tu_Plugin_Main::init();
}
add_action( 'plugins_loaded', 'tu_plugin_init' );

/**
 * Aviso de dependencia faltante
 */
function tu_plugin_missing_license_manager() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Tu Plugin Premium</strong> requiere que
            <strong>Imagina Updater Client</strong> esté instalado y activado.
        </p>
    </div>
    <?php
}
```

## 🔐 Proteger Funcionalidades Específicas

### Método 1: Verificación al Inicio (Recomendado)

La verificación de licencia en `plugins_loaded` previene que cualquier funcionalidad se cargue si no hay licencia válida.

```php
// En tu archivo principal
add_action( 'plugins_loaded', 'tu_plugin_init' );

function tu_plugin_init() {
    // ... código de verificación de licencia ...

    if ( ! $license->is_valid() ) {
        return; // No cargar nada
    }

    // Cargar todo el plugin
    require_once 'includes/class-main.php';
    require_once 'includes/class-features.php';
    require_once 'includes/class-api.php';
    // etc.
}
```

### Método 2: Verificación en Puntos Críticos

Para funcionalidades específicas, puedes añadir verificaciones adicionales:

```php
// En una función crítica
function tu_plugin_premium_feature() {
    // Verificar licencia
    if ( ! Imagina_License_SDK::is_licensed( 'tu-plugin-premium' ) ) {
        wp_die( 'Esta funcionalidad requiere una licencia válida.' );
    }

    // Código de la funcionalidad premium
    // ...
}
```

### Método 3: Protección de Endpoints AJAX

```php
// Proteger AJAX
add_action( 'wp_ajax_tu_plugin_action', 'tu_plugin_ajax_handler' );

function tu_plugin_ajax_handler() {
    // Verificar nonce
    check_ajax_referer( 'tu_plugin_nonce', 'nonce' );

    // Verificar licencia
    if ( ! Imagina_License_SDK::is_licensed( 'tu-plugin-premium' ) ) {
        wp_send_json_error( array(
            'message' => 'Licencia inválida',
        ) );
    }

    // Procesar AJAX
    // ...
}
```

### Método 4: Protección de REST API

```php
// Proteger endpoints REST
register_rest_route( 'tu-plugin/v1', '/data', array(
    'methods'  => 'GET',
    'callback' => 'tu_plugin_rest_handler',
    'permission_callback' => 'tu_plugin_rest_permission',
) );

function tu_plugin_rest_permission() {
    // Verificar licencia
    return Imagina_License_SDK::is_licensed( 'tu-plugin-premium' );
}
```

### Método 5: Protección de Shortcodes

```php
add_shortcode( 'tu_plugin_premium', 'tu_plugin_shortcode' );

function tu_plugin_shortcode( $atts ) {
    // Verificar licencia
    if ( ! Imagina_License_SDK::is_licensed( 'tu-plugin-premium' ) ) {
        return '<p>Este contenido requiere una licencia válida.</p>';
    }

    // Renderizar shortcode
    return '<div>Contenido premium</div>';
}
```

## 🎯 Configuración del Servidor

### Añadir el Plugin al Servidor

1. **Subir el plugin al servidor**:
   - Ve a `Plugins > Añadir Plugin` en el servidor
   - Sube el archivo ZIP de tu plugin premium

2. **Configurar permisos**:
   - Ve a `API Keys` y selecciona/crea una API Key
   - En "Tipo de Acceso", selecciona:
     - **All**: El cliente puede acceder a todos los plugins
     - **Specific**: Selecciona específicamente tu plugin premium
     - **Groups**: Añade tu plugin a un grupo (ej: "Premium")

3. **Asignar límites de activación** (opcional):
   - En la API Key, configura `Max Activations`
   - 0 = ilimitado
   - N = máximo N sitios pueden usar esta licencia

### Crear Grupos de Plugins (Opcional)

1. Ve a `Plugin Groups`
2. Crea un grupo (ej: "Premium", "Enterprise", "Basic")
3. Añade tu plugin al grupo
4. En las API Keys, asigna acceso por grupos

## 🏗️ Casos de Uso Comunes

### Caso 1: Plugin Freemium

Plugin con funcionalidad básica gratis y premium de pago:

```php
function tu_plugin_init() {
    // Cargar funcionalidad básica siempre
    require_once 'includes/class-basic-features.php';
    Tu_Plugin_Basic::init();

    // Cargar SDK de licencias
    require_once 'vendor/imagina-license-sdk/loader.php';

    // Verificar si hay licencia para premium
    $license = Imagina_License_SDK::init( array(
        'plugin_slug' => 'tu-plugin-premium',
        'plugin_name' => 'Tu Plugin Premium Features',
        'plugin_file' => __FILE__,
    ) );

    if ( $license->is_valid() ) {
        // Cargar funcionalidad premium
        require_once 'includes/class-premium-features.php';
        Tu_Plugin_Premium::init();
    }
}
```

### Caso 2: Múltiples Niveles de Licencia

Plugin con diferentes niveles (Basic, Pro, Enterprise):

```php
function tu_plugin_load_by_license_level() {
    // Siempre cargar core
    require_once 'includes/class-core.php';

    // Verificar licencia Basic
    $basic_license = Imagina_License_SDK::init( array(
        'plugin_slug' => 'tu-plugin-basic',
        'plugin_name' => 'Tu Plugin Basic',
        'plugin_file' => __FILE__,
    ) );

    if ( $basic_license->is_valid() ) {
        require_once 'includes/class-basic-features.php';
    }

    // Verificar licencia Pro
    $pro_license = Imagina_License_SDK::init( array(
        'plugin_slug' => 'tu-plugin-pro',
        'plugin_name' => 'Tu Plugin Pro',
        'plugin_file' => __FILE__,
    ) );

    if ( $pro_license->is_valid() ) {
        require_once 'includes/class-pro-features.php';
    }

    // Verificar licencia Enterprise
    $enterprise_license = Imagina_License_SDK::init( array(
        'plugin_slug' => 'tu-plugin-enterprise',
        'plugin_name' => 'Tu Plugin Enterprise',
        'plugin_file' => __FILE__,
    ) );

    if ( $enterprise_license->is_valid() ) {
        require_once 'includes/class-enterprise-features.php';
    }
}
```

### Caso 3: Add-ons con Licencia Individual

Plugin base + add-ons que requieren licencias separadas:

```php
// Plugin base (sin licencia)
function tu_plugin_base_init() {
    require_once 'includes/class-base.php';
    Tu_Plugin_Base::init();
}
add_action( 'plugins_loaded', 'tu_plugin_base_init' );

// Add-on 1 con licencia
function tu_plugin_addon1_init() {
    $license = Imagina_License_SDK::init( array(
        'plugin_slug' => 'tu-plugin-addon-seo',
        'plugin_name' => 'Tu Plugin - SEO Add-on',
        'plugin_file' => __FILE__,
    ) );

    if ( $license->is_valid() ) {
        require_once 'addons/seo/class-seo.php';
    }
}
add_action( 'plugins_loaded', 'tu_plugin_addon1_init', 20 );
```

## 🧪 Testing y Debugging

### Ver Estado de la Licencia

```php
// Obtener el validador
$validator = Imagina_License_SDK::get_validator( 'tu-plugin-premium' );

if ( $validator ) {
    // Ver datos de la licencia
    $license_data = $validator->get_license_data();
    error_log( print_r( $license_data, true ) );

    // Ver si está en grace period
    if ( $validator->is_in_grace_period() ) {
        $remaining = $validator->get_grace_period_remaining();
        error_log( sprintf( 'Grace period: %d seconds remaining', $remaining ) );
    }
}
```

### Forzar Verificación Remota

```php
// Forzar verificación (ignorar caché)
$validator = Imagina_License_SDK::get_validator( 'tu-plugin-premium' );
if ( $validator ) {
    $is_valid = $validator->force_check();
    error_log( 'Forced check result: ' . ( $is_valid ? 'valid' : 'invalid' ) );
}
```

### Ver Logs del Heartbeat

```php
// Obtener logs del heartbeat
$logs = Imagina_License_Heartbeat::get_logs( 50 );
foreach ( $logs as $log ) {
    error_log( sprintf(
        '[%s] %s: %s',
        $log['timestamp'],
        $log['level'],
        $log['message']
    ) );
}
```

## 📦 Distribución del Plugin

### Estructura Recomendada

```
tu-plugin-premium/
├── tu-plugin-premium.php       # Archivo principal
├── readme.txt
├── vendor/
│   └── imagina-license-sdk/    # SDK copiado aquí
│       ├── loader.php
│       ├── class-crypto.php
│       ├── class-license-validator.php
│       └── class-heartbeat.php
├── includes/
│   ├── class-main.php
│   └── ...
└── assets/
    ├── css/
    └── js/
```

### Empaquetar el Plugin

```bash
# Crear ZIP del plugin
cd tu-plugin-premium
zip -r ../tu-plugin-premium-1.0.0.zip . -x "*.git*" -x "node_modules/*" -x "*.DS_Store"
```

### Subir al Servidor

1. Ve al panel del servidor de actualizaciones
2. `Plugins > Añadir Plugin`
3. Sube el ZIP
4. El servidor detectará automáticamente la versión y metadatos

## 🔧 Troubleshooting

### El plugin se desactiva inmediatamente

**Causa**: Falta el plugin cliente o la licencia no es válida.

**Solución**:
1. Verifica que `imagina-updater-client` esté activo
2. Verifica que el sitio esté activado con un `activation_token` válido
3. Verifica que la API Key tenga permisos para este plugin

### El plugin funciona pero no se actualiza

**Causa**: El plugin no está siendo gestionado por Imagina Updater.

**Solución**:
1. En el cliente, ve a Configuración > Imagina Updater
2. Verifica que el plugin esté en la lista de "Plugins Habilitados"
3. Habilítalo si no lo está

### "Período de gracia" constante

**Causa**: El servidor no puede ser alcanzado para verificar la licencia.

**Solución**:
1. Verifica la conectividad con el servidor
2. Revisa los logs: `error_log( print_r( $validator->get_license_data(), true ) );`
3. Verifica que los endpoints de licencias estén funcionando: `/wp-json/imagina-updater/v1/license/verify`

## 📚 Próximos Pasos

- Lee [SECURITY.md](SECURITY.md) para entender las capas de seguridad
- Lee [API.md](API.md) para la referencia completa de la API
- Revisa el plugin de ejemplo en `example-premium-plugin/`
