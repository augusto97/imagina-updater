import { useMemo, useState } from 'react';
import { type ColumnDef } from '@tanstack/react-table';
import {
  KeyRound,
  Pencil,
  Plus,
  Power,
  RefreshCw,
  Trash2,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime, formatRelativeTime } from '@/lib/format';
import {
  useApiKeysList,
  useDeleteApiKey,
  useRegenerateApiKey,
  useToggleApiKeyActive,
} from './api';
import { ApiKeyDrawer } from './ApiKeyDrawer';
import { PlainKeyBanner } from './PlainKeyBanner';
import type { ApiKey, StatusFilter } from './types';

const PER_PAGE = 20;

const STATUS_TABS: Array<{ value: StatusFilter; label: string }> = [
  { value: 'all', label: 'Todas' },
  { value: 'active', label: 'Activas' },
  { value: 'inactive', label: 'Inactivas' },
];

const ACCESS_LABEL: Record<ApiKey['access_type'], string> = {
  all: 'Todos',
  specific: 'Específicos',
  groups: 'Grupos',
};

export function ApiKeysPage() {
  const [page, setPage] = useState(1);
  const [status, setStatus] = useState<StatusFilter>('all');
  const [search, setSearch] = useState('');
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState<ApiKey | undefined>(undefined);
  const [banner, setBanner] = useState<{ plainKey: string; siteName: string } | null>(null);

  const list = useApiKeysList({ page, per_page: PER_PAGE, status, search });
  const deleteMutation = useDeleteApiKey();
  const toggleMutation = useToggleApiKeyActive();
  const regenerateMutation = useRegenerateApiKey();

  const columns = useMemo<ColumnDef<ApiKey>[]>(
    () => [
      {
        accessorKey: 'site_name',
        header: 'Sitio',
        cell: ({ row }) => {
          const k = row.original;
          return (
            <div className="iaud-min-w-0">
              <div className="iaud-truncate iaud-font-medium iaud-text-foreground">
                {k.site_name}
              </div>
              <div className="iaud-truncate iaud-text-xs iaud-text-muted-foreground">
                {k.site_url}
              </div>
            </div>
          );
        },
      },
      {
        accessorKey: 'api_key_masked',
        header: 'API key',
        cell: ({ getValue }) => (
          <code className="iaud-rounded iaud-bg-muted iaud-px-1.5 iaud-py-0.5 iaud-font-mono iaud-text-xs">
            {String(getValue())}
          </code>
        ),
      },
      {
        accessorKey: 'access_type',
        header: 'Acceso',
        cell: ({ getValue }) => (
          <Badge variant="outline">
            {ACCESS_LABEL[getValue() as ApiKey['access_type']]}
          </Badge>
        ),
      },
      {
        id: 'activations',
        header: 'Activaciones',
        cell: ({ row }) => {
          const k = row.original;
          const max = k.max_activations === 0 ? '∞' : k.max_activations;
          return (
            <span className="iaud-tabular-nums iaud-text-sm">
              {k.activations_used} / {max}
            </span>
          );
        },
      },
      {
        accessorKey: 'is_active',
        header: 'Estado',
        cell: ({ row }) =>
          row.original.is_active ? (
            <Badge variant="success">Activa</Badge>
          ) : (
            <Badge variant="secondary">Inactiva</Badge>
          ),
      },
      {
        accessorKey: 'last_used',
        header: 'Último uso',
        cell: ({ getValue }) => {
          const v = getValue() as string | null;
          return v ? (
            <span className="iaud-text-xs iaud-text-muted-foreground" title={formatDateTime(v)}>
              {formatRelativeTime(v)}
            </span>
          ) : (
            <span className="iaud-text-xs iaud-text-muted-foreground">Nunca</span>
          );
        },
      },
      {
        id: 'actions',
        header: () => <span className="iaud-sr-only">Acciones</span>,
        cell: ({ row }) => {
          const k = row.original;
          return (
            <div className="iaud-flex iaud-items-center iaud-justify-end iaud-gap-1">
              <Button
                variant="ghost"
                size="icon"
                title="Editar"
                onClick={() => {
                  setEditing(k);
                  setDrawerOpen(true);
                }}
              >
                <Pencil className="iaud-h-4 iaud-w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                title={k.is_active ? 'Desactivar' : 'Activar'}
                onClick={() => {
                  toggleMutation.mutate({ id: k.id, is_active: !k.is_active });
                }}
              >
                <Power className="iaud-h-4 iaud-w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                title="Regenerar API key"
                onClick={() => {
                  if (
                    !window.confirm(
                      `Regenerar la API key de "${k.site_name}"? El sitio cliente perderá acceso hasta actualizar la nueva clave.`,
                    )
                  )
                    return;
                  void regenerateMutation
                    .mutateAsync(k.id)
                    .then((res) => {
                      if (res.plain_key) {
                        setBanner({ plainKey: res.plain_key, siteName: k.site_name });
                      }
                    });
                }}
              >
                <RefreshCw className="iaud-h-4 iaud-w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                title="Eliminar"
                onClick={() => {
                  if (
                    !window.confirm(
                      `Eliminar la API key de "${k.site_name}"? Esta acción es irreversible y los sitios cliente perderán acceso inmediatamente.`,
                    )
                  )
                    return;
                  deleteMutation.mutate(k.id);
                }}
              >
                <Trash2 className="iaud-h-4 iaud-w-4 iaud-text-destructive" />
              </Button>
            </div>
          );
        },
      },
    ],
    [deleteMutation, regenerateMutation, toggleMutation],
  );

  const total = list.data?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

  return (
    <div className="iaud-app iaud-min-h-screen iaud-bg-background iaud-p-6">
      <header className="iaud-mb-6 iaud-flex iaud-items-start iaud-justify-between iaud-gap-4">
        <div>
          <h1 className="iaud-flex iaud-items-center iaud-gap-2 iaud-text-2xl iaud-font-semibold iaud-tracking-tight iaud-text-foreground">
            <KeyRound className="iaud-h-6 iaud-w-6 iaud-text-primary" />
            API Keys
          </h1>
          <p className="iaud-mt-1 iaud-text-sm iaud-text-muted-foreground">
            Gestiona las claves que activan sitios cliente contra este servidor.
          </p>
        </div>
        <Button
          size="sm"
          onClick={() => {
            setEditing(undefined);
            setDrawerOpen(true);
          }}
        >
          <Plus className="iaud-h-4 iaud-w-4" />
          Nueva API key
        </Button>
      </header>

      {banner && (
        <div className="iaud-mb-4">
          <PlainKeyBanner
            plainKey={banner.plainKey}
            siteName={banner.siteName}
            onDismiss={() => setBanner(null)}
          />
        </div>
      )}

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
            <div className="iaud-w-full sm:iaud-w-64">
              <Input
                type="search"
                placeholder="Buscar por nombre o URL…"
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
              {Array.from({ length: 5 }).map((_, i) => (
                <Skeleton key={i} className="iaud-h-10 iaud-w-full" />
              ))}
            </div>
          ) : list.isError ? (
            <p className="iaud-p-4 iaud-text-sm iaud-text-destructive">
              Error al cargar API keys: {list.error instanceof Error ? list.error.message : 'desconocido'}.
            </p>
          ) : (
            <DataTable
              columns={columns}
              data={list.data?.items ?? []}
              emptyMessage={
                search || status !== 'all'
                  ? 'Sin resultados con los filtros actuales.'
                  : 'Aún no hay API keys creadas. Pulsa "Nueva API key" para empezar.'
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

      <ApiKeyDrawer
        open={drawerOpen}
        onOpenChange={(o) => {
          setDrawerOpen(o);
          if (!o) setEditing(undefined);
        }}
        editing={editing}
        onCreated={(plainKey, item) =>
          setBanner({ plainKey, siteName: item.site_name })
        }
      />
    </div>
  );
}
