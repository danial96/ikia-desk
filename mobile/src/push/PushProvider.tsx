import { useEffect, useRef, type PropsWithChildren } from 'react';
import { Platform } from 'react-native';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import Constants from 'expo-constants';
import { router } from 'expo-router';

import { api } from '@/api/client';
import { useConversations } from '@/api/chat';
import { useNotifications } from '@/api/queries';
import { APP_VERSION } from '@/config';
import { useSession } from '@/auth/session';
import { isChatOpen } from './activeChat';
import { routeForNotification } from './route';

// While the app is open: show the banner, unless it is about the chat the person is already reading.
Notifications.setNotificationHandler({
  handleNotification: async (n) => {
    const data = n.request.content.data as Record<string, unknown> | undefined;
    const reading = isChatOpen(data?.conversation_id);
    return { shouldShowBanner: !reading, shouldShowList: !reading, shouldPlaySound: !reading, shouldSetBadge: false };
  },
});

/** Asks permission, registers this phone's push token with the server, opens the right screen on a tap, and keeps the icon badge. */
export function PushProvider({ children }: PropsWithChildren) {
  const { user } = useSession();
  const registered = useRef<string | null>(null);

  // 1) permission + token, once per sign-in
  useEffect(() => {
    if (!user || Platform.OS === 'web' || !Device.isDevice) return;
    let cancelled = false;
    (async () => {
      try {
        if (Platform.OS === 'android') {
          await Notifications.setNotificationChannelAsync('messages', { name: 'Messages', importance: Notifications.AndroidImportance.HIGH, vibrationPattern: [0, 250, 250, 250] });
          await Notifications.setNotificationChannelAsync('tasks', { name: 'Tasks', importance: Notifications.AndroidImportance.DEFAULT });
        }
        let perm = await Notifications.getPermissionsAsync();
        if (!perm.granted && perm.canAskAgain) perm = await Notifications.requestPermissionsAsync();
        if (!perm.granted || cancelled) return;

        const projectId = Constants.expoConfig?.extra?.eas?.projectId ?? Constants.easConfig?.projectId;
        if (!projectId) return; // not linked to an Expo project yet (`eas init`): nothing to register against
        const { data: token } = await Notifications.getExpoPushTokenAsync({ projectId });
        if (cancelled || !token || registered.current === token) return;
        await api('devices', { method: 'POST', body: { push_token: token, platform: Platform.OS === 'ios' ? 'ios' : 'android', app_version: APP_VERSION } });
        registered.current = token;
      } catch {
        // offline or Expo unreachable: tried again next time the app opens
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [user?.id]); // eslint-disable-line react-hooks/exhaustive-deps

  // 2) a tap: from the background, or the one that launched the app
  const last = Notifications.useLastNotificationResponse();
  const handled = useRef<string | null>(null);
  useEffect(() => {
    if (!user || !last) return;
    const id = last.notification.request.identifier;
    if (handled.current === id || last.actionIdentifier !== Notifications.DEFAULT_ACTION_IDENTIFIER) return;
    handled.current = id;
    router.push(routeForNotification(last.notification.request.content.data as Record<string, unknown>));
    Notifications.clearLastNotificationResponseAsync().catch(() => {});
  }, [last, user]);

  // 3) the number on the app icon = unread chats + unread alerts
  const chats = useConversations();
  const alerts = useNotifications();
  const unread = (chats.data ?? []).reduce((n, c) => n + c.unread, 0) + (alerts.data?.unread_count ?? 0);
  useEffect(() => {
    if (!user || Platform.OS === 'web') return;
    Notifications.setBadgeCountAsync(unread).catch(() => {});
  }, [unread, user]);

  return <>{children}</>;
}
