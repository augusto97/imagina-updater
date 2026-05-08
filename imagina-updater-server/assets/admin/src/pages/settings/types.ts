export type LogLevel = 'DEBUG' | 'INFO' | 'WARNING' | 'ERROR';

export interface SettingsValues {
  enable_logging: boolean;
  log_level: LogLevel;
}

export interface SystemInfo {
  plugin_version: string | null;
  db_version: string;
  php_version: string;
  wp_version: string;
  mysql_version: string;
  license_extension_active: boolean;
  object_cache_supported: boolean;
}

export interface SettingsResponse {
  settings: SettingsValues;
  system: SystemInfo;
}

export type SettingsTab = 'general' | 'logging' | 'maintenance';
