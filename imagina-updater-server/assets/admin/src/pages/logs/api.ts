import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { adminDelete, adminGet } from '@/lib/api';
import type { LogLevelFilter, LogsListResponse } from './types';

interface ListParams {
  page: number;
  per_page: number;
  level: LogLevelFilter;
  search: string;
}

export function useLogsList(params: ListParams) {
  const query = new URLSearchParams({
    page: String(params.page),
    per_page: String(params.per_page),
    level: params.level === 'all' ? '' : params.level,
    search: params.search,
  }).toString();
  return useQuery({
    queryKey: ['logs', 'list', params],
    queryFn: () => adminGet<LogsListResponse>(`logs?${query}`),
    placeholderData: (prev) => prev,
  });
}

export function useClearLogs() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () => adminDelete<{ cleared: boolean }>('logs'),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['logs'] });
    },
  });
}

/**
 * URL absoluta del endpoint de descarga, lista para usar como href.
 * Incluye `?_wpnonce=` para que el navegador autentique la GET sin
 * tener que armar XHR (más simple para "clic → guardar como").
 */
export function getLogsDownloadUrl(): string | null {
  const cfg = window.iaudConfig;
  if (!cfg) return null;
  const base = cfg.adminUrl.replace(/\/$/, '');
  return `${base}/logs/download?_wpnonce=${encodeURIComponent(cfg.nonce)}`;
}
