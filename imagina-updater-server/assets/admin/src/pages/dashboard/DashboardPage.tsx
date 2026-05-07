import { useQueryClient } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { RefreshCw } from 'lucide-react';
import { StatsCards } from './StatsCards';
import { DownloadsChart } from './DownloadsChart';
import { RecentDownloadsTable } from './RecentDownloadsTable';
import { TopPluginsTable } from './TopPluginsTable';
import { QuickActions } from './QuickActions';

/**
 * Composición de la pantalla Dashboard (CLAUDE.md §6 fase 5.1).
 *
 * Layout: vertical stack de secciones, cada una en su Card.
 *  1. Header (título + acción "Actualizar todo").
 *  2. KPI cards (4 columnas en desktop, 2 en tablet, 1 en mobile).
 *  3. Gráfico de descargas 30 días.
 *  4. Grid 2 columnas: Últimas descargas + Top plugins.
 *  5. Quick actions.
 */
export function DashboardPage() {
  const queryClient = useQueryClient();
  const userName = window.iaudConfig?.currentUser ?? '';

  return (
    <div className="iaud-app iaud-min-h-screen iaud-bg-background iaud-p-6">
      <header className="iaud-mb-6 iaud-flex iaud-items-start iaud-justify-between iaud-gap-4">
        <div>
          <h1 className="iaud-text-2xl iaud-font-semibold iaud-tracking-tight iaud-text-foreground">
            Dashboard
          </h1>
          <p className="iaud-mt-1 iaud-text-sm iaud-text-muted-foreground">
            {userName ? `Hola, ${userName}. ` : ''}
            Resumen de actividad del servidor de actualizaciones.
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => void queryClient.invalidateQueries({ queryKey: ['dashboard'] })}
        >
          <RefreshCw className="iaud-h-4 iaud-w-4" />
          Actualizar
        </Button>
      </header>

      <div className="iaud-space-y-6">
        <StatsCards />

        <DownloadsChart />

        <div className="iaud-grid iaud-grid-cols-1 iaud-gap-6 lg:iaud-grid-cols-2">
          <RecentDownloadsTable />
          <TopPluginsTable />
        </div>

        <QuickActions />
      </div>
    </div>
  );
}
