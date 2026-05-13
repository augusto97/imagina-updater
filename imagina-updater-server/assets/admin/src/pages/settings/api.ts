import {
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { adminGet, adminPost, adminPut } from '@/lib/api';
import type { SettingsResponse, SettingsValues } from './types';

export function useSettings() {
  return useQuery({
    queryKey: ['settings'],
    queryFn: () => adminGet<SettingsResponse>('settings'),
  });
}

export function useUpdateSettings() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (values: Partial<SettingsValues>) =>
      adminPut<SettingsResponse>('settings', values),
    onSuccess: (data) => {
      qc.setQueryData(['settings'], data);
    },
  });
}

export function useRunMigrations() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: () =>
      adminPost<{ success: boolean; db_version: string }>(
        'maintenance/run-migrations',
        {},
      ),
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['settings'] });
    },
  });
}

export function useClearRateLimits() {
  return useMutation({
    mutationFn: () =>
      adminPost<{ success: boolean; message: string }>(
        'maintenance/clear-rate-limits',
        {},
      ),
  });
}
