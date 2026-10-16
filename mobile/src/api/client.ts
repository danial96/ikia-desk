import { API_BASE_URL, API_URL, APP_VERSION } from '@/config';
import type { StoreLinks, UpdateInfo } from './types';

/** The server answered with an error status. */
export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string | null,
    message: string,
    /** Field errors of a 422: { email: ['The email field is required.'] } */
    public errors: Record<string, string[]> | null = null,
    public retryAfter: number | null = null,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

/** No answer at all: offline, DNS, timeout, server down. */
export class NetworkError extends Error {
  constructor(message = 'No connection. Check your internet and try again.') {
    super(message);
    this.name = 'NetworkError';
  }
}

type Hooks = {
  /** A call that carried a token was refused (revoked, password changed, account deactivated): sign out. */
  onUnauthorized?: (code: string | null) => void;
  /** This build is older than the server's minimum: show the update screen. */
  onUpdateRequired?: (info: UpdateInfo) => void;
};

const hooks: Hooks = {};
let authToken: string | null = null;

export function setApiHooks(next: Hooks) {
  hooks.onUnauthorized = next.onUnauthorized;
  hooks.onUpdateRequired = next.onUpdateRequired;
}

export function setAuthToken(token: string | null) {
  authToken = token;
}

export function getAuthToken() {
  return authToken;
}

export type RequestOptions = {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
  query?: Record<string, string | number | boolean | null | undefined>;
  /** Send the bearer token (default). Login and app/config pass false. */
  auth?: boolean;
  signal?: AbortSignal;
  timeoutMs?: number;
};

function buildUrl(path: string, query?: RequestOptions['query']) {
  let url = `${API_URL}/${path.replace(/^\/+/, '')}`;
  if (query) {
    const parts: string[] = [];
    for (const [k, v] of Object.entries(query)) {
      if (v === undefined || v === null || v === '') continue;
      parts.push(`${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`);
    }
    if (parts.length) url += `?${parts.join('&')}`;
  }
  return url;
}

function firstFieldError(errors: Record<string, string[]> | null): string | null {
  if (!errors) return null;
  for (const list of Object.values(errors)) if (list?.length) return list[0];
  return null;
}

export async function api<T = unknown>(path: string, opts: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, query, auth = true, signal, timeoutMs = body instanceof FormData ? 120000 : 20000 } = opts;
  const usedToken = auth ? authToken : null;

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-App-Version': APP_VERSION,
  };
  if (usedToken) headers.Authorization = `Bearer ${usedToken}`;
  const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
  // a form sets its own Content-Type (with the multipart boundary), so it must not be set here
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';

  // One controller covers both the timeout and a cancel coming from the caller (React Query passes one).
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  const onAbort = () => controller.abort();
  signal?.addEventListener('abort', onAbort);

  let res: Response;
  try {
    res = await fetch(buildUrl(path, query), {
      method,
      headers,
      body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body),
      signal: controller.signal,
    });
  } catch (e) {
    if (signal?.aborted) throw e; // the caller cancelled on purpose
    throw new NetworkError();
  } finally {
    clearTimeout(timer);
    signal?.removeEventListener('abort', onAbort);
  }

  const text = await res.text();
  let json: any = null;
  if (text) {
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }
  }

  if (res.ok) return json as T;

  const errors = json?.errors && typeof json.errors === 'object' ? (json.errors as Record<string, string[]>) : null;
  const code: string | null = typeof json?.error === 'string' ? json.error : null;
  const message: string = firstFieldError(errors) ?? json?.message ?? (code ? code.replace(/_/g, ' ') : `Request failed (${res.status})`);

  if (res.status === 426) {
    hooks.onUpdateRequired?.({
      minVersion: json?.min_version ?? null,
      store: (json?.store as StoreLinks | undefined) ?? null,
      message,
    });
  } else if (res.status === 401 && usedToken) {
    hooks.onUnauthorized?.(code);
  }

  throw new ApiError(res.status, code, message, errors, typeof json?.retry_after === 'number' ? json.retry_after : null);
}

/**
 * Files inside messages and comments are stored as website addresses ("https://desk…/uploads/x.png/Name.png"). The
 * website route needs a browser session, so the app asks for the same path under /api/v1 and sends the bearer token.
 * Anything else (avatars, other sites) is returned untouched.
 */
export function toApiFileUrl(url: string): string {
  if (!url) return url;
  if (url.startsWith('/uploads/')) return `${API_URL}${url}`;
  if (url.startsWith(`${API_BASE_URL}/uploads/`)) return `${API_URL}${url.slice(API_BASE_URL.length)}`;
  return url;
}

/** Headers an <Image>/download needs for files from toApiFileUrl(). */
export function fileHeaders(): Record<string, string> {
  return authToken ? { Authorization: `Bearer ${authToken}` } : {};
}

export type LocalFile = { uri: string; name: string; mimeType?: string | null };

/** POST a phone file (from the picker, camera or recorder) as multipart form data under the field "file". */
export function uploadFile<T>(path: string, file: LocalFile, signal?: AbortSignal): Promise<T> {
  const form = new FormData();
  // React Native's FormData takes {uri, name, type} for a file on the phone; the DOM typings do not know that shape.
  form.append('file', { uri: file.uri, name: file.name, type: file.mimeType || 'application/octet-stream' } as unknown as Blob);
  return api<T>(path, { method: 'POST', body: form, signal });
}
