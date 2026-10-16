import { createContext, use, useEffect, useMemo, useState, type PropsWithChildren } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import type Pusher from 'pusher-js';

import { fetchAppConfig } from '@/api/auth';
import { getAuthToken } from '@/api/client';
import { callEngine } from '@/calls';
import { API_URL } from '@/config';
import { useSession } from '@/auth/session';
import { handleRealtime } from './events';
import { createPusher } from './pusherClient';

const RealtimeContext = createContext<{ connected: boolean }>({ connected: false });

/** True while the live connection is up. Screens poll slowly then, and quickly (a few seconds) when it is down. */
export const useRealtimeConnected = () => use(RealtimeContext).connected;

const EVENTS = ['chat.changed', 'task.changed', 'notif'] as const;

/** Keeps one live (Pusher) connection to this person's private channel while signed in, and refreshes screens on events. */
export function RealtimeProvider({ children }: PropsWithChildren) {
  const { user } = useSession();
  const qc = useQueryClient();
  const [connected, setConnected] = useState(false);
  const channelName = user?.realtime_channel ?? null;

  useEffect(() => {
    if (!channelName) return;
    let pusher: Pusher | null = null;
    let cancelled = false;

    (async () => {
      let cfg: Awaited<ReturnType<typeof fetchAppConfig>>;
      try {
        cfg = await fetchAppConfig();
      } catch {
        return; // offline: screens keep polling, and we try again next time the app opens
      }
      if (cancelled || !cfg.realtime) return;

      pusher = createPusher(cfg.realtime.key, {
        cluster: cfg.realtime.cluster,
        channelAuthorization: {
          endpoint: `${API_URL}/realtime/auth`,
          transport: 'ajax',
          // read per attempt so a re-login never reuses an old token
          headersProvider: () => ({ Authorization: `Bearer ${getAuthToken() ?? ''}`, Accept: 'application/json' }),
        },
      });
      pusher.connection.bind('connected', () => setConnected(true));
      pusher.connection.bind('disconnected', () => setConnected(false));
      pusher.connection.bind('unavailable', () => setConnected(false));
      pusher.connection.bind('failed', () => setConnected(false));

      const channel = pusher.subscribe(channelName);
      for (const ev of EVENTS) channel.bind(ev, (data: unknown) => handleRealtime(qc, ev, data));
      // voice calls: who is ringing / answered / hung up, and the connection handshake
      channel.bind('call.state', (data: any) => callEngine.onState(data));
      channel.bind('call.signal', (data: any) => void callEngine.onSignal(data));
      // after a reconnect we may have missed events: refetch what is on screen
      channel.bind('pusher:subscription_succeeded', () => {
        qc.invalidateQueries({ queryKey: ['convs'] });
        qc.invalidateQueries({ queryKey: ['chat'] });
        qc.invalidateQueries({ queryKey: ['notifications'] });
        void callEngine.resync();
      });
    })().catch(() => {
      /* live updates are a bonus: if they cannot start, screens simply keep polling */
    });

    return () => {
      cancelled = true;
      pusher?.disconnect();
      setConnected(false);
    };
  }, [channelName, qc]);

  const value = useMemo(() => ({ connected }), [connected]);
  return <RealtimeContext.Provider value={value}>{children}</RealtimeContext.Provider>;
}
