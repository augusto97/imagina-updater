export interface PluginGroupRow {
  id: number;
  name: string;
  description: string | null;
  plugin_count: number;
  linked_api_keys_count: number;
  created_at: string;
  /** Solo presente cuando se carga un único grupo (load_serialized). */
  plugin_ids?: number[];
}

export interface PluginGroupListResponse {
  items: PluginGroupRow[];
}

export interface PluginGroupFormValues {
  name: string;
  description: string;
  plugin_ids: number[];
}
