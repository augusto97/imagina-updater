/**
 * Tipos compartidos entre páginas del admin SPA.
 *
 * Para tipos específicos de una pantalla, preferir colocarlos en
 * `pages/<page>/types.ts`.
 */

export interface Plugin {
  id: number;
  slug: string;
  slug_override: string | null;
  name: string;
  current_version: string;
  is_premium: boolean;
  description: string | null;
  created_at: string;
  updated_at: string;
}

export interface ApiKey {
  id: number;
  name: string;
  site_url: string;
  access_type: 'all' | 'plugins' | 'groups';
  activations_used: number;
  max_activations: number;
  is_active: boolean;
  created_at: string;
  last_used_at: string | null;
}

export interface DashboardStats {
  total_plugins: number;
  active_api_keys: number;
  active_activations: number;
  downloads_24h: number;
}
