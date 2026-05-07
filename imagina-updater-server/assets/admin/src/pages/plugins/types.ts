export interface PluginRow {
  id: number;
  slug: string;
  slug_override: string | null;
  effective_slug: string;
  name: string;
  description: string | null;
  author: string | null;
  homepage: string | null;
  current_version: string;
  file_size: number;
  uploaded_at: string;
  is_premium: boolean;
  group_ids: number[];
  total_downloads: number;
}

export interface PluginListResponse {
  items: PluginRow[];
  total: number;
  page: number;
  per_page: number;
  license_extension_active: boolean;
}

export interface PluginVersion {
  id: number;
  version: string;
  file_size: number;
  changelog: string | null;
  uploaded_at: string;
}

export interface PluginUploadValues {
  file: File;
  changelog: string;
  description: string;
  is_premium: boolean;
  group_ids: number[];
}

export interface PluginEditValues {
  slug_override: string;
  description: string;
  group_ids: number[];
}
