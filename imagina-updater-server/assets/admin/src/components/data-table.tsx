import { flexRender, type Table as RTable } from '@tanstack/react-table';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

/**
 * Wrapper minimalista alrededor de TanStack Table v8 con los
 * primitives shadcn ya prefijados.
 *
 * Diseño deliberadamente plano: la paginación, el filtrado y el
 * ordering los maneja el servidor (REST `?page=&search=&status=`).
 * Ese trade-off mantiene los datasets grandes eficientes y el
 * componente sin estado interno complicado.
 *
 * El consumer crea la instancia con `useReactTable` y la pasa por
 * prop. Eso permite cablear `state.columnVisibility` y otros
 * estados controlados (sorting, etc.) directamente desde la página
 * sin que DataTable tenga que reflejar cada feature como prop.
 *
 * Helper opcional `<DataTableColumnsToggle table={table} />` para
 * exponer el dropdown de visibilidad de columnas.
 */
interface DataTableProps<TData> {
  table: RTable<TData>;
  emptyMessage?: string;
  /** Número total de columnas (incluyendo ocultas) para el colspan. */
  colSpan?: number;
}

export function DataTable<TData>({
  table,
  emptyMessage = 'Sin resultados.',
  colSpan,
}: DataTableProps<TData>) {
  const visibleColumnCount =
    colSpan ?? table.getVisibleLeafColumns().length;

  return (
    <Table>
      <TableHeader>
        {table.getHeaderGroups().map((headerGroup) => (
          <TableRow key={headerGroup.id}>
            {headerGroup.headers.map((header) => (
              <TableHead key={header.id}>
                {header.isPlaceholder
                  ? null
                  : flexRender(
                      header.column.columnDef.header,
                      header.getContext(),
                    )}
              </TableHead>
            ))}
          </TableRow>
        ))}
      </TableHeader>
      <TableBody>
        {table.getRowModel().rows.length === 0 ? (
          <TableRow>
            <TableCell
              colSpan={visibleColumnCount}
              className="iaud-py-6 iaud-text-center iaud-text-sm iaud-text-muted-foreground"
            >
              {emptyMessage}
            </TableCell>
          </TableRow>
        ) : (
          table.getRowModel().rows.map((row) => (
            <TableRow key={row.id}>
              {row.getVisibleCells().map((cell) => (
                <TableCell key={cell.id}>
                  {flexRender(cell.column.columnDef.cell, cell.getContext())}
                </TableCell>
              ))}
            </TableRow>
          ))
        )}
      </TableBody>
    </Table>
  );
}
