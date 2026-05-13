import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { PluginPicker } from '../api-keys/PluginPicker';
import { usePluginGroupsLite, useUpdatePlugin } from './api';
import type { PluginRow } from './types';

interface EditDrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  plugin: PluginRow | undefined;
}

export function EditDrawer({ open, onOpenChange, plugin }: EditDrawerProps) {
  const [slugOverride, setSlugOverride] = useState('');
  const [description, setDescription] = useState('');
  const [groupIds, setGroupIds] = useState<number[]>([]);
  const [error, setError] = useState<string | null>(null);

  const groups = usePluginGroupsLite();
  const update = useUpdatePlugin();

  useEffect(() => {
    if (!open || !plugin) return;
    setSlugOverride(plugin.slug_override ?? '');
    setDescription(plugin.description ?? '');
    setGroupIds(plugin.group_ids);
    setError(null);
  }, [open, plugin]);

  const groupItems = useMemo(
    () => (groups.data ?? []).map((g) => ({ id: g.id, label: g.name })),
    [groups.data],
  );

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (!plugin) return;
    setError(null);
    try {
      await update.mutateAsync({
        id: plugin.id,
        values: {
          slug_override: slugOverride.trim(),
          description: description.trim(),
          group_ids: groupIds,
        },
      });
      onOpenChange(false);
    } catch (e2) {
      setError(e2 instanceof Error ? e2.message : 'Error desconocido.');
    }
  }

  return (
    <Drawer
      open={open}
      onOpenChange={onOpenChange}
      title={plugin ? `Editar ${plugin.name}` : 'Editar plugin'}
      description="Los cambios afectan al manifiesto que ven los sitios cliente al hacer check-updates."
      footer={
        <>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => onOpenChange(false)}
            disabled={update.isPending}
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            form="iaud-edit-plugin-form"
            size="sm"
            disabled={update.isPending}
          >
            {update.isPending ? 'Guardando…' : 'Guardar cambios'}
          </Button>
        </>
      }
    >
      {plugin && (
        <form id="iaud-edit-plugin-form" onSubmit={onSubmit} className="iaud-space-y-4">
          <div className="iaud-rounded-md iaud-border iaud-border-border iaud-bg-muted/30 iaud-p-3 iaud-text-xs iaud-text-muted-foreground">
            <p>
              <span className="iaud-font-medium iaud-text-foreground">Slug original:</span>{' '}
              <code>{plugin.slug}</code>
            </p>
            <p className="iaud-mt-1">
              <span className="iaud-font-medium iaud-text-foreground">Versión actual:</span>{' '}
              <code>{plugin.current_version}</code>
            </p>
          </div>

          <div className="iaud-space-y-1.5">
            <Label htmlFor="iaud-edit-slug">Slug personalizado</Label>
            <Input
              id="iaud-edit-slug"
              value={slugOverride}
              onChange={(e) => setSlugOverride(e.target.value)}
              placeholder="Deja vacío para usar el slug original"
            />
            <p className="iaud-text-xs iaud-text-muted-foreground">
              Si el SDK injector renombra el directorio, fija aquí el slug
              correcto que el sitio cliente verá.
            </p>
          </div>

          <div className="iaud-space-y-1.5">
            <Label htmlFor="iaud-edit-description">Descripción</Label>
            <Textarea
              id="iaud-edit-description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={4}
            />
          </div>

          <div className="iaud-space-y-1.5">
            <Label>Grupos</Label>
            <PluginPicker
              items={groupItems}
              selected={groupIds}
              onChange={setGroupIds}
              isLoading={groups.isLoading}
              emptyMessage="No hay grupos creados todavía."
              searchPlaceholder="Buscar grupo…"
            />
          </div>

          {error && (
            <p className="iaud-rounded-md iaud-border iaud-border-destructive/50 iaud-bg-destructive/10 iaud-p-3 iaud-text-sm iaud-text-destructive">
              {error}
            </p>
          )}
        </form>
      )}
    </Drawer>
  );
}
