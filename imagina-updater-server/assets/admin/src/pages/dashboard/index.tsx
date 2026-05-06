import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import {
  QueryClient,
  QueryClientProvider,
} from '@tanstack/react-query';

import '@/styles/globals.css';

/**
 * Entry-point de la página Dashboard del admin del servidor.
 *
 * Esta primera versión es un placeholder visible para validar que:
 * 1. El bundle se carga (PHP encolla el JS).
 * 2. El contenedor `#iaud-dashboard` existe en la página.
 * 3. Tailwind con prefijo `iaud-` está activo (clases utility funcionan).
 * 4. La fuente Inter se carga via @fontsource sin pegar a Google Fonts.
 *
 * Las KPI cards, gráficos y tablas reales (CLAUDE.md §6 fase 5.1) se
 * implementan en commits posteriores tras crear los componentes
 * shadcn base.
 */

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30 * 1000,
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
});

function Dashboard() {
  return (
    <div className="iaud-app iaud-min-h-[200px] iaud-p-6">
      <header className="iaud-mb-4">
        <h1 className="iaud-text-2xl iaud-font-semibold iaud-text-foreground">
          Imagina Updater — Dashboard
        </h1>
        <p className="iaud-text-sm iaud-text-muted-foreground">
          Rediseño en construcción (Fase 5.0). Esta vista es un placeholder
          mientras se implementan las KPI cards, gráficos y tablas.
        </p>
      </header>
      <div className="iaud-rounded-lg iaud-border iaud-border-border iaud-bg-card iaud-p-4 iaud-text-sm iaud-text-card-foreground">
        El bundle React se ha cargado correctamente.
      </div>
    </div>
  );
}

const container = document.getElementById('iaud-dashboard');
if (container) {
  createRoot(container).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <Dashboard />
      </QueryClientProvider>
    </StrictMode>,
  );
}
