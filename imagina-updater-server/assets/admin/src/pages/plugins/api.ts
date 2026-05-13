import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import {
  adminDelete,
  adminGet,
  adminPost,
  adminPostMultipart,
  adminPut,
} from '@/lib/api';
import type { PluginGroupLite } from '../api-keys/types';
import type {
  PluginEditValues,
  PluginListResponse,
  PluginRow,
  PluginUploadValues,
  PluginVersion,
} from './types';

interface ListParams {
  page: number;
  per_page: number;
  search: string;
}

interface MutationResponse {
  item: PluginRow;
}

export function usePluginsList(params: ListParams) {
  const query = new URLSearchParams({
    page: String(params.page),
    per_page: String(params.per_page),
    search: params.search,
  }).toString();
  return useQuery({
    queryKey: ['plugins', 'list', params],
    queryFn: () => adminGet<PluginListResponse>(`plugins?${query}`),
    placeholderData: (prev) => prev,
  });
}

export function usePluginVersions(pluginId: number | null) {
  return useQuery({
    queryKey: ['plugins', 'versions', pluginId],
    queryFn: () => adminGet<PluginVersion[]>(`plugins/${pluginId}/versions`),
    enabled: pluginId !== null,
  });
}

export function usePluginGroupsLite() {
  return useQuery({
    queryKey: ['plugin-groups-lite'],
    queryFn: () => adminGet<PluginGroupLite[]>('plugin-groups'),
    staleTime: 60 * 1000,
  });
}

export function useUploadPlugin(
  onProgress?: (percent: number) => void,
) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: PluginUploadValues) => {
      const fd = new FormData();
      fd.append('plugin_file', values.file);
      if (values.changelog) fd.append('changelog', values.changelog);
      if (values.description) fd.append('description', values.description);
      if (values.is_premium) fd.append('is_premium', '1');
      values.group_ids.forEach((gid) =>
        fd.append('group_ids[]', String(gid)),
      );
      return adminPostMultipart<MutationResponse>('plugins/upload', fd, {
        ...(onProgress ? { onProgress } : {}),
      });
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['plugins'] });
      void qc.invalidateQueries({ queryKey: ['plugins-lite'] });
      void qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

export function useUpdatePlugin() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, values }: { id: number; values: PluginEditValues }) =>
      adminPut<MutationResponse>(`plugins/${id}`, values),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['plugins'] });
      void qc.invalidateQueries({ queryKey: ['plugins-lite'] });
    },
  });
}

export function useDeletePlugin() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) =>
      adminDelete<{ deleted: boolean; id: number }>(`plugins/${id}`),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['plugins'] });
      void qc.invalidateQueries({ queryKey: ['plugins-lite'] });
      void qc.invalidateQueries({ queryKey: ['dashboard'] });
    },
  });
}

export function useTogglePremium() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ id, is_premium }: { id: number; is_premium: boolean }) =>
      adminPost<MutationResponse>(`plugins/${id}/toggle-premium`, {
        is_premium,
      }),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['plugins'] });
    },
  });
}

export function useReinjectProtection() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) =>
      adminPost<MutationResponse & { reinjected: boolean }>(
        `plugins/${id}/reinject-protection`,
        {},
      ),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['plugins'] });
    },
  });
}
