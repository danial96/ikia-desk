import type { ActivityItem, CommentItem, TaskDetail, UserPayload } from '@/api/types';
import { activityText, appDate } from '@/lib/activity';
import { canDeleteComment, canEditComment, taskPerms } from '@/lib/permissions';

const person = (id: number) => ({ id, name: `P${id}`, avatar: '' });
const user = (id: number, can: Partial<UserPayload['can']> = {}) =>
  ({ id, can: { view_all_tasks: false, create_tasks: false, create_projects: false, is_admin: false, is_super_admin: false, ...can } }) as UserPayload;

function detail(over: Partial<TaskDetail> & { assigned?: number | null } = {}): TaskDetail {
  return {
    task: { id: 1, assigned_to: over.assigned ?? null } as TaskDetail['task'],
    creator: person(10),
    assignee: null,
    participants: [],
    observers: [],
    feed: [],
    viewedBy: [],
    lastSeenBy: null,
    canEdit: false,
    checklists: [],
    localFiles: [],
    ...over,
  } as TaskDetail;
}

describe('taskPerms()', () => {
  it('lets the creator do everything except nothing: edit comes from the server flag', () => {
    const p = taskPerms(user(10), detail({ canEdit: true }));
    expect(p).toEqual({ canEdit: true, canChangeStatus: true, canChangeFields: true, canChecklist: true, canDelete: true });
  });

  it('the person responsible can change status and fields but not edit or delete', () => {
    const p = taskPerms(user(20), detail({ assigned: 20 }));
    expect(p).toEqual({ canEdit: false, canChangeStatus: true, canChangeFields: true, canChecklist: true, canDelete: false });
  });

  it('a participant can change status and tick the checklist only', () => {
    const p = taskPerms(user(30), detail({ participants: [person(30)] }));
    expect(p).toEqual({ canEdit: false, canChangeStatus: true, canChangeFields: false, canChecklist: true, canDelete: false });
  });

  it('an observer can only read', () => {
    const p = taskPerms(user(40), detail({ observers: [person(40)] }));
    expect(p).toEqual({ canEdit: false, canChangeStatus: false, canChangeFields: false, canChecklist: false, canDelete: false });
  });

  it('admins may trash any task; only the super admin changes everything', () => {
    expect(taskPerms(user(50, { is_admin: true }), detail()).canDelete).toBe(true);
    const sup = taskPerms(user(60, { is_admin: true, is_super_admin: true }), detail());
    expect(sup).toMatchObject({ canChangeStatus: true, canChangeFields: true, canChecklist: true, canDelete: true });
  });
});

describe('comment rules', () => {
  const comment = (authorId: number, at: string, isSystem = false) => ({ id: 1, author: person(authorId), at, isSystem }) as CommentItem;
  const now = new Date('2026-10-15T12:00:00Z');

  it('own comments can be edited for 24 hours', () => {
    expect(canEditComment(comment(1, '2026-10-15T01:00:00Z'), user(1), now)).toBe(true);
    expect(canEditComment(comment(1, '2026-10-14T11:00:00Z'), user(1), now)).toBe(false);
    expect(canEditComment(comment(2, '2026-10-15T01:00:00Z'), user(1), now)).toBe(false);
    expect(canEditComment(comment(1, '2026-10-15T01:00:00Z', true), user(1), now)).toBe(false);
  });

  it('the author or the super admin may delete', () => {
    expect(canDeleteComment(comment(1, ''), user(1))).toBe(true);
    expect(canDeleteComment(comment(2, ''), user(1))).toBe(false);
    expect(canDeleteComment(comment(2, ''), user(9, { is_super_admin: true }))).toBe(true);
  });
});

describe('activityText()', () => {
  const act = (over: Partial<ActivityItem>): ActivityItem => ({ type: 'activity', at: '', author: 'Ali', action: 'updated', field: null, old: null, new: null, ...over });

  it('describes status, priority and responsible changes', () => {
    expect(activityText(act({ field: 'status', old: 'new', new: 'in_progress' }), 'UTC')).toBe('Ali changed status from New to In progress');
    expect(activityText(act({ field: 'priority', old: 'low', new: 'high' }), 'UTC')).toBe('Ali changed priority from low to high');
    expect(activityText(act({ field: 'assigned_to', old: 'Sara', new: 'Omar' }), 'UTC')).toBe('Ali changed responsible person from Sara to Omar');
    expect(activityText(act({ field: 'status', old: null, new: 'new' }), 'UTC')).toBe('Ali set status to New');
  });

  it('shows deadlines in the viewer\'s zone (stored values are Karachi wall-clock)', () => {
    // 18:00 Karachi = 13:00 UTC
    expect(activityText(act({ field: 'deadline', old: null, new: '2026-09-24 18:00:00' }), 'UTC')).toMatch(/^Ali set deadline to 24 Sep\w*, 1:00 pm$/);
    expect(activityText(act({ field: 'deadline', old: '2026-09-24 18:00:00', new: null }), 'Asia/Karachi')).toMatch(/changed deadline from 24 Sep\w*, 6:00 pm to none/);
  });

  it('describes the other actions', () => {
    expect(activityText(act({ action: 'created' }), 'UTC')).toBe('Ali created the task');
    expect(activityText(act({ action: 'edited_task' }), 'UTC')).toBe('Ali edited the task');
    expect(activityText(act({ action: 'attached file', new: 'a.pdf' }), 'UTC')).toBe('Ali attached a.pdf');
    expect(activityText(act({ action: 'checked checklist item', new: 'Call client' }), 'UTC')).toBe('Ali checked “Call client”');
    expect(activityText(act({ action: 'something_new' }), 'UTC')).toBe('Ali something new');
  });
});

describe('appDate()', () => {
  it('reads stored values as Karachi time', () => {
    expect(appDate('2026-09-24 18:00:00')?.toISOString()).toBe('2026-09-24T13:00:00.000Z');
    expect(appDate('2026-09-24')?.toISOString()).toBe('2026-09-23T19:00:00.000Z');
    expect(appDate('2026-09-24T18:00:00+00:00')?.toISOString()).toBe('2026-09-24T18:00:00.000Z');
    expect(appDate('nope')).toBeNull();
    expect(appDate(null)).toBeNull();
  });
});
