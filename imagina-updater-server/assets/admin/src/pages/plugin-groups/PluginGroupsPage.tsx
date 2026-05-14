import { useMemo, useState } from 'react';
import {
  getCoreRowModel,
  useReactTable,
  type ColumnDef,
} from '@tanstack/react-table';
import { FolderTree, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/data-table';
import { DataTableColumnsToggle } from '@/components/data-table-columns-toggle';
import { Skeleton } from '@/components/ui/skeleton';
import { useColumnVisibility } from '@/hooks/useColumnVisibility';
import { formatDateTime } from '@/lib/format';
import { useDeletePluginGroup, usePluginGroupsList } from './api';
import { PluginGroupDrawer } from './PluginGroupDrawer';
import type { PluginGroupRow } from './types';

const COLS_STORAGE_KEY = 'iaud:plugin-groups:cols';

export function PluginGroupsPage() {
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState<PluginGroupRow | undefined>(undefined);
  const [columnVisibility, setColumnVisibility] = useColumnVisibility(COLS_STORAGE_KEY);

  const list = usePluginGroupsList();
  const deleteMutation = useDeletePluginGroup();

  const columns = useMemo<ColumnDef<PluginGroupRow>[]>(
    () => [
      {
        id: 'name',
        accessorKey: 'name',
        header: 'Nombre',
        meta: { label: 'Nombre' },
        enableHiding: false,
        cell: ({ row }) => {
          const g = row.original;
          return (
            <div className="iaud-min-w-0">
              <div className="iaud-truncate iaud-font-medium iaud-text-foreground">
                {g.name}
              </div>
              {g.description && (
                <div className="iaud-truncate iaud-text-xs iaud-text-muted-foreground">
                  {g.description}
                </div>
              )}
            </div>
          );
        },
      },
      {
        id: 'plugin_count',
        accessorKey: 'plugin_count',
        header: () => <span className="iaud-block iaud-text-right">Plugins</span>,
        meta: { label: 'Plugins' },
        cell: ({ getValue }) => (
          <span className="iaud-block iaud-text-right iaud-tabular-nums iaud-text-sm">
            {Number(getValue())}
          </span>
        ),
      },
      {
        id: 'linked_api_keys_count',
        accessorKey: 'linked_api_keys_count',
        header: 'API keys',
        meta: { label: 'API keys vinculadas' },
        cell: ({ getValue }) => {
          const n = Number(getValue());
          return n > 0 ? (
            <Badge variant="outline">{n}</Badge>
          ) : (
            <span className="iaud-text-xs iaud-text-muted-foreground">—</span>
          );
        },
      },
      {
        id: 'created_at',
        accessorKey: 'created_at',
        header: 'Creado',
        meta: { label: 'Creado' },
        cell: ({ getValue }) => (
          <span className="iaud-text-xs iaud-text-muted-foreground">
            {formatDateTime(String(getValue()))}
          </span>
        ),
      },
      {
        id: 'actions',
        enableHiding: false,
        header: () => <span className="iaud-sr-only">Acciones</span>,
        cell: ({ row }) => {
          const g = row.original;
          return (
            <div className="iaud-flex iaud-items-center iaud-justify-end iaud-gap-1">
              <Button
                variant="ghost"
                size="icon"
                title="Editar"
                onClick={() => {
                  setEditing(g);
                  setDrawerOpen(true);
                }}
              >
                <Pencil className="iaud-h-4 iaud-w-4" />
              </Button>
              <Button
                variant="ghost"
                size="icon"
                title="Eliminar"
                onClick={() => {
                  const warn = g.linked_api_keys_count > 0
                    ? `Eliminar el grupo "${g.name}"? ${g.linked_api_keys_count} API key${g.linked_api_keys_count === 1 ? ' lo está' : 's lo están'} usando — perderán acceso a sus plugins.`
                    : `Eliminar el grupo "${g.name}"?`;
                  if (!window.confirm(warn)) return;
                  deleteMutation.mutate(g.id);
                }}
              >
                <Trash2 className="iaud-h-4 iaud-w-4 iaud-text-destructive" />
              </Button>
            </div>
          );
        },
      },
    ],
    [deleteMutation],
  );

  const table = useReactTable({
    data: list.data?.items ?? [],
    columns,
    state: { columnVisibility },
    onColumnVisibilityChange: setColumnVisibility,
    getCoreRowModel: getCoreRowModel(),
  });

  return (
    <div className="iaud-app iaud-min-h-screen iaud-bg-background iaud-p-6">
      <header className="iaud-mb-6 iaud-flex iaud-items-start iaud-justify-between iaud-gap-4">
        <div>
          <h1 className="iaud-flex iaud-items-center iaud-gap-2 iaud-text-2xl iaud-font-semibold iaud-tracking-tight iaud-text-foreground">
            <FolderTree className="iaud-h-6 iaud-w-6 iaud-text-primary" />
            Grupos de plugins
          </h1>
          <p className="iaud-mt-1 iaud-text-sm iaud-text-muted-foreground">
            Agrupa plugins para conceder acceso conjunto desde una API key.
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
          Nuevo grupo
        </Button>
      </header>

      <Card>
        <CardContent className="iaud-p-0">
          <div className="iaud-flex iaud-items-center iaud-justify-end iaud-border-b iaud-border-border iaud-p-3">
            <DataTableColumnsToggle table={table} />
          </div>

          {list.isLoading ? (
            <div className="iaud-space-y-2 iaud-p-4">
              {Array.from({ length: 4 }).map((_, i) => (
                <Skeleton key={i} className="iaud-h-10 iaud-w-full" />
              ))}
            </div>
          ) : list.isError ? (
            <p className="iaud-p-4 iaud-text-sm iaud-text-destructive">
              Error al cargar grupos:{' '}
              {list.error instanceof Error ? list.error.message : 'desconocido'}.
            </p>
          ) : (
            <DataTable
              table={table}
              emptyMessage='Aún no hay grupos. Pulsa "Nuevo grupo" para crear el primero.'
            />
          )}
        </CardContent>
      </Card>

      <PluginGroupDrawer
        open={drawerOpen}
        onOpenChange={(o) => {
          setDrawerOpen(o);
          if (!o) setEditing(undefined);
        }}
        editing={editing}
      />
    </div>
  );
}
