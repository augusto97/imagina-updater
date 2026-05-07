import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { adminDelete, adminGet, adminPost, adminPut } from '@/lib/api';
import type {
  ApiKeyFormValues,
  ApiKeyListResponse,
  ApiKeyMutationResponse,
  PluginGroupLite,
  PluginLite,
  StatusFilter,
} from './types';

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
    queryFn: () => adminGet<PluginLite[]>('plugins?lite=1'),
    staleTime: 60 * 1000,
  });
}

export function usePluginGroupsLite() {
  return useQuery({
    queryKey: ['plugin-groups-lite'],
    queryFn: () => adminGet<PluginGroupLite[]>('plugin-groups?lite=1'),
    staleTime: 60 * 1000,
  });
}
