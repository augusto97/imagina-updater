import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Activity, Download, KeyRound, Package } from 'lucide-react';
import { useDashboardStats } from './api';
import { formatNumber } from '@/lib/format';

interface StatCard {
  label: string;
  value: number | undefined;
  icon: React.ReactNode;
}

export function StatsCards() {
  const { data, isLoading, isError, error, refetch } = useDashboardStats();

  const cards: StatCard[] = [
    { label: 'Plugins', value: data?.total_plugins, icon: <Package className="iaud-h-4 iaud-w-4" /> },
    { label: 'API Keys activas', value: data?.active_api_keys, icon: <KeyRound className="iaud-h-4 iaud-w-4" /> },
    { label: 'Activaciones activas', value: data?.active_activations, icon: <Activity className="iaud-h-4 iaud-w-4" /> },
    { label: 'Descargas 24 h', value: data?.downloads_24h, icon: <Download className="iaud-h-4 iaud-w-4" /> },
  ];

  if (isError) {
    return (
      <Card>
        <CardContent className="iaud-p-4 iaud-text-sm iaud-text-destructive">
          Error al cargar estadísticas: {error instanceof Error ? error.message : 'desconocido'}.{' '}
          <button
            type="button"
            className="iaud-underline"
            onClick={() => void refetch()}
          >
            Reintentar
          </button>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="iaud-grid iaud-grid-cols-1 iaud-gap-3 sm:iaud-grid-cols-2 lg:iaud-grid-cols-4">
      {cards.map((card) => (
        <Card key={card.label}>
          <CardHeader className="iaud-flex-row iaud-items-center iaud-justify-between iaud-space-y-0">
            <CardTitle>{card.label}</CardTitle>
            <span className="iaud-text-muted-foreground">{card.icon}</span>
          </CardHeader>
          <CardContent>
            {isLoading || card.value === undefined ? (
              <Skeleton className="iaud-h-8 iaud-w-20" />
            ) : (
              <div className="iaud-text-2xl iaud-font-semibold iaud-tabular-nums">
                {formatNumber(card.value)}
              </div>
            )}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
