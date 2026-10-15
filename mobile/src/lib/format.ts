export const STATUS_LABEL: Record<string, string> = {
  new: 'New',
  pending: 'Pending',
  in_progress: 'In progress',
  reviewing: 'Reviewing',
  paused: 'Paused',
  completed: 'Completed',
};

/** "Oct 15, 6:30 PM". The server already sends deadlines in the person's own time zone (ISO-8601 with offset). */
export function formatDeadline(iso: string | null): string {
  if (!iso) return 'No deadline';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return 'No deadline';
  return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

/** "just now", "5 min ago", "3 h ago", "2 d ago", then a date. */
export function timeAgo(iso: string, now: Date = new Date()): string {
  const then = new Date(iso).getTime();
  if (Number.isNaN(then)) return '';
  const sec = Math.max(0, Math.round((now.getTime() - then) / 1000));
  if (sec < 45) return 'just now';
  if (sec < 3600) return `${Math.max(1, Math.round(sec / 60))} min ago`;
  if (sec < 86400) return `${Math.round(sec / 3600)} h ago`;
  if (sec < 7 * 86400) return `${Math.round(sec / 86400)} d ago`;
  return new Date(then).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
