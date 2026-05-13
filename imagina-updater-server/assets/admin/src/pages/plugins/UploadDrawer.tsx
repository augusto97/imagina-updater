import { useEffect, useMemo, useRef, useState, type FormEvent } from 'react';
import { FileArchive, ShieldCheck, Upload } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Drawer } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { PluginPicker } from '../api-keys/PluginPicker';
import { usePluginGroupsLite, useUploadPlugin } from './api';
import { formatBytes } from './lib';

interface UploadDrawerProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function UploadDrawer({ open, onOpenChange }: UploadDrawerProps) {
  const [file, setFile] = useState<File | null>(null);
  const [changelog, setChangelog] = useState('');
  const [description, setDescription] = useState('');
  const [isPremium, setIsPremium] = useState(false);
  const [groupIds, setGroupIds] = useState<number[]>([]);
  const [progress, setProgress] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [isDragOver, setIsDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const groups = usePluginGroupsLite();
  const upload = useUploadPlugin((p) => setProgress(p));

  const licenseExtensionActive = window.iaudConfig?.licenseExtensionActive ?? false;

  // Reset al abrir.
  useEffect(() => {
    if (!open) return;
    setFile(null);
    setChangelog('');
    setDescription('');
    setIsPremium(false);
    setGroupIds([]);
    setProgress(0);
    setError(null);
    setIsDragOver(false);
  }, [open]);

  const groupItems = useMemo(
    () => (groups.data ?? []).map((g) => ({ id: g.id, label: g.name })),
    [groups.data],
  );

  function handleFile(f: File | undefined) {
    if (!f) return;
    if (!f.name.toLowerCase().endsWith('.zip')) {
      setError('El archivo debe ser un .zip.');
      return;
    }
    setError(null);
    setFile(f);
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    if (!file) {
      setError('Selecciona un archivo ZIP.');
      return;
    }
    try {
      await upload.mutateAsync({
        file,
        changelog,
        description,
        is_premium: isPremium,
        group_ids: groupIds,
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
      title="Subir plugin"
      description="Sube un nuevo plugin o una nueva versión de uno existente."
      footer={
        <>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => onOpenChange(false)}
            disabled={upload.isPending}
          >
            Cancelar
          </Button>
          <Button
            type="submit"
            form="iaud-upload-form"
            size="sm"
            disabled={upload.isPending || !file}
          >
            {upload.isPending ? `Subiendo… ${progress}%` : 'Subir'}
          </Button>
        </>
      }
    >
      <form id="iaud-upload-form" onSubmit={onSubmit} className="iaud-space-y-4">
        <div
          className={cn(
            'iaud-rounded-md iaud-border-2 iaud-border-dashed iaud-border-border iaud-p-6 iaud-text-center iaud-transition-colors',
            isDragOver && 'iaud-border-primary iaud-bg-primary/5',
          )}
          onDragOver={(e) => {
            e.preventDefault();
            setIsDragOver(true);
          }}
          onDragLeave={() => setIsDragOver(false)}
          onDrop={(e) => {
            e.preventDefault();
            setIsDragOver(false);
            handleFile(e.dataTransfer.files[0]);
          }}
        >
          {file ? (
            <div className="iaud-flex iaud-items-center iaud-justify-center iaud-gap-3">
              <FileArchive className="iaud-h-6 iaud-w-6 iaud-text-primary" />
              <div className="iaud-text-left">
                <p className="iaud-text-sm iaud-font-medium iaud-text-foreground">
                  {file.name}
                </p>
                <p className="iaud-text-xs iaud-text-muted-foreground">
                  {formatBytes(file.size)}
                </p>
              </div>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => setFile(null)}
              >
                Cambiar
              </Button>
            </div>
          ) : (
            <>
              <Upload className="iaud-mx-auto iaud-h-8 iaud-w-8 iaud-text-muted-foreground" />
              <p className="iaud-mt-2 iaud-text-sm iaud-text-foreground">
                Arrastra el ZIP aquí o
              </p>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="iaud-mt-2"
                onClick={() => fileInputRef.current?.click()}
              >
                Selecciona un archivo
              </Button>
              <input
                ref={fileInputRef}
                type="file"
                accept=".zip,application/zip"
                className="iaud-hidden"
                onChange={(e) => handleFile(e.target.files?.[0])}
              />
            </>
          )}
        </div>

        {upload.isPending && (
          <div className="iaud-h-1 iaud-w-full iaud-overflow-hidden iaud-rounded-full iaud-bg-muted">
            <div
              className="iaud-h-full iaud-bg-primary iaud-transition-all"
              style={{ width: `${progress}%` }}
            />
          </div>
        )}

        <div className="iaud-space-y-1.5">
          <Label htmlFor="iaud-upload-changelog">Notas de la versión (opcional)</Label>
          <Textarea
            id="iaud-upload-changelog"
            value={changelog}
            onChange={(e) => setChangelog(e.target.value)}
            placeholder="Cambios incluidos en esta versión…"
            rows={3}
          />
        </div>

        <div className="iaud-space-y-1.5">
          <Label htmlFor="iaud-upload-description">Descripción del plugin (opcional)</Label>
          <Input
            id="iaud-upload-description"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="Sobrescribe la descripción del header del plugin."
          />
        </div>

        {licenseExtensionActive && (
          <label className="iaud-flex iaud-cursor-pointer iaud-items-start iaud-gap-3 iaud-rounded-md iaud-border iaud-border-border iaud-p-3 hover:iaud-bg-muted/50">
            <input
              type="checkbox"
              checked={isPremium}
              onChange={(e) => setIsPremium(e.target.checked)}
              className="iaud-mt-0.5"
            />
            <span className="iaud-flex-1">
              <span className="iaud-flex iaud-items-center iaud-gap-1.5 iaud-text-sm iaud-font-medium iaud-text-foreground">
                <ShieldCheck className="iaud-h-4 iaud-w-4 iaud-text-primary" />
                Marcar como premium
              </span>
              <span className="iaud-block iaud-text-xs iaud-text-muted-foreground">
                Inyecta protección de licencia en el ZIP tras la subida.
              </span>
            </span>
          </label>
        )}

        <div className="iaud-space-y-1.5">
          <Label>Grupos (opcional)</Label>
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
    </Drawer>
  );
}
