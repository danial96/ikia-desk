// Deadlines on the server are instants. People type and read them as wall-clock time in THEIR profile time zone
// (user.time_zone), which can differ from the phone's own zone. These helpers keep everything in the profile zone,
// the way the website does.

export type Wall = { y: number; m: number; d: number; h: number; min: number };

const pad = (n: number) => String(n).padStart(2, '0');

/** The clock reading of an instant in a time zone. */
export function wallParts(date: Date, timeZone: string): Wall {
  const o: Record<string, number> = {};
  new Intl.DateTimeFormat('en-GB', {
    timeZone,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: 'numeric',
    minute: 'numeric',
    hourCycle: 'h23',
  })
    .formatToParts(date)
    .forEach((p) => {
      if (p.type !== 'literal') o[p.type] = parseInt(p.value, 10);
    });
  return { y: o.year, m: o.month, d: o.day, h: o.hour % 24, min: o.minute };
}

/** "2026-10-15T18:30" — what the server expects: wall-clock in the SENDER's profile zone, no offset. */
export function toNaive(p: Wall): string {
  return `${p.y}-${pad(p.m)}-${pad(p.d)}T${pad(p.h)}:${pad(p.min)}`;
}

export function isoToNaive(iso: string | null, timeZone: string): string | null {
  if (!iso) return null;
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? null : toNaive(wallParts(d, timeZone));
}

/** A device-local Date whose components equal the wall clock — only used to feed a date/time picker. */
export function carrierDate(p: Wall): Date {
  return new Date(p.y, p.m - 1, p.d, p.h, p.min);
}

export function wallFromCarrier(d: Date): Wall {
  return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate(), h: d.getHours(), min: d.getMinutes() };
}

export function nowWall(timeZone: string, now: Date = new Date()): Wall {
  return wallParts(now, timeZone);
}

/** "Thu, 15 Oct 2026, 6:30 pm" in the person's own zone. */
export function formatDateTime(iso: string | null, timeZone: string): string {
  if (!iso) return 'No deadline';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return 'No deadline';
  const date = d.toLocaleDateString('en-GB', { timeZone, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  const time = d
    .toLocaleTimeString('en-GB', { timeZone, hour: 'numeric', minute: '2-digit', hour12: true })
    .replace(/\s*(AM|PM)$/i, (_m, a: string) => ` ${a.toLowerCase()}`);
  return `${date}, ${time}`;
}

/** "15 Oct, 6:30 pm" — short form for feeds. */
export function formatShort(iso: string, timeZone: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  const date = d.toLocaleDateString('en-GB', { timeZone, day: 'numeric', month: 'short' });
  const time = d
    .toLocaleTimeString('en-GB', { timeZone, hour: 'numeric', minute: '2-digit', hour12: true })
    .replace(/\s*(AM|PM)$/i, (_m, a: string) => ` ${a.toLowerCase()}`);
  return `${date}, ${time}`;
}

// ── Board columns ─────────────────────────────────────────────────────────────────────────────────────────────────

export type BoardColumn = 'overdue' | 'due_today' | 'due_this_week' | 'due_next_week' | 'no_deadline' | 'due_over_two_weeks' | 'completed';

export const BOARD_COLUMNS: { key: BoardColumn; label: string }[] = [
  { key: 'overdue', label: 'Overdue' },
  { key: 'due_today', label: 'Due today' },
  { key: 'due_this_week', label: 'This week' },
  { key: 'due_next_week', label: 'Next week' },
  { key: 'no_deadline', label: 'No deadline' },
  { key: 'due_over_two_weeks', label: 'Later' },
  { key: 'completed', label: 'Completed' },
];

const dateStr = (d: Date) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

/**
 * What dropping a card in a column means, the same rules as the website's board (kbColDeadline): the date moves,
 * the time of day stays. `undefined` = leave the deadline alone, `null` = clear it. Nothing can be dropped in Overdue.
 */
export function columnDeadline(col: BoardColumn, today: Wall): string | null | undefined {
  const base = new Date(today.y, today.m - 1, today.d);
  const add = (n: number) => {
    const d = new Date(base);
    d.setDate(base.getDate() + n);
    return dateStr(d);
  };
  const dow = base.getDay(); // 0 = Sunday
  const toFri = dow <= 5 ? 5 - dow || 7 : 6; // days until this (or, on a Friday, next) Friday
  switch (col) {
    case 'due_today':
      return dateStr(base);
    case 'due_this_week':
      return add(toFri);
    case 'due_next_week':
      return add(toFri + 7);
    case 'no_deadline':
      return null;
    case 'due_over_two_weeks':
      return add(21);
    default:
      return undefined;
  }
}

/** The body for PATCH tasks/{id}/move when a card goes from one column to another. */
export function moveBody(from: BoardColumn, to: BoardColumn, today: Wall): { status?: string; deadline?: string | null } {
  const body: { status?: string; deadline?: string | null } = {};
  if (to === 'completed') {
    body.status = 'completed';
    return body;
  }
  if (from === 'completed') body.status = 'in_progress';
  const dl = columnDeadline(to, today);
  if (dl !== undefined) body.deadline = dl;
  return body;
}

export function parseNaive(s: string): Wall | null {
  const m = s.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
  return m ? { y: +m[1], m: +m[2], d: +m[3], h: +m[4], min: +m[5] } : null;
}

/** A wall-clock reading as text: "Thu, 15 Oct 2026, 6:30 pm" (no zone conversion — it is already the person's clock). */
export function formatWall(w: Wall): string {
  return formatDateTime(new Date(Date.UTC(w.y, w.m - 1, w.d, w.h, w.min)).toISOString(), 'UTC');
}
