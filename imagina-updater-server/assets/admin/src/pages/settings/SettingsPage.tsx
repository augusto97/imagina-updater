import { useEffect, useState } from 'react';
import {
  Activity,
  AlertTriangle,
  CheckCircle2,
  CircleSlash,
  Database,
  ListMinus,
  Save,
  Settings as SettingsIcon,
  Wrench,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import {
  useClearRateLimits,
  useRunMigrations,
  useSettings,
  useUpdateSettings,
} from './api';
import type { LogLevel, SettingsTab, SettingsValues, SystemInfo } from './types';

const TABS: Array<{ value: SettingsTab; label: string; icon: React.ReactNode }> = [
  { value: 'general', label: 'General', icon: <Activity className="iaud-h-4 iaud-w-4" /> },
  { value: 'logging', label: 'Logging', icon: <ListMinus className="iaud-h-4 iaud-w-4" /> },
  { value: 'maintenance', label: 'Mantenimiento', icon: <Wrench className="iaud-h-4 iaud-w-4" /> },
];

export function SettingsPage() {
  const [tab, setTab] = useState<SettingsTab>('general');
  const settings = useSettings();

  return (
    <div className="iaud-app iaud-min-h-screen iaud-bg-background iaud-p-6">
      <header className="iaud-mb-6">
        <h1 className="iaud-flex iaud-items-center iaud-gap-2 iaud-text-2xl iaud-font-semibold iaud-tracking-tight iaud-text-foreground">
          <SettingsIcon className="iaud-h-6 iaud-w-6 iaud-text-primary" />
          Configuración
        </h1>
        <p className="iaud-mt-1 iaud-text-sm iaud-text-muted-foreground">
          Ajustes generales, logging y herramientas de mantenimiento del servidor.
        </p>
      </header>

      <nav className="iaud-mb-4 iaud-flex iaud-flex-wrap iaud-items-center iaud-gap-1 iaud-border-b iaud-border-border">
        {TABS.map((t) => (
          <button
            key={t.value}
            type="button"
            onClick={() => setTab(t.value)}
            className={cn(
              'iaud-inline-flex iaud-items-center iaud-gap-2 iaud-border-b-2 iaud-border-transparent iaud-px-3 iaud-py-2 iaud-text-sm iaud-font-medium iaud-text-muted-foreground hover:iaud-text-foreground',
              tab === t.value && 'iaud-border-primary iaud-text-foreground',
            )}
          >
            {t.icon}
            {t.label}
          </button>
        ))}
      </nav>

      {settings.isLoading ? (
        <Skeleton className="iaud-h-64 iaud-w-full" />
      ) : settings.isError ? (
        <Card>
          <CardContent className="iaud-p-4 iaud-text-sm iaud-text-destructive">
            Error al cargar la configuración:{' '}
            {settings.error instanceof Error ? settings.error.message : 'desconocido'}.
          </CardContent>
        </Card>
      ) : (
        <>
          {tab === 'general' && settings.data && (
            <GeneralTab system={settings.data.system} />
          )}
          {tab === 'logging' && settings.data && (
            <LoggingTab values={settings.data.settings} />
          )}
          {tab === 'maintenance' && <MaintenanceTab />}
        </>
      )}
    </div>
  );
}

function GeneralTab({ system }: { system: SystemInfo }) {
  const rows: Array<{ label: string; value: React.ReactNode }> = [
    { label: 'Versión del plugin', value: system.plugin_version ?? '—' },
    { label: 'Versión de la base de datos', value: system.db_version },
    { label: 'Versión de WordPress', value: system.wp_version },
    { label: 'Versión de PHP', value: system.php_version },
    { label: 'Versión de MySQL', value: system.mysql_version },
    {
      label: 'Extensión de licencias',
      value: system.license_extension_active ? (
        <Badge variant="success" className="iaud-gap-1">
          <CheckCircle2 className="iaud-h-3 iaud-w-3" />
          Activa
        </Badge>
      ) : (
        <Badge variant="secondary" className="iaud-gap-1">
          <CircleSlash className="iaud-h-3 iaud-w-3" />
          Inactiva
        </Badge>
      ),
    },
    {
      label: 'Object cache (flush_group)',
      value: system.object_cache_supported ? (
        <Badge variant="success">Soportado</Badge>
      ) : (
        <Badge variant="secondary">No soportado</Badge>
      ),
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Información del sistema</CardTitle>
        <CardDescription>
          Datos de entorno útiles al diagnosticar problemas.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <dl className="iaud-divide-y iaud-divide-border">
          {rows.map((row) => (
            <div
              key={row.label}
              className="iaud-flex iaud-items-center iaud-justify-between iaud-py-2 iaud-text-sm"
            >
              <dt className="iaud-text-muted-foreground">{row.label}</dt>
              <dd className="iaud-font-mono iaud-text-foreground">{row.value}</dd>
            </div>
          ))}
        </dl>
      </CardContent>
    </Card>
  );
}

function LoggingTab({ values }: { values: SettingsValues }) {
  const [enableLogging, setEnableLogging] = useState(values.enable_logging);
  const [logLevel, setLogLevel] = useState<LogLevel>(values.log_level);
  const [savedAt, setSavedAt] = useState<number | null>(null);
  const update = useUpdateSettings();

  // Si la query refresca con datos nuevos (otro tab del navegador, etc.)
  // mantenemos el estado local sincronizado.
  useEffect(() => {
    setEnableLogging(values.enable_logging);
    setLogLevel(values.log_level);
  }, [values.enable_logging, values.log_level]);

  const dirty =
    enableLogging !== values.enable_logging || logLevel !== values.log_level;

  async function onSave() {
    try {
      await update.mutateAsync({
        enable_logging: enableLogging,
        log_level: logLevel,
      });
      setSavedAt(Date.now());
    } catch {
      /* error queda en update.error */
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Configuración del logger</CardTitle>
        <CardDescription>
          Controla qué registra el plugin servidor en disco.
        </CardDescription>
      </CardHeader>
      <CardContent className="iaud-space-y-4">
        <label className="iaud-flex iaud-cursor-pointer iaud-items-start iaud-gap-3 iaud-rounded-md iaud-border iaud-border-border iaud-p-3 hover:iaud-bg-muted/50">
          <input
            type="checkbox"
            checked={enableLogging}
            onChange={(e) => setEnableLogging(e.target.checked)}
            className="iaud-mt-0.5"
          />
          <span className="iaud-flex-1">
            <span className="iaud-block iaud-text-sm iaud-font-medium iaud-text-foreground">
              Activar logging
            </span>
            <span className="iaud-block iaud-text-xs iaud-text-muted-foreground">
              Cuando está desactivado, el servidor no escribe nuevas entradas en
              disco. Las existentes siguen siendo visibles en la pantalla de Logs.
            </span>
          </span>
        </label>

        <div className="iaud-space-y-1.5">
          <Label htmlFor="iaud-log-level">Nivel mínimo</Label>
          <select
            id="iaud-log-level"
            value={logLevel}
            onChange={(e) => setLogLevel(e.target.value as LogLevel)}
            disabled={!enableLogging}
            className={cn(
              'iaud-h-9 iaud-w-full iaud-rounded-md iaud-border iaud-border-input iaud-bg-background iaud-px-3 iaud-text-sm iaud-shadow-sm sm:iaud-w-72',
              'focus-visible:iaud-outline-none focus-visible:iaud-ring-2 focus-visible:iaud-ring-ring focus-visible:iaud-ring-offset-2',
              'disabled:iaud-opacity-50',
            )}
          >
            <option value="DEBUG">DEBUG (todo)</option>
            <option value="INFO">INFO (recomendado)</option>
            <option value="WARNING">WARNING</option>
            <option value="ERROR">ERROR (solo errores)</option>
          </select>
          <p className="iaud-text-xs iaud-text-muted-foreground">
            Solo se escribirán entradas iguales o más graves que el nivel
            seleccionado.
          </p>
        </div>

        {update.isError && (
          <p className="iaud-rounded-md iaud-border iaud-border-destructive/50 iaud-bg-destructive/10 iaud-p-3 iaud-text-sm iaud-text-destructive">
            {update.error instanceof Error ? update.error.message : 'Error al guardar.'}
          </p>
        )}

        {savedAt !== null && !update.isPending && !update.isError && (
          <p className="iaud-text-xs iaud-text-emerald-700">Guardado correctamente.</p>
        )}

        <div className="iaud-flex iaud-justify-end">
          <Button
            type="button"
            size="sm"
            onClick={() => void onSave()}
            disabled={!dirty || update.isPending}
          >
            <Save className="iaud-h-4 iaud-w-4" />
            {update.isPending ? 'Guardando…' : 'Guardar'}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

function MaintenanceTab() {
  const migrations = useRunMigrations();
  const rateLimits = useClearRateLimits();

  return (
    <div className="iaud-space-y-4">
      <Card>
        <CardHeader>
          <CardTitle className="iaud-flex iaud-items-center iaud-gap-2">
            <Database className="iaud-h-4 iaud-w-4 iaud-text-muted-foreground" />
            Re-ejecutar migraciones de base de datos
          </CardTitle>
          <CardDescription>
            Crea las tablas custom si faltan y aplica las migraciones de
            columnas (idempotente, seguro en caliente).
          </CardDescription>
        </CardHeader>
        <CardContent className="iaud-space-y-3">
          {migrations.isError && (
            <p className="iaud-rounded-md iaud-border iaud-border-destructive/50 iaud-bg-destructive/10 iaud-p-3 iaud-text-sm iaud-text-destructive">
              {migrations.error instanceof Error
                ? migrations.error.message
                : 'Error al ejecutar las migraciones.'}
            </p>
          )}
          {migrations.isSuccess && (
            <p className="iaud-rounded-md iaud-border iaud-border-emerald-500/40 iaud-bg-emerald-500/10 iaud-p-3 iaud-text-sm iaud-text-emerald-700">
              Migraciones aplicadas. db_version: <code>{migrations.data.db_version}</code>.
            </p>
          )}
          <div className="iaud-flex iaud-justify-end">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => migrations.mutate()}
              disabled={migrations.isPending}
            >
              {migrations.isPending ? 'Ejecutando…' : 'Ejecutar migraciones'}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle className="iaud-flex iaud-items-center iaud-gap-2">
            <AlertTriangle className="iaud-h-4 iaud-w-4 iaud-text-amber-600" />
            Limpiar rate-limits
          </CardTitle>
          <CardDescription>
            Borra los transients de rate-limit del servidor. Útil si un cliente
            queda bloqueado por un pico anómalo. La operación está protegida con
            un throttle de 60 s y exige <code>manage_options</code>.
          </CardDescription>
        </CardHeader>
        <CardContent className="iaud-space-y-3">
          {rateLimits.isError && (
            <p className="iaud-rounded-md iaud-border iaud-border-destructive/50 iaud-bg-destructive/10 iaud-p-3 iaud-text-sm iaud-text-destructive">
              {rateLimits.error instanceof Error
                ? rateLimits.error.message
                : 'Error al limpiar rate-limits.'}
            </p>
          )}
          {rateLimits.isSuccess && (
            <p
              className={cn(
                'iaud-rounded-md iaud-border iaud-p-3 iaud-text-sm',
                rateLimits.data.success
                  ? 'iaud-border-emerald-500/40 iaud-bg-emerald-500/10 iaud-text-emerald-700'
                  : 'iaud-border-amber-500/40 iaud-bg-amber-500/10 iaud-text-amber-700',
              )}
            >
              {rateLimits.data.message}
            </p>
          )}
          <div className="iaud-flex iaud-justify-end">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => {
                if (
                  !window.confirm(
                    '¿Limpiar todos los rate-limits del servidor? Los clientes podrán volver a hacer requests inmediatamente.',
                  )
                )
                  return;
                rateLimits.mutate();
              }}
              disabled={rateLimits.isPending}
            >
              {rateLimits.isPending ? 'Limpiando…' : 'Limpiar rate-limits'}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
