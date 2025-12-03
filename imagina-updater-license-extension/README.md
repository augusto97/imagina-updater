# Imagina Updater License Extension

Extensión para [Imagina Updater Server](https://github.com/augusto97/imagina-updater) que agrega sistema completo de gestión de licencias para plugins premium.

## ¿Qué hace esta extensión?

Esta extensión te permite:

- **Marcar plugins como premium**: Decide qué plugins requieren licencia para funcionar
- **Inyección automática de SDK**: El SDK de licencias se inyecta automáticamente en plugins premium
- **Control remoto de licencias**: Activa/desactiva licencias desde el servidor
- **Verificación de integridad**: Sistema de 7 capas de seguridad para evitar hackeos

## Instalación

1. **Asegúrate de tener instalado [Imagina Updater Server](https://github.com/augusto97/imagina-updater)**
   - Esta extensión requiere que el plugin base esté instalado y activo

2. **Instala esta extensión**:
   ```bash
   cd wp-content/plugins
   # Copia la carpeta imagina-updater-license-extension aquí
   ```

3. **Activa el plugin** desde WordPress:
   - Ve a Plugins → Plugins instalados
   - Busca "Imagina Updater License Extension"
   - Haz clic en "Activar"

4. **La extensión agregará automáticamente**:
   - Campo `is_premium` a la tabla de plugins
   - Columna "Premium" en la lista de plugins
   - Checkbox en el formulario de subida de plugins
   - Endpoints de API para validación de licencias

## Uso

### Marcar un plugin como premium al subirlo

1. Ve a **Imagina Updater → Plugins**
2. En el formulario de subida, marca el checkbox **"Plugin Premium"**
3. Sube el plugin normalmente
4. El SDK de licencias se inyectará automáticamente si el plugin no lo tiene

### Marcar un plugin existente como premium

1. Ve a **Imagina Updater → Plugins**
2. En la columna **"Premium"**, marca el checkbox del plugin que quieres convertir en premium
3. En la próxima actualización del plugin, el SDK se inyectará automáticamente

### Desmarcar un plugin como premium

1. Ve a **Imagina Updater → Plugins**
2. En la columna **"Premium"**, desmarca el checkbox
3. El plugin seguirá funcionando normalmente sin requerir licencia

## Características del sistema de licencias

### 7 Capas de seguridad

1. **Validación remota**: Verifica licencias contra el servidor en cada carga
2. **Heartbeat**: Verificación en segundo plano cada 12 horas
3. **Firma HMAC-SHA256**: Tokens firmados imposibles de falsificar
4. **JWT con expiración**: Tokens de 24 horas de duración
5. **Verificación de integridad**: Detecta modificaciones del código
6. **Ofuscación de código**: Dificulta ingeniería inversa
7. **Múltiples puntos de verificación**: Validación en init, admin_init y otros hooks

### Funcionamiento

- **Gracia period**: 3 días por defecto si no hay conexión al servidor
- **Cache inteligente**: 6-12 horas para reducir llamadas al servidor
- **Sin impacto en rendimiento**: Verificaciones en segundo plano
- **Compatible con multisitio**: Funciona en instalaciones multisite

## Endpoints de API

La extensión agrega estos endpoints:

- `POST /wp-json/imagina-license/v1/validate` - Validar una licencia
- `POST /wp-json/imagina-license/v1/activate` - Activar una licencia en un sitio
- `POST /wp-json/imagina-license/v1/deactivate` - Desactivar una licencia
- `POST /wp-json/imagina-license/v1/heartbeat` - Verificación periódica

## Integración con el cliente

El plugin cliente (Imagina Updater Client) ya incluye soporte para el sistema de licencias. No necesitas hacer nada adicional en el lado del cliente.

## Desinstalación

Si desactivas esta extensión, los plugins premium seguirán funcionando pero sin verificación de licencias. El campo `is_premium` permanecerá en la base de datos.

Para eliminarlo completamente:
1. Desactiva la extensión
2. Elimina la carpeta `wp-content/plugins/imagina-updater-license-extension`
3. (Opcional) Ejecuta esta query SQL para eliminar el campo:
   ```sql
   ALTER TABLE wp_imagina_updater_plugins DROP COLUMN is_premium;
   ```

## Soporte

Para soporte o reportar problemas, abre un issue en el repositorio principal:
https://github.com/augusto97/imagina-updater/issues

## Licencia

GPL v2 or later
