import type { QueryClient } from '@tanstack/react-query';

// What the server publishes to a person's private channel (App\Support\Realtime). Only ids travel, never text:
// the app asks the API for the content, so the permission rules stay in one place.
//   chat.changed  { c: conversationId, k: new|read|edit|delete|react, m?: messageId, by: userId }
//   task.changed  { t: taskId, k: kind, by: userId }
//   notif         (a new in-app notification)

export type RealtimeEvent = 'chat.changed' | 'task.changed' | 'notif';

/** Turn a published event into "refetch what is now out of date". */
export function handleRealtime(qc: QueryClient, event: string, data: any): void {
  switch (event) {
    case 'chat.changed': {
      qc.invalidateQueries({ queryKey: ['convs'] });
      if (data && typeof data.c === 'number') qc.invalidateQueries({ queryKey: ['chat', data.c] });
      break;
    }
    case 'task.changed': {
      qc.invalidateQueries({ queryKey: ['tasks'] });
      qc.invalidateQueries({ queryKey: ['board'] });
      if (data && typeof data.t === 'number') qc.invalidateQueries({ queryKey: ['task', data.t] });
      break;
    }
    case 'notif': {
      qc.invalidateQueries({ queryKey: ['notifications'] });
      break;
    }
  }
}
