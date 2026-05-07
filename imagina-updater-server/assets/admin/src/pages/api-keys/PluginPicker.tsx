import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface Item {
  id: number;
  label: string;
  hint?: string;
}

interface PluginPickerProps {
  items: Item[] | undefined;
  selected: number[];
  onChange: (next: number[]) => void;
  isLoading?: boolean;
  emptyMessage?: string;
  searchPlaceholder?: string;
}

/**
 * Multi-select con búsqueda. Renderiza una lista virtualmente plana
 * (sin virtualización; los inventarios actuales son pequeños — si
 * supera ~500 plugins se añade @tanstack/react-virtual). La selección
 * vive completamente en el padre (controlled) para que el formulario
 * pueda reset/dirty/submit con un solo state.
 */
export function PluginPicker({
  items,
  selected,
  onChange,
  isLoading,
  emptyMessage = 'Sin elementos.',
  searchPlaceholder = 'Buscar…',
}: PluginPickerProps) {
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    if (!items) return [];
    const q = query.trim().toLowerCase();
    if (!q) return items;
    return items.filter(
      (it) =>
        it.label.toLowerCase().includes(q) ||
        (it.hint?.toLowerCase().includes(q) ?? false),
    );
  }, [items, query]);

  function toggle(id: number) {
    onChange(
      selected.includes(id)
        ? selected.filter((s) => s !== id)
        : [...selected, id],
    );
  }

  return (
    <div className="iaud-space-y-2">
      <Input
        type="search"
        placeholder={searchPlaceholder}
        value={query}
        onChange={(e) => setQuery(e.target.value)}
      />
      <div className="iaud-max-h-64 iaud-overflow-y-auto iaud-rounded-md iaud-border iaud-border-border">
        {isLoading ? (
          <div className="iaud-space-y-2 iaud-p-3">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="iaud-h-6 iaud-w-full" />
            ))}
          </div>
        ) : filtered.length === 0 ? (
          <p className="iaud-p-3 iaud-text-sm iaud-text-muted-foreground">
            {emptyMessage}
          </p>
        ) : (
          <ul className="iaud-divide-y iaud-divide-border">
            {filtered.map((it) => {
              const checked = selected.includes(it.id);
              return (
                <li key={it.id}>
                  <label
                    className={cn(
                      'iaud-flex iaud-cursor-pointer iaud-items-start iaud-gap-3 iaud-px-3 iaud-py-2 iaud-text-sm hover:iaud-bg-muted',
                      checked && 'iaud-bg-muted/50',
                    )}
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => toggle(it.id)}
                      className="iaud-mt-0.5"
                    />
                    <span className="iaud-flex-1">
                      <span className="iaud-block iaud-font-medium iaud-text-foreground">
                        {it.label}
                      </span>
                      {it.hint && (
                        <span className="iaud-block iaud-text-xs iaud-text-muted-foreground">
                          {it.hint}
                        </span>
                      )}
                    </span>
                  </label>
                </li>
              );
            })}
          </ul>
        )}
      </div>
      {selected.length > 0 && (
        <p className="iaud-text-xs iaud-text-muted-foreground">
          {selected.length} seleccionado{selected.length === 1 ? '' : 's'}.
        </p>
      )}
    </div>
  );
}
