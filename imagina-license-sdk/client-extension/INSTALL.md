# 📦 Instalación - Extensión del Cliente

Esta extensión añade el gestor de licencias al plugin `imagina-updater-client`.

## 🚀 Instalación

### Paso 1: Copiar Archivos

```bash
cd /ruta/a/imagina-updater-client

# Copiar gestor de licencias
cp /ruta/a/imagina-license-sdk/client-extension/class-license-manager.php includes/

# Copiar clase de criptografía
cp /ruta/a/imagina-license-sdk/client-extension/class-license-crypto-client.php includes/
```

### Paso 2: Modificar Plugin Principal

Editar `imagina-updater-client.php` y añadir después de cargar otras clases:

```php
/**
 * ==================================================
 * EXTENSIÓN DE LICENCIAS
 * ==================================================
 */

// Cargar clase de criptografía para licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-client.php';

// Cargar gestor de licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';

// Inicializar gestor de licencias (debe ser temprano)
add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ), 5 );
```

**Nota importante:** El hook debe tener prioridad 5 (temprano) para que esté disponible cuando los plugins premium se carguen.

### Paso 3: Verificar Instalación

1. Ve a cada sitio cliente donde esté instalado el plugin
2. Desactiva y reactiva el plugin `imagina-updater-client`
3. Verifica que no haya errores

### Paso 4: Verificar que la Clase esté Disponible

Puedes añadir temporalmente esto a `functions.php` de tu tema:

```php
add_action( 'admin_init', function() {
    if ( class_exists( 'Imagina_Updater_License_Manager' ) ) {
        error_log( '✅ License Manager disponible' );
    } else {
        error_log( '❌ License Manager NO disponible' );
    }
} );
```

Luego verifica el log:
```bash
tail -f wp-content/debug.log
```

## ✅ Verificación de Instalación Correcta

### Checklist

- [ ] Archivos copiados correctamente
- [ ] Código añadido a `imagina-updater-client.php`
- [ ] Plugin reactivado sin errores
- [ ] Clase `Imagina_Updater_License_Manager` disponible

### Comandos de Verificación

```bash
# Verificar que los archivos existan
ls -la imagina-updater-client/includes/class-license-manager.php
ls -la imagina-updater-client/includes/class-license-crypto-client.php

# Verificar contenido del archivo principal
grep -n "class-license-manager" imagina-updater-client/imagina-updater-client.php
```

### Test Manual

Crea un plugin de prueba temporal (`test-license-check.php`):

```php
<?php
/**
 * Plugin Name: Test License Check
 */

add_action( 'admin_notices', function() {
    if ( ! class_exists( 'Imagina_Updater_License_Manager' ) ) {
        echo '<div class="notice notice-error"><p>❌ License Manager NO disponible</p></div>';
        return;
    }

    $manager = Imagina_Updater_License_Manager::get_instance();
    if ( ! $manager ) {
        echo '<div class="notice notice-error"><p>❌ No se pudo obtener instancia</p></div>';
        return;
    }

    echo '<div class="notice notice-success"><p>✅ License Manager funcionando correctamente</p></div>';
} );
```

Activa este plugin temporalmente, deberías ver el mensaje verde.

## 🔧 Troubleshooting

### Error: "Class 'Imagina_Updater_License_Manager' not found"

**Posibles causas y soluciones:**

1. **Los archivos no están en el lugar correcto**
   ```bash
   # Verificar ubicación
   ls -la imagina-updater-client/includes/ | grep license
   ```

2. **El require_once está mal escrito**
   ```php
   // Correcto (con comillas simples):
   require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-manager.php';

   // Incorrecto:
   require_once 'includes/class-license-manager.php';
   ```

3. **El hook se ejecuta muy tarde**
   ```php
   // Debe tener prioridad 5 (temprano)
   add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ), 5 );
   ```

### Error: "Class 'Imagina_License_Crypto' not found"

**Solución:**
- Verifica que `class-license-crypto-client.php` esté en `includes/`
- Verifica que el `require_once` de crypto esté ANTES del de license-manager

### Plugins Premium no Detectan el Gestor

**Solución:**
```php
// En imagina-updater-client.php
// Asegúrate que el hook sea prioridad 5
add_action( 'plugins_loaded', array( 'Imagina_Updater_License_Manager', 'init' ), 5 );

// En tu plugin premium, carga en prioridad 10 o superior
add_action( 'plugins_loaded', 'tu_plugin_init', 10 );
```

## 🔍 Debugging

### Ver Estado del Gestor

```php
add_action( 'admin_init', function() {
    if ( ! class_exists( 'Imagina_Updater_License_Manager' ) ) {
        error_log( 'License Manager NO cargado' );
        return;
    }

    $manager = Imagina_Updater_License_Manager::get_instance();

    // Ver stats
    $stats = $manager->get_stats();
    error_log( 'License Stats: ' . print_r( $stats, true ) );

    // Ver info de licencia
    $info = $manager->get_license_info();
    error_log( 'License Info: ' . print_r( $info, true ) );
} );
```

### Test de Verificación de Plugin

```php
add_action( 'admin_init', function() {
    $manager = Imagina_Updater_License_Manager::get_instance();

    // Test con un plugin real
    $result = $manager->verify_plugin_license( 'mi-plugin-premium' );

    error_log( 'Verify Result: ' . print_r( $result, true ) );
} );
```

## 📝 Notas

- Esta extensión es **opcional** - el plugin de actualizaciones seguirá funcionando sin ella
- Solo se requiere si tienes plugins premium que usan el sistema de licencias
- No afecta el funcionamiento normal del sistema de actualizaciones
- Los plugins premium que usen el SDK de licencias **requieren** esta extensión

## 🔄 Actualización

Si ya tienes una versión anterior instalada:

1. **Respalda** los archivos actuales
2. **Reemplaza** con los nuevos archivos
3. **Reactiva** el plugin cliente
4. **Verifica** que todo funcione

```bash
# Respaldar
cp imagina-updater-client/includes/class-license-manager.php /tmp/backup/

# Actualizar
cp /ruta/a/imagina-license-sdk/client-extension/class-license-manager.php imagina-updater-client/includes/

# Verificar
wp plugin deactivate imagina-updater-client
wp plugin activate imagina-updater-client
```

## 📚 Próximos Pasos

Después de instalar la extensión del cliente:

1. Instala la **extensión del servidor** (si no lo has hecho)
2. Lee [INTEGRATION.md](../docs/INTEGRATION.md) para integrar el SDK en tus plugins premium
3. Crea tu primer plugin premium con licenciamiento
