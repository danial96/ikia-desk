import { formatDeadline, timeAgo } from '@/lib/format';

describe('timeAgo()', () => {
  const now = new Date('2026-10-15T12:00:00Z');

  it.each([
    ['2026-10-15T11:59:50Z', 'just now'],
    ['2026-10-15T11:55:00Z', '5 min ago'],
    ['2026-10-15T09:00:00Z', '3 h ago'],
    ['2026-10-13T12:00:00Z', '2 d ago'],
  ])('%s -> %s', (iso, expected) => {
    expect(timeAgo(iso, now)).toBe(expected);
  });

  it('shows a date after a week and tolerates garbage', () => {
    expect(timeAgo('2026-09-01T12:00:00Z', now)).toMatch(/Sep/);
    expect(timeAgo('not a date', now)).toBe('');
  });
});

describe('formatDeadline()', () => {
  it('says so when there is none', () => {
    expect(formatDeadline(null)).toBe('No deadline');
    expect(formatDeadline('garbage')).toBe('No deadline');
  });

  it('formats a real deadline', () => {
    expect(formatDeadline('2026-10-15T18:30:00+05:00')).toMatch(/Oct/);
  });
});
