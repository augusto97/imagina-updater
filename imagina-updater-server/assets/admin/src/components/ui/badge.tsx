import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'iaud-inline-flex iaud-items-center iaud-rounded-md iaud-border iaud-px-2 iaud-py-0.5 iaud-text-xs iaud-font-medium iaud-transition-colors',
  {
    variants: {
      variant: {
        default:
          'iaud-border-transparent iaud-bg-primary iaud-text-primary-foreground',
        secondary:
          'iaud-border-transparent iaud-bg-secondary iaud-text-secondary-foreground',
        success:
          'iaud-border-transparent iaud-bg-emerald-500/15 iaud-text-emerald-700',
        warning:
          'iaud-border-transparent iaud-bg-amber-500/15 iaud-text-amber-700',
        destructive:
          'iaud-border-transparent iaud-bg-destructive/15 iaud-text-destructive',
        outline:
          'iaud-border-border iaud-text-foreground',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
);

export interface BadgeProps
  extends React.HTMLAttributes<HTMLSpanElement>,
    VariantProps<typeof badgeVariants> {}

export function Badge({ className, variant, ...props }: BadgeProps) {
  return (
    <span className={cn(badgeVariants({ variant }), className)} {...props} />
  );
}
