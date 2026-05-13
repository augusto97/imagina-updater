import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { adminGet, adminPost } from '@/lib/api';
import type {
  ActivationRow,
  ActivationsListResponse,
  ActivationsStatusFilter,
  ApiKeyOptionLite,
} from './types';

interface ListParams {
  page: number;
  per_page: number;
  status: ActivationsStatusFilter;
  api_key_id: number;
  search: string;
}

export function useActivationsList(params: ListParams) {
  const query = new URLSearchParams({
    page: String(params.page),
    per_page: String(params.per_page),
    status: params.status,
    api_key_id: String(params.api_key_id),
    search: params.search,
  }).toString();
  return useQuery({
    queryKey: ['activations', 'list', params],
    queryFn: () => adminGet<ActivationsListResponse>(`activations?${query}`),
    placeholderData: (prev) => prev,
  });
}

export function useApiKeysOptions() {
  return useQuery({
    queryKey: ['api-keys-lite'],
    queryFn: () => adminGet<ApiKeyOptionLite[]>('api-keys?lite=1'),
    staleTime: 60 * 1000,
  });
}

export function useDeactivateActivation() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) =>
      adminPost<{ item: ActivationRow }>(`activations/${id}/deactivate`, {}),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['activations'] });
      // El conteo de activations_used de la API key cambia → invalidar.
      void qc.invalidateQueries({ queryKey: ['api-keys'] });
      void qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}
