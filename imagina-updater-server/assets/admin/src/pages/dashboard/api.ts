import { useQuery } from '@tanstack/react-query';
import { adminGet } from '@/lib/api';

/**
 * Hooks de fetching para el Dashboard. Cada uno mapea 1:1 con un
 * endpoint del namespace `imagina-updater/admin/v1/dashboard/...`.
 *
 * `staleTime` por defecto del QueryClient (30 s) es razonable: el
 * dashboard se mira de pasada, no requiere refetch agresivo. Para
 * forzar refresco manual hay un botón "Actualizar" en la UI.
 */

export interface DashboardStats {
  total_plugins: number;
  active_api_keys: number;
  active_activations: number;
  downloads_24h: number;
}

export interface DownloadsDayPoint {
  day: string; // YYYY-MM-DD
  count: number;
}

export interface RecentDownload {
  id: number;
  version: string;
  ip_address: string | null;
  downloaded_at: string;
  plugin_slug: string | null;
  plugin_name: string | null;
  site_name: string | null;
}

export interface TopPlugin {
  id: number;
  slug: string;
  name: string;
  current_version: string;
  downloads: number;
}

export function useDashboardStats() {
  return useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: () => adminGet<DashboardStats>('dashboard/stats'),
  });
}

export function useDownloads30d() {
  return useQuery({
    queryKey: ['dashboard', 'downloads-30d'],
    queryFn: () => adminGet<DownloadsDayPoint[]>('dashboard/downloads-30d'),
  });
}

export function useRecentDownloads() {
  return useQuery({
    queryKey: ['dashboard', 'recent-downloads'],
    queryFn: () => adminGet<RecentDownload[]>('dashboard/recent-downloads'),
  });
}

export function useTopPlugins() {
  return useQuery({
    queryKey: ['dashboard', 'top-plugins'],
    queryFn: () => adminGet<TopPlugin[]>('dashboard/top-plugins'),
  });
}
