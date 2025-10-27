# Imagina Updater

Sistema completo de gestión y distribución de actualizaciones personalizadas para plugins de WordPress. Permite gestionar actualizaciones de plugins propios desde un servidor central hacia múltiples sitios cliente.

## 📋 Descripción

Imagina Updater consta de dos plugins complementarios:

1. **Imagina Updater Server** - Plugin que se instala en tu sitio central para gestionar y distribuir actualizaciones
2. **Imagina Updater Client** - Plugin que se instala en los sitios cliente para recibir actualizaciones

## ✨ Características

### Plugin Servidor (Server)

- ✅ Subida de plugins en formato ZIP
- ✅ Gestión automática de versiones
- ✅ Sistema de API Keys para autenticación
- ✅ API REST segura para distribución
- ✅ Historial de versiones
- ✅ Registro de descargas y estadísticas
- ✅ Interfaz de administración intuitiva
- ✅ Validación automática de plugins
- ✅ Almacenamiento seguro de archivos

### Plugin Cliente (Client)

- ✅ Conexión segura al servidor central
- ✅ Selección de plugins a gestionar
- ✅ Integración nativa con el sistema de actualizaciones de WordPress
- ✅ Verificación automática de actualizaciones
- ✅ Actualización con un clic desde el panel de WordPress
- ✅ Validación de conexión en tiempo real

## 🚀 Instalación

### Paso 1: Instalar el Plugin Servidor

1. Copia la carpeta `imagina-updater-server` a `/wp-content/plugins/` de tu sitio central
2. Activa el plugin desde el panel de WordPress
3. Ve a **Imagina Updater** → **API Keys** y crea una nueva API Key para cada sitio cliente
4. Ve a **Imagina Updater** → **Plugins** y sube tus plugins

### Paso 2: Instalar el Plugin Cliente

1. Copia la carpeta `imagina-updater-client` a `/wp-content/plugins/` de cada sitio cliente
2. Activa el plugin desde el panel de WordPress
3. Ve a **Ajustes** → **Imagina Updater**
4. Configura:
   - **URL del Servidor**: URL completa de tu sitio central (ej: `https://miservidor.com`)
   - **API Key**: La API Key proporcionada por el servidor
5. Haz clic en **Guardar Configuración**
6. Selecciona los plugins que deseas gestionar desde el servidor central
7. Haz clic en **Guardar Selección**

## 📖 Uso

### Subir una Nueva Versión de un Plugin

1. En el sitio servidor, ve a **Imagina Updater** → **Plugins**
2. Sube el archivo ZIP de tu plugin
3. (Opcional) Agrega notas de la versión
4. Haz clic en **Subir Plugin**

El sistema detectará automáticamente:
- Si es un plugin nuevo o una actualización
- La versión del plugin
- Nombre, descripción y autor
- Validará que la nueva versión sea mayor a la actual

### Actualizar Plugins en Sitios Cliente

1. Las actualizaciones aparecerán automáticamente en **Plugins** → **Plugins Instalados**
2. Actualiza como cualquier otro plugin de WordPress:
   - Individualmente con "Actualizar ahora"
   - En lote seleccionando varios y usando "Actualizar"

## 🔒 Seguridad

- ✅ Autenticación mediante API Keys únicas
- ✅ Validación de nonces en todos los formularios
- ✅ Sanitización y validación de datos
- ✅ Almacenamiento seguro de archivos con `.htaccess`
- ✅ Verificación de permisos de usuario
- ✅ Registro de todas las descargas con IP y User Agent
- ✅ HTTPS recomendado para todas las comunicaciones

## 🛠️ Requisitos

- WordPress 5.8 o superior
- PHP 7.4 o superior
- Extensión ZipArchive de PHP
- HTTPS (recomendado)

## 📁 Estructura del Proyecto

```
imagina-updater/
├── imagina-updater-server/     # Plugin Servidor
│   ├── admin/                  # Interfaz de administración
│   │   ├── css/
│   │   └── views/
│   ├── api/                    # API REST
│   ├── includes/               # Clases principales
│   └── imagina-updater-server.php
│
└── imagina-updater-client/     # Plugin Cliente
    ├── admin/                  # Interfaz de administración
    │   ├── css/
    │   ├── js/
    │   └── views/
    ├── includes/               # Clases principales
    └── imagina-updater-client.php
```

## 🔌 API REST Endpoints

El plugin servidor expone los siguientes endpoints:

### `GET /wp-json/imagina-updater/v1/plugins`
Lista todos los plugins disponibles.

### `GET /wp-json/imagina-updater/v1/plugin/{slug}`
Obtiene información de un plugin específico.

### `POST /wp-json/imagina-updater/v1/check-updates`
Verifica actualizaciones para múltiples plugins.

**Parámetros:**
```json
{
  "plugins": {
    "plugin-slug": "1.0.0",
    "otro-plugin": "2.5.0"
  }
}
```

### `GET /wp-json/imagina-updater/v1/download/{slug}`
Descarga el archivo ZIP de un plugin.

### `GET /wp-json/imagina-updater/v1/validate`
Valida la API Key.

**Autenticación:**
Todas las peticiones requieren autenticación mediante:
- Header `Authorization: Bearer {api_key}`
- O header `X-API-Key: {api_key}`
- O parámetro de query `api_key={api_key}`

## 🤝 Contribuir

Las contribuciones son bienvenidas. Por favor:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Changelog

### Versión 1.0.0
- Lanzamiento inicial
- Plugin servidor con gestión de plugins y API Keys
- Plugin cliente con integración al sistema de actualizaciones de WordPress
- API REST completa
- Interfaces de administración
- Sistema de autenticación seguro

## 📄 Licencia

Este proyecto está licenciado bajo GPL v2 or later - ver el archivo LICENSE para más detalles.

## 👥 Autor

**Imagina**
- Website: https://imagina.dev

## 🐛 Reportar Bugs

Si encuentras algún bug, por favor crea un issue en GitHub con:
- Descripción detallada del problema
- Pasos para reproducirlo
- Versión de WordPress y PHP
- Logs de error si están disponibles

## ❓ Soporte

Para soporte y preguntas, abre un issue en GitHub o contacta al autor.

---

Hecho con ❤️ para la comunidad WordPress
