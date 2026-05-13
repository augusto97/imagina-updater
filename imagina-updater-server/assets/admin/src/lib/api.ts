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
      licenseExtensionActive?: boolean;
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
  return adminJsonRequest<T>('POST', path, body);
}

export async function adminPut<T>(path: string, body?: unknown): Promise<T> {
  return adminJsonRequest<T>('PUT', path, body);
}

export async function adminDelete<T>(path: string): Promise<T> {
  return adminJsonRequest<T>('DELETE', path);
}

async function adminJsonRequest<T>(
  method: 'POST' | 'PUT' | 'DELETE',
  path: string,
  body?: unknown,
): Promise<T> {
  const cfg = getConfig();
  const url = cfg.adminUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');

  const response = await fetch(url, {
    method,
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

/**
 * Variante multipart para subidas de archivos. NO se setea
 * Content-Type — el navegador añade el boundary correcto al detectar
 * un FormData en `body`.
 *
 * `onProgress` (opcional) recibe el porcentaje 0-100 mientras se
 * envía. Implementado con XHR porque `fetch` no expone progreso de
 * upload todavía.
 */
export async function adminPostMultipart<T>(
  path: string,
  formData: FormData,
  options?: { onProgress?: (percent: number) => void },
): Promise<T> {
  const cfg = getConfig();
  const url = cfg.adminUrl.replace(/\/$/, '') + '/' + path.replace(/^\//, '');

  return new Promise<T>((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.withCredentials = true;
    xhr.setRequestHeader('X-WP-Nonce', cfg.nonce);
    xhr.setRequestHeader('Accept', 'application/json');

    if (options?.onProgress && xhr.upload) {
      xhr.upload.onprogress = (e) => {
        if (e.lengthComputable) {
          options.onProgress!(Math.round((e.loaded / e.total) * 100));
        }
      };
    }

    xhr.onload = () => {
      let parsed: unknown;
      try {
        parsed = xhr.responseText ? JSON.parse(xhr.responseText) : null;
      } catch {
        parsed = null;
      }
      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(parsed as T);
        return;
      }
      const message =
        (parsed && typeof parsed === 'object' && 'message' in parsed
          ? String((parsed as { message: unknown }).message)
          : null) ?? `Request failed with status ${xhr.status}`;
      const err = new Error(message) as ApiError;
      err.status = xhr.status;
      reject(err);
    };
    xhr.onerror = () => {
      const err = new Error('Network error during upload.') as ApiError;
      err.status = 0;
      reject(err);
    };

    xhr.send(formData);
  });
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
