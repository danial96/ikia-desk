import { isHiddenFor, type ChatMessage } from '@/api/chat';
import { wallParts } from './time';

export type ChatRow = { type: 'day'; key: string; label: string } | { type: 'msg'; key: string; msg: ChatMessage; showAuthor: boolean };

/** The calendar day of a message in the viewer's zone ("2026-10-15"). Messages still being sent have no server date yet. */
export function dayOf(m: ChatMessage, timeZone: string): string {
  if (m.date) return m.date;
  const w = wallParts(new Date(m.createdTs * 1000), timeZone);
  return `${w.y}-${String(w.m).padStart(2, '0')}-${String(w.d).padStart(2, '0')}`;
}

export function dayLabel(day: string, today: string, yesterday: string): string {
  if (day === today) return 'Today';
  if (day === yesterday) return 'Yesterday';
  const [y, m, d] = day.split('-').map(Number);
  return new Date(Date.UTC(y, m - 1, d)).toLocaleDateString('en-GB', { timeZone: 'UTC', weekday: 'short', day: 'numeric', month: 'short', year: y === Number(today.slice(0, 4)) ? undefined : 'numeric' });
}

/**
 * Rows for an INVERTED list (newest first), with a day heading above the first message of each day.
 * In a group, the sender's name/photo shows when the sender changes from the message before it.
 */
export function buildRows(messages: ChatMessage[], meId: number, direct: boolean, timeZone: string, now: Date = new Date()): ChatRow[] {
  const visible = messages.filter((m) => !isHiddenFor(m, meId));
  const w = wallParts(now, timeZone);
  const pad = (n: number) => String(n).padStart(2, '0');
  const today = `${w.y}-${pad(w.m)}-${pad(w.d)}`;
  const yd = new Date(Date.UTC(w.y, w.m - 1, w.d - 1));
  const yesterday = `${yd.getUTCFullYear()}-${pad(yd.getUTCMonth() + 1)}-${pad(yd.getUTCDate())}`;

  const asc: ChatRow[] = [];
  let lastDay = '';
  let lastAuthor = -1;
  for (const m of visible) {
    const day = dayOf(m, timeZone);
    if (day !== lastDay) {
      asc.push({ type: 'day', key: `d${day}`, label: dayLabel(day, today, yesterday) });
      lastDay = day;
      lastAuthor = -1;
    }
    asc.push({ type: 'msg', key: `m${m.id}`, msg: m, showAuthor: !direct && !m.isMine && m.author.id !== lastAuthor });
    lastAuthor = m.author.id;
  }
  return asc.reverse();
}
