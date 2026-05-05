# Imagina Updater — Release branch

Esta rama (`release`) contiene los **ZIPs instalables** de los plugins del ecosistema Imagina Updater, listos para subir desde `Plugins → Añadir nuevo → Subir plugin` en WordPress.

> Esta rama es huérfana (no comparte historia con `main`) — solo contiene los ZIPs y este README. Se actualiza al cierre de cada fase de trabajo del repositorio.

## Contenido

| Plugin | Versión | Archivo |
|---|---|---|
| Imagina Updater Server | 1.0.0 | `imagina-updater-server-1.0.0.zip` |
| Imagina Updater Client | 1.0.2 | `imagina-updater-client-1.0.2.zip` |
| Imagina Updater License Extension | 5.3.0 | `imagina-updater-license-extension-5.3.0.zip` |

Los checksums SHA-256 están en `SHA256SUMS.txt` para verificar integridad después de descargar.

## Verificar integridad

```bash
sha256sum -c SHA256SUMS.txt
```

Salida esperada:

```
imagina-updater-server-1.0.0.zip: OK
imagina-updater-client-1.0.2.zip: OK
imagina-updater-license-extension-5.3.0.zip: OK
```

## Orden de instalación recomendado

1. **Servidor** (`imagina-updater-server-1.0.0.zip`) en la instalación de WordPress que actuará como hub central.
2. **License Extension** (`imagina-updater-license-extension-5.3.0.zip`) en la **misma** instalación del servidor (depende del plugin servidor, debe activarse después de él).
3. **Cliente** (`imagina-updater-client-1.0.2.zip`) en cada sitio cliente que vaya a recibir actualizaciones.

## Estado del corte

Esta versión refleja el estado del repositorio **post Fase 0 + Fase 1 (ambas mergeadas a main) + Fase 2 en `chore/phpcs-sweep`**:

- Fase 0: eliminado el SDK legacy `imagina-license-sdk/` de la raíz del repo. Documentación útil rescatada a `imagina-updater-license-extension/docs/`. Cleanups derivados en `diagnostico-licencias.php` y en el header PHPDoc del antiguo `loader.php`.
- Fase 1.1: eliminado `imagina-updater-license-extension/includes/license-sdk/` completo (4 archivos huérfanos). Resuelve el riesgo latente de `Cannot redeclare class Imagina_License_Crypto`. Sección 6 del diagnóstico reescrita.
- Fase 1.2: cliente sincronizado (header + constante) a `1.0.2`. Server y license-extension verificados consistentes.
- Fase 1.3: hardening de `$_FILES['plugin_file']` en el handler de upload del servidor (validación de `is_array`, `is_uploaded_file` en capa admin, `wp_unslash` en claves user-controlled).
- Fase 1.4: sweep de `wp_unslash()` en todos los reads `$_GET`/`$_POST` en admin de los 3 plugins; `phpcs:ignore NonceVerification.Recommended` en lecturas read-only de navegación.
- Fase 2.1 + 2.2: cabecera `phpcs:disable WordPress.DB.DirectDatabaseQuery.{NoCaching,DirectQuery}, WordPress.DB.PreparedSQL.InterpolatedNotPrepared` con justificación, a nivel de archivo, en los 12 archivos que usan `$wpdb` (en lugar de ~190 anotaciones inline).
- Fase 2.3: cierre de la última lectura `$_GET` read-only en `render_activations_page` con `phpcs:ignore NonceVerification.Recommended`.

## Versionado

- El versionado de cada ZIP refleja el header `Version:` del archivo principal de cada plugin en el momento de empaquetar.
- Los ZIPs se reemplazan in-place al subirse nuevas versiones (excepto cuando el nombre del archivo cambia, como ocurrió con el cliente al pasar de 1.0.0 a 1.0.2).

## Cómo se generaron

Desde la rama `chore/remove-legacy-sdk` (post-Fase 0):

```bash
cd /ruta/al/repo
zip -rq /tmp/imagina-updater-server-1.0.0.zip imagina-updater-server -x "*.DS_Store" "*/.git/*"
zip -rq /tmp/imagina-updater-client-1.0.0.zip imagina-updater-client -x "*.DS_Store" "*/.git/*"
zip -rq /tmp/imagina-updater-license-extension-5.3.0.zip imagina-updater-license-extension -x "*.DS_Store" "*/.git/*"
```

## Referencias

- Repositorio principal: rama `main`.
- Documento de guía: `CLAUDE.md` en `main`.
- Trabajo en curso: rama `chore/remove-legacy-sdk` (Fase 0).
