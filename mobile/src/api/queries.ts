import { useInfiniteQuery, useQuery } from '@tanstack/react-query';

import { api } from './client';
import type { NotificationsResponse, Page, TaskCard } from './types';

export type TaskFilters = { status?: string; search?: string; priority?: string; project_id?: number };

export function useTasks(filters: TaskFilters) {
  return useInfiniteQuery({
    queryKey: ['tasks', filters],
    initialPageParam: 1,
    queryFn: ({ pageParam, signal }) =>
      api<Page<TaskCard>>('tasks', { query: { ...filters, page: pageParam, per_page: 20 }, signal }),
    getNextPageParam: (last) => (last.meta.has_more ? last.meta.page + 1 : undefined),
  });
}

export function useNotifications() {
  return useQuery({
    queryKey: ['notifications'],
    queryFn: ({ signal }) => api<NotificationsResponse>('notifications', { signal }),
    refetchInterval: 60_000, // the unread badge; realtime (Pusher) replaces this polling in a later phase
  });
}
