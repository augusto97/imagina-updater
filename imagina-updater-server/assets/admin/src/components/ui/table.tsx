import * as React from 'react';
import { cn } from '@/lib/utils';

/**
 * Table — primitives shadcn/ui con prefijo iaud-.
 *
 * Densidad compacta: `py-2` en celdas (vs `py-4` del default shadcn).
 * Esto está alineado con la decisión de densidad de CLAUDE.md §5
 * (referencias: Linear, Stripe Dashboard).
 *
 * Estos primitives son la base para integrar TanStack Table v8 en
 * pantallas con tablas grandes (5.2 API Keys, 5.3 Plugins, etc.).
 * Para listados pequeños del Dashboard se usan directamente sin
 * @tanstack/react-table.
 */

export const Table = React.forwardRef<
  HTMLTableElement,
  React.HTMLAttributes<HTMLTableElement>
>(({ className, ...props }, ref) => (
  <div className="iaud-relative iaud-w-full iaud-overflow-auto">
    <table
      ref={ref}
      className={cn('iaud-w-full iaud-caption-bottom iaud-text-sm', className)}
      {...props}
    />
  </div>
));
Table.displayName = 'Table';

export const TableHeader = React.forwardRef<
  HTMLTableSectionElement,
  React.HTMLAttributes<HTMLTableSectionElement>
>(({ className, ...props }, ref) => (
  <thead
    ref={ref}
    className={cn('[&_tr]:iaud-border-b [&_tr]:iaud-border-border', className)}
    {...props}
  />
));
TableHeader.displayName = 'TableHeader';

export const TableBody = React.forwardRef<
  HTMLTableSectionElement,
  React.HTMLAttributes<HTMLTableSectionElement>
>(({ className, ...props }, ref) => (
  <tbody
    ref={ref}
    className={cn('[&_tr:last-child]:iaud-border-0', className)}
    {...props}
  />
));
TableBody.displayName = 'TableBody';

export const TableRow = React.forwardRef<
  HTMLTableRowElement,
  React.HTMLAttributes<HTMLTableRowElement>
>(({ className, ...props }, ref) => (
  <tr
    ref={ref}
    className={cn(
      'iaud-border-b iaud-border-border iaud-transition-colors hover:iaud-bg-muted/50',
      className,
    )}
    {...props}
  />
));
TableRow.displayName = 'TableRow';

export const TableHead = React.forwardRef<
  HTMLTableCellElement,
  React.ThHTMLAttributes<HTMLTableCellElement>
>(({ className, ...props }, ref) => (
  <th
    ref={ref}
    className={cn(
      'iaud-h-9 iaud-px-3 iaud-text-left iaud-align-middle iaud-text-xs iaud-font-medium iaud-uppercase iaud-tracking-wide iaud-text-muted-foreground',
      className,
    )}
    {...props}
  />
));
TableHead.displayName = 'TableHead';

export const TableCell = React.forwardRef<
  HTMLTableCellElement,
  React.TdHTMLAttributes<HTMLTableCellElement>
>(({ className, ...props }, ref) => (
  <td
    ref={ref}
    className={cn('iaud-px-3 iaud-py-2 iaud-align-middle', className)}
    {...props}
  />
));
TableCell.displayName = 'TableCell';
