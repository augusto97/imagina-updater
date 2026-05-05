# Imagina Updater — Release branch

Esta rama (`release`) contiene los **ZIPs instalables** de los plugins del ecosistema Imagina Updater, listos para subir desde `Plugins → Añadir nuevo → Subir plugin` en WordPress.

> Esta rama es huérfana (no comparte historia con `main`) — solo contiene los ZIPs y este README. Se actualiza al cierre de cada fase de trabajo del repositorio.

## Contenido

| Plugin | Versión | Archivo |
|---|---|---|
| Imagina Updater Server | 1.0.0 | `imagina-updater-server-1.0.0.zip` |
| Imagina Updater Client | 1.0.0 | `imagina-updater-client-1.0.0.zip` |
| Imagina Updater License Extension | 5.3.0 | `imagina-updater-license-extension-5.3.0.zip` |

Los checksums SHA-256 están en `SHA256SUMS.txt` para verificar integridad después de descargar.

## Verificar integridad

```bash
sha256sum -c SHA256SUMS.txt
```

Salida esperada:

```
imagina-updater-server-1.0.0.zip: OK
imagina-updater-client-1.0.0.zip: OK
imagina-updater-license-extension-5.3.0.zip: OK
```

## Orden de instalación recomendado

1. **Servidor** (`imagina-updater-server-1.0.0.zip`) en la instalación de WordPress que actuará como hub central.
2. **License Extension** (`imagina-updater-license-extension-5.3.0.zip`) en la **misma** instalación del servidor (depende del plugin servidor, debe activarse después de él).
3. **Cliente** (`imagina-updater-client-1.0.0.zip`) en cada sitio cliente que vaya a recibir actualizaciones.

## Estado del corte

Esta versión refleja el estado del repositorio **al cierre de la Fase 0** (rama `chore/remove-legacy-sdk`):

- Eliminado el SDK legacy `imagina-license-sdk/` de la raíz del repo.
- Documentación útil del SDK legacy rescatada a `imagina-updater-license-extension/docs/`.
- Las funcionalidades de los 3 plugins se mantienen 100 % intactas (la fase 0 era exclusivamente limpieza del repo, no tocó código activo de los plugins).

## Versionado

- El versionado de cada ZIP refleja el header `Version:` del archivo principal de cada plugin en el momento de empaquetar. La Fase 1 corregirá la desincronización conocida entre la versión del header del cliente y su constante interna.
- Los ZIPs se reemplazan in-place al subirse nuevas versiones.

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
