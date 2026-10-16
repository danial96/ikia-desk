import type { ChatMessage } from '@/api/chat';
import { buildRows, dayLabel, dayOf } from '@/lib/chatRows';

const msg = (id: number, ts: number, date: string, authorId = 1, over: Partial<ChatMessage> = {}): ChatMessage => ({
  id,
  text: 'x',
  isMine: false,
  time: '',
  date,
  createdTs: ts,
  editedAt: null,
  deletedFor: [],
  reactions: {},
  myReactions: [],
  author: { id: authorId, name: `U${authorId}`, avatar: '' },
  parentId: null,
  parentPreview: null,
  ...over,
});

const now = new Date('2026-10-15T12:00:00Z');

describe('buildRows()', () => {
  it('lists newest first with a day heading above each day\'s first message', () => {
    const rows = buildRows([msg(1, 1, '2026-10-14'), msg(2, 2, '2026-10-14'), msg(3, 3, '2026-10-15')], 9, true, 'UTC', now);

    expect(rows.map((r) => (r.type === 'day' ? `day:${r.label}` : `m${r.msg.id}`))).toEqual(['m3', 'day:Today', 'm2', 'm1', 'day:Yesterday']);
  });

  it('hides messages deleted for me', () => {
    const rows = buildRows([msg(1, 1, '2026-10-15', 2, { deletedFor: [9] }), msg(2, 2, '2026-10-15')], 9, true, 'UTC', now);

    expect(rows.filter((r) => r.type === 'msg')).toHaveLength(1);
  });

  it('in a group shows the sender once per run, never for my own messages, never in a direct chat', () => {
    const list = [msg(1, 1, '2026-10-15', 2), msg(2, 2, '2026-10-15', 2), msg(3, 3, '2026-10-15', 3), msg(4, 4, '2026-10-15', 9, { isMine: true })];

    const group = buildRows(list, 9, false, 'UTC', now).filter((r) => r.type === 'msg').map((r) => (r.type === 'msg' ? [r.msg.id, r.showAuthor] : []));
    expect(group).toEqual([[4, false], [3, true], [2, false], [1, true]]);

    const direct = buildRows(list, 9, true, 'UTC', now).filter((r) => r.type === 'msg').map((r) => (r.type === 'msg' ? r.showAuthor : null));
    expect(direct).toEqual([false, false, false, false]);
  });

  it('starts a new run after a day heading', () => {
    const rows = buildRows([msg(1, 1, '2026-10-14', 2), msg(2, 2, '2026-10-15', 2)], 9, false, 'UTC', now).filter((r) => r.type === 'msg');

    expect(rows.every((r) => r.type === 'msg' && r.showAuthor)).toBe(true);
  });
});

describe('days', () => {
  it('gives a message still being sent today\'s date in the viewer\'s zone', () => {
    // 20:00 UTC is already the next day in Karachi
    const ts = Math.floor(new Date('2026-10-15T20:00:00Z').getTime() / 1000);
    expect(dayOf(msg(-1, ts, ''), 'Asia/Karachi')).toBe('2026-10-16');
    expect(dayOf(msg(-1, ts, ''), 'UTC')).toBe('2026-10-15');
  });

  it('labels today, yesterday, and other days (year only when it differs)', () => {
    expect(dayLabel('2026-10-15', '2026-10-15', '2026-10-14')).toBe('Today');
    expect(dayLabel('2026-10-14', '2026-10-15', '2026-10-14')).toBe('Yesterday');
    expect(dayLabel('2026-09-02', '2026-10-15', '2026-10-14')).toMatch(/^Wed,? 2 Sep\w*$/);
    expect(dayLabel('2025-09-02', '2026-10-15', '2026-10-14')).toMatch(/2025/);
  });
});
