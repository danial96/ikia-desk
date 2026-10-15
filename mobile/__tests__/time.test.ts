import { carrierDate, columnDeadline, formatDateTime, isoToNaive, moveBody, toNaive, wallFromCarrier, wallParts, type Wall } from '@/lib/time';

describe('wall clock in a profile time zone', () => {
  it('reads the same instant differently per zone', () => {
    const instant = new Date('2026-10-15T13:30:00Z');

    expect(wallParts(instant, 'Asia/Karachi')).toEqual({ y: 2026, m: 10, d: 15, h: 18, min: 30 });
    expect(wallParts(instant, 'UTC')).toEqual({ y: 2026, m: 10, d: 15, h: 13, min: 30 });
    expect(wallParts(instant, 'America/New_York')).toEqual({ y: 2026, m: 10, d: 15, h: 9, min: 30 });
  });

  it('rolls over midnight into the next day', () => {
    expect(wallParts(new Date('2026-10-15T20:00:00Z'), 'Asia/Karachi')).toMatchObject({ d: 16, h: 1, min: 0 });
  });

  it('writes midnight as 00:00, never 24:00', () => {
    expect(toNaive(wallParts(new Date('2026-10-15T19:00:00Z'), 'Asia/Karachi'))).toBe('2026-10-16T00:00');
  });

  it('makes the naive string the server expects from an ISO deadline', () => {
    expect(isoToNaive('2026-10-15T18:30:00+05:00', 'Asia/Karachi')).toBe('2026-10-15T18:30');
    expect(isoToNaive('2026-10-15T18:30:00+05:00', 'UTC')).toBe('2026-10-15T13:30');
    expect(isoToNaive(null, 'UTC')).toBeNull();
    expect(isoToNaive('garbage', 'UTC')).toBeNull();
  });

  it('round-trips through the picker carrier date', () => {
    const w: Wall = { y: 2026, m: 1, d: 5, h: 7, min: 5 };
    expect(wallFromCarrier(carrierDate(w))).toEqual(w);
    expect(toNaive(w)).toBe('2026-01-05T07:05');
  });

  it('formats a deadline in the person\'s zone', () => {
    expect(formatDateTime('2026-10-15T13:30:00Z', 'Asia/Karachi')).toBe('Thu, 15 Oct 2026, 6:30 pm');
    expect(formatDateTime(null, 'UTC')).toBe('No deadline');
  });
});

describe('board columns', () => {
  // Thursday 15 Oct 2026
  const thu: Wall = { y: 2026, m: 10, d: 15, h: 10, min: 0 };

  it('picks the date a card gets when dropped in a column', () => {
    expect(columnDeadline('due_today', thu)).toBe('2026-10-15');
    expect(columnDeadline('due_this_week', thu)).toBe('2026-10-16'); // Friday
    expect(columnDeadline('due_next_week', thu)).toBe('2026-10-23');
    expect(columnDeadline('due_over_two_weeks', thu)).toBe('2026-11-05');
    expect(columnDeadline('no_deadline', thu)).toBeNull();
    expect(columnDeadline('completed', thu)).toBeUndefined();
    expect(columnDeadline('overdue', thu)).toBeUndefined();
  });

  it('on a Friday "this week" means next Friday, on a Saturday the coming Friday', () => {
    expect(columnDeadline('due_this_week', { ...thu, d: 16 })).toBe('2026-10-23');
    expect(columnDeadline('due_this_week', { ...thu, d: 17 })).toBe('2026-10-23');
    expect(columnDeadline('due_this_week', { ...thu, d: 18 })).toBe('2026-10-23'); // Sunday
  });

  it('builds the move body: completing, reopening, and plain deadline moves', () => {
    expect(moveBody('due_today', 'completed', thu)).toEqual({ status: 'completed' });
    expect(moveBody('completed', 'due_today', thu)).toEqual({ status: 'in_progress', deadline: '2026-10-15' });
    expect(moveBody('due_today', 'no_deadline', thu)).toEqual({ deadline: null });
    expect(moveBody('completed', 'overdue', thu)).toEqual({ status: 'in_progress' });
  });
});

import { formatWall, parseNaive } from '@/lib/time';

describe('naive strings', () => {
  it('parses what toNaive writes, with T or a space', () => {
    expect(parseNaive('2026-10-15T18:30')).toEqual({ y: 2026, m: 10, d: 15, h: 18, min: 30 });
    expect(parseNaive('2026-10-15 07:05')).toEqual({ y: 2026, m: 10, d: 15, h: 7, min: 5 });
    expect(parseNaive('garbage')).toBeNull();
  });

  it('shows a wall clock without shifting it', () => {
    expect(formatWall({ y: 2026, m: 10, d: 15, h: 18, min: 30 })).toBe('Thu, 15 Oct 2026, 6:30 pm');
  });
});
