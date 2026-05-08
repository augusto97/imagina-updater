export type LogLevel = 'DEBUG' | 'INFO' | 'WARNING' | 'ERROR' | 'UNKNOWN';
export type LogLevelFilter = 'all' | LogLevel;

export interface LogEntry {
  timestamp: string;
  level: LogLevel;
  message: string;
  context: string | null;
}

export interface LogsListResponse {
  items: LogEntry[];
  total: number;
  page: number;
  per_page: number;
  log_enabled: boolean;
  log_file: string | null;
}
