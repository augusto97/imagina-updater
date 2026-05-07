import { defineConfig, type Plugin } from 'vite';
import react from '@vitejs/plugin-react';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { writeFileSync } from 'node:fs';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Páginas admin del plugin servidor. Cada entrada compila a un bundle
 * independiente que el PHP encolla solo en su pantalla correspondiente
 * (ver §3.2 de CLAUDE.md: enqueue condicional por $hook).
 *
 * Para añadir una pantalla nueva: crear `src/pages/<name>/index.tsx` y
 * añadir el slug aquí.
 */
const PAGES = ['dashboard', 'api-keys', 'plugins', 'plugin-groups'] as const;

/**
 * Emite, junto a cada bundle, un archivo `<entry>.asset.php` que el
 * PHP lee con `include` para obtener la lista de dependencies y la
 * version (cache-busting). Convención compatible con la usada por
 * @wordpress/dependency-extraction-webpack-plugin.
 *
 * En esta primera iteración, React y ReactDOM NO se externalizan
 * (van bundleados). Por eso `dependencies` queda vacío. Si en el
 * futuro se externaliza a wp.element, esta función añade `'wp-element'`,
 * `'wp-i18n'`, etc.
 */
function emitWordpressAssetFiles(): Plugin {
  return {
    name: 'emit-wordpress-asset-files',
    apply: 'build',
    writeBundle(options, bundle) {
      const outDir = options.dir;
      if (!outDir) return;

      for (const fileName of Object.keys(bundle)) {
        const chunk = bundle[fileName];
        if (!chunk || chunk.type !== 'chunk' || !chunk.isEntry) continue;

        const entryName = chunk.name;
        const version = (chunk as { code?: string }).code
          ? simpleHash((chunk as { code: string }).code)
          : Date.now().toString(36);

        const phpContents =
          `<?php\n` +
          `// Generated automatically by Vite. Do not edit by hand.\n` +
          `return array(\n` +
          `\t'dependencies' => array(),\n` +
          `\t'version' => '${version}',\n` +
          `);\n`;

        writeFileSync(`${outDir}/${entryName}.asset.php`, phpContents);
      }
    },
  };
}

function simpleHash(input: string): string {
  let h = 0;
  for (let i = 0; i < input.length; i += 1) {
    h = (h * 31 + input.charCodeAt(i)) | 0;
  }
  return (h >>> 0).toString(16);
}

export default defineConfig({
  plugins: [react(), emitWordpressAssetFiles()],
  resolve: {
    alias: { '@': resolve(__dirname, 'src') },
  },
  build: {
    outDir: '../dist',
    emptyOutDir: true,
    sourcemap: false,
    cssCodeSplit: true,
    rollupOptions: {
      input: Object.fromEntries(
        PAGES.map((p) => [p, resolve(__dirname, `src/pages/${p}/index.tsx`)]),
      ),
      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        assetFileNames: (assetInfo) => {
          // CSS toma el nombre de la entry para que el enqueue PHP lo
          // localice como `<entry>.css` (no `style-<hash>.css`).
          if (assetInfo.name?.endsWith('.css')) {
            return '[name].css';
          }
          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
  server: {
    port: 5174,
    strictPort: true,
  },
});
