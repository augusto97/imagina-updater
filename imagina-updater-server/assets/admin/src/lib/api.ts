/**
 * Cliente REST minimal para los endpoints admin del plugin servidor.
 *
 * El namespace REST que consume es `/imagina-updater/admin/v1/` —
 * separado del público `/imagina-updater/v1/` (CLAUDE.md §6 fase 5.0).
 * Esta separación garantiza que cambios en la SPA no rompan la
 * retrocompatibilidad de los sitios cliente que usan v1.
 *
 * `iaudConfig` es inyectado vía `wp_localize_script` desde el PHP
 * que encolla este bundle (ver §3.2 de CLAUDE.md).
 */

declare global {
  interface Window {
    iaudConfig?: {
      apiUrl: string;
      adminUrl: string;
      nonce: string;
      currentUser?: string;
      locale?: string;
      siteUrl?: string;
    };
  }
}

export interface ApiError extends Error {
  status: number;
  code?: string;
}

function getConfig() {
  const cfg = window.iaudConfig;
  if (!cfg) {
    throw new Error(
      'iaudConfig no está disponible en window. ¿Se ejecutó wp_localize_script?',
    );
  }
  return cfg;
}

/**
 * GET request al namespace admin (`/imagina-updater/admin/v1/...`).
 * Maneja nonce y errores. No cachea (de eso se ocupa TanStack Query).
 */
export async function adminGet<T>(path: string): Promise<T> {
  const cfg = getConfig();
  const url = cfg.adminUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');

  const response = await fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': cfg.nonce,
      Accept: 'application/json',
    },
  });

  return parseResponse<T>(response);
}

export async function adminPost<T>(
  path: string,
  body?: unknown,
): Promise<T> {
  const cfg = getConfig();
  const url = cfg.adminUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');

  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': cfg.nonce,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
  });

  return parseResponse<T>(response);
}

async function parseResponse<T>(response: Response): Promise<T> {
  if (!response.ok) {
    let errorBody: { code?: string; message?: string } = {};
    try {
      errorBody = (await response.json()) as typeof errorBody;
    } catch {
      // Cuerpo no-JSON (p.ej. error de servidor en HTML). El status
      // sigue siendo informativo.
    }

    const error = new Error(
      errorBody.message ?? `Request failed with status ${response.status}`,
    ) as ApiError;
    error.status = response.status;
    if (errorBody.code) {
      error.code = errorBody.code;
    }
    throw error;
  }

  return (await response.json()) as T;
}
