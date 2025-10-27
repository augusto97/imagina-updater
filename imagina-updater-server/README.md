# Imagina Updater Server

Plugin servidor para gestionar y distribuir actualizaciones de plugins personalizados a múltiples sitios WordPress.

## Descripción

Este plugin convierte tu sitio WordPress en un servidor central de actualizaciones para plugins propios. Permite subir nuevas versiones de plugins y distribuirlas automáticamente a todos los sitios cliente conectados.

## Características

- 📦 **Gestión de Plugins**: Sube y gestiona múltiples versiones de tus plugins
- 🔑 **Sistema de API Keys**: Control de acceso para cada sitio cliente
- 📊 **Estadísticas**: Registro de descargas y uso por sitio
- 🔒 **Seguridad**: Almacenamiento protegido y autenticación robusta
- 📝 **Historial**: Mantiene todas las versiones anteriores
- 🚀 **API REST**: Endpoints seguros para distribución

## Instalación

1. Copia la carpeta completa a `/wp-content/plugins/`
2. Activa el plugin desde el panel de WordPress
3. Ve a **Imagina Updater** en el menú de administración

## Uso

### Crear API Keys

1. Ve a **Imagina Updater** → **API Keys**
2. Ingresa el nombre y URL del sitio cliente
3. Haz clic en **Crear API Key**
4. Copia la API Key generada (solo se muestra una vez)
5. Proporciona la API Key al administrador del sitio cliente

### Subir Plugins

1. Ve a **Imagina Updater** → **Plugins**
2. Selecciona el archivo ZIP del plugin
3. (Opcional) Agrega notas de la versión
4. Haz clic en **Subir Plugin**

El sistema automáticamente:
- Extrae la información del plugin
- Valida la versión
- Actualiza o crea el registro
- Guarda el historial de versiones anteriores

### Gestionar Plugins

- **Ver plugins**: Lista todos los plugins gestionados
- **Descargar**: Descarga cualquier versión
- **Eliminar**: Elimina un plugin y todas sus versiones

## Estructura de Base de Datos

El plugin crea 4 tablas:

- `{prefix}_imagina_updater_api_keys`: Gestión de API Keys
- `{prefix}_imagina_updater_plugins`: Plugins y versiones actuales
- `{prefix}_imagina_updater_versions`: Historial de versiones
- `{prefix}_imagina_updater_downloads`: Log de descargas

## API REST

### Base URL
```
https://tu-sitio.com/wp-json/imagina-updater/v1/
```

### Endpoints

#### Listar Plugins
```
GET /plugins
```

#### Información de Plugin
```
GET /plugin/{slug}
```

#### Verificar Actualizaciones
```
POST /check-updates
Body: {"plugins": {"slug": "version"}}
```

#### Descargar Plugin
```
GET /download/{slug}
```

#### Validar API Key
```
GET /validate
```

### Autenticación

Todas las peticiones requieren API Key mediante:
```
Authorization: Bearer {api_key}
```

## Requisitos

- WordPress 5.8+
- PHP 7.4+
- Extensión ZipArchive
- Permisos de escritura en `/wp-content/uploads/`

## Seguridad

- ✅ Archivos protegidos con `.htaccess`
- ✅ Validación de nonces
- ✅ Sanitización de datos
- ✅ API Keys únicas
- ✅ Registro de actividad

## Licencia

GPL v2 or later
