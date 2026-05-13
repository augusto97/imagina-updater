# CLAUDE.md — Imagina Updater

> Este documento es la guía de trabajo para Claude Code sobre el repositorio `imagina-updater`. Léelo completo antes de cualquier intervención. Cuando trabajes en una fase específica, vuelve a la sección correspondiente.

---

## 0. Cómo usar este documento

- **Antes de tocar código**: lee las secciones 1, 2, 3 y 4. Sin excepciones.
- **Cuando vayas a trabajar en una fase**: lee la sección correspondiente del **Plan de trabajo (sección 6)** y vuelve a las **Reglas críticas (sección 4)**.
- **Si una indicación de este documento entra en conflicto con una petición puntual del usuario**: detente, expón el conflicto y espera confirmación. No improvises.
- **Idioma**: comentarios de código y mensajes de UI en español; nombres de variables, clases y archivos en inglés. Mensajes de commit en inglés (Conventional Commits).

---

## 1. Contexto del proyecto

Imagina Updater es un ecosistema de WordPress para distribuir plugins propios desde un servidor central a múltiples sitios cliente, con licenciamiento integrado.

### 1.1 Componentes del repositorio (estado objetivo, después de la Fase 0)

```
imagina-updater/
├── imagina-updater-server/              ← Plugin SERVIDOR
├── imagina-updater-client/              ← Plugin CLIENTE
├── imagina-updater-license-extension/   ← Extensión de LICENCIAS (v5.3.0+)
├── diagnostico-licencias.php            ← Script de diagnóstico
└── CLAUDE.md                            ← Este documento
```

> ⚠️ **Importante**: la carpeta `imagina-license-sdk/` debe ELIMINARSE en la Fase 0. Si la ves, no construyas nada sobre ella.

### 1.2 Responsabilidades

| Componente | Responsabilidad |
|---|---|
| `imagina-updater-server` | API REST, gestión de plugins, API keys, activaciones, grupos, logs |
| `imagina-updater-client` | Conectarse al servidor, integrar con sistema nativo de updates de WP, gestor de licencias para plugins premium |
| `imagina-updater-license-extension` | Marca plugins como Premium, inyecta código de protección al subir el ZIP, gestiona License Keys y activations de licencias |

### 1.3 Modelo de autenticación de DOS NIVELES

Esto es fundamental, **no se debe romper bajo ningún concepto**:

1. **API Key (`ius_…`)** — formato: `ius_` + 60 caracteres hex. Se usa SOLO para:
   - Endpoint `/activate` (intercambia API key por activation_token)
   - Endpoint `/validate` (validar que la API key sigue activa)

2. **Activation Token (`iat_…`)** — formato: `iat_` + 60 caracteres hex. Se usa para TODO lo demás:
   - `/plugins`, `/plugin/{slug}`, `/check-updates`, `/download/{slug}`, `/deactivate-self`
   - Siempre acompañado del header **`X-Site-Domain`** (debe coincidir con el dominio registrado al activar; si no, 403)

### 1.4 Tablas de base de datos

Servidor:
- `wp_imagina_updater_api_keys`
- `wp_imagina_updater_plugins` (incluye `slug_override` y, si la extensión está activa, `is_premium`)
- `wp_imagina_updater_versions`
- `wp_imagina_updater_downloads`
- `wp_imagina_updater_plugin_groups`
- `wp_imagina_updater_plugin_group_items`
- `wp_imagina_updater_activations`

License extension:
- `wp_imagina_license_keys`
- `wp_imagina_license_activations`

### 1.5 Hooks extensibles del servidor (NO romper)

```
imagina_updater_after_upload_form
imagina_updater_after_move_plugin_file   ← aquí se inyecta protección
imagina_updater_after_upload_plugin
imagina_updater_plugins_table_header
imagina_updater_plugins_table_row
```

### 1.6 Flujos críticos a respetar

**Activación de un sitio:**
```
Cliente → POST /activate {api_key, site_domain}
       ← {activation_token: iat_…, activations_used, max_activations}
       Cliente guarda token; desde ahora usa Bearer iat_… + X-Site-Domain
```

**Check de updates:**
```
Transient cache (12h) → si miss → POST /check-updates con activation_token
                    → set_transient resultado
                    → WP transient `update_plugins` se modifica
                    → WP descarga vía http_request_args (que inyecta auth headers)
```

**Upload con protección:**
```
Admin sube ZIP → upload_plugin() valida MIME, extract_plugin_info
              → wp_filesystem copia a uploads/imagina-updater-plugins/
              → do_action('imagina_updater_after_move_plugin_file')
                   ↓ (si is_premium)
                   SDK_Injector: extract → inject → rezip → backup
              → $wpdb->insert/update con TRANSACTION
              → do_action('imagina_updater_after_upload_plugin')
```

---

## 2. Stack tecnológico

### 2.1 Backend (PHP/WordPress) — existente, a refactorizar gradualmente

- **WordPress** 5.8+
- **PHP** 7.4+ (compatible con shared hosting; nada que requiera extensiones raras)
- **Arquitectura objetivo**: clases con namespaces y autoload PSR-4 (Fase 4)
- **Persistencia**: tablas MySQL custom con `dbDelta`. **No abusar de `wp_options` ni meta tables** para datos masivos
- **REST**: `register_rest_route` nativo, permission callbacks con capabilities y nonces
- **Tareas en background**: Action Scheduler cuando aplique (heartbeat, limpiezas)
- **Hooks**: usar los de WP/WooCommerce en lugar de modificar core
- **Compatibilidad**: shared hosting, OpenLiteSpeed (cuando haya `.htaccess` involucrado)

### 2.2 Frontend (admin SPA) — nuevo, para el rediseño (Fase 5)

- **React** 18
- **TypeScript** (strict mode)
- **Vite** como bundler
- **Tailwind CSS** con prefijo `iaud-` (de "imagina-updater") para evitar colisiones con WP admin y otros plugins
- **shadcn/ui** sobre Tailwind, con prefijo aplicado en la config
- **TanStack Table** v8 para todas las tablas de datos (plugins, api keys, activations, logs)
- **Lucide React** para iconos
- **i18n**: `wp_set_script_translations` con dominio `imagina-updater-server` (servidor) e `imagina-updater-client` (cliente)
- **Estado**: hooks nativos de React + `@tanstack/react-query` para fetching de la REST API. Nada de Redux ni Zustand salvo justificación explícita

### 2.3 Calidad y rendimiento

- **Bundle objetivo**: < 250 KB gzip por pantalla (split por ruta)
- **Code splitting**: una entry por sección admin (dashboard, plugins, api-keys, activations, logs, settings, plugin-groups)
- **Encolado condicional**: el bundle SOLO se carga en las pantallas del plugin. Nunca en todo `wp-admin`
- **Sin frameworks pesados** adicionales (no Material UI, no Ant Design, no Chakra, no Mantine)
- **Sin dependencias del servidor** (Node solo en build; en producción es CSS/JS estático)

---

## 3. Convenciones de código

### 3.1 PHP

**Seguridad** (no negociable):
- Toda entrada `$_GET`/`$_POST`/`$_REQUEST`/`$_COOKIE`/`$_SERVER` se procesa con `wp_unslash()` ANTES de sanitizar
- Sanitización por tipo: `sanitize_text_field`, `sanitize_key`, `absint`, `esc_url_raw`, `sanitize_email`, `sanitize_textarea_field`
- Toda salida HTML escapada: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` cuando aplique
- Toda query SQL con `$wpdb->prepare()`. Los nombres de tabla pueden interpolarse pero deben venir SIEMPRE de `$wpdb->prefix . 'string_literal_constante'`, nunca de input
- Nonces obligatorios en formularios admin (`wp_nonce_field` + `check_admin_referer`) y en endpoints REST (`X-WP-Nonce` + `wp_verify_nonce`)
- Capabilities: `manage_options` por defecto en admin; los endpoints REST deben tener su propio permission_callback

**Estructura**:
- Una clase por archivo, archivos prefijados `class-`
- Nombres de clase con prefijo del componente: `Imagina_Updater_Server_*`, `Imagina_Updater_Client_*`, `Imagina_License_*`
- Métodos `private` salvo que la API pública lo requiera
- Constantes en MAYÚSCULAS dentro de clases

**Headers de archivos PHP** (todos):
```php
<?php
/**
 * Breve descripción de qué hace este archivo.
 *
 * @package Imagina_Updater_Server
 */

if (!defined('ABSPATH')) {
    exit;
}
```

### 3.2 Frontend

**Estructura de carpetas** (por plugin que tenga admin SPA):

```
imagina-updater-server/
├── assets/
│   ├── admin/                    ← código fuente
│   │   ├── src/
│   │   │   ├── main.tsx          ← entry global (si aplica)
│   │   │   ├── pages/
│   │   │   │   ├── Dashboard/
│   │   │   │   ├── Plugins/
│   │   │   │   ├── ApiKeys/
│   │   │   │   ├── PluginGroups/
│   │   │   │   ├── Activations/
│   │   │   │   ├── Logs/
│   │   │   │   └── Settings/
│   │   │   ├── components/       ← compartidos
│   │   │   │   └── ui/           ← shadcn components
│   │   │   ├── hooks/
│   │   │   ├── lib/              ← api client, utils, i18n
│   │   │   ├── types/
│   │   │   └── styles/
│   │   │       └── globals.css   ← Tailwind base + variables
│   │   ├── tailwind.config.ts
│   │   ├── vite.config.ts
│   │   ├── tsconfig.json
│   │   └── package.json
│   └── dist/                     ← build output (gitignored o no, decisión del equipo)
└── admin/
    └── views/
        ├── dashboard.php         ← contenedor mínimo: <div id="iaud-dashboard"></div>
        ├── plugins.php
        └── …
