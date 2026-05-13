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
import { useTopPlugins } from './api';
import { formatNumber } from '@/lib/format';

export function TopPluginsTable() {
  const { data, isLoading, isError, error } = useTopPlugins();

  return (
    <Card>
      <CardHeader>
        <CardTitle>Top plugins por descargas</CardTitle>
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
                <TableHead className="iaud-text-right">Descargas</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {data.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="iaud-font-medium">{row.name}</TableCell>
                  <TableCell className="iaud-tabular-nums iaud-text-muted-foreground">
                    {row.current_version}
                  </TableCell>
                  <TableCell className="iaud-text-right iaud-tabular-nums">
                    {formatNumber(row.downloads)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        ) : (
          <p className="iaud-p-4 iaud-text-sm iaud-text-muted-foreground">
            No hay plugins registrados todavía.
          </p>
        )}
      </CardContent>
    </Card>
  );
}
