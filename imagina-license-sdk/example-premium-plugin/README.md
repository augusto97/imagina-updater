# 📦 Example Premium Plugin

Plugin de ejemplo que demuestra la integración completa del sistema de licencias Imagina.

## 📋 Qué es Este Plugin

Este es un **plugin de ejemplo completamente funcional** que muestra:

✅ Cómo integrar el SDK de licencias en tu plugin
✅ Verificación de licencia al cargar el plugin
✅ Protección de funcionalidades premium
✅ Manejo del grace period
✅ Interfaz de administración con estado de licencia
✅ Protección de endpoints AJAX y REST API
✅ Uso de hooks para reaccionar a cambios de licencia

## 🚀 Cómo Usar Este Ejemplo

### Opción 1: Instalar y Probar

1. **Copiar el SDK al plugin:**
   ```bash
   cd example-premium-plugin
   mkdir -p vendor
   cp -r ../sdk vendor/imagina-license-sdk
   ```

2. **Crear ZIP del plugin:**
   ```bash
   zip -r example-premium-plugin.zip . -x "*.git*" -x "node_modules/*"
   ```

3. **Subir al servidor de actualizaciones:**
   - Ve al panel del servidor
   - Plugins > Añadir Plugin
   - Sube `example-premium-plugin.zip`

4. **Configurar permisos:**
   - Crea/edita una API Key
   - Asigna acceso al plugin `example-premium`

5. **Instalar en un sitio cliente:**
   - Asegúrate que `imagina-updater-client` esté instalado
   - Instala el plugin `example-premium-plugin`
   - El plugin verificará automáticamente la licencia

### Opción 2: Usar como Plantilla

1. **Copiar el plugin:**
   ```bash
   cp -r example-premium-plugin mi-nuevo-plugin
   cd mi-nuevo-plugin
   ```

2. **Renombrar archivos:**
   ```bash
   mv example-premium.php mi-nuevo-plugin.php
   ```

3. **Buscar y reemplazar:**
   - `example-premium` → `mi-nuevo-plugin`
   - `Example_Premium` → `Mi_Nuevo_Plugin`
   - `EXAMPLE_PREMIUM` → `MI_NUEVO_PLUGIN`
   - `Example Premium` → `Mi Nuevo Plugin`

4. **Editar headers del plugin:**
   ```php
   /**
    * Plugin Name: Mi Nuevo Plugin
    * Plugin URI: https://tu-sitio.com/plugin
    * Description: Descripción de tu plugin
    * Version: 1.0.0
    * Author: Tu Nombre
    */
   ```

5. **Copiar el SDK:**
   ```bash
   mkdir -p vendor
   cp -r ../sdk vendor/imagina-license-sdk
   ```

6. **Desarrollar tu funcionalidad:**
   - Edita `includes/class-main.php`
   - Añade tus características en `includes/`
   - Mantén la estructura de licenciamiento

## 📂 Estructura del Plugin

```
example-premium-plugin/
├── example-premium.php                    # Archivo principal
│   ├── Verificación de dependencias
│   ├── Carga del SDK de licencias
│   ├── Inicialización de validación
│   └── Carga condicional del plugin
│
├── includes/
│   ├── class-license-integration.php     # Helpers de licencia
│   │   ├── Métodos de verificación
│   │   ├── Widget de estado
│   │   └── Hooks de invalidación
│   │
│   └── class-main.php                     # Funcionalidad principal
│       ├── Admin menu
│       ├── Settings
│       ├── REST API
│       ├── AJAX
│       └── Shortcodes
│
├── admin/
│   └── views/
│       └── license-notice.php             # Vista de aviso de licencia
│
├── vendor/
│   └── imagina-license-sdk/               # SDK (copiado)
│       ├── loader.php
│       ├── class-crypto.php
│       ├── class-license-validator.php
│       └── class-heartbeat.php
│
└── README.md                              # Este archivo
```

## 🔍 Análisis del Código

### Archivo Principal (`example-premium.php`)

```php
// 1. Verificar dependencias
function example_premium_check_dependencies() {
    if ( ! class_exists( 'Imagina_Updater_License_Manager' ) ) {
        add_action( 'admin_notices', 'example_premium_missing_license_manager_notice' );
        return false;
    }
    return true;
}

// 2. Cargar SDK
require_once $sdk_path;

// 3. Inicializar validación
$license = Imagina_License_SDK::init( array(
    'plugin_slug'  => EXAMPLE_PREMIUM_SLUG,
    'plugin_name'  => 'Example Premium Plugin',
    'plugin_file'  => EXAMPLE_PREMIUM_PLUGIN_FILE,
    'grace_period' => 3 * DAY_IN_SECONDS,
) );

// 4. Verificar licencia
if ( ! $license->is_valid() ) {
    return; // No cargar funcionalidades
}

// 5. Cargar plugin completo
example_premium_load_plugin();
```

### Protección de Funcionalidades

**Admin Menu:**
```php
// Solo se añade si la licencia es válida
// (porque solo se carga class-main.php si es válida)
add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
```

**AJAX:**
```php
public function handle_ajax() {
    // Verificación adicional
    if ( ! Imagina_License_SDK::is_licensed( EXAMPLE_PREMIUM_SLUG ) ) {
        wp_send_json_error( array( 'message' => 'Licencia inválida' ) );
    }
    // Procesar AJAX...
}
```

