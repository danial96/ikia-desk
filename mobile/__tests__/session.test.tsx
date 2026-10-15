import { act, renderHook, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { PropsWithChildren } from 'react';

import { api, ApiError, getAuthToken, setApiHooks, setAuthToken } from '@/api/client';
import type { UserPayload } from '@/api/types';
import { SessionProvider, useSession } from '@/auth/session';
import * as tokenStore from '@/auth/tokenStore';

jest.mock('@/auth/tokenStore');
jest.mock('@/api/auth');
import * as authApi from '@/api/auth';

const store = tokenStore as jest.Mocked<typeof tokenStore>;
const server = authApi as jest.Mocked<typeof authApi>;

function reply(status: number, body: unknown) {
  return { ok: status >= 200 && status < 300, status, text: () => Promise.resolve(JSON.stringify(body)) } as Response;
}

const user = { id: 7, name: 'Huzaifa', email: 'h@example.com', role: 'employee' } as UserPayload;

function setup() {
  const queryClient = new QueryClient();
  const wrapper = ({ children }: PropsWithChildren) => (
    <QueryClientProvider client={queryClient}>
      <SessionProvider>{children}</SessionProvider>
    </QueryClientProvider>
  );
  return { queryClient, ...renderHook(() => useSession(), { wrapper }) };
}

beforeEach(() => {
  jest.resetAllMocks();
  setAuthToken(null);
  setApiHooks({});
  store.getToken.mockResolvedValue(null);
  store.getUser.mockResolvedValue(null);
  store.saveSession.mockResolvedValue();
  store.saveUser.mockResolvedValue();
  store.clearSession.mockResolvedValue();
});

describe('SessionProvider', () => {
  it('starts signed out when nothing is stored', async () => {
    const { result } = setup();

    await waitFor(() => expect(result.current.status).toBe('signedOut'));
    expect(result.current.user).toBeNull();
  });

  it('opens straight into the app from the stored sign-in, then refreshes the profile', async () => {
    store.getToken.mockResolvedValue('tok');
    store.getUser.mockResolvedValue(user);
    server.fetchMe.mockResolvedValue({ ...user, name: 'Huzaifa K' });

    const { result } = setup();

    await waitFor(() => expect(result.current.status).toBe('signedIn'));
    expect(getAuthToken()).toBe('tok');
    await waitFor(() => expect(result.current.user?.name).toBe('Huzaifa K'));
    expect(store.saveUser).toHaveBeenCalledWith(expect.objectContaining({ name: 'Huzaifa K' }));
  });

  it('stays signed in with the stored profile when the server cannot be reached', async () => {
    store.getToken.mockResolvedValue('tok');
    store.getUser.mockResolvedValue(user);
    server.fetchMe.mockRejectedValue(new Error('offline'));

    const { result } = setup();

    await waitFor(() => expect(result.current.status).toBe('signedIn'));
    await act(async () => {});
    expect(result.current.user?.name).toBe('Huzaifa');
  });

  it('treats a token without a stored profile as signed out and tidies up', async () => {
    store.getToken.mockResolvedValue('orphan');

    const { result } = setup();

    await waitFor(() => expect(result.current.status).toBe('signedOut'));
    expect(store.clearSession).toHaveBeenCalled();
  });

  it('signs in: stores the token securely and uses it', async () => {
    server.login.mockResolvedValue({ token: 'new-token', user });
    const { result } = setup();
    await waitFor(() => expect(result.current.status).toBe('signedOut'));

    await act(async () => {
      await result.current.signIn('h@example.com', 'pw');
    });

    expect(server.login).toHaveBeenCalledWith('h@example.com', 'pw');
    expect(store.saveSession).toHaveBeenCalledWith('new-token', user);
    expect(getAuthToken()).toBe('new-token');
    expect(result.current.status).toBe('signedIn');
    expect(result.current.user?.id).toBe(7);
  });

  it('a failed sign-in leaves the person signed out and rethrows', async () => {
    server.login.mockRejectedValue(new ApiError(422, 'invalid_credentials', 'Invalid credentials.'));
    const { result } = setup();
    await waitFor(() => expect(result.current.status).toBe('signedOut'));

    await expect(act(async () => result.current.signIn('h@example.com', 'bad'))).rejects.toBeInstanceOf(ApiError);

    expect(result.current.status).toBe('signedOut');
    expect(store.saveSession).not.toHaveBeenCalled();
  });

  it('signs out: tells the server, forgets the token and clears cached data', async () => {
    server.login.mockResolvedValue({ token: 't', user });
    server.logout.mockResolvedValue({ ok: true });
    const { result, queryClient } = setup();
    await waitFor(() => expect(result.current.status).toBe('signedOut'));
    await act(async () => {
      await result.current.signIn('h@example.com', 'pw');
    });
    queryClient.setQueryData(['tasks'], { secret: true });

    await act(async () => {
      await result.current.signOut();
    });

    expect(server.logout).toHaveBeenCalled();
    expect(store.clearSession).toHaveBeenCalled();
    expect(getAuthToken()).toBeNull();
    expect(queryClient.getQueryData(['tasks'])).toBeUndefined();
    expect(result.current.status).toBe('signedOut');
  });

  it('still signs out locally when the server cannot be told (offline)', async () => {
    server.login.mockResolvedValue({ token: 't', user });
    server.logout.mockRejectedValue(new ApiError(500, null, 'boom'));
    const { result } = setup();
    await waitFor(() => expect(result.current.status).toBe('signedOut'));
    await act(async () => {
      await result.current.signIn('h@example.com', 'pw');
    });

    await act(async () => {
      await result.current.signOut();
    });

    expect(result.current.status).toBe('signedOut');
    expect(getAuthToken()).toBeNull();
  });

  it('signs out by itself when the server refuses the token (revoked or account switched off)', async () => {
    server.login.mockResolvedValue({ token: 't', user });
    const { result } = setup();
    await waitFor(() => expect(result.current.status).toBe('signedOut'));
    await act(async () => {
      await result.current.signIn('h@example.com', 'pw');
    });
    expect(result.current.status).toBe('signedIn');

    // a real api() call that the server refuses with 401 — api() calls the hook this provider registered
    global.fetch = jest.fn().mockResolvedValue(reply(401, { error: 'deactivated', message: 'Your account has been deactivated.' })) as unknown as typeof fetch;
    await act(async () => {
      await api('tasks').catch(() => {});
    });

    await waitFor(() => expect(result.current.status).toBe('signedOut'));
    expect(getAuthToken()).toBeNull();
  });

  it('shows the update screen state when the server says this build is too old', async () => {
    const { result } = setup();
    await waitFor(() => expect(result.current.status).toBe('signedOut'));

    global.fetch = jest.fn().mockResolvedValue(reply(426, { error: 'update_required', message: 'Please update.', min_version: '2.0.0', store: null })) as unknown as typeof fetch;
    await act(async () => {
      await api('app/config', { auth: false }).catch(() => {});
    });

    expect(result.current.updateInfo?.minVersion).toBe('2.0.0');
  });
});
