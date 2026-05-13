# Imagina Updater Server — Admin SPA

Carpeta de código fuente del rediseño del admin del plugin servidor (CLAUDE.md §6 fase 5).

## Stack

- React 18, TypeScript 5 (strict + `noUncheckedIndexedAccess` + `exactOptionalPropertyTypes`)
- Vite 5 multi-entry (una entry por pantalla admin)
- Tailwind 3.4 con prefijo `iaud-` y `corePlugins.preflight: false`
- shadcn/ui sobre Tailwind (componentes copiados a `src/components/ui/`)
- TanStack Query 5 + TanStack Table 8
- Lucide React para iconos
- `@fontsource/inter` self-hosted (sin Google Fonts)

## Comandos

```bash
cd imagina-updater-server/assets/admin
npm install
npm run dev          # vite dev server en http://localhost:5174
npm run build        # tsc -b && vite build → ../dist/
npm run type-check   # tsc --noEmit
npm run lint         # eslint .
```

## Output del build

`vite build` emite a `imagina-updater-server/assets/dist/` (gitignored). Por cada entry en `vite.config.ts > PAGES` se generan tres archivos:

- `<entry>.js` — bundle JavaScript de la página
- `<entry>.css` — estilos compilados (Tailwind + globals)
- `<entry>.asset.php` — `array('dependencies' => …, 'version' => …)` que el PHP lee con `include` para hacer `wp_enqueue_script` con cache-busting

## Encolado en PHP

Cada pantalla del admin carga su bundle **solo** en el `$hook` correspondiente:

```php
public function enqueue_dashboard_assets($hook) {
    if ($hook !== 'imagina-updater_page_imagina-updater-dashboard') {
        return;
    }
    $asset = include IMAGINA_UPDATER_SERVER_PLUGIN_DIR . 'assets/dist/dashboard.asset.php';
    wp_enqueue_script(
        'iaud-dashboard',
        IMAGINA_UPDATER_SERVER_PLUGIN_URL . 'assets/dist/dashboard.js',
        $asset['dependencies'],
        $asset['version'],
        true
    );
    wp_enqueue_style(
        'iaud-dashboard',
        IMAGINA_UPDATER_SERVER_PLUGIN_URL . 'assets/dist/dashboard.css',
        array(),
        $asset['version']
    );
    wp_localize_script('iaud-dashboard', 'iaudConfig', array(
        'apiUrl'   => esc_url_raw(rest_url('imagina-updater/v1/')),
        'adminUrl' => esc_url_raw(rest_url('imagina-updater/admin/v1/')),
        'nonce'    => wp_create_nonce('wp_rest'),
    ));
}
```

## Ámbito CSS

Las variables CSS de la SPA viven scoped en `.iaud-app`, NO en `:root`. La view PHP debe envolver el contenedor con esa clase:

```php
<div class="wrap iaud-app">
    <div id="iaud-dashboard"></div>
</div>
```

Esto garantiza que ningún token (color, radius, fuente) se filtre al resto del wp-admin.

## Páginas

Lista actual en `vite.config.ts > PAGES`. Para añadir una nueva:

1. Crear `src/pages/<slug>/index.tsx` siguiendo el patrón de `dashboard/`.
2. Añadir el slug al array `PAGES` en `vite.config.ts`.
3. Añadir el `enqueue_<slug>_assets` correspondiente en PHP.
