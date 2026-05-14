# Imagina Updater — Release branch

Esta rama (`release`) contiene los **ZIPs instalables** de los plugins del ecosistema Imagina Updater, listos para subir desde `Plugins → Añadir nuevo → Subir plugin` en WordPress.

> Esta rama es huérfana (no comparte historia con `main`) — solo contiene los ZIPs y este README. Se actualiza al cierre de cada fase de trabajo del repositorio.

## Contenido

| Plugin | Versión | Archivo |
|---|---|---|
| Imagina Updater Server | 1.1.3 | `imagina-updater-server-1.1.3.zip` |
| Imagina Updater Client | 1.0.2 | `imagina-updater-client-1.0.2.zip` |
| Imagina Updater License Extension | 5.3.1 | `imagina-updater-license-extension-5.3.1.zip` |

Los checksums SHA-256 están en `SHA256SUMS.txt` para verificar integridad después de descargar.

## Verificar integridad

```bash
sha256sum -c SHA256SUMS.txt
```

Salida esperada:

```
imagina-updater-server-1.1.3.zip: OK
imagina-updater-client-1.0.2.zip: OK
imagina-updater-license-extension-5.3.1.zip: OK
```

## Orden de instalación recomendado

1. **Servidor** (`imagina-updater-server-1.1.3.zip`) en la instalación de WordPress que actuará como hub central.
2. **License Extension** (`imagina-updater-license-extension-5.3.1.zip`) en la **misma** instalación del servidor (depende del plugin servidor, debe activarse después de él).
3. **Cliente** (`imagina-updater-client-1.0.2.zip`) en cada sitio cliente que vaya a recibir actualizaciones.

## Estado del corte

Esta versión refleja el estado del repositorio **post Fase 0/1/2/3/4/5.0–5.7 (todas mergeadas a `main`)**. El servidor incluye el rediseño completo del admin como SPA React + TypeScript + Vite (Fase 5), construido y empaquetado dentro del ZIP en `imagina-updater-server/assets/dist/`.

- **Fase 0–3** — ver historial en `CLAUDE.md` (cleanup SDK legacy, fixes críticos, sweep PHPCS, robustez en streaming/cache/rate-limit).
- **Fase 4** — arquitectura: composer.json con classmap autoload en los 3 plugins; `docs/HOOKS.md` con los 6 `do_action` del servidor; `rezip_plugin` two-phase commit atómico. 4.4 (Action Scheduler) diferida.
- **Fase 5** — admin SPA del servidor: 7 submenús **"(nuevo)"** conviven con los legacy mientras se valida en producción.
  - 5.0 Setup técnico (Vite multi-entry, Tailwind con prefijo `iaud-`, shadcn primitives, REST namespace `imagina-updater/admin/v1`).
  - 5.1 Dashboard (4 KPIs + chart SVG 30d + tablas + quick actions).
  - 5.2 API Keys (CRUD + drawer + banner con clave en claro una sola vez).
  - 5.3 Plugins (upload drag-and-drop, premium toggle, re-inyección, versions).
  - 5.4 Plugin Groups (CRUD con conteo de API keys vinculadas).
  - 5.5 Activations (tabla con filtros estado/api-key/dominio + desactivar).
  - 5.6 Logs (visor parseado + filtros por nivel + descarga streaming).
  - 5.7 Configuración (tabs General / Logging / Mantenimiento).

**Backend nuevo:** ~30 endpoints bajo `wp-json/imagina-updater/admin/v1/` con permission `manage_options` + nonce `wp_rest`. La API pública `wp-json/imagina-updater/v1/` queda intacta (no rompe sitios cliente en producción).

**Frontend (servidor):** ~80 KB gzip por pantalla en media (chunk compartido de TanStack Query 65 KB + entry 2–6 KB + CSS Tailwind 25 KB). Bien dentro del budget de 250 KB / pantalla.

## Versionado

- El versionado de cada ZIP refleja el header `Version:` del archivo principal de cada plugin en el momento de empaquetar.
- Los ZIPs se reemplazan in-place al subirse nuevas versiones (excepto cuando el nombre del archivo cambia, como ocurrió con el cliente al pasar de 1.0.0 a 1.0.2).

## Cómo se generaron

Desde `main` (post Fase 5):

```bash
cd /ruta/al/repo
git checkout main && git pull

# 1. Construir los bundles del admin SPA del servidor
cd imagina-updater-server/assets/admin
npm install
npm run build           # genera assets/dist/{dashboard,api-keys,plugins,plugin-groups,
                        # activations,logs,settings}.{js,asset.php} + iaud.css

# 2. Volver a la raíz y empaquetar (excluyendo dev-only del SPA)
cd ../../..
zip -rq /tmp/imagina-updater-server-1.0.0.zip imagina-updater-server \
  -x "imagina-updater-server/assets/admin/node_modules/*" \
     "imagina-updater-server/assets/admin/src/*" \
     "imagina-updater-server/assets/admin/index.html" \
     "imagina-updater-server/assets/admin/package*.json" \
     "imagina-updater-server/assets/admin/*.config.*" \
     "imagina-updater-server/assets/admin/tsconfig*.json" \
     "imagina-updater-server/assets/admin/components.json" \
     "imagina-updater-server/assets/admin/README.md" \
     "imagina-updater-server/assets/admin/.gitignore" \
     "*.DS_Store" "*/.git/*"

zip -rq /tmp/imagina-updater-client-1.0.2.zip imagina-updater-client \
  -x "*.DS_Store" "*/.git/*"

zip -rq /tmp/imagina-updater-license-extension-5.3.0.zip imagina-updater-license-extension \
  -x "*.DS_Store" "*/.git/*"

sha256sum /tmp/imagina-updater-*.zip > /tmp/SHA256SUMS.txt
```

`assets/dist/` SÍ va dentro del ZIP del servidor (es lo que WordPress carga en producción). Los archivos de dev del SPA (node_modules, src/, configs) quedan fuera para no inflar el ZIP — solo importan al desarrollar.

## Referencias

- Repositorio principal: rama `main`.
- Documento de guía: `CLAUDE.md` en `main`.
- Workflow de desarrollo: cada fase tiene su propio merge commit en `main` (`git log --merges main` los lista).
