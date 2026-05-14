import { useState } from 'react';
import { type Table } from '@tanstack/react-table';
import { Columns3 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent } from '@/components/ui/popover';

/**
 * Dropdown de visibilidad de columnas para una tabla TanStack.
 *
 * Filtra automáticamente las columnas "no toggleables" (las que
 * no tienen `accessorKey` o `id` legible, ej. la columna de
 * acciones). El admin nunca debería poder ocultar la columna de
 * acciones.
 *
 * Etiqueta de cada columna: prioridad
 *   1. `meta.label` (string custom)
 *   2. `id`
 *   3. `accessorKey`
 *   4. fallback "Columna"
 */

interface Props<TData> {
  table: Table<TData>;
  /** Columnas que NUNCA aparecen en el toggle (ej. ['actions']). */
  pinnedIds?: string[];
}

export function DataTableColumnsToggle<TData>({
  table,
  pinnedIds = [],
}: Props<TData>) {
  const [open, setOpen] = useState(false);

  const toggleable = table
    .getAllLeafColumns()
    .filter((c) => c.getCanHide() && !pinnedIds.includes(c.id));

  if (toggleable.length === 0) return null;

  return (
    <Popover
      open={open}
      onOpenChange={setOpen}
      trigger={
        <Button variant="outline" size="sm">
          <Columns3 className="iaud-h-4 iaud-w-4" />
          Columnas
        </Button>
      }
    >
      <PopoverContent className="iaud-min-w-[14rem] iaud-p-2">
        <p className="iaud-px-2 iaud-py-1 iaud-text-xs iaud-font-medium iaud-uppercase iaud-tracking-wide iaud-text-muted-foreground">
          Mostrar columnas
        </p>
        <ul className="iaud-space-y-0.5">
          {toggleable.map((column) => {
            const meta = column.columnDef.meta as { label?: string } | undefined;
            const label =
              meta?.label ??
              column.id ??
              'Columna';
            return (
              <li key={column.id}>
                <label className="iaud-flex iaud-cursor-pointer iaud-items-center iaud-gap-2 iaud-rounded-sm iaud-px-2 iaud-py-1.5 iaud-text-sm hover:iaud-bg-muted">
                  <input
                    type="checkbox"
                    checked={column.getIsVisible()}
                    onChange={(e) => column.toggleVisibility(e.target.checked)}
                  />
                  <span className="iaud-text-foreground">{label}</span>
                </label>
              </li>
            );
          })}
        </ul>
      </PopoverContent>
    </Popover>
  );
}
