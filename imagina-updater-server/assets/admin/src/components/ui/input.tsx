import * as React from 'react';
import { cn } from '@/lib/utils';

export interface InputProps
  extends React.InputHTMLAttributes<HTMLInputElement> {}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type = 'text', ...props }, ref) => (
    <input
      ref={ref}
      type={type}
      className={cn(
        'iaud-flex iaud-h-9 iaud-w-full iaud-rounded-md iaud-border iaud-border-input iaud-bg-background iaud-px-3 iaud-py-1 iaud-text-sm iaud-shadow-sm iaud-transition-colors',
        'placeholder:iaud-text-muted-foreground',
        'focus-visible:iaud-outline-none focus-visible:iaud-ring-2 focus-visible:iaud-ring-ring focus-visible:iaud-ring-offset-2',
        'disabled:iaud-cursor-not-allowed disabled:iaud-opacity-50',
        className,
      )}
      {...props}
    />
  ),
);
Input.displayName = 'Input';
