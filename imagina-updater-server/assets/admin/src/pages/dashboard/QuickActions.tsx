import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { KeyRound, Upload } from 'lucide-react';

/**
 * Acciones rápidas. Por ahora son links a las pantallas legacy PHP
 * (Plugins / API Keys). Cuando se completen 5.2 y 5.3 se reemplazan
 * por handlers de drawers SPA inline.
 */
export function QuickActions() {
  const adminBase = window.iaudConfig?.siteUrl
    ? `${window.iaudConfig.siteUrl.replace(/\/$/, '')}/wp-admin/admin.php`
    : '/wp-admin/admin.php';

  return (
    <Card>
      <CardHeader>
        <CardTitle>Acciones rápidas</CardTitle>
      </CardHeader>
      <CardContent className="iaud-flex iaud-flex-col iaud-gap-2 sm:iaud-flex-row">
        <Button
          variant="default"
          size="sm"
          onClick={() => goTo(`${adminBase}?page=imagina-updater-plugins`)}
        >
          <Upload className="iaud-h-4 iaud-w-4" />
          Subir plugin
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={() => goTo(`${adminBase}?page=imagina-updater-api-keys`)}
        >
          <KeyRound className="iaud-h-4 iaud-w-4" />
          Crear API key
        </Button>
      </CardContent>
    </Card>
  );
}

function goTo(url: string) {
  window.location.assign(url);
}
