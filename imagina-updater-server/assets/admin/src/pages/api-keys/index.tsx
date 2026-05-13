import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import '@/styles/globals.css';
import { ApiKeysPage } from './ApiKeysPage';

/**
 * Entry-point del bundle API Keys (CLAUDE.md §6 fase 5.2). Monta en
 * `#iaud-api-keys` que el PHP renderiza desde
 * `Imagina_Updater_Server_Admin::render_spa_api_keys_page()`.
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

const container = document.getElementById('iaud-api-keys');
if (container) {
  createRoot(container).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <ApiKeysPage />
      </QueryClientProvider>
    </StrictMode>,
  );
}
