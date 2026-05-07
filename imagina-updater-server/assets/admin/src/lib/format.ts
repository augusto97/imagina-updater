/**
 * Helpers de formateo. Usan `iaudConfig.locale` cuando está disponible,
 * cayendo a `es-ES` si no.
 */

function getLocale(): string {
  return window.iaudConfig?.locale ?? 'es-ES';
}

export function formatNumber(value: number): string {
  return new Intl.NumberFormat(getLocale()).format(value);
}

export function formatDateTime(input: string): string {
  const date = new Date(input.replace(' ', 'T') + 'Z');
  if (Number.isNaN(date.getTime())) {
    return input;
  }
  return new Intl.DateTimeFormat(getLocale(), {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(date);
}

export function formatRelativeTime(input: string): string {
  const date = new Date(input.replace(' ', 'T') + 'Z');
  if (Number.isNaN(date.getTime())) {
    return input;
  }
  const diffMs = date.getTime() - Date.now();
  const diffMin = Math.round(diffMs / (60 * 1000));
  const rtf = new Intl.RelativeTimeFormat(getLocale(), { numeric: 'auto' });

  const absMin = Math.abs(diffMin);
  if (absMin < 60) return rtf.format(diffMin, 'minute');
  const diffHr = Math.round(diffMin / 60);
  if (Math.abs(diffHr) < 24) return rtf.format(diffHr, 'hour');
  const diffDay = Math.round(diffHr / 24);
  return rtf.format(diffDay, 'day');
}
