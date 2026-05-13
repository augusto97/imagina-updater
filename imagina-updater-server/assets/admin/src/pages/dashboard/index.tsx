import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import '@/styles/globals.css';
import { DashboardPage } from './DashboardPage';

/**
 * Entry-point del bundle Dashboard (CLAUDE.md §6 fase 5.1).
 *
 * Monta en el contenedor `#iaud-dashboard` que el PHP renderiza desde
 * `Imagina_Updater_Server_Admin::render_spa_dashboard_page()`.
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

const container = document.getElementById('iaud-dashboard');
if (container) {
  createRoot(container).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <DashboardPage />
      </QueryClientProvider>
    </StrictMode>,
  );
}
