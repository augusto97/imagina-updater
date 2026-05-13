import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { PluginPicker } from '../api-keys/PluginPicker';
import {
  useCreatePluginGroup,
  usePluginsLite,
  useUpdatePluginGroup,
} from './api';
import type { PluginGroupRow } from './types';

interface DrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  editing?: PluginGroupRow | undefined;
}

const EMPTY = { name: '', description: '', plugin_ids: [] as number[] };

export function PluginGroupDrawer({ open, onOpenChange, editing }: DrawerProps) {
  const isEditing = Boolean(editing);
  const [values, setValues] = useState(EMPTY);
  const [error, setError] = useState<string | null>(null);

  const plugins = usePluginsLite();
  const create = useCreatePluginGroup();
  const update = useUpdatePluginGroup();

  useEffect(() => {
    if (!open) return;
    setError(null);
    setValues(
      editing
        ? {
            name: editing.name,
            description: editing.description ?? '',
            plugin_ids: editing.plugin_ids ?? [],
          }
        : EMPTY,
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

  const isSaving = create.isPending || update.isPending;

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);

    if (!values.name.trim()) {
      setError('El nombre del grupo es obligatorio.');
      return;
    }

    try {
      if (isEditing && editing) {
        await update.mutateAsync({ id: editing.id, values });
      } else {
        await create.mutateAsync(values);
      }
      onOpenChange(false);
    } catch (e2) {
      setError(e2 instanceof Error ? e2.message : 'Error desconocido.');
    }
  }

  return (
    <Drawer
      open={open}
      onOpenChange={onOpenChange}
      title={isEditing ? 'Editar grupo' : 'Nuevo grupo'}
      description="Los grupos agrupan plugins para conceder acceso conjunto a una API key."
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
            form="iaud-group-form"
            size="sm"
            disabled={isSaving}
          >
            {isSaving ? 'Guardando…' : isEditing ? 'Guardar cambios' : 'Crear'}
          </Button>
        </>
      }
    >
      <form id="iaud-group-form" onSubmit={onSubmit} className="iaud-space-y-4">
        <div className="iaud-space-y-1.5">
          <Label htmlFor="iaud-group-name">Nombre</Label>
          <Input
            id="iaud-group-name"
            value={values.name}
            onChange={(e) => setValues((v) => ({ ...v, name: e.target.value }))}
            placeholder="ej. Suite enterprise"
            autoFocus
          />
        </div>

        <div className="iaud-space-y-1.5">
          <Label htmlFor="iaud-group-description">Descripción (opcional)</Label>
          <Textarea
            id="iaud-group-description"
            value={values.description}
            onChange={(e) =>
              setValues((v) => ({ ...v, description: e.target.value }))
            }
            rows={3}
          />
        </div>

        <div className="iaud-space-y-1.5">
          <Label>Plugins incluidos</Label>
          <PluginPicker
            items={pluginItems}
            selected={values.plugin_ids}
            onChange={(next) => setValues((v) => ({ ...v, plugin_ids: next }))}
            isLoading={plugins.isLoading}
            emptyMessage="No hay plugins en el servidor todavía."
            searchPlaceholder="Buscar plugin…"
          />
        </div>

        {isEditing && editing && editing.linked_api_keys_count > 0 && (
          <p className="iaud-rounded-md iaud-border iaud-border-amber-500/40 iaud-bg-amber-500/10 iaud-p-3 iaud-text-xs iaud-text-amber-700">
            Este grupo está referenciado por {editing.linked_api_keys_count}{' '}
            API key{editing.linked_api_keys_count === 1 ? '' : 's'}. Cambiar
            su composición altera el acceso que esas keys conceden.
          </p>
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
