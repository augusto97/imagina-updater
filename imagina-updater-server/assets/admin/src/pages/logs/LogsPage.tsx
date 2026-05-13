import { useState } from 'react';
import {
  AlertCircle,
  Bug,
  Download,
  FileText,
  Info,
  Trash2,
  TriangleAlert,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { formatDateTime } from '@/lib/format';
import { getLogsDownloadUrl, useClearLogs, useLogsList } from './api';
import type { LogEntry, LogLevel, LogLevelFilter } from './types';

const PER_PAGE = 100;

const LEVEL_TABS: Array<{ value: LogLevelFilter; label: string }> = [
  { value: 'all', label: 'Todos' },
  { value: 'DEBUG', label: 'Debug' },
  { value: 'INFO', label: 'Info' },
  { value: 'WARNING', label: 'Warning' },
  { value: 'ERROR', label: 'Error' },
];

const LEVEL_BADGE: Record<LogLevel, { variant: 'default' | 'secondary' | 'success' | 'warning' | 'destructive' | 'outline'; icon: React.ReactNode }> = {
  DEBUG: { variant: 'secondary', icon: <Bug className="iaud-h-3 iaud-w-3" /> },
  INFO: { variant: 'outline', icon: <Info className="iaud-h-3 iaud-w-3" /> },
  WARNING: { variant: 'warning', icon: <TriangleAlert className="iaud-h-3 iaud-w-3" /> },
  ERROR: { variant: 'destructive', icon: <AlertCircle className="iaud-h-3 iaud-w-3" /> },
  UNKNOWN: { variant: 'secondary', icon: <Info className="iaud-h-3 iaud-w-3" /> },
};

export function LogsPage() {
  const [page, setPage] = useState(1);
  const [level, setLevel] = useState<LogLevelFilter>('all');
  const [search, setSearch] = useState('');

  const list = useLogsList({ page, per_page: PER_PAGE, level, search });
  const clear = useClearLogs();
  const downloadUrl = getLogsDownloadUrl();

  const total = list.data?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
  const items = list.data?.items ?? [];
  const logEnabled = list.data?.log_enabled ?? true;

  return (
    <div className="iaud-app iaud-min-h-screen iaud-bg-background iaud-p-6">
      <header className="iaud-mb-6 iaud-flex iaud-items-start iaud-justify-between iaud-gap-4">
        <div>
          <h1 className="iaud-flex iaud-items-center iaud-gap-2 iaud-text-2xl iaud-font-semibold iaud-tracking-tight iaud-text-foreground">
            <FileText className="iaud-h-6 iaud-w-6 iaud-text-primary" />
            Logs
          </h1>
          <p className="iaud-mt-1 iaud-text-sm iaud-text-muted-foreground">
            Eventos registrados por el plugin servidor.
          </p>
        </div>
        <div className="iaud-flex iaud-items-center iaud-gap-2">
          {downloadUrl && total > 0 && (
            <a
              href={downloadUrl}
              download
              className={buttonVariants({ variant: 'outline', size: 'sm' })}
            >
              <Download className="iaud-h-4 iaud-w-4" />
              Descargar
            </a>
          )}
          <Button
            variant="outline"
            size="sm"
            disabled={total === 0 || clear.isPending}
            onClick={() => {
              if (
                !window.confirm(
                  '¿Eliminar todos los logs (incluyendo archivos rotados)? Esta acción es irreversible.',
                )
              )
                return;
              clear.mutate();
            }}
          >
            <Trash2 className="iaud-h-4 iaud-w-4" />
            {clear.isPending ? 'Limpiando…' : 'Limpiar'}
          </Button>
        </div>
      </header>

      {!logEnabled && (
        <p className="iaud-mb-4 iaud-rounded-md iaud-border iaud-border-amber-500/40 iaud-bg-amber-500/10 iaud-p-3 iaud-text-xs iaud-text-amber-700">
          El logger está deshabilitado en Configuración. Las nuevas entradas no se están registrando; este visor solo muestra el historial existente.
        </p>
      )}

      <Card>
        <CardContent className="iaud-p-0">
          <div className="iaud-flex iaud-flex-col iaud-gap-3 iaud-border-b iaud-border-border iaud-p-3 sm:iaud-flex-row sm:iaud-items-center sm:iaud-justify-between">
            <div className="iaud-flex iaud-flex-wrap iaud-items-center iaud-gap-1">
              {LEVEL_TABS.map((tab) => (
                <Button
                  key={tab.value}
                  variant={level === tab.value ? 'secondary' : 'ghost'}
                  size="sm"
                  onClick={() => {
                    setLevel(tab.value);
                    setPage(1);
                  }}
                >
                  {tab.label}
                </Button>
              ))}
            </div>
            <div className="iaud-w-full sm:iaud-w-72">
              <Input
                type="search"
                placeholder="Buscar en mensaje o contexto…"
                value={search}
                onChange={(e) => {
                  setSearch(e.target.value);
                  setPage(1);
                }}
              />
            </div>
          </div>

          {list.isLoading ? (
            <div className="iaud-space-y-2 iaud-p-4">
              {Array.from({ length: 8 }).map((_, i) => (
                <Skeleton key={i} className="iaud-h-6 iaud-w-full" />
              ))}
            </div>
          ) : list.isError ? (
            <p className="iaud-p-4 iaud-text-sm iaud-text-destructive">
              Error: {list.error instanceof Error ? list.error.message : 'desconocido'}.
            </p>
          ) : items.length === 0 ? (
            <p className="iaud-p-6 iaud-text-center iaud-text-sm iaud-text-muted-foreground">
              {search || level !== 'all'
                ? 'Sin resultados con los filtros actuales.'
                : 'No hay entradas en el log todavía.'}
            </p>
          ) : (
            <ul className="iaud-divide-y iaud-divide-border">
              {items.map((entry, idx) => (
                <LogEntryRow key={`${entry.timestamp}-${idx}`} entry={entry} />
              ))}
            </ul>
          )}

          {total > 0 && (
            <div className="iaud-flex iaud-items-center iaud-justify-between iaud-border-t iaud-border-border iaud-px-3 iaud-py-2 iaud-text-xs iaud-text-muted-foreground">
              <span>
                {total} entrada{total === 1 ? '' : 's'} · Página {page} de {totalPages}
              </span>
              <div className="iaud-flex iaud-items-center iaud-gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1 || list.isFetching}
                >
                  Anterior
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page >= totalPages || list.isFetching}
                >
                  Siguiente
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

function LogEntryRow({ entry }: { entry: LogEntry }) {
  const cfg = LEVEL_BADGE[entry.level];
  const [expanded, setExpanded] = useState(false);
  const hasContext = entry.context !== null && entry.context.length > 0;
  return (
    <li className="iaud-px-3 iaud-py-2 iaud-text-sm hover:iaud-bg-muted/50">
      <div className="iaud-flex iaud-items-start iaud-gap-3">
        <Badge variant={cfg.variant} className="iaud-mt-0.5 iaud-shrink-0 iaud-gap-1">
          {cfg.icon}
          {entry.level}
        </Badge>
        <div className="iaud-min-w-0 iaud-flex-1">
          <div className="iaud-flex iaud-items-baseline iaud-gap-2 iaud-text-xs iaud-text-muted-foreground iaud-tabular-nums">
            <span>{entry.timestamp ? formatDateTime(entry.timestamp) : '—'}</span>
          </div>
          <p
            className={cn(
              'iaud-mt-0.5 iaud-break-words iaud-text-foreground',
              !expanded && 'iaud-line-clamp-2',
            )}
          >
            {entry.message}
          </p>
          {hasContext && (
            <div className="iaud-mt-1">
              <button
                type="button"
                onClick={() => setExpanded((v) => !v)}
                className="iaud-text-xs iaud-text-muted-foreground iaud-underline"
              >
                {expanded ? 'Ocultar contexto' : 'Ver contexto'}
              </button>
              {expanded && (
                <pre className="iaud-mt-1 iaud-overflow-x-auto iaud-rounded-md iaud-bg-muted iaud-p-2 iaud-font-mono iaud-text-xs iaud-text-foreground">
                  {entry.context}
                </pre>
              )}
            </div>
          )}
        </div>
      </div>
    </li>
  );
}
