import { useMemo, useState } from 'react';
import { type ColumnDef } from '@tanstack/react-table';
import { FolderTree, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/data-table';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime } from '@/lib/format';
import { useDeletePluginGroup, usePluginGroupsList } from './api';
import { PluginGroupDrawer } from './PluginGroupDrawer';
import type { PluginGroupRow } from './types';

export function PluginGroupsPage() {
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [editing, setEditing] = useState<PluginGroupRow | undefined>(undefined);

  const list = usePluginGroupsList();
  const deleteMutation = useDeletePluginGroup();

  const columns = useMemo<ColumnDef<PluginGroupRow>[]>(
    () => [
      {
        accessorKey: 'name',
        header: 'Nombre',
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
        accessorKey: 'plugin_count',
        header: () => <span className="iaud-block iaud-text-right">Plugins</span>,
        cell: ({ getValue }) => (
          <span className="iaud-block iaud-text-right iaud-tabular-nums iaud-text-sm">
            {Number(getValue())}
          </span>
        ),
      },
      {
        accessorKey: 'linked_api_keys_count',
        header: 'API keys',
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
        accessorKey: 'created_at',
        header: 'Creado',
        cell: ({ getValue }) => (
          <span className="iaud-text-xs iaud-text-muted-foreground">
            {formatDateTime(String(getValue()))}
          </span>
        ),
      },
      {
        id: 'actions',
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
              columns={columns}
              data={list.data?.items ?? []}
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
