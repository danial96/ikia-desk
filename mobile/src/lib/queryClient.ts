import { AppState, Platform } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { focusManager, onlineManager, QueryClient } from '@tanstack/react-query';

import { ApiError } from '@/api/client';

export function createQueryClient() {
  return new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        retry: (failureCount, error) => {
          // A "no" from the server (403, 404, 422…) will not change by asking again; a dropped connection or a 5xx might.
          if (error instanceof ApiError && error.status < 500) return false;
          return failureCount < 2;
        },
      },
    },
  });
}

/** Tell React Query when the phone is offline and when the app comes back to the front, so screens refresh themselves. */
export function wireQueryClientToDevice() {
  onlineManager.setEventListener((setOnline) =>
    NetInfo.addEventListener((state) => setOnline(state.isConnected !== false && state.isInternetReachable !== false)),
  );

  const sub = AppState.addEventListener('change', (state) => {
    if (Platform.OS !== 'web') focusManager.setFocused(state === 'active');
  });
  return () => sub.remove();
}
