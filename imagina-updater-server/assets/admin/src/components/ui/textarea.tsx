import * as React from 'react';
import { cn } from '@/lib/utils';

export const Textarea = React.forwardRef<
  HTMLTextAreaElement,
  React.TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className, ...props }, ref) => (
  <textarea
    ref={ref}
    className={cn(
      'iaud-flex iaud-min-h-[60px] iaud-w-full iaud-rounded-md iaud-border iaud-border-input iaud-bg-background iaud-px-3 iaud-py-2 iaud-text-sm iaud-shadow-sm',
      'placeholder:iaud-text-muted-foreground',
      'focus-visible:iaud-outline-none focus-visible:iaud-ring-2 focus-visible:iaud-ring-ring focus-visible:iaud-ring-offset-2',
      'disabled:iaud-cursor-not-allowed disabled:iaud-opacity-50',
      className,
    )}
    {...props}
  />
));
Textarea.displayName = 'Textarea';
