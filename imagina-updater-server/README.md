=== Imagina Updater Server ===
Contributors: imaginagroup
Tags: updates, plugins, distribution, license, api
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin servidor para gestionar y distribuir actualizaciones de plugins personalizados a múltiples sitios WordPress.

== Description ==

Este plugin convierte tu sitio WordPress en un servidor central de actualizaciones para plugins propios. Permite subir nuevas versiones de plugins y distribuirlas automáticamente a todos los sitios cliente conectados.

= Características =

* **Gestión de Plugins**: Sube y gestiona múltiples versiones de tus plugins
* **Sistema de API Keys**: Control de acceso para cada sitio cliente
* **Estadísticas**: Registro de descargas y uso por sitio
* **Seguridad**: Almacenamiento protegido y autenticación robusta
* **Historial**: Mantiene todas las versiones anteriores
* **API REST**: Endpoints seguros para distribución

== Installation ==

1. Copia la carpeta completa a `/wp-content/plugins/`
2. Activa el plugin desde el panel de WordPress
3. Ve a **Imagina Updater** en el menú de administración

== Frequently Asked Questions ==

= ¿Cómo creo API Keys? =

1. Ve a **Imagina Updater** → **API Keys**
2. Ingresa el nombre y URL del sitio cliente
3. Haz clic en **Crear API Key**
4. Copia la API Key generada (solo se muestra una vez)
5. Proporciona la API Key al administrador del sitio cliente

= ¿Cómo subo plugins? =

1. Ve a **Imagina Updater** → **Plugins**
2. Selecciona el archivo ZIP del plugin
3. (Opcional) Agrega notas de la versión
4. Haz clic en **Subir Plugin**

El sistema automáticamente:
* Extrae la información del plugin
* Valida la versión
* Actualiza o crea el registro
* Guarda el historial de versiones anteriores

== Changelog ==

= 1.0.0 =
* Versión inicial
* Gestión de plugins y versiones
* Sistema de API Keys
* API REST para distribución
* Sistema de activaciones por sitio

== Upgrade Notice ==

= 1.0.0 =
Versión inicial del plugin.

== API REST ==

= Base URL =

`https://tu-sitio.com/wp-json/imagina-updater/v1/`

= Endpoints =

* `GET /plugins` - Listar plugins
* `GET /plugin/{slug}` - Información de plugin
* `POST /check-updates` - Verificar actualizaciones
* `GET /download/{slug}` - Descargar plugin
* `GET /validate` - Validar API Key

= Autenticación =

Todas las peticiones requieren API Key mediante:
`Authorization: Bearer {api_key}`

== Requirements ==

* WordPress 5.8+
* PHP 7.4+
* Extensión ZipArchive
* Permisos de escritura en `/wp-content/uploads/`

== Security ==

* Archivos protegidos con `.htaccess`
* Validación de nonces
* Sanitización de datos
* API Keys únicas
* Registro de actividad
