import { useQuery } from '@tanstack/react-query';

import { api } from './client';
import type { NotificationsResponse } from './types';

export function useNotifications() {
  return useQuery({
    queryKey: ['notifications'],
    queryFn: ({ signal }) => api<NotificationsResponse>('notifications', { signal }),
    refetchInterval: 60_000, // the unread badge; realtime (Pusher) replaces this polling in a later phase
  });
}
