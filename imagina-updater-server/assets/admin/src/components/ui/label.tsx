import * as React from 'react';
import { cn } from '@/lib/utils';

export const Label = React.forwardRef<
  HTMLLabelElement,
  React.LabelHTMLAttributes<HTMLLabelElement>
>(({ className, ...props }, ref) => (
  <label
    ref={ref}
    className={cn(
      'iaud-text-sm iaud-font-medium iaud-leading-none iaud-text-foreground',
      'peer-disabled:iaud-cursor-not-allowed peer-disabled:iaud-opacity-70',
      className,
    )}
    {...props}
  />
));
Label.displayName = 'Label';
