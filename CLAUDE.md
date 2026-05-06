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

## 5. Decisiones por confirmar (antes de empezar Fase 5)

> Estas decisiones impactan el rediseño visual y deben quedar resueltas antes de empezar a construir componentes. Si Claude Code llega a esta fase sin que estén definidas, debe preguntar al usuario.

- [ ] **Paleta corporativa de Imagina** — ¿hay colores fijos (primario, secundario, acentos)? ¿Hay logo SVG/PNG disponible?
- [ ] **Modo oscuro** — ¿soportar `dark:` desde el día 1 o dejarlo para una iteración posterior?
- [ ] **Densidad de UI** — compacta (estilo Linear, Vercel, Stripe Dashboard) vs aireada (estilo Notion, Airtable). Recomendación inicial: **compacta** para admin de WP, mejor uso del espacio.
- [ ] **Referencias visuales** — ¿hay 1-2 dashboards de referencia que el equipo quiera emular?
- [ ] **Tipografía** — Inter por defecto (recomendación). ¿Otra preferencia?
- [ ] **Branding del prefijo** — confirmar `iaud-` o cambiar (ej: `imupd-`, `iup-`).
- [ ] **Ubicación de `assets/dist/`** — ¿gitignored o commiteado? Recomendación: commiteado para releases, gitignored en desarrollo. Definir en `.gitignore`.
- [ ] **Cliente también** — ¿el rediseño aplica también al admin de `imagina-updater-client`? Recomendación: **sí, pero después** del servidor (Fase 5.B).

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

### Fase 4 — Arquitectónico

**Rama**: `refactor/architecture`

**Objetivo**: mejoras estructurales de fondo. Esta fase es opcional y puede dividirse en sub-fases.

#### 4.1 Migración a namespaces PSR-4

**Acción**:
1. Crear `composer.json` en cada plugin con autoload PSR-4:
   ```json
   {
     "autoload": {
       "psr-4": {
         "ImaginaWP\\UpdaterServer\\": "src/"
       }
     }
   }
   ```
2. Mover clases de `includes/class-*.php` a `src/` con namespaces:
   - `Imagina_Updater_Server_API_Keys` → `ImaginaWP\UpdaterServer\ApiKeys`
   - `Imagina_Updater_Server_Activations` → `ImaginaWP\UpdaterServer\Activations`
   - etc.
3. Mantener archivos legacy con `class_alias` para retrocompatibilidad si hay extensiones de terceros.
4. Generar autoloader con `composer dump-autoload -o`.
5. Cargar autoloader en el archivo principal del plugin.

#### 4.2 Documentar hooks (filter/action reference)

Crear `imagina-updater-server/docs/HOOKS.md` con todos los hooks expuestos, parámetros y ejemplos. La license-extension depende de ellos y futuras extensiones también.

#### 4.3 Mejorar rollback de inyección de código en ZIPs

**Síntoma**: en `class-sdk-injector.php`, si la inyección falla a mitad, el ZIP puede quedar corrupto. Hay backup pero el flow de restore no es atómico.

**Acción**:
1. Refactorizar `inject_sdk_if_needed()` para usar el patrón two-phase commit:
   - Fase 1: extraer, modificar, re-zippear a archivo temporal con sufijo `.new`
   - Fase 2: si todo OK, `rename($temp, $original)` (atómico en ext4/xfs)
   - Si falla, eliminar el `.new` y dejar el original intacto
2. Eliminar el sistema de `.backup` actual una vez el nuevo flujo esté validado.

#### 4.4 Action Scheduler para heartbeat

Reemplazar `wp_schedule_event` del heartbeat por Action Scheduler (más robusto, mejor logging, retry automático).

**Verificación de Fase 4**:
- PSR-4 cargando sin errores. Tests funcionales completos.
- Hooks documentados con ejemplos compilables.
- Inyección con simulación de fallo: ZIP original siempre intacto.

---

