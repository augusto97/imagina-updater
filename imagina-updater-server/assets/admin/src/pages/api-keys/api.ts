import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { adminGet, adminPost } from '@/lib/api';
import type {
  ApiKeyFormValues,
  ApiKeyListResponse,
  ApiKeyMutationResponse,
  PluginGroupLite,
  PluginLite,
  StatusFilter,
} from './types';

/**
 * adminPut/adminDelete no existen en lib/api.ts (lib mantiene solo
 * GET/POST). Para PUT/DELETE armamos fetch inline aquí — cambiar a
 * helpers compartidos cuando una segunda pantalla los necesite.
 */
async function adminPut<T>(path: string, body: unknown): Promise<T> {
  return adminFetch<T>(path, 'PUT', body);
}
async function adminDelete<T>(path: string): Promise<T> {
  return adminFetch<T>(path, 'DELETE');
}
async function adminFetch<T>(
  path: string,
  method: 'PUT' | 'DELETE',
  body?: unknown,
): Promise<T> {
  const cfg = window.iaudConfig;
  if (!cfg) throw new Error('iaudConfig no disponible');
  const url =
    cfg.adminUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');

  const response = await fetch(url, {
    method,
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': cfg.nonce,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
  });

  if (!response.ok) {
    let errBody: { message?: string } = {};
    try {
      errBody = (await response.json()) as typeof errBody;
    } catch {
      /* opaque */
    }
    throw new Error(errBody.message ?? `Request failed with ${response.status}`);
  }
  return (await response.json()) as T;
}

interface ListParams {
  page: number;
  per_page: number;
  status: StatusFilter;
  search: string;
}

export function useApiKeysList(params: ListParams) {
  const query = new URLSearchParams({
    page: String(params.page),
    per_page: String(params.per_page),
    status: params.status,
    search: params.search,
  }).toString();
  return useQuery({
    queryKey: ['api-keys', 'list', params],
    queryFn: () => adminGet<ApiKeyListResponse>(`api-keys?${query}`),
    placeholderData: (prev) => prev,
  });
}

export function useCreateApiKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: ApiKeyFormValues) =>
      adminPost<ApiKeyMutationResponse>('api-keys', values),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['api-keys'] });
      void qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

export function useUpdateApiKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      values,
    }: {
      id: number;
      values: ApiKeyFormValues;
    }) => adminPut<ApiKeyMutationResponse>(`api-keys/${id}`, values),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['api-keys'] });
    },
  });
}

export function useDeleteApiKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) =>
      adminDelete<{ deleted: boolean; id: number }>(`api-keys/${id}`),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['api-keys'] });
      void qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

export function useToggleApiKeyActive() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, is_active }: { id: number; is_active: boolean }) =>
      adminPost<ApiKeyMutationResponse>(`api-keys/${id}/toggle-active`, {
        is_active,
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['api-keys'] });
      void qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

export function useRegenerateApiKey() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) =>
      adminPost<ApiKeyMutationResponse>(`api-keys/${id}/regenerate`, {}),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['api-keys'] });
    },
  });
}

export function usePluginsLite() {
  return useQuery({
    queryKey: ['plugins-lite'],
    queryFn: () => adminGet<PluginLite[]>('plugins'),
    staleTime: 60 * 1000,
  });
}

export function usePluginGroupsLite() {
  return useQuery({
    queryKey: ['plugin-groups-lite'],
    queryFn: () => adminGet<PluginGroupLite[]>('plugin-groups'),
    staleTime: 60 * 1000,
  });
}
