import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

/**
 * Button — port de shadcn/ui con prefijo iaud-.
 *
 * Variants: default (primario), secondary, outline, ghost, destructive.
 * Sizes: sm, default, lg, icon. Densidad compacta = `default` apunta a
 * `h-9` en lugar de `h-10` del shadcn upstream (CLAUDE.md §5).
 */

const buttonVariants = cva(
  'iaud-inline-flex iaud-items-center iaud-justify-center iaud-gap-2 iaud-whitespace-nowrap iaud-rounded-md iaud-text-sm iaud-font-medium iaud-transition-colors focus-visible:iaud-outline-none focus-visible:iaud-ring-2 focus-visible:iaud-ring-ring focus-visible:iaud-ring-offset-2 disabled:iaud-pointer-events-none disabled:iaud-opacity-50',
  {
    variants: {
      variant: {
        default:
          'iaud-bg-primary iaud-text-primary-foreground hover:iaud-bg-primary/90',
        secondary:
          'iaud-bg-secondary iaud-text-secondary-foreground hover:iaud-bg-secondary/80',
        outline:
          'iaud-border iaud-border-border iaud-bg-background hover:iaud-bg-accent hover:iaud-text-accent-foreground',
        ghost:
          'hover:iaud-bg-accent hover:iaud-text-accent-foreground',
        destructive:
          'iaud-bg-destructive iaud-text-destructive-foreground hover:iaud-bg-destructive/90',
      },
      size: {
        sm: 'iaud-h-8 iaud-px-3 iaud-text-xs',
        default: 'iaud-h-9 iaud-px-4',
        lg: 'iaud-h-10 iaud-px-6',
        icon: 'iaud-h-9 iaud-w-9',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, type = 'button', ...props }, ref) => (
    <button
      ref={ref}
      type={type}
      className={cn(buttonVariants({ variant, size }), className)}
      {...props}
    />
  ),
);
Button.displayName = 'Button';

export { buttonVariants };
