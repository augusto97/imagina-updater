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

Esta versión refleja el estado del repositorio **post Fase 0/1/2/3 (todas mergeadas a main) + Fase 4 en `refactor/architecture`**:

- Fase 0–3: ver historial en `CLAUDE.md` (cleanup SDK legacy, fixes críticos, sweep PHPCS, robustez en streaming/cache/rate-limit).
- Fase 4.1 (parcial): añadido `composer.json` con `autoload.classmap` en cada uno de los 3 plugins. Sin renombres de clases ni cambios runtime; los entry points siguen cargando con `require_once`. La migración completa a `src/` con namespaces queda diferida hasta una sesión con WP local para validar end-to-end.
- Fase 4.2: añadido `imagina-updater-server/docs/HOOKS.md` con los 6 hooks `do_action` que expone el servidor (parámetros, ejemplos, consumidores). Cliente y license-extension no exponen hooks propios.
- Fase 4.3: `Imagina_License_SDK_Injector::rezip_plugin` reescrito a two-phase commit: ZIP nuevo se construye en `.new`, `rename()` atómico al final. Si falla a mitad (open/addFile/close/rename), el ZIP original queda intacto. Eliminado el sistema de `.backup` previo.
- Fase 4.4: diferida (Action Scheduler añadiría dependencia externa; decisión pendiente).

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
