import { useMemo, useState } from 'react';
import { type ColumnDef } from '@tanstack/react-table';
import {
  History,
  Package,
  Pencil,
  Plus,
  ShieldCheck,
  ShieldOff,
  Sparkles,
  Trash2,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { formatNumber, formatRelativeTime } from '@/lib/format';
import {
  useDeletePlugin,
  usePluginsList,
  useReinjectProtection,
  useTogglePremium,
} from './api';
import { EditDrawer } from './EditDrawer';
import { UploadDrawer } from './UploadDrawer';
import { VersionsDrawer } from './VersionsDrawer';
import type { PluginRow } from './types';

const PER_PAGE = 20;

export function PluginsPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [uploadOpen, setUploadOpen] = useState(false);
  const [editing, setEditing] = useState<PluginRow | undefined>(undefined);
  const [versionsFor, setVersionsFor] = useState<PluginRow | undefined>(undefined);

  const list = usePluginsList({ page, per_page: PER_PAGE, search });
  const deleteMutation = useDeletePlugin();
  const togglePremium = useTogglePremium();
  const reinject = useReinjectProtection();

  const licenseExtensionActive =
    list.data?.license_extension_active ??
    window.iaudConfig?.licenseExtensionActive ??
    false;

  const columns = useMemo<ColumnDef<PluginRow>[]>(() => {
    const cols: ColumnDef<PluginRow>[] = [
      {
        accessorKey: 'name',
        header: 'Plugin',
        cell: ({ row }) => {
          const p = row.original;
          return (
            <div className="iaud-min-w-0">
              <div className="iaud-flex iaud-items-center iaud-gap-2">
                <span className="iaud-truncate iaud-font-medium iaud-text-foreground">
                  {p.name}
                </span>
                {p.is_premium && (
                  <Badge variant="default" className="iaud-gap-1">
                    <Sparkles className="iaud-h-3 iaud-w-3" />
                    Premium
                  </Badge>
                )}
              </div>
              <div className="iaud-truncate iaud-text-xs iaud-text-muted-foreground">
                <code>{p.effective_slug}</code>
                {p.slug_override && (
                  <span className="iaud-ml-2 iaud-italic">
                    (override de <code>{p.slug}</code>)
                  </span>
                )}
              </div>
            </div>
          );
        },
      },
      {
        accessorKey: 'current_version',
        header: 'Versión',
        cell: ({ getValue }) => (
          <code className="iaud-rounded iaud-bg-muted iaud-px-1.5 iaud-py-0.5 iaud-font-mono iaud-text-xs">
            {String(getValue())}
          </code>
        ),
      },
      {
        accessorKey: 'total_downloads',
        header: () => <span className="iaud-block iaud-text-right">Descargas</span>,
        cell: ({ getValue }) => (
          <span className="iaud-block iaud-text-right iaud-tabular-nums iaud-text-sm">
            {formatNumber(Number(getValue()))}
          </span>
        ),
      },
      {
        accessorKey: 'group_ids',
        header: 'Grupos',
        cell: ({ getValue }) => {
          const ids = getValue() as number[];
          return ids.length > 0 ? (
            <Badge variant="outline">{ids.length}</Badge>
          ) : (
            <span className="iaud-text-xs iaud-text-muted-foreground">—</span>
          );
        },
      },
      {
        accessorKey: 'uploaded_at',
        header: 'Última subida',
        cell: ({ getValue }) => (
          <span className="iaud-text-xs iaud-text-muted-foreground">
            {formatRelativeTime(String(getValue()))}
          </span>
        ),
      },
      {
        id: 'actions',
        header: () => <span className="iaud-sr-only">Acciones</span>,
        cell: ({ row }) => {
          const p = row.original;
          return (
            <div className="iaud-flex iaud-items-center iaud-justify-end iaud-gap-1">
              <Button
                variant="ghost"
                size="icon"
                title="Ver versiones"
                onClick={() => setVersionsFor(p)}
              >
                <History className="iaud-h-4 iaud-w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                title="Editar"
                onClick={() => setEditing(p)}
              >
                <Pencil className="iaud-h-4 iaud-w-4" />
              </Button>
              {licenseExtensionActive && (
                <Button
                  variant="ghost"
                  size="icon"
                  title={p.is_premium ? 'Marcar como NO premium' : 'Marcar como premium'}
                  onClick={() => {
                    if (
                      !p.is_premium &&
                      !window.confirm(
                        `Marcar "${p.name}" como premium e inyectar protección de licencia ahora?`,
                      )
                    )
                      return;
                    if (
                      p.is_premium &&
                      !window.confirm(
                        `Quitar el flag premium de "${p.name}"? El ZIP ya inyectado no se desinfectará automáticamente.`,
                      )
                    )
                      return;
                    togglePremium.mutate({ id: p.id, is_premium: !p.is_premium });
                  }}
                >
                  {p.is_premium ? (
                    <ShieldOff className="iaud-h-4 iaud-w-4" />
                  ) : (
                    <ShieldCheck className="iaud-h-4 iaud-w-4" />
                  )}
                </Button>
              )}
              {licenseExtensionActive && p.is_premium && (
                <Button
                  variant="ghost"
                  size="icon"
                  title="Re-inyectar protección"
                  onClick={() => {
                    if (
                      !window.confirm(
                        `Re-inyectar protección en "${p.name}"? Esto regenera el ZIP en disco.`,
                      )
                    )
                      return;
                    reinject.mutate(p.id);
                  }}
                >
                  <Sparkles className="iaud-h-4 iaud-w-4" />
                </Button>
              )}
              <Button
                variant="ghost"
                size="icon"
                title="Eliminar"
                onClick={() => {
                  if (
                    !window.confirm(
                      `Eliminar el plugin "${p.name}" y todas sus versiones? Esta acción es irreversible.`,
                    )
                  )
                    return;
                  deleteMutation.mutate(p.id);
                }}
              >
                <Trash2 className="iaud-h-4 iaud-w-4 iaud-text-destructive" />
              </Button>
            </div>
          );
        },
      },
    ];
    return cols;
  }, [deleteMutation, licenseExtensionActive, reinject, togglePremium]);

  const total = list.data?.total ?? 0;
  const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));

  return (
    <div className="iaud-app iaud-min-h-screen iaud-bg-background iaud-p-6">
      <header className="iaud-mb-6 iaud-flex iaud-items-start iaud-justify-between iaud-gap-4">
        <div>
          <h1 className="iaud-flex iaud-items-center iaud-gap-2 iaud-text-2xl iaud-font-semibold iaud-tracking-tight iaud-text-foreground">
            <Package className="iaud-h-6 iaud-w-6 iaud-text-primary" />
            Plugins
          </h1>
          <p className="iaud-mt-1 iaud-text-sm iaud-text-muted-foreground">
            Sube y gestiona los plugins que el servidor distribuye.
          </p>
        </div>
        <Button size="sm" onClick={() => setUploadOpen(true)}>
          <Plus className="iaud-h-4 iaud-w-4" />
          Subir plugin
        </Button>
      </header>

      <Card>
        <CardContent className="iaud-p-0">
          <div className="iaud-flex iaud-items-center iaud-justify-end iaud-border-b iaud-border-border iaud-p-3">
            <div className="iaud-w-full sm:iaud-w-64">
              <Input
                type="search"
                placeholder="Buscar por nombre o slug…"
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
              Error: {list.error instanceof Error ? list.error.message : 'desconocido'}.
            </p>
          ) : (
            <DataTable
              columns={columns}
              data={list.data?.items ?? []}
              emptyMessage={
                search
                  ? 'Sin resultados con esa búsqueda.'
                  : 'No hay plugins subidos. Pulsa "Subir plugin" para empezar.'
              }
            />
          )}

          {total > 0 && (
            <div className="iaud-flex iaud-items-center iaud-justify-between iaud-border-t iaud-border-border iaud-px-3 iaud-py-2 iaud-text-xs iaud-text-muted-foreground">
              <span>
                {total} plugin{total === 1 ? '' : 's'} · Página {page} de {totalPages}
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

      <UploadDrawer open={uploadOpen} onOpenChange={setUploadOpen} />
      <EditDrawer
        open={Boolean(editing)}
        onOpenChange={(o) => {
          if (!o) setEditing(undefined);
        }}
        plugin={editing}
      />
      <VersionsDrawer
        open={Boolean(versionsFor)}
        onOpenChange={(o) => {
          if (!o) setVersionsFor(undefined);
        }}
        plugin={versionsFor}
      />
    </div>
  );
}
