# 📦 Instalación - Extensión del Servidor

Esta extensión añade endpoints de validación de licencias al plugin `imagina-updater-server`.

## 🚀 Instalación

### Paso 1: Copiar Archivos

```bash
cd /ruta/a/imagina-updater-server

# Copiar extensión de la API
cp /ruta/a/imagina-license-sdk/server-extension/class-license-api.php api/

# Copiar clase de criptografía
cp /ruta/a/imagina-license-sdk/server-extension/class-license-crypto-server.php includes/
```

### Paso 2: Modificar Plugin Principal

Editar `imagina-updater-server.php` y añadir al final (antes del último `}` o `?>`):

```php
/**
 * ==================================================
 * EXTENSIÓN DE LICENCIAS
 * ==================================================
 */

// Cargar clase de criptografía para licencias
require_once plugin_dir_path( __FILE__ ) . 'includes/class-license-crypto-server.php';

// Cargar API de licencias
require_once plugin_dir_path( __FILE__ ) . 'api/class-license-api.php';

// Registrar rutas de la API de licencias
add_action( 'rest_api_init', array( 'Imagina_Updater_License_API', 'register_routes' ) );
```

### Paso 3: Verificar Instalación

1. Ve al panel de WordPress del servidor
2. Desactiva y reactiva el plugin `imagina-updater-server`
3. Verifica que no haya errores

### Paso 4: Probar Endpoints

Puedes probar los endpoints desde la línea de comandos:

```bash
# Nota: Reemplaza los valores de ejemplo

# 1. Primero necesitas un activation_token válido del cliente

# 2. Probar endpoint de verificación de licencia
curl -X POST "https://tu-servidor.com/wp-json/imagina-updater/v1/license/verify" \
  -H "Authorization: Bearer iat_tu_activation_token_aqui" \
  -H "X-Site-Domain: tu-dominio.com" \
  -H "Content-Type: application/json" \
  -d '{"plugin_slug":"tu-plugin-premium"}'
```

Respuesta esperada:
```json
{
  "is_valid": true,
  "plugin_slug": "tu-plugin-premium",
  "plugin_name": "Tu Plugin Premium",
  "license_token": "eyJ...",
  "expires_at": 1234567890,
  "site_domain": "tu-dominio.com",
  "verified_at": "2025-01-15 10:30:00",
  "signature": "abc123..."
}
```

## ✅ Verificación de Instalación Correcta

### Checklist

- [ ] Archivos copiados correctamente
- [ ] Código añadido a `imagina-updater-server.php`
- [ ] Plugin reactivado sin errores
- [ ] Endpoints REST disponibles en `/wp-json/imagina-updater/v1/license/*`

### Comandos de Verificación

```bash
# Verificar que los archivos existan
ls -la imagina-updater-server/api/class-license-api.php
ls -la imagina-updater-server/includes/class-license-crypto-server.php

# Verificar que las rutas REST estén registradas
wp rest route list | grep "imagina-updater/v1/license"
```

Deberías ver:
```
imagina-updater/v1/license/verify
imagina-updater/v1/license/info
imagina-updater/v1/license/verify-batch
```

## 🔧 Troubleshooting

### Error: "Class 'Imagina_License_Crypto' not found"

**Solución:**
- Verifica que `class-license-crypto-server.php` esté en `includes/`
- Verifica que el `require_once` esté ANTES del `require_once` de la API

### Error: "Cannot modify header information"

**Solución:**
- Verifica que no haya espacios o saltos de línea antes de `<?php` en los archivos
- Verifica que no haya `?>` al final de los archivos

### Los endpoints devuelven 404

**Solución:**
1. Ve a `Ajustes > Enlaces permanentes`
2. Guarda (sin cambiar nada)
3. Esto regenera las reglas de rewrite

## 📝 Notas

- Esta extensión es **opcional** - el plugin de actualizaciones seguirá funcionando sin ella
- Solo se requiere si vas a usar el sistema de licencias para plugins premium
- No afecta el funcionamiento normal del sistema de actualizaciones
