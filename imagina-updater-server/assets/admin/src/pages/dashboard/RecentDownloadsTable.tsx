import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { useRecentDownloads } from './api';
import { formatRelativeTime } from '@/lib/format';

export function RecentDownloadsTable() {
  const { data, isLoading, isError, error } = useRecentDownloads();

  return (
    <Card>
      <CardHeader>
        <CardTitle>Últimas descargas</CardTitle>
      </CardHeader>
      <CardContent className="iaud-p-0">
        {isLoading ? (
          <div className="iaud-space-y-2 iaud-p-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="iaud-h-6 iaud-w-full" />
            ))}
          </div>
        ) : isError ? (
          <p className="iaud-p-4 iaud-text-sm iaud-text-destructive">
            Error: {error instanceof Error ? error.message : 'desconocido'}.
          </p>
        ) : data && data.length > 0 ? (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Plugin</TableHead>
                <TableHead>Versión</TableHead>
                <TableHead>Sitio</TableHead>
                <TableHead className="iaud-text-right">Cuándo</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="iaud-font-medium">
                    {row.plugin_name ?? row.plugin_slug ?? '—'}
                  </TableCell>
                  <TableCell className="iaud-tabular-nums iaud-text-muted-foreground">
                    {row.version}
                  </TableCell>
                  <TableCell className="iaud-text-muted-foreground">
                    {row.site_name ?? '—'}
                  </TableCell>
                  <TableCell className="iaud-text-right iaud-text-muted-foreground">
                    {formatRelativeTime(row.downloaded_at)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        ) : (
          <p className="iaud-p-4 iaud-text-sm iaud-text-muted-foreground">
            Aún no se han registrado descargas.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
