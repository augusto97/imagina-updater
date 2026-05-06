import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * Combina clases utility de Tailwind con prioridad correcta cuando
 * hay conflictos (ej. `iaud-p-2` + `iaud-p-4` resuelve a `iaud-p-4`).
 *
 * Uso estándar en todos los componentes shadcn/ui.
 */
export function cn(...inputs: ClassValue[]): string {
  return twMerge(clsx(inputs));
}
