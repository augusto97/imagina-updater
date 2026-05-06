import type { Config } from 'tailwindcss';
import animate from 'tailwindcss-animate';

/**
 * Tailwind config para el admin SPA del servidor.
 *
 * Decisiones clave (CLAUDE.md §5):
 * - `prefix: 'iaud-'` evita colisiones con clases del propio wp-admin
 *   y de cualquier otro plugin que use Tailwind sin prefijar.
 * - `corePlugins.preflight: false`: NO resetear estilos globales del
 *   admin de WP. El reset que sí queremos vive scoped en `.iaud-app`
 *   dentro de `globals.css`.
 * - Tokens semánticos (`bg-iaud-background`, `text-iaud-foreground`,
 *   etc.) leen variables CSS definidas en `globals.css`. Esto permite
 *   cambiar la paleta corporativa modificando solo las variables.
 */
const config = {
  prefix: 'iaud-',
  darkMode: 'class', // anclado para Fase 5.B (modo oscuro diferido).
  content: ['./src/**/*.{ts,tsx}', './index.html'],
  corePlugins: {
    preflight: false,
  },
  theme: {
    container: {
      center: true,
      padding: '1rem',
    },
    extend: {
      colors: {
        border: 'hsl(var(--iaud-border))',
        input: 'hsl(var(--iaud-input))',
        ring: 'hsl(var(--iaud-ring))',
        background: 'hsl(var(--iaud-background))',
        foreground: 'hsl(var(--iaud-foreground))',
        primary: {
          DEFAULT: 'hsl(var(--iaud-primary))',
          foreground: 'hsl(var(--iaud-primary-foreground))',
        },
        secondary: {
          DEFAULT: 'hsl(var(--iaud-secondary))',
          foreground: 'hsl(var(--iaud-secondary-foreground))',
        },
        muted: {
          DEFAULT: 'hsl(var(--iaud-muted))',
          foreground: 'hsl(var(--iaud-muted-foreground))',
        },
        accent: {
          DEFAULT: 'hsl(var(--iaud-accent))',
          foreground: 'hsl(var(--iaud-accent-foreground))',
        },
        destructive: {
          DEFAULT: 'hsl(var(--iaud-destructive))',
          foreground: 'hsl(var(--iaud-destructive-foreground))',
        },
        card: {
          DEFAULT: 'hsl(var(--iaud-card))',
          foreground: 'hsl(var(--iaud-card-foreground))',
        },
        popover: {
          DEFAULT: 'hsl(var(--iaud-popover))',
          foreground: 'hsl(var(--iaud-popover-foreground))',
        },
      },
      borderRadius: {
        lg: 'var(--iaud-radius)',
        md: 'calc(var(--iaud-radius) - 2px)',
        sm: 'calc(var(--iaud-radius) - 4px)',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      keyframes: {
        'accordion-down': {
          from: { height: '0' },
          to: { height: 'var(--radix-accordion-content-height)' },
        },
        'accordion-up': {
          from: { height: 'var(--radix-accordion-content-height)' },
          to: { height: '0' },
        },
      },
      animation: {
        'accordion-down': 'accordion-down 0.2s ease-out',
        'accordion-up': 'accordion-up 0.2s ease-out',
      },
    },
  },
  plugins: [animate],
} satisfies Config;

export default config;
