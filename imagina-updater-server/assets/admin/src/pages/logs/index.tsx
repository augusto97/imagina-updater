import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

import '@/styles/globals.css';
import { LogsPage } from './LogsPage';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 10 * 1000,
      refetchOnWindowFocus: false,
      retry: 1,
    },
  },
});

const container = document.getElementById('iaud-logs');
if (container) {
  createRoot(container).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <LogsPage />
      </QueryClientProvider>
    </StrictMode>,
  );
}