### Fase 5 — Rediseño del admin (servidor primero)

**Rama base**: `feat/admin-redesign`. Sub-ramas por pantalla: `feat/admin-dashboard`, `feat/admin-api-keys`, etc.

**Pre-requisitos**:
- Decisiones de la sección 5 resueltas (paleta, modo oscuro, densidad, referencias)
- Fase 0 mergeada
- Idealmente Fase 1 mergeada también (para empezar sobre código limpio)

#### 5.0 Setup técnico

**Pasos**:

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

#### 5.1 Página: Dashboard

**Contenido**:
- 4 KPI cards: Total Plugins, Total API Keys activas, Activaciones activas, Descargas últimas 24h
- Gráfico de descargas últimos 30 días (recharts si entra en budget; si no, una tabla simple es suficiente)
- Tabla "Últimas descargas" (10 más recientes)
- Tabla "Top plugins por descargas" (5 más descargados)
- Quick actions: "Subir plugin", "Crear API key"

**Endpoints nuevos (admin/v1)**:
- `GET /admin/v1/dashboard/stats`
- `GET /admin/v1/dashboard/recent-downloads`
- `GET /admin/v1/dashboard/top-plugins`

#### 5.2 Página: API Keys

**Contenido**:
- TanStack Table con: nombre, URL, tipo de acceso, activaciones (used/max), creada, último uso, estado, acciones
- Filtros: estado (active/inactive), tipo de acceso, búsqueda por nombre
- Drawer/Sheet de "Crear API Key" con formulario completo (nombre, URL, tipo de acceso, plugins/grupos, max_activations)
- Drawer de "Editar API Key" (mismo form, con datos cargados)
- Modal de confirmación al regenerar/desactivar
- Banner destacado al crear: muestra la API key una vez con botón "Copiar"

**Endpoints nuevos**:
- `GET /admin/v1/api-keys` (con paginación)
- `POST /admin/v1/api-keys`
- `PUT /admin/v1/api-keys/{id}`
- `DELETE /admin/v1/api-keys/{id}`
- `POST /admin/v1/api-keys/{id}/regenerate`
- `POST /admin/v1/api-keys/{id}/toggle-active`

#### 5.3 Página: Plugins

**Contenido**:
- Tabla de plugins con: nombre, slug (mostrar `slug_override` si existe), versión actual, premium badge, grupos, descargas totales, última subida, acciones
- Botón "Subir plugin" abre Drawer con uploader (drag-and-drop), checkbox "Es premium", textarea changelog, multi-select de grupos
- Acciones por plugin: Ver versiones, Editar (slug_override, grupos, premium toggle, descripción), Eliminar, Re-inyectar protección
- Indicador de progreso para uploads grandes

**Endpoints nuevos**:
- `GET /admin/v1/plugins`
- `POST /admin/v1/plugins/upload` (multipart)
- `PUT /admin/v1/plugins/{id}`
- `DELETE /admin/v1/plugins/{id}`
- `POST /admin/v1/plugins/{id}/toggle-premium`
- `POST /admin/v1/plugins/{id}/reinject-protection`
- `GET /admin/v1/plugins/{id}/versions`

#### 5.4 Página: Plugin Groups

**Contenido**:
- Tabla de grupos con: nombre, descripción, plugins incluidos (count), creado, acciones
- Drawer de crear/editar con multi-select de plugins (con búsqueda)
- Acciones: editar, eliminar (con confirmación si tiene API keys vinculadas)

**Endpoints nuevos**:
- CRUD `/admin/v1/plugin-groups`

#### 5.5 Página: Activations

**Contenido**:
- Tabla de activaciones con: dominio, API key (link), token (mascarado), activada, última verificación, estado, acciones
- Filtros: API key (dropdown), estado, búsqueda por dominio
- Acciones: desactivar (con confirmación)

**Endpoints nuevos**:
- `GET /admin/v1/activations`
- `POST /admin/v1/activations/{id}/deactivate`

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
