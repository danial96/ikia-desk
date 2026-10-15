import type { CommentItem, TaskDetail, UserPayload } from '@/api/types';

/**
 * What to OFFER on a task. These mirror the server's rules (TaskController, shared_api.php) so people are not shown
 * buttons that would fail; the server still decides every action.
 */
export function taskPerms(user: UserPayload, d: TaskDetail) {
  const me = user.id;
  const isSuper = user.can.is_super_admin;
  const created = d.creator?.id === me;
  const assigned = d.task.assigned_to === me;
  const participant = d.participants.some((p) => p.id === me);
  const member = created || assigned || participant;

  return {
    /** Full edit form (title, description, project, people): super admin or the creator. */
    canEdit: d.canEdit,
    /** Status: anyone on the task. */
    canChangeStatus: isSuper || member,
    /** Priority, deadline, responsible person: super admin, creator or the person responsible. */
    canChangeFields: isSuper || created || assigned,
    canChecklist: isSuper || member,
    /** Move to Trash: any admin, or the creator. */
    canDelete: user.can.is_admin || created,
  };
}

const DAY_MS = 24 * 60 * 60 * 1000;

/** Own comment, within 24 hours. */
export function canEditComment(c: CommentItem, user: UserPayload, now: Date = new Date()): boolean {
  return !c.isSystem && c.author.id === user.id && now.getTime() - new Date(c.at).getTime() <= DAY_MS;
}

export function canDeleteComment(c: CommentItem, user: UserPayload): boolean {
  return c.author.id === user.id || user.can.is_super_admin;
}