```

**Naming**:
- Componentes: `PascalCase.tsx`
- Hooks: `useFooBar.ts`
- Utils: `kebab-case.ts`
- Tipos: `kebab-case.types.ts` o exportados desde `types/index.ts`

**Tailwind con prefijo**:
- Toda clase utility: `iaud-flex iaud-gap-4 iaud-p-6`
- Clases de shadcn ya vienen con el prefijo aplicado por la config
- Ver `tailwind.config.ts`: `prefix: 'iaud-'`

**Carga de bundle (PHP)**:
```php
public function enqueue_scripts($hook) {
    // Solo cargar en pantallas del plugin
    if (strpos($hook, 'imagina-updater') === false) {
        return;
    }

    $asset_file = include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'assets/dist/page-name.asset.php';

    wp_enqueue_script(
        'iaud-page-name',
        IMAGINA_UPDATER_SERVER_PLUGIN_URL . 'assets/dist/page-name.js',
        $asset_file['dependencies'],
        $asset_file['version'],
        true
    );

    wp_enqueue_style(
        'iaud-page-name',
        IMAGINA_UPDATER_SERVER_PLUGIN_URL . 'assets/dist/page-name.css',
        [],
        $asset_file['version']
    );

    wp_set_script_translations('iaud-page-name', 'imagina-updater-server');

    // Inyectar config inicial (URL del REST, nonce, datos del usuario, etc.)
    wp_localize_script('iaud-page-name', 'iaudConfig', [
        'apiUrl' => esc_url_raw(rest_url('imagina-updater/v1/')),
        'adminUrl' => esc_url_raw(rest_url('imagina-updater/admin/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'currentUser' => wp_get_current_user()->display_name,
    ]);
}
```

### 3.3 Commits

Conventional Commits, en inglés:

```
feat(server): add custom admin REST namespace
fix(license-extension): prevent Imagina_License_Crypto duplicate declaration
chore(client): align plugin header version with constant
refactor(server): wrap admin actions with wp_unslash before sanitization
docs(claude): add Phase 5 design tokens
```

Tipos válidos: `feat`, `fix`, `chore`, `refactor`, `docs`, `style`, `test`, `perf`, `build`.

Scope sugerido: `server`, `client`, `license-extension`, `admin-ui`, `claude`, `repo`.

---

## 4. Reglas críticas — NO HACER

> Estas reglas existen porque romperlas causa fallos en producción, rompe sitios cliente o introduce vulnerabilidades. Si dudas, pregunta al usuario antes de proceder.

1. **NO modificar el modelo de doble token** (API key vs activation_token). Cualquier cambio en `/activate`, validación de tokens o headers `X-Site-Domain` requiere aprobación explícita.
2. **NO eliminar ni renombrar los hooks** listados en sección 1.5. La extensión de licencias depende de ellos.
3. **NO cargar el bundle de React en todo `wp-admin`**. Solo en las pantallas del plugin. Verificar con `$hook` siempre.
4. **NO usar `wp_options` para datos masivos** (logs, activaciones, descargas). Esos van a tablas custom.
5. **NO romper retrocompatibilidad de la REST API** (`/imagina-updater/v1/`). Sitios cliente en producción dependen de los endpoints actuales. Si necesitas cambiar la firma, crea `v2` y deja `v1` funcionando.
6. **NO inyectar dependencias frontend pesadas**. Si hay duda sobre añadir una librería, justificarlo en el PR. El budget es estricto.
7. **NO usar `eval()`, `create_function()`, `extract()` con input externo**, ni `unserialize()` sobre datos no confiables.
8. **NO commitear archivos de build (`assets/dist/`) sin acuerdo explícito** — decidir en Fase 5 si se gitignoran o no según workflow del equipo.
9. **NO cambiar las firmas HMAC ni el algoritmo de tokens** sin migración planificada. Sitios cliente con tokens viejos se romperían.
10. **NO desactivar las verificaciones de integridad** del código inyectado por la extensión de licencias.
11. **NO meter UI en archivos PHP** una vez migrada una pantalla a React. El PHP queda solo como contenedor mínimo (`<div id="iaud-…"></div>`) y el enqueue del bundle.
12. **NO añadir polyfills viejos** ni soporte para IE/navegadores legacy. WP admin moderno = navegadores actuales.

---

## 5. Decisiones de Fase 5 — RESUELTAS (defaults adoptados por autorización del usuario)

> Estas decisiones quedaron pendientes hasta el inicio de Fase 5. El usuario autorizó proceder con las recomendaciones del documento, así que se fijaron los valores por defecto descritos abajo. **Toda nueva pantalla debe respetar estos valores. Cambiarlos requiere aprobación explícita.**

- [x] **Paleta corporativa de Imagina** — sin paleta entregada. Defaults provisionales:
  - Base neutra: escala `slate` de Tailwind (texto/fondos/borders) — `slate-50` … `slate-950`.
  - Color primario: variable CSS `--iaud-primary` con valor inicial `oklch(0.55 0.18 250)` (azul). Cuando llegue la paleta real, se cambia el valor de la variable y los acentos heredan automáticamente.
  - Logo: placeholder textual hasta que se entregue SVG/PNG. Cuando llegue, se coloca en `imagina-updater-server/assets/admin/src/assets/logo.svg`.
- [x] **Modo oscuro** — NO desde el día 1. Iteración posterior. Razón: `wp-admin` no expone modo oscuro nativo, mezclar light/dark añade casos edge sin retorno hasta tener la SPA estable.
- [x] **Densidad de UI** — compacta (estilo Linear, Vercel, Stripe Dashboard). Spacing scale ajustado: `p-3` por defecto en cards y filas de tabla; `p-4` solo cuando haya jerarquía visual; `p-6` reservado a drawers y modales.
- [x] **Referencias visuales** — Linear (interacciones, microcopy, densidad) + Stripe Dashboard (tablas con muchas columnas, formularios). Vercel Dashboard como referencia secundaria para visualización de listados.
- [x] **Tipografía** — Inter por defecto. Self-hosted vía `@fontsource/inter` (no peticiones a Google Fonts) con weights `400` / `500` / `600` / `700`.
- [x] **Branding del prefijo** — `iaud-` confirmado. Aplicado vía `prefix: 'iaud-'` en `tailwind.config.ts`. Variables CSS también prefijadas (`--iaud-primary`, `--iaud-bg`, etc.).
- [x] **Ubicación de `assets/dist/`** — **gitignored** en el repositorio principal (`main` y feature branches). Los builds finales se empaquetan en los ZIPs de la rama `release` (que es huérfana). El flujo de release ejecuta `npm run build` antes de `zip`.
- [x] **Cliente también** — sí, pero en Fase 5.B (después de mergear el rediseño completo del servidor a `main`). El cliente reusa el mismo stack, mismo prefijo, misma paleta y los componentes shadcn ya construidos en `imagina-updater-server/assets/admin/src/components/ui/`.

**Stack confirmado** (consistente con sección 2.2):

- React 18.3.x, ReactDOM 18.3.x — bundleados (no externalizados a `wp.element`) en la primera iteración. Optimización a externals queda diferida; el budget de 250 KB gzip per page lo permite con holgura.
- TypeScript 5.6+ en strict mode (`"strict": true, "noUncheckedIndexedAccess": true, "exactOptionalPropertyTypes": true`).
- Vite 5.4+ con multi-entry (una entry por pantalla admin).
- Tailwind 3.4+ con `prefix: 'iaud-'` y `corePlugins.preflight: false`.
- shadcn/ui sobre Tailwind, con componentes copiados a `src/components/ui/` (no `npm install`-ables; el CLI solo genera).
- TanStack Query 5.x para fetching/caché del REST. TanStack Table 8.x para todas las tablas.
- Lucide React para iconos.
- `clsx` + `tailwind-merge` para combinar utility classes condicionalmente (helper `cn()`).

---

## 6. Plan de trabajo

> El trabajo se divide en fases. **No avanzar a la siguiente fase sin merge de la anterior y verificación manual del usuario.** Cada fase tiene una rama dedicada que se mergea a `main` (o `develop` si el equipo lo prefiere) al cierre.

### Fase 0 — Limpieza: eliminar `imagina-license-sdk/`

**Rama**: `chore/remove-legacy-sdk`

**Objetivo**: eliminar el SDK legacy v1.0/v4.0 que está deprecado y crea ambigüedad arquitectónica. Rescatar documentación útil antes de borrar.

**Razonamiento**: el SDK legacy:
- Está acoplado 100% a WordPress (usa `ABSPATH`, `wp_die`, etc.) → no sirve para apps no-WP
- Su clase `Imagina_License_Crypto` choca con la del license-extension → riesgo activo de fatal error
- Sus endpoints (`/license/verify`, etc.) no son los que usa el sistema actual
- Para apps no-WP en el futuro se construirá un SDK nuevo desde cero (probablemente PHP plano + cliente Node/Python), no se rescata el actual

**Pasos**:

1. Crear rama `chore/remove-legacy-sdk`
2. Antes de borrar, **rescatar documentación útil**:
   - De `imagina-license-sdk/docs/SECURITY.md` → mover las secciones que describen las 7 capas de seguridad a `imagina-updater-license-extension/docs/SECURITY.md` (crear si no existe). Adaptar el lenguaje al sistema actual (auto-inyección, no integración manual).
   - De `imagina-license-sdk/docs/API.md` → revisar y mover lo que aplique al sistema actual a `imagina-updater-license-extension/docs/API.md`. Descartar lo que sea solo del SDK manual.
   - De `imagina-license-sdk/docs/INTEGRATION.md` → descartar (el sistema actual no requiere integración manual).
   - `QUICK_START.md`, `LEEME-PRIMERO.txt`, `ESTRUCTURA.txt`, `install.sh`, `instalar-facil.sh` → descartar.
3. Eliminar `imagina-license-sdk/` por completo
4. Buscar referencias en el resto del repo:
   ```bash
   grep -r "imagina-license-sdk" .
   grep -r "imagina_license_sdk" .
   ```
   Si aparecen referencias en código activo (no esperado), reportar al usuario antes de tocar.
5. Verificar que `diagnostico-licencias.php` no referencie archivos del SDK legacy. Si lo hace, ajustarlo o quitar la sección correspondiente.
6. Commit: `chore(repo): remove legacy imagina-license-sdk`
7. Abrir PR con descripción explicando la decisión y enlazando este documento.

**Verificación post-merge**:
- Activar y desactivar los tres plugins (server, client, license-extension) en una WP local. No debe haber errores PHP.
- Subir un plugin marcado Premium y verificar que la inyección sigue funcionando.
- Activar un sitio cliente y verificar el flujo completo.

**Cambios adicionales realizados durante esta fase (fuera del plan original)**

> Registro histórico de cambios que no estaban en el plan inicial pero se ejecutaron antes de cerrar la fase, con autorización explícita del usuario.

- **Fix `diagnostico-licencias.php` sección 7**: el chequeo `strpos($filename, 'imagina-license-sdk/loader.php')` siempre daba "❌ SDK NO encontrado" porque el injector v4 (`Imagina_License_SDK_Injector`) inyecta la protección **inline** en el archivo principal del plugin y ya no copia archivos SDK al ZIP. Se reemplazó por una búsqueda de la constante `PROTECTION_MARKER` (`'IMAGINA LICENSE PROTECTION'`) en cualquier archivo `.php` del ZIP. Resultado: el diagnóstico ahora reporta correctamente si la inyección funcionó.
- **Limpieza del comentario obsoleto en `imagina-updater-license-extension/includes/license-sdk/loader.php` línea 8**: el ejemplo `require_once plugin_dir_path( __FILE__ ) . 'vendor/imagina-license-sdk/loader.php';` referenciaba un path inexistente y un patrón de uso (integración manual con vendor/) que el sistema actual ya no usa. Se sustituyó por una nota que explica el estado actual y remite a la Fase 1.1 para la decisión definitiva sobre `includes/license-sdk/`.

**Pendientes derivados, NO ejecutados (siguen en su fase)**:

- La sección 6 del diagnóstico ("ARCHIVOS DEL SDK EN LA EXTENSIÓN") sigue listando como "requeridos" `loader.php`, `class-crypto.php`, `class-license-validator.php`, `class-heartbeat.php` dentro de `includes/license-sdk/`. Esos archivos existen pero **no son cargados por código activo** (verificado con `grep -rn "require.*license-sdk"`). La revisión y posible reescritura de esa sección depende de la decisión final de Fase 1.1 sobre qué hacer con `includes/license-sdk/`.

---

### Fase 1 — Correcciones críticas (riesgo de fatal o vulnerabilidad)

**Rama**: `fix/critical-issues`

**Objetivo**: resolver los 4 issues que pueden causar fatal errors, vulnerabilidades reales o comportamiento incorrecto en producción.

#### 1.1 Clase `Imagina_License_Crypto` declarada en dos archivos — RESUELTO

> **Estado**: ✅ resuelto en la rama `fix/critical-issues` (ver "Cambios realizados" más abajo en esta misma sub-sección).

**Síntoma original**: si por error se cargaban ambos archivos (`imagina-updater-license-extension/includes/class-license-crypto-server.php` Y `imagina-updater-license-extension/includes/license-sdk/class-crypto.php`), PHP lanzaba `Cannot redeclare class`.

**Verificación realizada antes de actuar**:

Se buscó en todo el repositorio cualquier `require/include` a archivos de `includes/license-sdk/`, autoloaders que los referenciaran, usos de las constantes `IMAGINA_LICENSE_SDK_*`, usos de `Imagina_License_SDK::`, `Imagina_License_Validator`, `Imagina_License_Heartbeat`, y se inspeccionó el método `rezip_plugin()` del injector v4. Resultados:

- 0 cargas activas de cualquier archivo de `includes/license-sdk/`.
- 0 referencias a las constantes / clases del SDK manual fuera del propio directorio.
- El injector v4 (`Imagina_License_SDK_Injector::rezip_plugin`) **no copia ningún archivo del SDK al ZIP** del plugin premium — solo re-empaqueta lo extraído más la inyección inline en el archivo principal.
- El `spl_autoload_register` que vivía en `license-sdk/loader.php` nunca se registraba porque `loader.php` no era cargado por nada.

Conclusión: el directorio `includes/license-sdk/` era **código completamente huérfano**, vestigio de versiones anteriores del injector que sí copiaban el SDK al ZIP. La afirmación previa en este documento de que "es código que usa el plugin instalado en clientes premium" era **inexacta** para el modelo del injector v4 (corregida ahora).

**Acción ejecutada** (autorizada por el usuario tras presentarle 4 alternativas):

1. Eliminado `imagina-updater-license-extension/includes/license-sdk/` completo (4 archivos: `loader.php`, `class-crypto.php`, `class-license-validator.php`, `class-heartbeat.php`).
2. Actualizada la sección 6 de `diagnostico-licencias.php` que listaba esos archivos como "requeridos": ahora chequea los archivos clave del sistema de protección actual (`class-sdk-injector.php`, `class-protection-generator.php`, `class-license-crypto-server.php`, `class-license-api.php`) y emite advertencia si vuelve a aparecer el directorio legacy.
3. Corregida la afirmación inexacta de este mismo documento sobre `includes/license-sdk/`.
4. NO se añadieron guardas `class_exists` defensivas porque, eliminado el duplicado, el riesgo desaparece. Si reaparece (regresión, instalación legacy), el diagnóstico lo detecta.

**Verificación post-cambio**:

- `grep -rn "license-sdk\|Imagina_License_Validator\|Imagina_License_Heartbeat\|IMAGINA_LICENSE_SDK_LOADED" --include="*.php" .` → 0 referencias huérfanas; las que quedan son intencionadas (clase `Imagina_License_SDK_Injector` activa, tags `@package` de PHPDoc, comentarios del propio fix).
- Probar en WP local: activar/desactivar los 3 plugins y subir un plugin marcado Premium para confirmar que la inyección sigue funcionando (verificación post-merge pendiente del usuario).

#### 1.2 Versión desincronizada en cliente — RESUELTO

> **Estado**: ✅ resuelto en `fix/critical-issues`.

**Síntoma original**: el header del plugin decía `Version: 1.0.0` pero la constante `IMAGINA_UPDATER_CLIENT_VERSION` estaba en `'1.0.1'`. Esto rompe assumptions en cualquier código que compare `get_plugin_data()` vs la constante (incluyendo el sistema de updates de WP).

**Acción ejecutada**:

1. Server (`imagina-updater-server.php`) y license-extension (`imagina-updater-license-extension.php`) verificados consistentes (`1.0.0` ↔ `1.0.0`, `5.3.0` ↔ `5.3.0`). Sin cambios.
2. Cliente: header y constante sincronizados a `1.0.2` (se tomó la rama "bump opcional para marcar este fix" de las dos descritas en el plan).

#### 1.3 `$_FILES['plugin_file']` sin sanitizar — RESUELTO

> **Estado**: ✅ resuelto en `fix/critical-issues`.

**Síntoma original**: en `admin/class-admin.php` del servidor (rama `imagina_upload_plugin`), se accedía a `$_FILES['plugin_file']` sin validar `is_array`, leyendo la clave `name` sin `wp_unslash`, y sin verificar `is_uploaded_file()` en la capa de admin (solo dentro de `Imagina_Updater_Server_Plugin_Manager::upload_plugin`).

**Acción ejecutada** (en `admin/class-admin.php`, rama `imagina_upload_plugin`):

1. `is_array($_FILES['plugin_file'])` se valida ANTES de tocar cualquier clave.
2. Bind a una copia local `$plugin_file` para no repetir la indexación; `error` se lee como `(int)` para comparación segura.
3. `is_uploaded_file($plugin_file['tmp_name'])` se verifica también en la capa de admin (defense in depth; el plugin manager mantiene su propia comprobación).
4. Cada clave de usuario se trata según su tipo:
   - `name` → `sanitize_file_name(wp_unslash(...))`.
   - `error` → `(int)` cast (no requiere unslash; es constante de PHP).
   - `tmp_name` → solo `is_uploaded_file()` (path del servidor, no input directo).
5. Se añadieron comentarios explicando por qué NO se llama a `wp_unslash()` sobre el array completo `$_FILES['plugin_file']`.
6. Inputs hermanos del mismo handler (`changelog`, `plugin_groups`, `force_replace`) actualizados al mismo estándar.

#### 1.4 `wp_unslash()` faltante en inputs — RESUELTO

> **Estado**: ✅ resuelto en `fix/critical-issues` con tres commits atómicos (uno por plugin).

**Síntoma original**: PHPCS reportaba varios `$_GET['action']`, `$_POST['…']` sin `wp_unslash()` previo a sanitización en los admin de los tres plugins.

**Acción ejecutada** (`admin/class-admin.php` de cada plugin):

- **Server**: `intval(wp_unslash($_GET['id'|'group_id'|'api_key_id']))`, `intval(wp_unslash($_POST['api_key_id'|'plugin_id'|'group_id'|'max_activations']))`, `array_map('intval', (array) wp_unslash($_POST['allowed_plugins'|'allowed_groups'|'plugin_ids']))`, todos los `$_GET['action'] === 'literal'` envueltos con `wp_unslash`. `render_plugin_groups_page` lee `$_GET['action']` y `$_GET['group_id']` con `sanitize_key(wp_unslash(...))` / `intval(wp_unslash(...))` y comentario `phpcs:ignore NonceVerification.Recommended` (read-only, los handlers de mutación validan su propio nonce).
- **Client**: `imagina_save_config` (server_url, log_level, change_api_key, api_key), handlers `deactivate_license` e `install_plugin` (incluye extracción local del nonce `$_GET['_wpnonce']` con sanitize + unslash y `wp_die` con `esc_html__`), `imagina_save_plugins` (`array_map('sanitize_text_field', wp_unslash(...))` sobre `enabled_plugins`), `imagina_save_display_mode` (`plugin_display_mode`).
- **License-extension**: `intval(wp_unslash(...))` en todos los `imagina_license_plugin_id`, `license_id`, `plugin_id`, `activation_id`, `max_activations`. `sanitize_email/text_field/textarea_field/key(wp_unslash(...))` en `customer_email`, `customer_name`, `expires_at`, `order_id`, `notes`, `status`, `imagina_license_action`. Comparaciones `$_POST['is_premium'] == 1` y `$_POST['imagina_license_action'] === '...'` envueltas con `wp_unslash`. `render_license_keys_page` con read-only nav (`plugin_filter`, `status_filter`, `edit_id`, `view_id`) hoisted a locales con `intval(wp_unslash(...))` y `phpcs:ignore NonceVerification.Recommended`.

Las llamadas a `isset()` / `empty()` puramente existenciales se dejaron sin tocar (no leen el valor).

`php -l` ejecutado sobre los tres archivos: sin errores de sintaxis.

**Verificación de Fase 1** (toda la fase, pendiente del usuario antes del merge):

- Correr PHPCS con WordPress-Extra y validar que los issues críticos están resueltos (Fase 2 hará el sweep general).
- Tests manuales: subir plugin (incluyendo Premium para verificar inyección), crear API key, activar sitio cliente, descargar plugin, crear/editar/eliminar license key.

---

### Fase 2 — PHPCS sweep (warnings sistemáticos) — RESUELTO

> **Estado**: ✅ resuelto en `chore/phpcs-sweep`. Ver "Cambios realizados" más abajo.

**Rama**: `chore/phpcs-sweep`

**Objetivo original**: resolver el resto de warnings de PHPCS que no entraron en Fase 1.

#### 2.1 `WordPress.DB.DirectDatabaseQuery.NoCaching` + 2.2 `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` — RESUELTO

**Razonamiento del approach elegido**: la guía original sugería anotar cada query individualmente con `phpcs:ignore`. El audit del CSV (`imagina-updater-server-imagina-updater-server-php-20260121-162424.csv`) reveló ~190 sitios afectados a través de 12 archivos, todos con la misma justificación (tablas custom propias del plugin, nombres de tabla siempre derivados de `$wpdb->prefix` + constante literal). Anotar 190 veces es ruido en code review sin valor extra.

**Approach aplicado**: cabecera `phpcs:disable` a nivel de archivo en los 12 archivos que usan `$wpdb`, con la justificación una sola vez:

```php
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// Justificación (Fase 2): este archivo opera sobre tablas custom propias del
// plugin. Las queries directas son intencionales (no hay caché de objetos
// compartido que invalidar para datos de baja cardinalidad), y los nombres
// de tabla se construyen con $wpdb->prefix concatenado a constantes
// literales, nunca a partir de input de usuario.
```

Archivos cubiertos (12):

- Server: `includes/class-database.php`, `includes/class-plugin-manager.php`, `includes/class-plugin-groups.php`, `includes/class-api-keys.php`, `includes/class-activations.php`, `admin/class-admin.php`, `api/class-rest-api.php`.
- License-extension: `includes/class-database.php`, `includes/class-license-api.php`, `includes/class-admin.php`, `includes/class-sdk-injector.php`.
- Client: `includes/class-updater.php`.

#### 2.3 `WordPress.Security.NonceVerification.Recommended` — RESUELTO

Las 6 advertencias del CSV (líneas 1005-1006 y 1048 originales del server admin) corresponden a lecturas read-only de navegación en `render_plugin_groups_page` y `render_activations_page`. Estas no requieren nonce (son filtros para el render, no mutaciones — los handlers de mutación validan su propio nonce).

**Acción ejecutada**:

- `render_plugin_groups_page` y reads equivalentes en `render_license_keys_page` (license-extension) ya recibieron el `phpcs:ignore WordPress.Security.NonceVerification.Recommended` durante Fase 1.4.
- `render_activations_page` cerrado en este commit con la misma convención: `// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation filter; the mutation handler (deactivate_activation) validates its own nonce.`

#### Cambios adicionales (fuera del plan original) — REPORTADOS, NO EJECUTADOS

- **Caché selectivo en endpoints de listado grandes** (logs, downloads, activations): el plan sugería `wp_cache_get`/`wp_cache_set` 60s "selectivamente". No ejecutado en Fase 2 — requiere análisis de patrones de acceso reales y puede solaparse con Fase 3 (robustez). Pendiente para Fase 2.x o Fase 3.
- **`class-protection-generator.php` líneas 364-367**: dos lecturas `$_POST['ilp_action']` / `$_POST['ilp_plugin']` dentro de un heredoc `<<<'PHPCODE'`. Es el template del código de protección que se inyecta en plugins premium del cliente, no código del propio servidor. El linter no escanea heredocs en single-quote, así que no genera warnings PHPCS, pero el código generado SÍ ejecuta sin `wp_unslash` ni nonce check. Hardening pendiente para una fase futura porque:
  1. Tocar el generator implica re-inyectar todos los plugins premium ya distribuidos (carga operativa).
  2. Puede intersectar con CLAUDE.md §4 regla crítica 9 (no cambiar firmas HMAC ni algoritmo de tokens sin migración planificada).
- **Otras categorías visibles en el CSV original** que NO están en el alcance de Fase 2 (`PluginCheck.Security.DirectDB.UnescapedDBParameter`, `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound`, `WordPress.PHP.DevelopmentFunctions.error_log_*`, `WordPress.Security.ValidatedSanitizedInput.MissingUnslash`/`InputNotSanitized`/`InputNotValidated`): se dejan para una Fase 2.x o se reasignan al plan según prioridad.

**Verificación de Fase 2**:

- `php -l` ejecutado sobre los 12 archivos modificados: sin errores de sintaxis.
- Re-correr Plugin Check (CSV) tras estos cambios para confirmar que los warnings cubiertos se silencian — pendiente del usuario (no tengo phpcs/Plugin Check instalado en este entorno).
- Snapshot del CSV inicial vs final adjuntable al PR cuando se mergee.

---

### Fase 3 — Robustez — RESUELTO

> **Estado**: ✅ resuelto en `refactor/robustness` (4 commits atómicos, uno por sub-issue).

**Rama**: `refactor/robustness`

**Objetivo original**: resolver problemas que no rompen hoy pero pueden romper bajo carga o en edge cases.

#### 3.1 `find_plugin_file` con matching parcial peligroso — RESUELTO

**Síntoma original**: en `imagina-updater-client/includes/class-updater.php` (`find_plugin_file`), el fallback hacía match por prefijo (`strpos($indexed_slug, $slug_lower . '-') === 0`): `"plugin"` matcheaba `"plugin-pro"`. Riesgo: dos plugins con prefijo común, mismatch silencioso.

**Acción ejecutada**:

- Eliminado el bloque del fallback completo. Si no hay match exacto en `$this->plugin_index`, `find_plugin_file()` ahora devuelve `false`.
- Comentario en el código apunta al `slug_override` del servidor como mecanismo correcto para resolver ambigüedades reales.

#### 3.2 Cache de errores 5min retrasa recuperación — RESUELTO

**Síntoma original**: cuando `check_updates()` fallaba, el cliente cacheaba respuesta vacía 5 minutos. Si el admin reactivaba una API key o corregía la URL, el cliente tardaba hasta 5 min en darse cuenta.

**Acción ejecutada** (`imagina-updater-client/includes/class-updater.php` ~línea 183):

- TTL del cache de error reducido de `5 * MINUTE_IN_SECONDS` a `MINUTE_IN_SECONDS` (60 s).
- **Auto-clear ya estaba en su sitio**: cada handler de admin que muta config (`save_config`, `deactivate_license`, `save_plugins`, `save_display_mode`) llama a `imagina_updater_client()->clear_update_caches()`, que borra todos los `_transient_imagina_updater_*` (incluyendo `imagina_updater_check_*`). No hizo falta cableado adicional.
- El **botón "Reintentar conexión"** del plan se reasigna a Fase 5 (rediseño del admin); requiere endpoint AJAX nuevo y UI.

#### 3.3 Descarga de ZIP sin streaming (riesgo OOM) — RESUELTO

**Síntoma original**: en `imagina-updater-server/api/class-rest-api.php`, `download_plugin()` hacía `echo $wp_filesystem->get_contents($plugin->file_path)`, cargando el ZIP entero a memoria. Para ZIPs grandes (>50 MB) excedía el `memory_limit` típico de shared hosting.

**Acción ejecutada**:

- Reemplazado por streaming con `fopen()` + `fread()` 8 KB + `flush()` + `fclose()`. Memoria constante (~8 KB) independientemente del tamaño del ZIP.
- WP_Filesystem no expone primitiva de streaming, así que `fopen()` directo es la herramienta correcta aquí.
- Si `fopen()` falla, retorna `WP_Error` 500 en lugar de servir un body vacío silenciosamente.
- Headers (`Content-Type`, `Content-Disposition`, `Content-Length`, no-cache) preservados intactos.
- `ob_end_clean()` previo preservado.

#### 3.4 `clear_rate_limits` con `DELETE LIKE` pesado — RESUELTO

**Síntoma original**: el barrido global con `DELETE FROM wp_options WHERE option_name LIKE '_transient_imagina_updater_rate_%'` (×2, valor + timeout) era costoso en sitios con muchos transients y no estaba protegido contra invocaciones repetidas.

**Acción ejecutada** (`Imagina_Updater_Server_REST_API::clear_rate_limits`):

- Invocaciones con `$api_key + $site_domain` o con `$ip` siguen baratas (un solo `delete_transient` con clave conocida); no se throttlean.
- Invocaciones globales (sin parámetros) ahora requieren:
  1. `current_user_can('manage_options')` — si falla, log warning + `return false`.
  2. Throttle de 60 s con transient `imagina_updater_clear_rate_limits_throttle`. Si el barrido se ejecutó hace menos de 60 s, se rechaza con log.
- Si la instalación soporta `wp_cache_flush_group` (`wp_cache_supports('flush_group')` — WP 6.1+ con backend compatible, ej. Redis Object Cache), se hace `wp_cache_flush_group('transient')` antes del `DELETE LIKE`. Con object cache, O(1) en lugar de O(n).
- El `DELETE LIKE` se mantiene como fallback defensivo (algunos backends siguen escribiendo a `wp_options`).
- Función ahora retorna `bool` (true en éxito, false en rechazo). PHPDoc actualizado para marcarla como operación de emergencia.

**Verificación de Fase 3**:

- `php -l` ejecutado sobre los 2 archivos modificados (`imagina-updater-client/includes/class-updater.php`, `imagina-updater-server/api/class-rest-api.php`): sin errores de sintaxis.
- Pendiente del usuario en WP local:
  - Test de carga: descargar un ZIP de 100 MB y verificar `memory_get_peak_usage()` ≤ 16 MB en el proceso de descarga.
  - Test funcional: cambiar API key en el cliente, validar que el siguiente `check_updates()` se reintenta sin esperar 5 minutos.
  - Test funcional: instalar dos plugins con prefijo común (ej. `acme` y `acme-pro`) y verificar que `find_plugin_file('acme')` devuelve solo el primero.
  - Test funcional: invocar `clear_rate_limits()` global desde admin dos veces seguidas y confirmar que la segunda devuelve `false` con warning en el log.

---

### Fase 4 — Arquitectónico — PARCIALMENTE RESUELTA

> **Estado**: 4.2 ✅ y 4.3 ✅ resueltos en `refactor/architecture`. 4.1 ⚠️ hecha solo a medias (composer.json sin migración de namespaces). 4.4 ⏸️ diferida (añade dependencia externa).

**Rama**: `refactor/architecture`

**Objetivo original**: mejoras estructurales de fondo. Esta fase es opcional y puede dividirse en sub-fases.

#### 4.1 Migración a namespaces PSR-4 — PARCIAL

> **Estado**: ⚠️ solo se añadió `composer.json` con classmap autoload en los 3 plugins. La migración de archivos a `src/` con namespaces y `class_alias` queda diferida hasta que haya WP local para validar end-to-end.

**Acción ejecutada**:

- Creado `imagina-updater-server/composer.json`, `imagina-updater-client/composer.json`, `imagina-updater-license-extension/composer.json` con:
  - Metadata estándar (name, description, license, php constraint).
  - `autoload.classmap` apuntando a las carpetas existentes (`includes/`, `admin/`, `api/`).
  - Sin renombres de clases, sin movimientos de archivos, sin cambios en los entry points.
- El loader actual (`require_once includes/class-*.php` en el archivo principal) se mantiene intacto. Los plugins distribuidos sin `vendor/` siguen funcionando exactamente igual.

**Lo que esto habilita**:

- Tooling de análisis estático (PHPStan, Psalm) y dependencias dev vía `composer install`.
- Declaración de intención: este proyecto se gestiona como un proyecto PHP moderno, no como un conjunto suelto de archivos.
- Base preparada para la migración completa de namespaces.

**Lo que NO se hizo (deferred)**:

- Mover `includes/class-*.php` a `src/` con namespaces (`ImaginaWP\UpdaterServer\`, `ImaginaWP\UpdaterClient\`, `ImaginaWP\LicenseExtension\`).
- Renombrar clases (`Imagina_Updater_Server_API_Keys` → `ImaginaWP\UpdaterServer\ApiKeys`, etc.).
- Añadir `class_alias` para retrocompatibilidad.
- Cargar el autoloader de Composer en runtime desde los archivos principales.

**Razonamiento**: una migración de namespaces sobre ~25 archivos de clase sin un entorno WP local para smoke-test arriesga rupturas sutiles (autoloader edge cases, `instanceof` checks, datos serializados en BD que referencian clases por nombre antiguo) que solo aparecerían en producción. Mejor diferirlo a una sesión con la verificación en vivo disponible.

#### 4.2 Documentar hooks (filter/action reference) — RESUELTO

**Acción ejecutada**: creado `imagina-updater-server/docs/HOOKS.md` con los 6 hooks que expone el servidor (todos `do_action`; 0 `apply_filters`):

- `imagina_updater_after_upload_form`
- `imagina_updater_after_move_plugin_file` ← punto de inyección de protección
- `imagina_updater_after_upload_plugin`
- `imagina_updater_plugins_column_toggles`
- `imagina_updater_plugins_table_header`
- `imagina_updater_plugins_table_row` (recibe `$plugin`)

Cada entrada documenta cuándo se dispara, parámetros (con tipos y mutabilidad), origen (archivo + línea), consumidores actuales (license-extension), ejemplo de uso, y best practices (capabilities, performance, two-phase commit).

El cliente y la license-extension **no exponen hooks propios** (auditado con `grep "do_action\|apply_filters"`), así que no se creó HOOKS.md para ellos. Si en el futuro empiezan a exponer hooks, se añadirá uno por plugin siguiendo el mismo template.

#### 4.3 Mejorar rollback de inyección de código en ZIPs — RESUELTO

**Síntoma original**: el flujo de `rezip_plugin()` hacía `copy(original, .backup) → unlink(original) → ZipArchive::CREATE → addFile loop → close → unlink(.backup)`. Entre el `unlink(original)` y un `close()` exitoso había una ventana donde un crash de PHP corrompía el plugin. Además se ignoraba el valor de retorno de `$zip->close()` y `addFile()`.

**Acción ejecutada** (`Imagina_License_SDK_Injector::rezip_plugin`):

1. El nuevo ZIP se construye en `$output_zip . '.new'` — el original NO se toca.
2. Si `ZipArchive::open`, `addFile` o `close` fallan: borrar `.new`, devolver error con mensaje específico. Original intacto.
3. En éxito: `rename(.new, original)` — atómico en filesystems POSIX (ext4/xfs/btrfs cuando ambas rutas comparten mountpoint, que es siempre el caso aquí).
4. Si `rename` falla (raro: cross-filesystem, permisos): borrar `.new`, devolver error. Original intacto.
5. Eliminado el sistema de `.backup` por innecesario con este flujo.
6. Añadido `ZipArchive::OVERWRITE` para que un `.new` huérfano de un intento previo se reemplace, y `unlink` preventivo de `.new` al inicio.
7. Valor de retorno de `addFile` y `close` ahora se chequea.

Ambos call sites (`inject_sdk_if_needed` línea ~128 y `remove_protection` línea ~612) van por este private method, así que ambos heredan el comportamiento.

#### 4.4 Action Scheduler para heartbeat — DIFERIDA

Reemplazar `wp_schedule_event` del heartbeat por Action Scheduler. Diferida explícitamente al inicio de la fase porque añade dependencia externa (la librería de Action Scheduler, normalmente embebida en WooCommerce). Pendiente para una sub-fase futura cuando se decida si:

- Embeber Action Scheduler como dependencia composer.
- O depender de la presencia de WooCommerce.
- O quedarse con `wp_schedule_event` y añadir lógica propia de retry.

**Verificación de Fase 4**:

- 4.1 (parcial): `composer.json` válido en los 3 plugins; `composer install` opcional para tooling. Sin cambios runtime.
- 4.2: `HOOKS.md` revisable manualmente; los ejemplos son copy-paste compilables.
- 4.3 pendiente del usuario en WP local: simular fallo durante inyección (p.ej. permisos read-only en uploads/, espacio en disco saturado) y confirmar que el ZIP original sigue intacto y descargable.

**Cambios adicionales realizados durante esta fase**: ninguno fuera del plan.

**Pendientes derivados**:

- Migración completa de PSR-4 (parte de 4.1 que quedó fuera).
- Decisión sobre 4.4 (Action Scheduler vs wp_schedule_event con retry propio).

---

### Fase 5 — Rediseño del admin (servidor primero)

**Rama base**: `feat/admin-redesign`. Sub-ramas por pantalla: `feat/admin-dashboard`, `feat/admin-api-keys`, etc.

**Pre-requisitos**:
- Decisiones de la sección 5 resueltas (paleta, modo oscuro, densidad, referencias)
- Fase 0 mergeada
- Idealmente Fase 1 mergeada también (para empezar sobre código limpio)

#### 5.0 Setup técnico — RESUELTO

> **Estado**: ✅ resuelto en `feat/admin-redesign`. Skeleton técnico operativo; el primer bundle (Dashboard placeholder) compila y se puede cargar desde wp-admin pendiente de cablear el enqueue PHP por pantalla (5.1+).

**Acción ejecutada**:

1. Creada `imagina-updater-server/assets/admin/` con `package.json`, `tsconfig.json` (strict + `noUncheckedIndexedAccess` + `exactOptionalPropertyTypes`), `tsconfig.node.json`, `vite.config.ts`, `tailwind.config.ts`, `postcss.config.js`, `components.json`, `index.html` (solo dev), `.gitignore` y `README.md`.
2. `vite.config.ts` con multi-entry parametrizada por el array `PAGES` (solo `dashboard` en este commit; añadir slugs aquí + crear `src/pages/<slug>/index.tsx` para nuevas pantallas). Plugin local `emit-wordpress-asset-files` que escribe `<entry>.asset.php` junto a cada bundle, con la convención `array('dependencies' => array(), 'version' => '<hash>')`.
3. **React/ReactDOM bundleados** (no externalizados a `wp.element`) en esta primera iteración; el budget de 250 KB gzip per page tiene holgura. `dependencies` queda vacío en los `.asset.php`. Migrar a externals queda como optimización posterior si algún bundle se acerca al techo.
4. Tailwind con `prefix: 'iaud-'`, `corePlugins.preflight: false` y tokens semánticos (`--iaud-primary`, `--iaud-bg`, etc.) que viven scoped en `.iaud-app` dentro de `src/styles/globals.css`. Modo oscuro reservado pero sin valores (Fase 5.B).
5. `components.json` con `prefix: 'iaud-'`, `baseColor: 'slate'` y aliases (`@/components`, `@/lib/utils`). Listo para que `npx shadcn@latest add button` (etc.) coloque componentes con el prefijo correcto.
6. `src/lib/utils.ts` con el helper `cn()` (clsx + tailwind-merge) y `src/lib/api.ts` con cliente REST mínimo: `adminGet`/`adminPost` apuntando a `iaudConfig.adminUrl` (inyectado desde PHP vía `wp_localize_script`) con header `X-WP-Nonce`.
7. `src/pages/dashboard/index.tsx` placeholder funcional: monta en `#iaud-dashboard`, envuelve con `QueryClientProvider`, demuestra que Tailwind con prefijo está activo. Las KPI cards reales se construyen en 5.1.
8. **REST admin namespace** creado: `imagina-updater-server/api/class-admin-rest-api.php` con la clase `Imagina_Updater_Server_Admin_REST_API` (singleton) y namespace `imagina-updater/admin/v1`. Permission callback común: `current_user_can('manage_options')` + nonce `wp_rest`. Primer endpoint `GET /dashboard/stats` que devuelve los 4 KPIs (total plugins, active api keys, active activations, downloads 24h) consultando las tablas custom directamente. La API pública `/imagina-updater/v1/` queda intacta (CLAUDE.md §4 regla 5).
9. Loader actualizado: `imagina-updater-server.php` añade el `require_once` y la inicialización del singleton junto al REST público.
10. `.gitignore` raíz añade `imagina-updater-server/assets/dist/` y `imagina-updater-client/assets/dist/` para que los builds NO se commiteen en `main`/feature branches. Los builds finales viajan en los ZIPs de la rama `release` (CLAUDE.md §5).

**Pendiente para 5.1**:

- Crear el sub-menú admin que renderiza la view `dashboard.php` con `<div id="iaud-dashboard"></div>`.
- `enqueue_dashboard_assets($hook)` con guard `if ($hook !== '...') return;` (CLAUDE.md §4 regla 3).
- KPI cards reales con datos de `GET /admin/v1/dashboard/stats`.
- Gráfico de descargas 30d (decisión: recharts si entra en budget, si no tabla).

**Pasos originales del plan (referencia, ya cubiertos)**:

1. Crear `imagina-updater-server/assets/admin/` con esta estructura:
   ```
   assets/admin/
   ├── package.json
   ├── tsconfig.json
   ├── vite.config.ts
   ├── tailwind.config.ts
   ├── postcss.config.js
   ├── components.json          ← shadcn config
   ├── src/
   │   ├── pages/
   │   ├── components/
   │   ├── hooks/
   │   ├── lib/
   │   ├── types/
   │   └── styles/
   └── .gitignore
   ```

2. **`package.json`** dependencias mínimas:
   ```json
   {
     "name": "imagina-updater-server-admin",
     "private": true,
     "type": "module",
     "scripts": {
       "dev": "vite",
       "build": "tsc -b && vite build",
       "lint": "eslint . --ext ts,tsx",
       "type-check": "tsc --noEmit"
     },
     "dependencies": {
       "@tanstack/react-query": "^5.x",
       "@tanstack/react-table": "^8.x",
       "lucide-react": "^0.x",
       "react": "^18.x",
       "react-dom": "^18.x",
       "tailwindcss": "^3.x",
       "tailwind-merge": "^2.x",
       "clsx": "^2.x"
     },
     "devDependencies": {
       "@types/react": "^18.x",
       "@types/react-dom": "^18.x",
       "@vitejs/plugin-react": "^4.x",
       "@wordpress/dependency-extraction-webpack-plugin": "^5.x",
       "autoprefixer": "^10.x",
       "postcss": "^8.x",
       "typescript": "^5.x",
       "vite": "^5.x"
     }
   }
   ```

3. **`vite.config.ts`** con multi-entry y plugin para que externalice `@wordpress/*` y `react`:
   - Entries: `dashboard`, `plugins`, `api-keys`, `plugin-groups`, `activations`, `logs`, `settings`
   - Output: `../dist/` (relativo a `assets/admin/`, así sale en `assets/dist/`)
   - Generar archivos `*.asset.php` con dependencies para `wp_enqueue_script`

4. **`tailwind.config.ts`** con prefijo `iaud-`:
   ```ts
   export default {
     prefix: 'iaud-',
     content: ['./src/**/*.{ts,tsx}'],
     theme: { extend: { /* design tokens */ } },
     plugins: [],
     corePlugins: { preflight: false }, // muy importante: NO resetear estilos globales del admin de WP
   }
   ```

5. **shadcn/ui setup** con `components.json` apuntando al prefijo y a `src/components/ui/`. Instalar componentes incrementalmente, no de golpe.

6. **`globals.css`** con:
   - `@tailwind base; @tailwind components; @tailwind utilities;`
   - Variables CSS de shadcn dentro de `.iaud-app` (scope), NO en `:root` (no contaminar el admin de WP)
   - Reset SOLO dentro del scope `.iaud-app`

7. **REST namespace separado para el admin**: crear `/imagina-updater/admin/v1/` para los endpoints que solo usa la SPA. Permission callback: `current_user_can('manage_options')` + nonce. La API pública `/imagina-updater/v1/` se mantiene intacta.

#### 5.1 Página: Dashboard — RESUELTA

> **Estado**: ✅ resuelta en `feat/admin-dashboard` (encadenada sobre `feat/admin-redesign`).

**Acción ejecutada**:

1. **Endpoints nuevos** en `Imagina_Updater_Server_Admin_REST_API`:
   - `GET /admin/v1/dashboard/stats` (ya existía desde 5.0).
   - `GET /admin/v1/dashboard/downloads-30d` — serie diaria (30 entries con días vacíos rellenos a 0).
   - `GET /admin/v1/dashboard/recent-downloads` — últimas 10 descargas con plugin y site_name (LEFT JOIN sobre plugins + api_keys).
   - `GET /admin/v1/dashboard/top-plugins` — top 5 por descargas totales (LEFT JOIN + COUNT + GROUP BY).
   Permission callback heredado: `manage_options` + nonce `wp_rest`.
2. **Submenu admin** "Dashboard (nuevo)" añadido en `class-admin.php`. Convive con la pantalla legacy mientras se completa el rediseño (cuando estén las 7 pantallas SPA, se hará el flip y se eliminan las views PHP antiguas).
3. **Enqueue condicional** (CLAUDE.md §4 regla 3): el método `enqueue_scripts($hook)` ahora compara `$hook === $this->spa_dashboard_hook` (suffix capturado al registrar la submenu). Si coincide, encolla SOLO el bundle SPA (sin CSS legacy ni jQuery). Si no, comportamiento previo. Si el bundle no está construido, muestra `admin_notices` con instrucciones.
4. **`render_spa_dashboard_page()`** queda como contenedor mínimo: `<div class="wrap"><div id="iaud-dashboard"></div></div>` (CLAUDE.md §4 regla 11).
5. **`wp_localize_script('iaud-dashboard', 'iaudConfig', …)`** inyecta `apiUrl`, `adminUrl`, `nonce` (wp_rest), `currentUser`, `locale` (formato BCP 47), `siteUrl`. `wp_set_script_translations` con dominio `imagina-updater-server`.
6. **shadcn primitives** copiados a `src/components/ui/` con prefijo `iaud-`: `card.tsx` (Card / CardHeader / CardTitle / CardDescription / CardContent / CardFooter), `button.tsx` (con `cva` y variantes default/secondary/outline/ghost/destructive + sizes sm/default/lg/icon, `default = h-9` por densidad compacta), `table.tsx` (con `py-2` por defecto vs `py-4` upstream), `skeleton.tsx`.
7. **Página Dashboard** dividida en módulos en `src/pages/dashboard/`:
   - `api.ts` — 4 hooks de TanStack Query mapeando 1:1 con endpoints.
   - `StatsCards.tsx` — grid responsive de 4 KPIs con iconos Lucide y skeleton loaders.
   - `DownloadsChart.tsx` — bar chart 30d en SVG inline. **Decisión**: NO añadir recharts/chart.js para mantener bundle bajo budget (CLAUDE.md §2.3). ~30 líneas de SVG con tooltip via `<title>`.
   - `RecentDownloadsTable.tsx` — tabla shadcn con plugin/version/sitio/cuándo (formateo relativo).
   - `TopPluginsTable.tsx` — tabla shadcn con plugin/versión/descargas (tabular-nums).
   - `QuickActions.tsx` — botones a las pantallas legacy de Plugins / API Keys (links a `admin.php?page=…`).
   - `DashboardPage.tsx` — composición vertical (header con botón "Actualizar" que invalida queries `['dashboard']`, KPIs, chart, grid 2 columnas con tablas, quick actions).
   - `index.tsx` — entry-point con `QueryClientProvider`.
8. **`src/lib/format.ts`** — helpers `formatNumber`, `formatDateTime`, `formatRelativeTime` con `Intl.*` y locale de `iaudConfig.locale`. Asume datetime de DB en UTC (ver "Limitación conocida" abajo).
9. **`vite.config.ts`** — `__dirname` calculado vía `fileURLToPath(import.meta.url)` (ESM puro, no depende de polyfills de Vite).

**Limitación conocida**: `formatDateTime` y `formatRelativeTime` interpretan los strings `downloaded_at` como UTC (`new Date(input + 'Z')`). Si el servidor MySQL no está en UTC, los timestamps mostrados estarán desplazados. WP recomienda UTC; revisar al cerrar Fase 5 con WP local.

**Pendiente para 5.2 (API Keys)**: la primera pantalla con tabla grande activa el patrón de `useTanStackTable` shared hook + `<DataTable>` componente reutilizable. Los primitives `Table*` ya están listos para integrarse.

**Verificación pendiente del usuario** (en WP local):

- [ ] `cd imagina-updater-server/assets/admin && npm install && npm run build` — compila sin errores; genera `dashboard.{js,css,asset.php}` en `assets/dist/`.
- [ ] Activar el plugin servidor; aparece la submenu "Dashboard (nuevo)".
- [ ] Abrir esa pantalla — el bundle se carga, render del dashboard sin errores en consola, KPIs llegan, chart se dibuja.
- [ ] Navegar a otra pantalla del plugin (ej. "Plugins" legacy) — el bundle SPA NO se carga (verificar en Network tab que `dashboard.js` no aparece).
- [ ] Navegar a una pantalla NO del plugin (ej. "Posts") — verificar que ni el bundle SPA ni el CSS legacy del plugin se cargan ahí.
- [ ] Subir plugin nuevo + descargar desde un sitio cliente; volver al dashboard y pulsar "Actualizar" — los contadores y la fila nueva aparecen.

**Endpoints documentados (admin/v1)**:
- `GET /admin/v1/dashboard/stats`
- `GET /admin/v1/dashboard/downloads-30d`
- `GET /admin/v1/dashboard/recent-downloads`
- `GET /admin/v1/dashboard/top-plugins`

#### 5.2 Página: API Keys — RESUELTA

> **Estado**: ✅ resuelta en `feat/admin-api-keys` (encadenada sobre `feat/admin-dashboard`).

**Acción ejecutada**:

1. **Endpoints nuevos** en `Imagina_Updater_Server_Admin_REST_API` (todos heredan `manage_options` + nonce `wp_rest`):
   - `GET /admin/v1/api-keys?page=&per_page=&status=&search=` — listado paginado con filtros. La columna `activations_used` se calcula con LEFT JOIN sobre `activations` (sólo `is_active=1`) para evitar N+1.
   - `POST /admin/v1/api-keys` — crear. Devuelve `{ item, plain_key }`. **`plain_key` es la única vez que el backend expone la clave en claro.**
   - `PUT /admin/v1/api-keys/{id}` — actualizar nombre/URL/permisos/max_activations. NO regenera la clave.
   - `DELETE /admin/v1/api-keys/{id}` — eliminar.
   - `POST /admin/v1/api-keys/{id}/regenerate` — genera nueva clave manteniendo el resto. Devuelve `{ item, plain_key }`.
   - `POST /admin/v1/api-keys/{id}/toggle-active` — body `{ is_active: bool }` o sin body (toggle).
   - `GET /admin/v1/plugins` (lite — id/slug/effective_slug/name) y `GET /admin/v1/plugin-groups` (lite — id/name) para los pickers del drawer. Las versiones completas llegan en 5.3 y 5.4.
2. **Helpers** privados en la clase REST:
   - `mask_api_key($plain)` → `ius_••••aBcD` (primeros 4 + últimos 4).
   - `serialize_api_key($row, $activations_used)` → forma uniforme; **NUNCA** incluye `api_key` en claro.
   - `parse_api_key_payload($request)` → sanitiza el body común a create + update.
   - `load_api_key_serialized($id)` → reload con LEFT-JOIN del activations_used para devolver la fila tras mutación.
3. **Wiring WP** (`admin/class-admin.php`):
   - Nueva submenu **"API Keys (nuevo)"** convive con la legacy.
   - Refactor del enqueue: `enqueue_scripts($hook)` ahora itera un map `[$hook_suffix => $bundle_slug]` y llama al método genérico `enqueue_spa_bundle($bundle)`. Esto reemplaza al `enqueue_spa_dashboard_assets()` específico de 5.0/5.1, y permite añadir cualquier pantalla SPA futura con dos líneas (un nuevo `add_submenu_page` + una entrada en el map).
   - `render_spa_api_keys_page()` = contenedor mínimo (`<div id="iaud-api-keys"></div>`).
4. **Vite multi-entry** ampliado: `PAGES = ['dashboard', 'api-keys']`. El emitter de `.asset.php` ya manejaba múltiples entries.
5. **Primitives shadcn** nuevos en `src/components/ui/`:
   - `input.tsx`, `label.tsx`, `textarea.tsx` — controlados, mismas tokens que el resto.
   - `badge.tsx` — variantes default/secondary/success/warning/destructive/outline (cva).
   - `drawer.tsx` — **implementación custom sin Radix** (~100 líneas). Maneja backdrop con click-to-close, Escape para cerrar, body scroll lock, slide-in con `tailwindcss-animate`. Documentado el camino para sustituirlo por `@radix-ui/react-dialog` cuando hagan falta focus traps o multi-instancia.
6. **Componente reutilizable** `src/components/data-table.tsx` — wrapper minimalista sobre TanStack Table v8 con los primitives shadcn. Diseño plano: paginación/filtros server-side (la mantenemos en el REST). Si una pantalla concreta necesita client-side sorting/pagination con dataset pequeño, puede usar `useReactTable` directamente.
7. **Página API Keys** en `src/pages/api-keys/` (8 archivos):
   - `types.ts` — `ApiKey`, `ApiKeyFormValues`, `AccessType`, `StatusFilter`, lookups Lite.
   - `api.ts` — 7 hooks de TanStack Query: `useApiKeysList` (con `placeholderData: prev → prev` para suavizar paginación), `useCreateApiKey`, `useUpdateApiKey`, `useDeleteApiKey`, `useToggleApiKeyActive`, `useRegenerateApiKey`, `usePluginsLite`, `usePluginGroupsLite`. Las mutaciones invalidan `['api-keys']` y, cuando aplica, también `['dashboard']`.
   - `PluginPicker.tsx` — multi-select con búsqueda y checkboxes. Reutilizable para plugins y para grupos.
   - `ApiKeyDrawer.tsx` — drawer create + edit en un solo componente. Reset al abrir/cambiar de modo. Radio cards para `access_type`. Renderiza `<PluginPicker>` condicional según el access_type. Validación inline (nombre + URL no vacíos).
   - `PlainKeyBanner.tsx` — banner que muestra la clave **una sola vez** con botón "Copiar" (Clipboard API + fallback a `document.execCommand('copy')` para entornos no-HTTPS / navegadores viejos).
   - `ApiKeysPage.tsx` — composición: header con CTA "Nueva API key", banner condicional, tabs (Todas/Activas/Inactivas), search input, `<DataTable>` con 7 columnas (sitio, api_key_masked, access_type badge, activations used/max, estado badge, último uso, acciones), pager simple Anterior/Siguiente. Acciones por fila: Editar, Toggle, Regenerar (con `window.confirm`), Eliminar (con `window.confirm`).
   - `index.tsx` — entry-point con `QueryClientProvider`.
   - `lib/api.ts` extendido inline con `adminPut`/`adminDelete` (se moverá a `src/lib/api.ts` cuando una segunda pantalla los necesite).
8. **Confirmaciones destructivas**: implementadas con `window.confirm` para mantener bundle pequeño en esta primera iteración. Si el equipo decide pasar a `<AlertDialog>` (shadcn), se sustituyen los `window.confirm` por un componente reutilizable; tarea diferida hasta que haya un caso real de "deshacer" o copia rica que justifique.

**Decisiones de scope (deliberadas)**:

- **Drawer custom, no Radix**: ~5 KB ahorrados, suficiente para el caso de uso actual (un solo drawer abierto a la vez, formulario simple). Reemplazable cuando lo justifique.
- **`window.confirm` en lugar de `<AlertDialog>`**: nativo, accesible, cero coste. Cumple la regla de no-regression del PHP legacy (que también usa confirmaciones nativas).
- **`<select>` no necesario**: `access_type` se modela con radio cards (mejor UX para 3 opciones excluyentes con descripción) en lugar de un dropdown.
- **No toast/snackbar**: errores se muestran inline en el drawer; éxitos cierran el drawer y refrescan la tabla. Si más adelante hay acciones background (regen + reload, batch delete), se añade un sistema de notificaciones.

**Verificación pendiente del usuario** (en WP local):

- [ ] `cd imagina-updater-server/assets/admin && npm run build` — compila los **dos** bundles (`dashboard` + `api-keys`); ambos `.asset.php` se generan.
- [ ] Aparece la submenu "API Keys (nuevo)". Abrir — el bundle se carga, tabla pinta (vacía o con keys existentes).
- [ ] Crear: nombre + URL + access_type + max_activations → submit → banner aparece con la clave en claro y copy funcional → cerrar banner → la fila aparece en la tabla con `api_key_masked`.
- [ ] Editar una key existente → cambia nombre/URL/access_type/permisos → guardar → fila refrescada.
- [ ] Toggle activo/inactivo → estado cambia visual e instantáneo (mutation invalida la lista).
- [ ] Regenerar → confirm → banner con la clave nueva.
- [ ] Eliminar → confirm → fila desaparece.
- [ ] Filtros: tabs Todas/Activas/Inactivas + búsqueda por nombre/URL — la URL del REST refleja `?status=&search=` y el pager se reinicia a 1.
- [ ] Pager Anterior/Siguiente con >20 keys.
- [ ] Network tab en pantalla "Plugins (legacy)" — bundle `api-keys.js` NO debe cargarse.

**Pendiente para 5.3 (Plugins)**: la pantalla con upload de ZIP, drag-and-drop, premium toggle, listado de versiones por plugin, re-inyección de protección. El endpoint `GET /admin/v1/plugins` actual es la versión Lite — se ampliará con `?page=&search=`, plus 5+ endpoints nuevos. La pantalla reusará `<DataTable>`, `<Drawer>`, `<PluginPicker>` ya construidos.

#### 5.3 Página: Plugins — RESUELTA

> **Estado**: ✅ resuelta en `feat/admin-plugins` (encadenada sobre `feat/admin-api-keys`).

**Acción ejecutada**:

1. **Endpoints nuevos** en `Imagina_Updater_Server_Admin_REST_API`:
   - `GET /admin/v1/plugins?page=&per_page=&search=` — paginado, full. Devuelve `{ items, total, page, per_page, license_extension_active }`. Cada `PluginRow` incluye `total_downloads` (LEFT JOIN downloads + COUNT) y `group_ids` (cargado en bulk vía `load_group_ids_for_plugins` para evitar N+1). El helper `serialize_plugin` calcula `effective_slug` y respeta `is_premium` solo si la extensión está activa.
   - `GET /admin/v1/plugins?lite=1` — modo dual: el endpoint anterior (Lite) creado en 5.2 ahora se obtiene con este flag. Pickers de la pantalla API Keys actualizados para añadir `?lite=1` al request.
   - `POST /admin/v1/plugins/upload` — multipart. Body `plugin_file` (ZIP) + opcional `changelog`, `description`, `is_premium`, `group_ids[]`. Estrategia premium: el upload base hace `is_premium=0` (los hooks legacy NO inyectan), y si el body pidió premium hacemos `UPDATE is_premium=1` + `Imagina_License_SDK_Injector::inject_sdk_if_needed()` manualmente. Esto desacopla la SPA de la lectura `$_POST['is_premium']` del flujo legacy y mantiene los hooks intactos para el form PHP viejo (CLAUDE.md §4 regla 2).
   - `PUT /admin/v1/plugins/{id}` — edita `slug_override`, `description`, `group_ids` (atómico via `set_plugin_groups`). NO toca `is_premium` (endpoint dedicado).
   - `DELETE /admin/v1/plugins/{id}`.
   - `POST /admin/v1/plugins/{id}/toggle-premium` — body `{ is_premium }` o toggle. Cuando enciende, intenta inyectar protección. Cuando apaga, NO desinyecta el código existente del ZIP (queda como tarea futura).
   - `POST /admin/v1/plugins/{id}/reinject-protection` — re-inyecta. Maneja el shape `{ success, message }` que devuelve `inject_sdk_if_needed`.
   - `GET /admin/v1/plugins/{id}/versions` — historial de versiones del plugin.
2. **Helpers** privados nuevos: `license_extension_active()` (chequea `class_exists('Imagina_License_SDK_Injector')`), `serialize_plugin()`, `load_group_ids_for_plugins()` (bulk-load con `IN (?, ?, …)` para evitar N+1), `set_plugin_groups()` (delete + insert atómico), `load_plugin_serialized()`.
3. **Wiring WP**:
   - Submenu **"Plugins (nuevo)"** convive con la legacy.
   - `enqueue_scripts($hook)` añade el slug en el map `[hook → bundle]`.
   - `iaudConfig` ahora incluye `licenseExtensionActive` (boolean) — el frontend gatea visualmente las acciones premium/reinject según esto.
   - `render_spa_plugins_page()` = contenedor mínimo (`<div id="iaud-plugins"></div>`).
4. **Shared lib** `src/lib/api.ts` extendido:
   - Promovidos `adminPut` / `adminDelete` (que en 5.2 vivían inline en `pages/api-keys/api.ts`).
   - Nuevo `adminPostMultipart(path, FormData, { onProgress })` con XHR (necesario porque `fetch` no expone progreso de upload). Reportería de % via `xhr.upload.onprogress`.
   - Tipo de `iaudConfig` extendido con `licenseExtensionActive?: boolean`.
5. **Página Plugins** en `src/pages/plugins/` (8 archivos):
   - `types.ts` — `PluginRow`, `PluginListResponse`, `PluginVersion`, `PluginUploadValues`, `PluginEditValues`.
   - `lib.ts` — helper `formatBytes()`.
   - `api.ts` — 7 hooks de TanStack Query: `usePluginsList` (con `placeholderData: prev → prev`), `usePluginVersions` (con `enabled: pluginId !== null`), `usePluginGroupsLite` (compartido con API Keys via misma queryKey), `useUploadPlugin` (acepta callback `onProgress`), `useUpdatePlugin`, `useDeletePlugin`, `useTogglePremium`, `useReinjectProtection`. Mutaciones invalidan `['plugins']`, `['plugins-lite']` (el picker de API Keys) y, cuando aplica, `['dashboard']`.
   - `UploadDrawer.tsx` — drawer con drag-and-drop **sin librerías**. Usa eventos `onDragOver/onDragLeave/onDrop` nativos. Validación cliente de extensión `.zip`. Muestra archivo seleccionado con tamaño formateado. Progress bar inline durante el upload. Toggle premium SOLO se renderiza si `licenseExtensionActive`. Reusa `<PluginPicker>` para los grupos.
   - `EditDrawer.tsx` — formulario de edición (slug_override, description, groups). Reset al abrir/cambiar de plugin. Reusa `<PluginPicker>`.
   - `VersionsDrawer.tsx` — tabla read-only de versiones. Lee solo cuando el drawer está abierto (`enabled` en el query).
   - `PluginsPage.tsx` — composición: header con CTA "Subir plugin", search input, `<DataTable>` con 6 columnas (Plugin con badge premium, Versión, Descargas, Grupos count, Última subida, Acciones), pager Anterior/Siguiente. Acciones por fila: Ver versiones, Editar, Toggle premium (gated), Re-inyectar (gated + solo si is_premium), Eliminar. Todas las acciones destructivas piden `window.confirm`.
   - `index.tsx` — entry-point con `QueryClientProvider`.
6. **Vite multi-entry** ampliado: `PAGES = ['dashboard', 'api-keys', 'plugins']`.

**Decisiones de scope (deliberadas)**:

- **Drag-and-drop sin librerías** — el patrón nativo del DOM (`onDragOver` + `onDrop`) es suficiente para nuestro caso (un solo file, validación simple). Cero dependencias añadidas.
- **Toggle premium NO desinyecta** — apagar `is_premium=0` solo limpia el flag en BD; el código de protección permanece embebido en el ZIP. Desinyectar requiere parsing y rebuild adicional, fuera del scope de 5.3. El admin que necesite limpiar puede re-subir un ZIP fresco.
- **Sin `<select>` de filtros** — solo búsqueda por nombre/slug. Si el equipo necesita filtros tipo "premium / no premium / con grupos / sin grupos", se añade en una iteración posterior.
- **No bulk actions** — el listado actual no soporta selección múltiple ni operaciones en bote (delete masivo, toggle masivo). Si surge la necesidad real, se añade `<Checkbox>` columna 0 + barra de acciones flotante.
- **VersionsDrawer read-only** — listar versiones, no eliminarlas ni promover una vieja a "current". Esas operaciones son raras y peligrosas; quedan en pantalla legacy hasta que aparezca un caso real.
- **Modo `is_premium=true` en upload requiere extensión activa** — si la extensión no está cargada, el endpoint devuelve 400. La SPA oculta el checkbox; pero si alguien hace POST directo, el backend protege.

**Verificación pendiente del usuario** (en WP local):

- [ ] `cd imagina-updater-server/assets/admin && npm run build` — compila los **tres** bundles (`dashboard`, `api-keys`, `plugins`).
- [ ] Aparece la submenu "Plugins (nuevo)". Tabla pinta los plugins existentes con `total_downloads`, badge premium (solo si extensión activa), grupos count.
- [ ] Drag-and-drop de un ZIP en el drawer de upload — se ve el filename + tamaño. Submit con/sin premium → fila aparece.
- [ ] Subida con archivo no-ZIP → error visible inline.
- [ ] Editar slug_override → la columna refleja "(override de slug-original)" tras guardar.
- [ ] Editar grupos → el contador de grupos cambia.
- [ ] Toggle premium (con extensión activa) → badge aparece/desaparece. La acción de re-inyectar aparece solo cuando is_premium=1.
- [ ] Re-inyectar → ZIP en disco se actualiza (puede verificarse con `unzip -p <zip> <main-file>.php | grep IMAGINA`).
- [ ] Versiones drawer → lista con sizes, fechas y changelogs.
- [ ] Eliminar → el plugin desaparece de la tabla y del picker de API Keys (cache invalidado).
- [ ] Si la extensión está desactivada: no aparecen las acciones premium ni re-inyectar; el checkbox del drawer tampoco; backend devuelve 400 si se fuerza.
- [ ] Picker de plugins en pantalla API Keys → sigue funcionando (consume `?lite=1`).

**Pendiente para 5.4 (Plugin Groups)**: pantalla mucho más simple. Tabla de grupos, drawer create/edit con `<PluginPicker>` (ya construido). Endpoints: CRUD `/admin/v1/plugin-groups`. La pantalla reusará absolutamente todos los primitives.

#### 5.4 Página: Plugin Groups — RESUELTA

> **Estado**: ✅ resuelta en `feat/admin-plugin-groups` (encadenada sobre `feat/admin-plugins`).

**Acción ejecutada**:

1. **Endpoints CRUD** en `Imagina_Updater_Server_Admin_REST_API`:
   - `GET /admin/v1/plugin-groups` — modo dual (mismo patrón que `/plugins`):
     - `?lite=1` → array plano `[{id, name}]` (consumido por los pickers de drawers de API Keys y Plugins; rutas existentes ajustadas para añadir el flag).
     - sin `lite` → `{ items: [...] }` con `id`, `name`, `description`, `plugin_count`, `linked_api_keys_count`, `created_at`.
   - `POST /admin/v1/plugin-groups` — body `{ name, description?, plugin_ids? }`.
   - `PUT /admin/v1/plugin-groups/{id}` — mismo body.
   - `DELETE /admin/v1/plugin-groups/{id}` — devuelve `{ deleted, id, orphaned_api_keys_count }` para que la SPA pueda comunicar consecuencias.
2. **Helpers** privados nuevos:
   - `load_linked_api_keys_count_by_group(int[] $group_ids): array<int, int>` — usa `JSON_CONTAINS(allowed_groups, %s)` sobre `wp_imagina_updater_api_keys`. Cae a 0 silenciosamente si MySQL no expone `JSON_CONTAINS` (versiones <5.7), suprimiendo errores con `$wpdb->suppress_errors()`. La pantalla seguirá funcional sin ese contador.
   - `load_plugin_group_serialized(int $id)` — recarga un grupo concreto con `plugin_count`, `plugin_ids`, `linked_api_keys_count`.
   - `parse_plugin_group_payload($request)` — sanitiza body común (name + description + plugin_ids).
3. **Wiring WP**:
   - Submenu **"Grupos (nuevo)"** convive con la legacy.
   - Map de enqueue actualizado: `[hook → 'plugin-groups']`.
   - `render_spa_plugin_groups_page()` = contenedor mínimo.
4. **Vite multi-entry** ampliado: `PAGES = ['dashboard', 'api-keys', 'plugins', 'plugin-groups']`. Cuarto bundle activo.
5. **Pickers actualizados**: `usePluginGroupsLite()` en `pages/api-keys/api.ts` y `pages/plugins/api.ts` ahora consumen `?lite=1`.
6. **Página Plugin Groups** en `src/pages/plugin-groups/` (5 archivos):
   - `types.ts` — `PluginGroupRow`, `PluginGroupListResponse`, `PluginGroupFormValues`. `plugin_ids` es opcional (solo viene en el response de mutaciones, no en el listado).
   - `api.ts` — 4 hooks: `usePluginGroupsList`, `usePluginsLite` (compartido vía mismo queryKey), `useCreatePluginGroup`, `useUpdatePluginGroup`, `useDeletePluginGroup`. Mutaciones invalidan `['plugin-groups']`, `['plugin-groups-lite']`, `['plugins']` (porque la columna "Grupos count" en la tabla de Plugins puede cambiar) y `['api-keys']` cuando se elimina un grupo (las keys vinculadas pierden acceso).
   - `PluginGroupDrawer.tsx` — drawer create + edit en uno. Reset al abrir/cambiar editing. Reusa `<PluginPicker>` para los plugins. Banner amber-tinted cuando se edita un grupo con API keys vinculadas.
   - `PluginGroupsPage.tsx` — composición lean: header con CTA, `<DataTable>` de 5 columnas (Nombre + descripción, Plugins count, API keys vinculadas, Creado, Acciones). Acción Eliminar con `window.confirm` que cita el número de API keys afectadas. NO hay tabs ni search por ahora (los listados de grupos suelen ser cortos; si el inventario crece se añade).
   - `index.tsx` — entry-point con `QueryClientProvider`.

**Decisiones de scope (deliberadas)**:

- **Sin filtros ni paginación** — los grupos se cuentan en decenas, no en miles. Si crece el inventario se añade.
- **Eliminación NO bloquea por API keys vinculadas** — solo avisa. El usuario decide. Las API keys con grupos huérfanos quedan funcionalmente igual al estado "tipo de acceso = grupos" sin grupos seleccionados (= sin acceso a nada). El admin debe reconfigurar esas keys explícitamente.
- **`JSON_CONTAINS` con fallback a 0** — preferí compatibilidad ancha sobre exactitud absoluta del contador. En MySQL 5.6 (raro hoy) la columna mostraría `—` siempre. En MySQL 5.7+ / MariaDB 10.2.4+ funciona perfecto.
- **No bulk actions** — mismo razonamiento que en 5.3.
- **Sin search** — al ser pocos grupos, scrollear o usar Ctrl+F del navegador suele bastar. Reversible si llega feedback.

**Verificación pendiente del usuario** (en WP local):

- [ ] `cd imagina-updater-server/assets/admin && npm run build` — compila los **cuatro** bundles.
- [ ] Aparece la submenu "Grupos (nuevo)". Tabla pinta los grupos existentes con `plugin_count` y `linked_api_keys_count` correctos.
- [ ] Crear grupo: nombre + descripción + selección de plugins → fila aparece + `plugin_count` correcto.
- [ ] Editar: cambiar nombre/descripción/plugins → fila se refresca. La columna "Grupos count" en la pantalla Plugins refleja el cambio.
- [ ] Editar un grupo con API keys vinculadas: el banner amber aparece dentro del drawer.
- [ ] Eliminar: confirm con conteo de API keys → fila desaparece + las keys afectadas siguen visibles en pantalla API Keys (cache `['api-keys']` invalidado).
- [ ] Picker en API Keys / Plugins → sigue funcionando con `?lite=1`.
- [ ] En MySQL antiguo sin `JSON_CONTAINS`: `linked_api_keys_count` muestra `—` pero todo lo demás funciona.

**Pendiente para 5.5 (Activations)**: tabla con dropdown de filtro por API key (consumirá `?lite=1` o un nuevo lite endpoint para api-keys), filtro por estado, búsqueda por dominio, acción "desactivar". Reusará todos los primitives.

#### 5.5 Página: Activations — RESUELTA

> **Estado**: ✅ resuelta en `feat/admin-activations` (encadenada sobre `feat/admin-plugin-groups`).

**Acción ejecutada**:

1. **Endpoints nuevos** en `Imagina_Updater_Server_Admin_REST_API`:
   - `GET /admin/v1/activations?page=&per_page=&status=&api_key_id=&search=` — paginado con LEFT JOIN sobre api_keys para incluir `site_name` sin un round-trip extra. Filtros combinables: estado (active/inactive/all), api_key_id (0 = todas) y `search` por dominio (LIKE).
   - `POST /admin/v1/activations/{id}/deactivate` — wrapper sobre `Imagina_Updater_Server_Activations::deactivate_site($id)`. Devuelve la fila actualizada serializada.
   - **Nuevo modo dual** en `GET /admin/v1/api-keys`: `?lite=1` devuelve `[{id, site_name}]` para alimentar el dropdown filtro de esta pantalla. La forma paginada existente sigue funcional.
2. **Helpers** privados nuevos: `mask_activation_token()` (formato `iat_••••aBcD`) y `serialize_activation()` (forma uniforme con `is_active` boolean, fechas como strings, token mascarado).
3. **Wiring WP** (`admin/class-admin.php`):
   - Submenu **"Activaciones (nuevo)"** convive con la legacy.
   - Map de enqueue actualizado: `[hook → 'activations']`.
   - `render_spa_activations_page()` = contenedor mínimo (`<div id="iaud-activations"></div>`).
4. **Vite multi-entry** ampliado: `PAGES = ['dashboard', 'api-keys', 'plugins', 'plugin-groups', 'activations']`. Quinto bundle.
5. **Página Activations** en `src/pages/activations/` (4 archivos):
   - `types.ts` — `ActivationRow`, `ActivationsListResponse`, `ApiKeyOptionLite`.
   - `api.ts` — 3 hooks: `useActivationsList` (con `placeholderData`), `useApiKeysOptions` (consume `?lite=1`), `useDeactivateActivation`. La mutación invalida `['activations']`, `['api-keys']` (porque `activations_used` cambia) y `['dashboard']`.
   - `ActivationsPage.tsx` — composición: header, tabs Todas/Activas/Inactivas, dropdown nativo `<select>` con todas las API keys + opción "Todas las API keys", search por dominio. `<DataTable>` de 6 columnas (Dominio + sub-línea con site_name de la API key, Token mascarado, Estado badge, Activada, Última verificación, Acciones). Pager Anterior/Siguiente. La acción "Desactivar" solo aparece cuando `is_active=true`; cuando ya está inactiva, muestra el `deactivated_at` en relativa.
   - `index.tsx` — entry-point.

**Decisiones de scope (deliberadas)**:

- **`<select>` nativo para el filtro de API key** — ~0 KB extra vs. instalar Radix Select. Se ve consistente con el resto de inputs gracias a las clases utility.
- **No se permite "reactivar"** — la activación se cierra; el sitio cliente debe pedir una nueva (`POST /activate`) para volver. Coherente con el flujo del dominio (CLAUDE.md §1.6); si en el futuro se necesita "rehydrate", se añade endpoint dedicado.
- **Sin acciones bulk** — el caso de uso típico es desactivar puntualmente por sitio comprometido o cliente que se baja. Si surge demanda real, columna 0 + barra flotante.
- **Token mascarado, no copiable** — no se expone el token en claro nunca (mismo principio que API Keys §5.2). El admin no debería tener motivo legítimo para copiarlo.

**Verificación pendiente del usuario** (en WP local):

- [ ] `cd imagina-updater-server/assets/admin && npm run build` — compila los **cinco** bundles.
- [ ] Submenu "Activaciones (nuevo)" carga; tabla pinta activaciones existentes con dominio, site_name de la API key, token mascarado, estado.
- [ ] Tabs Todas/Activas/Inactivas filtran correctamente.
- [ ] Dropdown de API key filtra por `api_key_id`.
- [ ] Búsqueda por dominio (LIKE).
- [ ] Acción Desactivar → confirm → fila pasa a "Inactiva", `activations_used` de la pantalla API Keys decrementa, KPI del Dashboard refleja el cambio.
- [ ] Activación ya inactiva muestra "Desactivada hace X" en lugar de botón.
- [ ] Network tab: `activations.js` solo se carga en su pantalla.

**Pendiente para 5.6 (Logs)**: visor con virtual scroll (TanStack Virtual), filtros por nivel, search, clear/download. Es la única pantalla con dataset potencialmente muy grande, así que activamos virtualization aquí por primera vez.

#### 5.6 Página: Logs

**Contenido**:
- Visor de logs con virtual scroll (TanStack Virtual o similar)
- Filtros por nivel (DEBUG, INFO, WARNING, ERROR)
- Búsqueda por texto
- Botón "Limpiar logs"
- Botón "Descargar logs"

**Endpoints nuevos**:
- `GET /admin/v1/logs?level=&search=&page=`
- `DELETE /admin/v1/logs`
- `GET /admin/v1/logs/download`

#### 5.7 Página: Configuración

**Contenido**:
- Tabs: General, Logging, Mantenimiento (DB migrations, clear caches)
- Forms standard de settings con validación

**Endpoints nuevos**:
- `GET /admin/v1/settings`
- `PUT /admin/v1/settings`
- `POST /admin/v1/maintenance/run-migrations`
- `POST /admin/v1/maintenance/clear-rate-limits`

#### 5.B Cliente — Rediseño posterior

Una vez completadas 5.1-5.7 y mergeadas, replicar el approach en el cliente. Pantallas:
- Dashboard (estado de conexión, próximas verificaciones)
- Configuración (URL servidor, API key, modo de visualización)
- Plugins disponibles (lista del servidor con instalación/habilitación)

**Verificación de Fase 5** (por pantalla):
- Bundle size < 250 KB gzip
- Lighthouse accessibility > 95
- Funcional: paridad completa con la pantalla PHP que reemplaza
- Sin estilos del bundle leakeando al resto de `wp-admin`
- i18n cargando strings en es_ES y en_US

---

## 7. Workflow para Claude Code

### 7.1 Antes de cada cambio
1. Leer secciones 1, 2, 3, 4 si es la primera intervención del día.
2. Identificar la fase del trabajo en sección 6.
3. Confirmar al usuario qué archivos vas a tocar y qué efectos colaterales hay.
4. Crear rama si no existe.

### 7.2 Durante el cambio
1. Cambios pequeños y atómicos. Un commit = una idea.
2. Si descubres un bug que no estaba en el plan, **reportarlo y preguntar**, no arreglarlo on-the-fly.
3. Tests manuales después de cada cambio significativo (ver verificaciones de cada fase).

### 7.3 Después del cambio
1. PHPCS limpio en los archivos tocados.
2. Si tocaste frontend: type-check, build sin errores, bundle size dentro del budget.
3. PR con descripción clara, referencia a la fase, screenshots si es UI.

### 7.4 Cómo testear cada cambio (resumen)
- **Backend PHP**: WP local (Local by Flywheel, Lando, Studio o similar). Tener los 3 plugins activos.
- **REST API**: Postman/Bruno con las dos auth (API key e iat).
- **Frontend**: `npm run dev` en `assets/admin/`, acceder a la pantalla del plugin con el bundle de dev cargado.
- **Sistema completo**: 2 instalaciones de WP locales, una como servidor y otra como cliente, simular el flujo completo.

### 7.5 Rollback
- Cada fase es una rama. Si algo se rompe en producción, revert del merge commit.
- Backups automáticos: la inyección de protección hace backup del ZIP original, mantener ese mecanismo.
- BD: las migraciones deben ser idempotentes y, cuando añadan columnas, usar `IF NOT EXISTS` o verificar con `SHOW COLUMNS`.

---

## 8. Glosario rápido

| Término | Significado |
|---|---|
| **API Key** | Token `ius_…` que el admin entrega al cliente. Solo activa sitios. |
| **Activation Token** | Token `iat_…` que el servidor devuelve al activar. Se usa para todas las llamadas REST después de activar. |
| **Slug override** | Slug alternativo para un plugin, sobrescribe el auto-generado. Útil cuando el SDK injector renombra. |
| **Heartbeat** | Verificación periódica (12h) que ejecuta WP-Cron para revalidar licencias. |
| **Grace period** | Tiempo (7 días en license-extension) que un sitio puede operar sin conexión al servidor antes de que su licencia se invalide. |
| **Premium plugin** | Plugin marcado `is_premium=1`. La extensión inyecta protección al subirlo. |
| **Protection code** | Código PHP único por plugin (`ILP_<hash>`) que verifica licencia al cargar el plugin. |
| **iaud-** | Prefijo de Tailwind para evitar colisiones con WP admin y otros plugins. |

---

## 9. Contacto y dudas

Si Claude Code encuentra una situación no cubierta por este documento:
1. **Detente**.
2. Resume la situación en chat con el usuario.
3. Propón 2-3 opciones de resolución con pros/contras.
4. Espera confirmación antes de proceder.

No improvises sobre arquitectura, seguridad ni convenciones. La consistencia importa más que la velocidad.

---

**Última actualización**: este documento se actualiza al cierre de cada fase. La versión actual cubre Fases 0-5. Cuando se abran nuevas fases (Fase 6+: SDK no-WP, multisite admin, etc.) se añadirán secciones aquí.
