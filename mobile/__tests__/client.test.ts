import { api, ApiError, NetworkError, setApiHooks, setAuthToken, toApiFileUrl } from '@/api/client';
import { API_URL } from '@/config';
import { errorMessage } from '@/lib/errors';

function reply(status: number, body: unknown) {
  return Promise.resolve({ ok: status >= 200 && status < 300, status, text: () => Promise.resolve(body === undefined ? '' : JSON.stringify(body)) } as Response);
}

let fetchMock: jest.Mock;

beforeEach(() => {
  fetchMock = jest.fn();
  global.fetch = fetchMock as unknown as typeof fetch;
  setAuthToken(null);
  setApiHooks({});
});

describe('api()', () => {
  it('calls /api/v1 with JSON headers and the app version', async () => {
    fetchMock.mockReturnValue(reply(200, { ok: true }));

    await api('app/config', { auth: false });

    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toBe(`${API_URL}/app/config`);
    expect(init.headers.Accept).toBe('application/json');
    expect(init.headers['X-App-Version']).toMatch(/^\d+\.\d+\.\d+/);
    expect(init.headers.Authorization).toBeUndefined();
  });

  it('sends the bearer token and a JSON body', async () => {
    setAuthToken('tok123');
    fetchMock.mockReturnValue(reply(200, { success: true }));

    await api('tasks', { method: 'POST', body: { title: 'x' } });

    const init = fetchMock.mock.calls[0][1];
    expect(init.headers.Authorization).toBe('Bearer tok123');
    expect(init.headers['Content-Type']).toBe('application/json');
    expect(init.body).toBe('{"title":"x"}');
    expect(init.method).toBe('POST');
  });

  it('builds the query string and skips empty values', async () => {
    fetchMock.mockReturnValue(reply(200, {}));

    await api('tasks', { query: { status: 'in_progress', search: '', page: 2, project_id: undefined, q: 'a b&c' } });

    expect(fetchMock.mock.calls[0][0]).toBe(`${API_URL}/tasks?status=in_progress&page=2&q=a%20b%26c`);
  });

  it('returns the parsed body on success and null for an empty one', async () => {
    fetchMock.mockReturnValueOnce(reply(200, { a: 1 }));
    expect(await api('x')).toEqual({ a: 1 });
    fetchMock.mockReturnValueOnce(reply(204, undefined));
    expect(await api('x')).toBeNull();
  });

  it('throws an ApiError with the first field message for a 422', async () => {
    fetchMock.mockReturnValue(reply(422, { message: 'The given data was invalid.', errors: { email: ['The email field is required.'] } }));

    const err: any = await api('auth/login', { method: 'POST', auth: false }).catch((e: any) => e);

    expect(err).toBeInstanceOf(ApiError);
    expect(err.status).toBe(422);
    expect(err.message).toBe('The email field is required.');
    expect(err.errors).toEqual({ email: ['The email field is required.'] });
  });

  it('keeps the server error code and retry_after', async () => {
    fetchMock.mockReturnValue(reply(429, { error: 'too_many_attempts', message: 'Too many login attempts.', retry_after: 42 }));

    const err: any = await api('auth/login', { auth: false }).catch((e: any) => e);

    expect(err.code).toBe('too_many_attempts');
    expect(err.retryAfter).toBe(42);
    expect(errorMessage(err)).toBe('Too many attempts. Try again in 42 seconds.');
  });

  it('signs out through the hook when a call that carried a token gets 401', async () => {
    const onUnauthorized = jest.fn();
    setApiHooks({ onUnauthorized });
    setAuthToken('revoked');
    fetchMock.mockReturnValue(reply(401, { error: 'deactivated', message: 'Your account has been deactivated.' }));

    await expect(api('auth/me')).rejects.toBeInstanceOf(ApiError);

    expect(onUnauthorized).toHaveBeenCalledWith('deactivated');
  });

  it('does not treat a 401 as a sign-out when no token was sent', async () => {
    const onUnauthorized = jest.fn();
    setApiHooks({ onUnauthorized });
    fetchMock.mockReturnValue(reply(401, { error: 'unauthenticated' }));

    await api('app/config', { auth: false }).catch(() => {});

    expect(onUnauthorized).not.toHaveBeenCalled();
  });

  it('reports an outdated build through the hook on 426', async () => {
    const onUpdateRequired = jest.fn();
    setApiHooks({ onUpdateRequired });
    fetchMock.mockReturnValue(reply(426, { error: 'update_required', message: 'Please update IKIA Desk.', min_version: '1.2.0', store: { android: 'https://play', ios: null } }));

    await api('tasks').catch(() => {});

    expect(onUpdateRequired).toHaveBeenCalledWith({ minVersion: '1.2.0', store: { android: 'https://play', ios: null }, message: 'Please update IKIA Desk.' });
  });

  it('turns a failed fetch into a NetworkError', async () => {
    fetchMock.mockRejectedValue(new TypeError('Network request failed'));

    const err: any = await api('tasks').catch((e: any) => e);

    expect(err).toBeInstanceOf(NetworkError);
    expect(errorMessage(err)).toMatch(/No connection/);
  });

  it('lets a caller cancel without it looking like a network failure', async () => {
    const ctrl = new AbortController();
    fetchMock.mockImplementation((_u: string, init: RequestInit) => new Promise((_res, rej) => init.signal!.addEventListener('abort', () => rej(new DOMException('Aborted', 'AbortError')))));

    const p = api('tasks', { signal: ctrl.signal });
    ctrl.abort();

    await expect(p).rejects.not.toBeInstanceOf(NetworkError);
  });

  it('survives a non-JSON error page', async () => {
    fetchMock.mockReturnValue(Promise.resolve({ ok: false, status: 502, text: () => Promise.resolve('<html>Bad gateway</html>') } as Response));

    const err: any = await api('tasks').catch((e: any) => e);

    expect(err).toBeInstanceOf(ApiError);
    expect(err.status).toBe(502);
    expect(errorMessage(err)).toMatch(/server had a problem/);
  });
});

describe('toApiFileUrl()', () => {
  it('moves website upload addresses under /api/v1', () => {
    expect(toApiFileUrl('https://desk.ikiatech.com/uploads/up_1.png/Photo.png')).toBe(`${API_URL}/uploads/up_1.png/Photo.png`);
    expect(toApiFileUrl('/uploads/up_1.png')).toBe(`${API_URL}/uploads/up_1.png`);
  });

  it('leaves avatars and other addresses alone', () => {
    expect(toApiFileUrl('https://desk.ikiatech.com/storage/avatars/a.jpg')).toBe('https://desk.ikiatech.com/storage/avatars/a.jpg');
    expect(toApiFileUrl('https://example.com/uploads/x.png')).toBe('https://example.com/uploads/x.png');
    expect(toApiFileUrl('')).toBe('');
  });
});