**REST API:**
```php
public function rest_permission_check() {
    $validator = Imagina_License_SDK::get_validator( EXAMPLE_PREMIUM_SLUG );
    if ( ! $validator || ! $validator->is_valid() ) {
        return false;
    }
    return true;
}
```

**Shortcodes:**
```php
public function shortcode_example( $atts ) {
    if ( ! Imagina_License_SDK::is_licensed( EXAMPLE_PREMIUM_SLUG ) ) {
        return '<p>Este contenido requiere una licencia válida.</p>';
    }
    // Renderizar shortcode...
}
```

## 🎓 Patrones de Uso

### Patrón 1: Verificación al Cargar (Recomendado)

```php
add_action( 'plugins_loaded', 'mi_plugin_init' );

function mi_plugin_init() {
    $license = Imagina_License_SDK::init( [...] );

    if ( ! $license->is_valid() ) {
        return; // No cargar nada
    }

    // Cargar todo el plugin
    require_once 'includes/class-main.php';
}
```

**Ventajas:**
- ✅ Simple y efectivo
- ✅ Nada se carga sin licencia
- ✅ Menor overhead

### Patrón 2: Verificación por Funcionalidad

```php
function mi_funcionalidad_premium() {
    if ( ! Imagina_License_SDK::is_licensed( 'mi-plugin' ) ) {
        return new WP_Error( 'no_license', 'Requiere licencia' );
    }

    // Ejecutar funcionalidad...
}
```

**Ventajas:**
- ✅ Control granular
- ✅ Útil para funcionalidades específicas

### Patrón 3: Modo Freemium

```php
// Cargar funcionalidad básica siempre
require_once 'includes/class-basic.php';

// Verificar licencia para premium
$license = Imagina_License_SDK::init( [...] );
if ( $license->is_valid() ) {
    require_once 'includes/class-premium.php';
}
```

**Ventajas:**
- ✅ Funcionalidad básica gratis
- ✅ Premium requiere licencia

## 🛠️ Personalización

### Cambiar el Período de Gracia

```php
$license = Imagina_License_SDK::init( array(
    'plugin_slug'  => 'mi-plugin',
    'plugin_name'  => 'Mi Plugin',
    'plugin_file'  => __FILE__,
    'grace_period' => 7 * DAY_IN_SECONDS, // 7 días en lugar de 3
) );
```

### Añadir Widget de Estado en Admin

```php
add_action( 'admin_notices', function() {
    Example_Premium_License_Integration::render_license_widget();
} );
```

### Hook Personalizado al Invalidar Licencia

```php
add_action( 'imagina_license_invalid_mi-plugin', function() {
    // Tu código cuando se invalida la licencia
    error_log( '[Mi Plugin] Licencia invalidada' );

    // Limpiar datos
    delete_option( 'mi_plugin_premium_data' );

    // Notificar
    wp_mail( get_option( 'admin_email' ), 'Licencia inválida', 'Mensaje...' );
} );
```

## 🧪 Testing

### Test Local

1. **Sin licencia configurada:**
   - Activa el plugin
   - Deberías ver aviso de "Plugin de Licencias Requerido"

2. **Con licencia inválida:**
   - Configura Imagina Updater Client con token inválido
   - Deberías ver aviso de "Licencia inválida"

3. **Con licencia válida:**
   - Configura correctamente el cliente
   - El plugin debería funcionar normalmente
   - Ve a "Example Premium" en el admin

4. **Durante grace period:**
   - Desactiva temporalmente el servidor
   - El plugin debería seguir funcionando
   - Deberías ver aviso de "Período de Gracia"

### Test de Verificación Forzada

```php
// Añade temporalmente a functions.php
add_action( 'admin_init', function() {
    $validator = Imagina_License_SDK::get_validator( 'example-premium' );
    if ( $validator ) {
        $result = $validator->force_check();
        error_log( 'Force check result: ' . ( $result ? 'valid' : 'invalid' ) );
    }
} );
```

## 📚 Recursos Adicionales

- [Guía de Integración Completa](../docs/INTEGRATION.md)
- [Documento de Seguridad](../docs/SECURITY.md)
- [Referencia de API](../docs/API.md)

## 💡 Tips

1. **Siempre verifica dependencias** antes de cargar el SDK
2. **Usa el grace period** para mejor experiencia de usuario
3. **Protege múltiples puntos** (AJAX, REST, shortcodes)
4. **Muestra avisos claros** cuando falta la licencia
5. **Log de eventos** para debugging
6. **Hooks de invalidación** para limpiar datos

## 🐛 Troubleshooting

### "Plugin de Licencias Requerido"

- Verifica que `imagina-updater-client` esté activo
- Verifica que la extensión del cliente esté instalada

### "Licencia inválida"

- Verifica la configuración del cliente
- Verifica que el plugin esté en los permisos de la API Key
- Usa `force_check()` para debugging

### El plugin se desactiva solo

- Revisa el grace period (puede haber expirado)
- Verifica conectividad con el servidor
- Revisa los logs del heartbeat

## 📝 Licencia del Ejemplo

Este código de ejemplo es de dominio público. Puedes usarlo libremente como base para tus plugins premium.
