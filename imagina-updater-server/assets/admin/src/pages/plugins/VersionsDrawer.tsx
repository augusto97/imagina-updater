import { Drawer } from '@/components/ui/drawer';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { usePluginVersions } from './api';
import { formatBytes } from './lib';
import type { PluginRow } from './types';

interface VersionsDrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  plugin: PluginRow | undefined;
}

export function VersionsDrawer({ open, onOpenChange, plugin }: VersionsDrawerProps) {
  const versions = usePluginVersions(open && plugin ? plugin.id : null);

  return (
    <Drawer
      open={open}
      onOpenChange={onOpenChange}
      title={plugin ? `Versiones de ${plugin.name}` : 'Versiones'}
      description="Historial completo de subidas para este plugin."
    >
      {versions.isLoading ? (
        <div className="iaud-space-y-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="iaud-h-8 iaud-w-full" />
          ))}
        </div>
      ) : versions.isError ? (
        <p className="iaud-text-sm iaud-text-destructive">
          Error al cargar versiones:{' '}
          {versions.error instanceof Error ? versions.error.message : 'desconocido'}.
        </p>
      ) : versions.data && versions.data.length > 0 ? (
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Versión</TableHead>
              <TableHead>Tamaño</TableHead>
              <TableHead>Subida</TableHead>
              <TableHead>Notas</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {versions.data.map((v) => (
              <TableRow key={v.id}>
                <TableCell className="iaud-font-mono iaud-text-xs">{v.version}</TableCell>
                <TableCell className="iaud-tabular-nums iaud-text-xs iaud-text-muted-foreground">
                  {formatBytes(v.file_size)}
                </TableCell>
                <TableCell className="iaud-text-xs iaud-text-muted-foreground">
                  {formatDateTime(v.uploaded_at)}
                </TableCell>
                <TableCell className="iaud-max-w-xs iaud-truncate iaud-text-xs iaud-text-muted-foreground" title={v.changelog ?? ''}>
                  {v.changelog ?? '—'}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      ) : (
        <p className="iaud-text-sm iaud-text-muted-foreground">
          No hay versiones registradas.
        </p>
      )}
    </Drawer>
  );
}
