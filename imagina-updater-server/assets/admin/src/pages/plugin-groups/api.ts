import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import {
  adminDelete,
  adminGet,
  adminPost,
  adminPut,
} from '@/lib/api';
import type { PluginLite } from '../api-keys/types';
import type {
  PluginGroupFormValues,
  PluginGroupListResponse,
  PluginGroupRow,
} from './types';

interface MutationResponse {
  item: PluginGroupRow;
}

export function usePluginGroupsList() {
  return useQuery({
    queryKey: ['plugin-groups', 'list'],
    queryFn: () => adminGet<PluginGroupListResponse>('plugin-groups'),
  });
}

export function usePluginsLite() {
  return useQuery({
    queryKey: ['plugins-lite'],
    queryFn: () => adminGet<PluginLite[]>('plugins?lite=1'),
    staleTime: 60 * 1000,
  });
}

export function useCreatePluginGroup() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: PluginGroupFormValues) =>
      adminPost<MutationResponse>('plugin-groups', values),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['plugin-groups'] });
      void qc.invalidateQueries({ queryKey: ['plugin-groups-lite'] });
    },
  });
}

export function useUpdatePluginGroup() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({
      id,
      values,
    }: {
      id: number;
      values: PluginGroupFormValues;
    }) => adminPut<MutationResponse>(`plugin-groups/${id}`, values),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['plugin-groups'] });
      void qc.invalidateQueries({ queryKey: ['plugin-groups-lite'] });
      // Plugins list muestra count de grupos por plugin → refrescar.
      void qc.invalidateQueries({ queryKey: ['plugins'] });
    },
  });
}

export function useDeletePluginGroup() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) =>
      adminDelete<{ deleted: boolean; id: number; orphaned_api_keys_count: number }>(
        `plugin-groups/${id}`,
      ),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['plugin-groups'] });
      void qc.invalidateQueries({ queryKey: ['plugin-groups-lite'] });
      void qc.invalidateQueries({ queryKey: ['plugins'] });
      void qc.invalidateQueries({ queryKey: ['api-keys'] });
    },
  });
}
