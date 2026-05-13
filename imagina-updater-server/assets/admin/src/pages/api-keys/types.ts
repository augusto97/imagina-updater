export type AccessType = 'all' | 'specific' | 'groups';
export type StatusFilter = 'all' | 'active' | 'inactive';

export interface ApiKey {
  id: number;
  site_name: string;
  site_url: string;
  api_key_masked: string;
  is_active: boolean;
  access_type: AccessType;
  allowed_plugins: number[];
  allowed_groups: number[];
  max_activations: number;
  activations_used: number;
  created_at: string;
  last_used: string | null;
}

export interface ApiKeyListResponse {
  items: ApiKey[];
  total: number;
  page: number;
  per_page: number;
}

export interface ApiKeyMutationResponse {
  item: ApiKey;
  /** Solo presente en create + regenerate. */
  plain_key?: string;
}

export interface PluginLite {
  id: number;
  slug: string;
  slug_override: string | null;
  effective_slug: string;
  name: string;
}

export interface PluginGroupLite {
  id: number;
  name: string;
}

export interface ApiKeyFormValues {
  site_name: string;
  site_url: string;
  access_type: AccessType;
  allowed_plugins: number[];
  allowed_groups: number[];
  max_activations: number;
}
