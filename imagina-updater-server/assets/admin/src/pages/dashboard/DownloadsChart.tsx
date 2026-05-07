import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useDownloads30d } from './api';
import { formatNumber } from '@/lib/format';

/**
 * Bar chart minimalista de descargas últimos 30 días, dibujado con SVG
 * inline. Decisión consciente de NO añadir recharts/chart.js para
 * mantener el bundle por debajo del budget (CLAUDE.md §2.3). Si en
 * fases futuras se necesita más complejidad (tooltips ricos, leyendas
 * múltiples, animaciones), se reevalúa.
 */
export function DownloadsChart() {
  const { data, isLoading, isError, error } = useDownloads30d();

  return (
    <Card>
      <CardHeader>
        <CardTitle>Descargas — últimos 30 días</CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <Skeleton className="iaud-h-32 iaud-w-full" />
        ) : isError ? (
          <p className="iaud-text-sm iaud-text-destructive">
            Error al cargar la serie: {error instanceof Error ? error.message : 'desconocido'}.
          </p>
        ) : data && data.length > 0 ? (
          <ChartBody data={data} />
        ) : (
          <p className="iaud-text-sm iaud-text-muted-foreground">Sin datos.</p>
        )}
      </CardContent>
    </Card>
  );
}

interface ChartBodyProps {
  data: Array<{ day: string; count: number }>;
}

function ChartBody({ data }: ChartBodyProps) {
  const max = Math.max(...data.map((d) => d.count), 1);
  const total = data.reduce((sum, d) => sum + d.count, 0);
  const peak = data.reduce((acc, d) => (d.count > acc.count ? d : acc), data[0]!);

  const width = 600;
  const height = 120;
  const barWidth = width / data.length;
  const gap = 2;

  return (
    <div className="iaud-space-y-3">
      <div className="iaud-flex iaud-items-baseline iaud-gap-4 iaud-text-sm">
        <span className="iaud-text-2xl iaud-font-semibold iaud-tabular-nums iaud-text-foreground">
          {formatNumber(total)}
        </span>
        <span className="iaud-text-muted-foreground">descargas totales</span>
      </div>
      <svg
        viewBox={`0 0 ${width} ${height}`}
        preserveAspectRatio="none"
        role="img"
        aria-label={`Descargas diarias últimos 30 días. Pico: ${formatNumber(peak.count)} el ${peak.day}.`}
        className="iaud-h-32 iaud-w-full"
      >
        {data.map((d, i) => {
          const h = (d.count / max) * (height - 4);
          const x = i * barWidth + gap / 2;
          const y = height - h;
          return (
            <rect
              key={d.day}
              x={x}
              y={y}
              width={Math.max(barWidth - gap, 1)}
              height={h}
              rx={1}
              fill="hsl(var(--iaud-primary))"
              opacity={d.count === 0 ? 0.15 : 1}
            >
              <title>
                {d.day}: {formatNumber(d.count)}
              </title>
            </rect>
          );
        })}
      </svg>
      <div className="iaud-flex iaud-justify-between iaud-text-xs iaud-text-muted-foreground">
        <span>{data[0]!.day}</span>
        <span>{data[data.length - 1]!.day}</span>
      </div>
    </div>
  );
}
