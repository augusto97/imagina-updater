# Imagina Updater Client

Plugin cliente para recibir actualizaciones de plugins desde un servidor central Imagina Updater.

## Descripción

Este plugin se conecta a un servidor central Imagina Updater para recibir actualizaciones de plugins personalizados. Se integra perfectamente con el sistema nativo de actualizaciones de WordPress.

## Características

- 🔌 **Conexión Segura**: Comunicación autenticada con el servidor central
- ✅ **Selección de Plugins**: Elige qué plugins gestionar desde el servidor
- 🔄 **Actualizaciones Automáticas**: Integración nativa con WordPress
- 📊 **Estado en Tiempo Real**: Visualiza el estado de cada plugin
- 🎯 **Simple y Directo**: Configuración en minutos

## Instalación

1. Copia la carpeta completa a `/wp-content/plugins/`
2. Activa el plugin desde el panel de WordPress
3. Ve a **Ajustes** → **Imagina Updater**

## Configuración

### Paso 1: Conectar al Servidor

1. Ve a **Ajustes** → **Imagina Updater**
2. Ingresa la **URL del Servidor** (ej: `https://miservidor.com`)
3. Ingresa la **API Key** proporcionada por el administrador del servidor
4. Haz clic en **Guardar Configuración**
5. (Opcional) Haz clic en **Probar Conexión** para verificar

### Paso 2: Seleccionar Plugins

1. En la misma página, verás la lista de plugins disponibles en el servidor
2. Marca los plugins que deseas gestionar desde el servidor central
3. Haz clic en **Guardar Selección**

## Uso

Una vez configurado, el sistema funciona automáticamente:

1. WordPress verificará actualizaciones periódicamente
2. Las actualizaciones aparecerán en **Plugins** → **Plugins Instalados**
3. Actualiza normalmente usando el botón "Actualizar ahora"
4. También puedes actualizar en lote seleccionando varios plugins

## Estados de Plugins

El panel muestra diferentes estados:

- 🟢 **Actualizado**: El plugin está en la última versión
- 🟡 **Actualización disponible**: Hay una nueva versión en el servidor
- ⚪ **No instalado**: El plugin está en el servidor pero no instalado localmente
- 🔵 **Habilitado**: El plugin está configurado para recibir actualizaciones

## Preguntas Frecuentes

### ¿Puedo actualizar solo algunos plugins?

Sí, solo marca los plugins que deseas gestionar desde el servidor central. Los demás seguirán funcionando normalmente.

### ¿Qué pasa si desactivo el plugin?

Las actualizaciones desde el servidor dejarán de funcionar, pero tus plugins instalados seguirán funcionando normalmente.

### ¿Puedo cambiar de servidor?

Sí, simplemente actualiza la URL del servidor y la API Key en la configuración.

### ¿Es seguro?

Sí, todas las comunicaciones están autenticadas con API Key y se recomienda usar HTTPS.

## Requisitos

- WordPress 5.8+
- PHP 7.4+
- Conexión a internet
- API Key válida de un servidor Imagina Updater

## Solución de Problemas

### No aparecen actualizaciones

1. Verifica que la conexión al servidor sea exitosa (**Probar Conexión**)
2. Asegúrate de haber marcado los plugins en la configuración
3. Ve a **Plugins** → **Plugins Instalados** y haz clic en "Buscar actualizaciones"
4. Verifica que la versión en el servidor sea mayor a la instalada

### Error de conexión

1. Verifica que la URL del servidor sea correcta (sin `/wp-json` al final)
2. Asegúrate de que la API Key sea válida
3. Verifica que el servidor esté accesible
4. Revisa los logs de error de WordPress

### El plugin no aparece en la lista

1. Asegúrate de que el plugin esté subido en el servidor
2. Verifica que el slug del plugin coincida
3. Refresca la página de configuración

## Seguridad

- ✅ Autenticación con API Key
- ✅ Validación de nonces
- ✅ Sanitización de datos
- ✅ Comunicación HTTPS recomendada

## Soporte

Para soporte y preguntas:
- Crea un issue en GitHub
- Contacta al administrador de tu servidor central

## Licencia

GPL v2 or later
