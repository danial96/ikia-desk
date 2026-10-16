// Where a tapped notification should take the person. The server puts conversation_id / task_id in `data`
// (App\Support\MobilePush::message); `url` is the website-style address it was built from, kept as a fallback.

export type Target = { pathname: '/chat/[id]'; params: { id: string } } | { pathname: '/task/[id]'; params: { id: string } } | { pathname: '/notifications' };

const toId = (v: unknown): string | null => {
  const n = typeof v === 'string' ? Number(v) : typeof v === 'number' ? v : NaN;
  return Number.isInteger(n) && n > 0 ? String(n) : null;
};

export function routeForNotification(data: Record<string, unknown> | null | undefined): Target {
  const d = data ?? {};
  const conv = toId(d.conversation_id) ?? (typeof d.url === 'string' ? toId(d.url.match(/[?&]conv=(\d+)/)?.[1]) : null);
  if (conv) return { pathname: '/chat/[id]', params: { id: conv } };
  const task = toId(d.task_id) ?? (typeof d.url === 'string' ? toId(d.url.match(/[?&]task=(\d+)/)?.[1]) : null);
  if (task) return { pathname: '/task/[id]', params: { id: task } };
  return { pathname: '/notifications' };
}
