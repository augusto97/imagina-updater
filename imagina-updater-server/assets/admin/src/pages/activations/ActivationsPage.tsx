import { useMemo, useState } from 'react';
import {
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table';
import { Globe2, Power } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/data-table';
import { DataTableColumnsToggle } from '@/components/data-table-columns-toggle';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { useColumnVisibility } from '@/hooks/useColumnVisibility';
import { cn } from '@/lib/utils';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import {
  useActivationsList,
  useApiKeysOptions,
  useDeactivateActivation,
} from './api';
import type { ActivationRow, ActivationsStatusFilter } from './types';

const PER_PAGE = 20;
const COLS_STORAGE_KEY = 'iaud:activations:cols';

const STATUS_TABS: Array<{ value: ActivationsStatusFilter; label: string }> = [
  { value: 'all', label: 'Todas' },
  { value: 'active', label: 'Activas' },
  { value: 'inactive', label: 'Inactivas' },
];

export function ActivationsPage() {
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<ActivationsStatusFilter>('all');
  const [apiKeyId, setApiKeyId] = useState(0);
  const [search, setSearch] = useState('');
  const [columnVisibility, setColumnVisibility] = useColumnVisibility(COLS_STORAGE_KEY);

  const list = useActivationsList({
    page,
    per_page: PER_PAGE,
    status,
    api_key_id: apiKeyId,
    search,
  });
  const apiKeys = useApiKeysOptions();
  const deactivate = useDeactivateActivation();

  const columns = useMemo<ColumnDef<ActivationRow>[]>(
    () => [
      {
        id: 'site_domain',
        accessorKey: 'site_domain',
        header: 'Dominio',
        meta: { label: 'Dominio' },
        enableHiding: false,
        cell: ({ row }) => {
          const a = row.original;
          return (
            <div className="iaud-min-w-0">
              <div className="iaud-truncate iaud-font-medium iaud-text-foreground">
                {a.site_domain}
              </div>
              <div className="iaud-truncate iaud-text-xs iaud-text-muted-foreground">
                API key: {a.site_name ?? '—'}
              </div>
            </div>
          );
        },
      },
      {
        id: 'token_masked',
        accessorKey: 'token_masked',
        header: 'Token',
        meta: { label: 'Token' },
        cell: ({ getValue }) => (
          <code className="iaud-rounded iaud-bg-muted iaud-px-1.5 iaud-py-0.5 iaud-font-mono iaud-text-xs">
            {String(getValue())}
          </code>
        ),
      },
      {
        id: 'is_active',
        accessorKey: 'is_active',
        header: 'Estado',
        meta: { label: 'Estado' },
        cell: ({ row }) =>
          row.original.is_active ? (
            <Badge variant="success">Activa</Badge>
          ) : (
            <Badge variant="secondary">Inactiva</Badge>
          ),
      },
      {
        id: 'activated_at',
        accessorKey: 'activated_at',
        header: 'Activada',
        meta: { label: 'Activada' },
        cell: ({ getValue }) => (
          <span className="iaud-text-xs iaud-text-muted-foreground">
            {formatDateTime(String(getValue()))}
          </span>
        ),
      },
      {
        id: 'last_verified',
        accessorKey: 'last_verified',
        header: 'Última verificación',
        meta: { label: 'Última verificación' },
        cell: ({ getValue }) => {
          const v = getValue() as string | null;
          return v ? (
            <span
              className="iaud-text-xs iaud-text-muted-foreground"
              title={formatDateTime(v)}
            >
              {formatRelativeTime(v)}
            </span>
          ) : (
            <span className="iaud-text-xs iaud-text-muted-foreground">Nunca</span>
          );
        },
      },
      {
        id: 'actions',
        enableHiding: false,
        header: () => <span className="iaud-sr-only">Acciones</span>,
        cell: ({ row }) => {
          const a = row.original;
          if (!a.is_active) {
            return (
              <span className="iaud-block iaud-text-right iaud-text-xs iaud-text-muted-foreground">
                {a.deactivated_at
                  ? `Desactivada ${formatRelativeTime(a.deactivated_at)}`
                  : 'Inactiva'}
              </span>
            );
          }
          return (
            <div className="iaud-flex iaud-items-center iaud-justify-end">
              <Button
                variant="ghost"
                size="icon"
                title="Desactivar"
                onClick={() => {
                  if (
                    !window.confirm(
                      `Desactivar la activación de "${a.site_domain}"? El sitio cliente perderá acceso al servidor de actualizaciones.`,
                    )
                  )
                    return;
                  deactivate.mutate(a.id);
                }}
              >
                <Power className="iaud-h-4 iaud-w-4 iaud-text-destructive" />
              </Button>
            </div>
          );
        },
      },
    ],
    [deactivate],
  );

  const table = useReactTable({
    data: list.data?.items ?? [],
    columns,
    state: { columnVisibility },
    onColumnVisibilityChange: setColumnVisibility,
    getCoreRowModel: getCoreRowModel(),
  });

  const total = list.data?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

  return (
    <div className="iaud-app iaud-min-h-screen iaud-bg-background iaud-p-6">
      <header className="iaud-mb-6">
        <h1 className="iaud-flex iaud-items-center iaud-gap-2 iaud-text-2xl iaud-font-semibold iaud-tracking-tight iaud-text-foreground">
          <Globe2 className="iaud-h-6 iaud-w-6 iaud-text-primary" />
          Activaciones
        </h1>
        <p className="iaud-mt-1 iaud-text-sm iaud-text-muted-foreground">
          Sitios cliente que han activado una API key contra este servidor.
        </p>
      </header>

      <Card>
        <CardContent className="iaud-p-0">
          <div className="iaud-flex iaud-flex-col iaud-gap-3 iaud-border-b iaud-border-border iaud-p-3 sm:iaud-flex-row sm:iaud-items-center sm:iaud-justify-between">
            <div className="iaud-flex iaud-items-center iaud-gap-1">
              {STATUS_TABS.map((tab) => (
                <Button
                  key={tab.value}
                  variant={status === tab.value ? 'secondary' : 'ghost'}
                  size="sm"
                  onClick={() => {
                    setStatus(tab.value);
                    setPage(1);
                  }}
                >
                  {tab.label}
                </Button>
              ))}
            </div>
            <div className="iaud-flex iaud-flex-col iaud-gap-2 sm:iaud-flex-row sm:iaud-items-center">
              <DataTableColumnsToggle table={table} />
              <select
                value={apiKeyId}
                onChange={(e) => {
                  setApiKeyId(Number(e.target.value));
                  setPage(1);
                }}
                className={cn(
                  'iaud-h-9 iaud-rounded-md iaud-border iaud-border-input iaud-bg-background iaud-px-3 iaud-text-sm iaud-shadow-sm',
                  'focus-visible:iaud-outline-none focus-visible:iaud-ring-2 focus-visible:iaud-ring-ring focus-visible:iaud-ring-offset-2',
                )}
              >
                <option value={0}>Todas las API keys</option>
                {(apiKeys.data ?? []).map((k) => (
                  <option key={k.id} value={k.id}>
                    {k.site_name}
                  </option>
                ))}
              </select>
              <div className="iaud-w-full sm:iaud-w-64">
                <Input
                  type="search"
                  placeholder="Buscar dominio…"
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value);
                    setPage(1);
                  }}
                />
              </div>
            </div>
          </div>

          {list.isLoading ? (
            <div className="iaud-space-y-2 iaud-p-4">
              {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="iaud-h-10 iaud-w-full" />
              ))}
            </div>
          ) : list.isError ? (
            <p className="iaud-p-4 iaud-text-sm iaud-text-destructive">
              Error: {list.error instanceof Error ? list.error.message : 'desconocido'}.
            </p>
          ) : (
            <DataTable
              table={table}
              emptyMessage={
                search || status !== 'all' || apiKeyId > 0
                  ? 'Sin resultados con los filtros actuales.'
                  : 'Aún no se ha activado ningún sitio contra este servidor.'
              }
            />
          )}

          {total > 0 && (
            <div className="iaud-flex iaud-items-center iaud-justify-between iaud-border-t iaud-border-border iaud-px-3 iaud-py-2 iaud-text-xs iaud-text-muted-foreground">
              <span>
                {total} resultado{total === 1 ? '' : 's'} · Página {page} de {totalPages}
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
