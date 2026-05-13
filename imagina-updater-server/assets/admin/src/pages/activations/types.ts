export type ActivationsStatusFilter = 'all' | 'active' | 'inactive';

export interface ActivationRow {
  id: number;
  api_key_id: number;
  site_name: string | null;
  site_domain: string;
  token_masked: string;
  is_active: boolean;
  activated_at: string;
  last_verified: string | null;
  deactivated_at: string | null;
}

export interface ActivationsListResponse {
  items: ActivationRow[];
  total: number;
  page: number;
  per_page: number;
}

export interface ApiKeyOptionLite {
  id: number;
  site_name: string;
}
