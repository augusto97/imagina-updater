import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
  useCreateApiKey,
  usePluginGroupsLite,
  usePluginsLite,
  useUpdateApiKey,
} from './api';
import { PluginPicker } from './PluginPicker';
import type { AccessType, ApiKey, ApiKeyFormValues } from './types';

const ACCESS_TYPE_OPTIONS: Array<{ value: AccessType; label: string; description: string }> = [
  {
    value: 'all',
    label: 'Todos los plugins',
    description: 'La API key tendrá acceso a cualquier plugin del servidor.',
  },
  {
    value: 'specific',
    label: 'Plugins específicos',
    description: 'Acceso solo a los plugins seleccionados.',
  },
  {
    value: 'groups',
    label: 'Grupos',
    description: 'Acceso a los plugins de los grupos seleccionados.',
  },
];

interface DrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Si está presente, el drawer entra en modo edición. */
  editing?: ApiKey | undefined;
  /** Callback cuando se crea una nueva key (para mostrar el banner del plain key). */
  onCreated?: (plainKey: string, item: ApiKey) => void;
}

const EMPTY_VALUES: ApiKeyFormValues = {
  site_name: '',
  site_url: '',
  access_type: 'all',
  allowed_plugins: [],
  allowed_groups: [],
  max_activations: 1,
};

export function ApiKeyDrawer({ open, onOpenChange, editing, onCreated }: DrawerProps) {
  const isEditing = Boolean(editing);
  const [values, setValues] = useState<ApiKeyFormValues>(EMPTY_VALUES);
  const [error, setError] = useState<string | null>(null);

  const create = useCreateApiKey();
  const update = useUpdateApiKey();
  const plugins = usePluginsLite();
  const groups = usePluginGroupsLite();

  // Reset al abrir / cambiar de modo.
  useEffect(() => {
    if (!open) return;
    setError(null);
    setValues(
      editing
        ? {
            site_name: editing.site_name,
            site_url: editing.site_url,
            access_type: editing.access_type,
            allowed_plugins: editing.allowed_plugins,
            allowed_groups: editing.allowed_groups,
            max_activations: editing.max_activations,
          }
        : EMPTY_VALUES,
    );
  }, [open, editing]);

  const pluginItems = useMemo(
    () =>
      (plugins.data ?? []).map((p) => ({
        id: p.id,
        label: p.name,
        hint: p.effective_slug,
      })),
    [plugins.data],
  );
  const groupItems = useMemo(
    () => (groups.data ?? []).map((g) => ({ id: g.id, label: g.name })),
    [groups.data],
  );

  const isSaving = create.isPending || update.isPending;

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);

    if (!values.site_name.trim() || !values.site_url.trim()) {
      setError('Nombre y URL del sitio son obligatorios.');
      return;
    }

    try {
      if (isEditing && editing) {
        await update.mutateAsync({ id: editing.id, values });
        onOpenChange(false);
      } else {
        const result = await create.mutateAsync(values);
        if (result.plain_key) {
          onCreated?.(result.plain_key, result.item);
        }
        onOpenChange(false);
      }
    } catch (e2) {
      setError(e2 instanceof Error ? e2.message : 'Error desconocido.');
    }
  }

  return (
    <Drawer
      open={open}
      onOpenChange={onOpenChange}
      title={isEditing ? 'Editar API key' : 'Nueva API key'}
      description={
        isEditing
          ? 'Cambia el nombre, URL, permisos o el límite de activaciones.'
          : 'La clave en claro se mostrará una sola vez al crearla.'
      }
      footer={
        <>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => onOpenChange(false)}
            disabled={isSaving}
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            form="iaud-api-key-form"
            size="sm"
            disabled={isSaving}
          >
            {isSaving ? 'Guardando…' : isEditing ? 'Guardar cambios' : 'Crear'}
          </Button>
        </>
      }
    >
      <form id="iaud-api-key-form" onSubmit={onSubmit} className="iaud-space-y-4">
        <div className="iaud-space-y-1.5">
          <Label htmlFor="iaud-key-name">Nombre del sitio</Label>
          <Input
            id="iaud-key-name"
            value={values.site_name}
            onChange={(e) => setValues((v) => ({ ...v, site_name: e.target.value }))}
            placeholder="ej. Cliente Acme — producción"
            autoFocus
          />
        </div>

        <div className="iaud-space-y-1.5">
          <Label htmlFor="iaud-key-url">URL del sitio</Label>
          <Input
            id="iaud-key-url"
            type="url"
            value={values.site_url}
            onChange={(e) => setValues((v) => ({ ...v, site_url: e.target.value }))}
            placeholder="https://cliente.example.com"
          />
        </div>

        <div className="iaud-space-y-1.5">
          <Label htmlFor="iaud-key-max">Activaciones máximas</Label>
          <Input
            id="iaud-key-max"
            type="number"
            min={0}
            value={values.max_activations}
            onChange={(e) =>
              setValues((v) => ({ ...v, max_activations: Math.max(0, Number(e.target.value) || 0) }))
            }
          />
          <p className="iaud-text-xs iaud-text-muted-foreground">
            Usa <code>0</code> para ilimitado.
          </p>
        </div>

        <fieldset className="iaud-space-y-2">
          <legend className="iaud-text-sm iaud-font-medium iaud-text-foreground">
            Tipo de acceso
          </legend>
          <div className="iaud-space-y-2">
            {ACCESS_TYPE_OPTIONS.map((opt) => (
              <label
                key={opt.value}
                className={cn(
                  'iaud-flex iaud-cursor-pointer iaud-items-start iaud-gap-3 iaud-rounded-md iaud-border iaud-border-border iaud-p-3 hover:iaud-bg-muted/50',
                  values.access_type === opt.value && 'iaud-border-primary iaud-bg-primary/5',
                )}
              >
                <input
                  type="radio"
                  name="iaud-access-type"
                  value={opt.value}
                  checked={values.access_type === opt.value}
                  onChange={() => setValues((v) => ({ ...v, access_type: opt.value }))}
                  className="iaud-mt-0.5"
                />
                <span className="iaud-flex-1">
                  <span className="iaud-block iaud-text-sm iaud-font-medium iaud-text-foreground">
                    {opt.label}
                  </span>
                  <span className="iaud-block iaud-text-xs iaud-text-muted-foreground">
                    {opt.description}
                  </span>
                </span>
              </label>
            ))}
          </div>
        </fieldset>

        {values.access_type === 'specific' && (
          <div className="iaud-space-y-1.5">
            <Label>Plugins permitidos</Label>
            <PluginPicker
              items={pluginItems}
              selected={values.allowed_plugins}
              onChange={(next) => setValues((v) => ({ ...v, allowed_plugins: next }))}
              isLoading={plugins.isLoading}
              emptyMessage="Aún no hay plugins en el servidor."
              searchPlaceholder="Buscar plugin…"
            />
          </div>
        )}

        {values.access_type === 'groups' && (
          <div className="iaud-space-y-1.5">
            <Label>Grupos permitidos</Label>
            <PluginPicker
              items={groupItems}
              selected={values.allowed_groups}
              onChange={(next) => setValues((v) => ({ ...v, allowed_groups: next }))}
              isLoading={groups.isLoading}
              emptyMessage="Aún no hay grupos creados."
              searchPlaceholder="Buscar grupo…"
            />
          </div>
        )}

        {error && (
          <p className="iaud-rounded-md iaud-border iaud-border-destructive/50 iaud-bg-destructive/10 iaud-p-3 iaud-text-sm iaud-text-destructive">
            {error}
          </p>
        )}
      </form>
    </Drawer>
  );
}
