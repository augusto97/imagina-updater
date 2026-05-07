import { useState } from 'react';
import { Check, Copy, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface BannerProps {
  plainKey: string;
  siteName: string;
  onDismiss: () => void;
}

/**
 * Banner persistente arriba de la tabla. Muestra el `api_key` en
 * claro UNA SOLA VEZ tras crear o regenerar. El backend nunca
 * vuelve a exponerlo, así que el botón "Copiar" es la única vía
 * fiable para el admin.
 *
 * Caveat: si el navegador no expone Clipboard API (HTTP no
 * localhost), `copyToClipboard` cae a `document.execCommand` como
 * fallback. Si nada funciona, el textarea queda visible para
 * copia manual.
 */
export function PlainKeyBanner({ plainKey, siteName, onDismiss }: BannerProps) {
  const [copied, setCopied] = useState(false);

  async function copyToClipboard() {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(plainKey);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1500);
        return;
      }
    } catch {
      // Cae al fallback.
    }
    const ta = document.createElement('textarea');
    ta.value = plainKey;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try {
      document.execCommand('copy');
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } catch {
      /* user copia manual */
    }
    document.body.removeChild(ta);
  }

  return (
    <div className="iaud-rounded-lg iaud-border iaud-border-primary/40 iaud-bg-primary/5 iaud-p-4">
      <div className="iaud-flex iaud-items-start iaud-justify-between iaud-gap-4">
        <div className="iaud-min-w-0">
          <p className="iaud-text-sm iaud-font-semibold iaud-text-foreground">
            API key generada para {siteName}
          </p>
          <p className="iaud-mt-0.5 iaud-text-xs iaud-text-muted-foreground">
            Cópiala ahora — por seguridad, no se volverá a mostrar.
          </p>
        </div>
        <button
          type="button"
          onClick={onDismiss}
          className="iaud-rounded-md iaud-p-1 iaud-text-muted-foreground hover:iaud-bg-muted hover:iaud-text-foreground"
          aria-label="Descartar"
        >
          <X className="iaud-h-4 iaud-w-4" />
        </button>
      </div>
      <div className="iaud-mt-3 iaud-flex iaud-items-center iaud-gap-2">
        <code className="iaud-flex-1 iaud-overflow-x-auto iaud-rounded-md iaud-border iaud-border-border iaud-bg-background iaud-px-3 iaud-py-2 iaud-font-mono iaud-text-xs">
          {plainKey}
        </code>
        <Button
          type="button"
          size="sm"
          variant={copied ? 'secondary' : 'default'}
          onClick={() => void copyToClipboard()}
          className={cn(copied && 'iaud-text-emerald-700')}
        >
          {copied ? (
            <>
              <Check className="iaud-h-4 iaud-w-4" />
              Copiado
            </>
          ) : (
            <>
              <Copy className="iaud-h-4 iaud-w-4" />
              Copiar
            </>
          )}
        </Button>
      </div>
    </div>
  );
}
