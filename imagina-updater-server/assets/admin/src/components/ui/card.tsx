import * as React from 'react';
import { cn } from '@/lib/utils';

/**
 * Card primitives — port directo de shadcn/ui adaptado al prefijo
 * `iaud-`. Densidad compacta por defecto (padding `p-3`/`p-4`,
 * CLAUDE.md §5).
 */

export const Card = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn(
      'iaud-rounded-lg iaud-border iaud-border-border iaud-bg-card iaud-text-card-foreground iaud-shadow-sm',
      className,
    )}
    {...props}
  />
));
Card.displayName = 'Card';

export const CardHeader = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn('iaud-flex iaud-flex-col iaud-space-y-1 iaud-p-4 iaud-pb-2', className)}
    {...props}
  />
));
CardHeader.displayName = 'CardHeader';

export const CardTitle = React.forwardRef<
  HTMLHeadingElement,
  React.HTMLAttributes<HTMLHeadingElement>
>(({ className, ...props }, ref) => (
  <h3
    ref={ref}
    className={cn(
      'iaud-text-sm iaud-font-medium iaud-tracking-tight iaud-text-muted-foreground',
      className,
    )}
    {...props}
  />
));
CardTitle.displayName = 'CardTitle';

export const CardDescription = React.forwardRef<
  HTMLParagraphElement,
  React.HTMLAttributes<HTMLParagraphElement>
>(({ className, ...props }, ref) => (
  <p
    ref={ref}
    className={cn('iaud-text-sm iaud-text-muted-foreground', className)}
    {...props}
  />
));
CardDescription.displayName = 'CardDescription';

export const CardContent = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div ref={ref} className={cn('iaud-p-4 iaud-pt-2', className)} {...props} />
));
CardContent.displayName = 'CardContent';

export const CardFooter = React.forwardRef<
  HTMLDivElement,
  React.HTMLAttributes<HTMLDivElement>
>(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn('iaud-flex iaud-items-center iaud-p-4 iaud-pt-0', className)}
    {...props}
  />
));
CardFooter.displayName = 'CardFooter';
