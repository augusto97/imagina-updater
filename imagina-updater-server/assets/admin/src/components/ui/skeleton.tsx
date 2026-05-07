import * as React from 'react';
import { cn } from '@/lib/utils';

/**
 * Skeleton — placeholder animado para estados de carga.
 *
 * Uso: `<Skeleton className="iaud-h-4 iaud-w-24" />`.
 */
export function Skeleton({
  className,
  ...props
}: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('iaud-animate-pulse iaud-rounded-md iaud-bg-muted', className)}
      {...props}
    />
  );
}
