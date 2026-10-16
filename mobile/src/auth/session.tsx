import { createContext, use, useCallback, useEffect, useMemo, useRef, useState, type PropsWithChildren } from 'react';
import { useQueryClient } from '@tanstack/react-query';

import { fetchMe, login, logout } from '@/api/auth';
import { ApiError, NetworkError, setApiHooks, setAuthToken } from '@/api/client';
import type { UpdateInfo, UserPayload } from '@/api/types';
import { callEngine } from '@/calls';
import { setSessionUserId } from './sessionRef';
import * as tokenStore from './tokenStore';

export type SessionStatus = 'loading' | 'signedOut' | 'signedIn';

type SessionValue = {
  status: SessionStatus;
  user: UserPayload | null;
  /** Set when the server says this build is too old; the app then shows only the update screen. */
  updateInfo: UpdateInfo | null;
  signIn: (email: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
  /** Re-reads the profile (permissions, notification switches) from the server. */
  refreshUser: () => Promise<void>;
};

const SessionContext = createContext<SessionValue | null>(null);

export function useSession(): SessionValue {
  const value = use(SessionContext);
  if (!value) throw new Error('useSession must be used inside <SessionProvider>');
  return value;
}

export function SessionProvider({ children }: PropsWithChildren) {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState<SessionStatus>('loading');
  const [user, setUser] = useState<UserPayload | null>(null);
  const [updateInfo, setUpdateInfo] = useState<UpdateInfo | null>(null);
  const alive = useRef(true);

  /** Forget this phone's sign-in locally (the server may or may not know yet). */
  const clearLocal = useCallback(async () => {
    callEngine.reset();
    setAuthToken(null);
    await tokenStore.clearSession();
    queryClient.clear();
    if (!alive.current) return;
    setUser(null);
    setStatus('signedOut');
  }, [queryClient]);

  // Server says no (token revoked, password changed, account switched off) or this build is outdated.
  useEffect(() => {
    setApiHooks({
      onUnauthorized: () => void clearLocal(),
      onUpdateRequired: (info) => setUpdateInfo(info),
    });
    return () => setApiHooks({});
  }, [clearLocal]);

  // Start up: use what is stored, open immediately, and check with the server in the background.
  useEffect(() => {
    alive.current = true;
    (async () => {
      const [token, cached] = await Promise.all([tokenStore.getToken(), tokenStore.getUser()]);
      if (!alive.current) return;
      if (!token || !cached) {
        if (token) await tokenStore.clearSession(); // a token without a profile is a half-finished sign-in
        setStatus('signedOut');
        return;
      }
      setAuthToken(token);
      setUser(cached);
      setStatus('signedIn');
      try {
        const fresh = await fetchMe();
        if (!alive.current) return;
        setUser(fresh);
        await tokenStore.saveUser(fresh);
      } catch {
        // offline or server trouble: keep the cached profile. A 401/426 was already handled by the api hooks.
      }
    })();
    return () => {
      alive.current = false;
    };
  }, []);

  const signIn = useCallback(async (email: string, password: string) => {
    const res = await login(email, password);
    await tokenStore.saveSession(res.token, res.user);
    setAuthToken(res.token);
    setUser(res.user);
    setStatus('signedIn');
  }, []);

  const signOut = useCallback(async () => {
    try {
      await logout(); // tells the server to forget this phone (and its push registration)
    } catch (e) {
      // offline: we still sign out here; the server-side token just stays until the next password change
      if (!(e instanceof NetworkError) && !(e instanceof ApiError)) throw e;
    }
    await clearLocal();
  }, [clearLocal]);

  const refreshUser = useCallback(async () => {
    const fresh = await fetchMe();
    setUser(fresh);
    await tokenStore.saveUser(fresh);
  }, []);

  useEffect(() => {
    setSessionUserId(user?.id ?? null);
  }, [user?.id]);

  const value = useMemo(
    () => ({ status, user, updateInfo, signIn, signOut, refreshUser }),
    [status, user, updateInfo, signIn, signOut, refreshUser],
  );

  return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}
