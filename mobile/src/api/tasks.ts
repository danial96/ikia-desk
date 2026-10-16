import { useInfiniteQuery, useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';

import { api, uploadFile, type LocalFile } from './client';
import type { Board, Page, ProjectLite, TaskCard, TaskDetail, TaskInput, TrashedTask, UserLite } from './types';

export type TaskFilters = { status?: string; search?: string; priority?: string; project_id?: number; assignee_id?: number };

// ── Reading ───────────────────────────────────────────────────────────────────────────────────────────────────────

export function useTasks(filters: TaskFilters) {
  return useInfiniteQuery({
    queryKey: ['tasks', filters],
    initialPageParam: 1,
    queryFn: ({ pageParam, signal }) => api<Page<TaskCard>>('tasks', { query: { ...filters, page: pageParam, per_page: 20 }, signal }),
    getNextPageParam: (last) => (last.meta.has_more ? last.meta.page + 1 : undefined),
  });
}

export function useBoard(filters: Omit<TaskFilters, 'status'> & { status?: string }) {
  return useQuery({
    queryKey: ['board', filters],
    queryFn: ({ signal }) => api<Board>('tasks/board', { query: filters, signal }),
    refetchInterval: 30_000,
  });
}

export function useTask(id: number) {
  return useQuery({
    queryKey: ['task', id],
    queryFn: ({ signal }) => api<TaskDetail>(`local-task/${id}`, { signal }),
    refetchInterval: 15_000, // the website's panel polls every 3 s; realtime replaces this in a later phase
  });
}

export function useTrash() {
  return useQuery({
    queryKey: ['trash'],
    queryFn: ({ signal }) => api<Page<TrashedTask>>('tasks/trash', { signal }),
  });
}

export function useUsers() {
  return useQuery({
    queryKey: ['users'],
    queryFn: ({ signal }) => api<{ data: UserLite[] }>('users', { signal }).then((r) => r.data),
    staleTime: 5 * 60_000,
  });
}

export function useProjects() {
  return useQuery({
    queryKey: ['projects'],
    queryFn: ({ signal }) => api<{ data: ProjectLite[] }>('projects', { signal }).then((r) => r.data),
    staleTime: 5 * 60_000,
  });
}

// ── Writing ───────────────────────────────────────────────────────────────────────────────────────────────────────

/** Anything that changes a task refreshes the lists, the board and (when known) that task's page. */
export function refreshTasks(qc: QueryClient, id?: number) {
  qc.invalidateQueries({ queryKey: ['tasks'] });
  qc.invalidateQueries({ queryKey: ['board'] });
  qc.invalidateQueries({ queryKey: ['trash'] });
  if (id !== undefined) qc.invalidateQueries({ queryKey: ['task', id] });
}

export function useCreateTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: TaskInput) => api<{ success: boolean; task: { id: number; title: string } }>('tasks', { method: 'POST', body: input }),
    onSuccess: () => refreshTasks(qc),
  });
}

export function useUpdateTask(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (input: TaskInput) => api<{ success: boolean }>(`tasks/${id}`, { method: 'PUT', body: input }),
    onSuccess: () => refreshTasks(qc, id),
  });
}

export type TaskField = 'status' | 'priority' | 'deadline' | 'assigned_to' | 'project_id';

/** One field at a time, with the rules for who may change which (status: anyone on the task; the rest: owner/assignee). */
export function useUpdateField(id: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: { field: TaskField; value: string | number | null }) => api<{ success: boolean }>(`tasks/${id}/field`, { method: 'PATCH', body: v }),
    onSuccess: () => refreshTasks(qc, id),
  });
}

export function useMoveTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (v: { id: number; body: { status?: string; deadline?: string | null } }) =>
      api<{ success: boolean; overdue: boolean }>(`tasks/${v.id}/move`, { method: 'PATCH', body: v.body }),
    onSettled: (_d, _e, v) => refreshTasks(qc, v.id),
  });
}

export function useDeleteTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api<{ success: boolean }>(`tasks/${id}`, { method: 'DELETE' }),
    onSuccess: (_d, id) => {
      refreshTasks(qc, id);
      qc.removeQueries({ queryKey: ['task', id] });
    },
  });
}

export function useRestoreTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api<{ success: boolean }>(`tasks/${id}/restore`, { method: 'POST' }),
    onSuccess: () => refreshTasks(qc),
  });
}

export function useForceDeleteTask() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api<{ success: boolean }>(`tasks/${id}/force`, { method: 'DELETE' }),
    onSuccess: () => refreshTasks(qc),
  });
}

// ── Comments & checklist ──────────────────────────────────────────────────────────────────────────────────────────

export function useTaskComments(taskId: number) {
  const qc = useQueryClient();
  const done = () => qc.invalidateQueries({ queryKey: ['task', taskId] });
  return {
    add: useMutation({
      mutationFn: (v: { content: string; parent_id?: number | null; mentions?: number[] }) => api(`local-task/${taskId}/comment`, { method: 'POST', body: v }),
      onSuccess: () => {
        done();
        qc.invalidateQueries({ queryKey: ['tasks'] }); // a comment bumps the task to the top of "latest activity"
      },
    }),
    edit: useMutation({
      mutationFn: (v: { id: number; content: string }) => api(`local-task/comments/${v.id}`, { method: 'PATCH', body: { content: v.content } }),
      onSuccess: done,
    }),
    remove: useMutation({
      mutationFn: (id: number) => api(`local-task/comments/${id}`, { method: 'DELETE' }),
      onSuccess: done,
    }),
    react: useMutation({
      mutationFn: (v: { id: number; emoji: string }) => api(`local-task/comments/${v.id}/react`, { method: 'POST', body: { emoji: v.emoji } }),
      onSuccess: done,
    }),
  };
}

export function useChecklist(taskId: number) {
  const qc = useQueryClient();
  const done = () => qc.invalidateQueries({ queryKey: ['task', taskId] });
  return {
    add: useMutation({
      mutationFn: (text: string) => api(`local-task/${taskId}/checklist`, { method: 'POST', body: { text } }),
      onSuccess: done,
    }),
    toggle: useMutation({
      mutationFn: (itemId: number) => api(`local-task/${taskId}/checklist/${itemId}`, { method: 'PATCH' }),
      onSuccess: done,
    }),
    remove: useMutation({
      mutationFn: (itemId: number) => api(`local-task/${taskId}/checklist/${itemId}`, { method: 'DELETE' }),
      onSuccess: done,
    }),
  };
}

/** Attach a file or photo to the task itself (the "Files" card), as opposed to inside a comment. */
export function useAttachToTask(taskId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: (file: LocalFile) => uploadFile<{ ok: boolean }>(`local-task/${taskId}/attach`, file),
    onSuccess: () => refreshTasks(qc, taskId),
  });
}
