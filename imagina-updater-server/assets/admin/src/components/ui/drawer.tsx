import * as React from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Drawer (sheet lateral) implementado sin Radix para mantener el bundle
 * pequeño. Maneja:
 *  - Backdrop con click-to-close.
 *  - Cierre con tecla Escape.
 *  - Bloqueo del scroll del body mientras está abierto.
 *  - Animación slide-in/out con tailwindcss-animate.
 *
 * Uso:
 *   const [open, setOpen] = useState(false);
 *   <Drawer open={open} onOpenChange={setOpen} title="Crear API key">
 *     ...contenido...
 *   </Drawer>
 *
 * Si el día de mañana se necesitan focus traps, multi-instancia o
 * animaciones más finas, sustituir por @radix-ui/react-dialog (es
 * drop-in para esta API mediante el patrón Sheet de shadcn).
 */

interface DrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title?: string;
  description?: string;
  children: React.ReactNode;
  footer?: React.ReactNode;
  /** Anchura del panel; default 'iaud-w-full sm:iaud-max-w-md'. */
  className?: string;
}

export function Drawer({
  open,
  onOpenChange,
  title,
  description,
  children,
  footer,
  className,
}: DrawerProps) {
  React.useEffect(() => {
    if (!open) return;

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onOpenChange(false);
    };
    document.addEventListener('keydown', onKey);
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prevOverflow;
    };
  }, [open, onOpenChange]);

  if (!open) return null;

  return (
    <div
      className="iaud-fixed iaud-inset-0 iaud-z-[10000] iaud-flex"
      role="dialog"
      aria-modal="true"
      aria-label={title}
    >
      <button
        type="button"
        aria-label="Cerrar"
        onClick={() => onOpenChange(false)}
        className="iaud-absolute iaud-inset-0 iaud-bg-black/40 iaud-transition-opacity iaud-animate-in iaud-fade-in-0"
      />
      <aside
        className={cn(
          'iaud-relative iaud-ml-auto iaud-flex iaud-h-full iaud-w-full sm:iaud-max-w-md iaud-flex-col iaud-bg-background iaud-shadow-lg iaud-animate-in iaud-slide-in-from-right',
          className,
        )}
      >
        <header className="iaud-flex iaud-items-start iaud-justify-between iaud-gap-4 iaud-border-b iaud-border-border iaud-px-6 iaud-py-4">
          <div className="iaud-min-w-0">
            {title && (
              <h2 className="iaud-text-base iaud-font-semibold iaud-text-foreground">
                {title}
              </h2>
            )}
            {description && (
              <p className="iaud-mt-0.5 iaud-text-sm iaud-text-muted-foreground">
                {description}
              </p>
            )}
          </div>
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className="iaud-rounded-md iaud-p-1 iaud-text-muted-foreground hover:iaud-bg-muted hover:iaud-text-foreground"
            aria-label="Cerrar drawer"
          >
            <X className="iaud-h-4 iaud-w-4" />
          </button>
        </header>

        <div className="iaud-flex-1 iaud-overflow-y-auto iaud-px-6 iaud-py-4">
          {children}
        </div>

        {footer && (
          <footer className="iaud-flex iaud-items-center iaud-justify-end iaud-gap-2 iaud-border-t iaud-border-border iaud-px-6 iaud-py-3">
            {footer}
          </footer>
        )}
      </aside>
    </div>
  );
}
