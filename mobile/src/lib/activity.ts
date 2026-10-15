import type { ActivityItem } from '@/api/types';
import { STATUS_LABEL } from './format';
import { formatShort } from './time';

/** Values like "2026-09-24 18:00:00" are Asia/Karachi wall-clock times (how the server stores and logs deadlines). */
export function appDate(value: string | null): Date | null {
  if (!value) return null;
  let s = value.trim();
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) s += 'T00:00:00';
  if (!/(Z|[+-]\d{2}:?\d{2})$/i.test(s)) s = s.replace(' ', 'T') + '+05:00';
  const d = new Date(s);
  return Number.isNaN(d.getTime()) ? null : d;
}

const FIELD_LABEL: Record<string, string> = {
  status: 'status',
  priority: 'priority',
  deadline: 'deadline',
  assigned_to: 'responsible person',
  project_id: 'project',
  title: 'title',
};

function value(field: string | null, v: string | null, timeZone: string): string {
  if (v === null || v === '') return 'none';
  if (field === 'status') return STATUS_LABEL[v] ?? v;
  if (field === 'deadline') {
    const d = appDate(v);
    return d ? formatShort(d.toISOString(), timeZone) : v;
  }
  return v;
}

/** One human sentence for a line of the task's history. */
export function activityText(a: ActivityItem, timeZone: string): string {
  const who = a.author || 'Someone';
  switch (a.action) {
    case 'created':
      return `${who} created the task`;
    case 'updated': {
      const f = a.field ?? '';
      const label = FIELD_LABEL[f] ?? f.replace(/_/g, ' ');
      if (a.old === null || a.old === '') return `${who} set ${label} to ${value(f, a.new, timeZone)}`;
      return `${who} changed ${label} from ${value(f, a.old, timeZone)} to ${value(f, a.new, timeZone)}`;
    }
    case 'edited_task':
      return `${who} edited the task`;
    case 'attached file':
      return `${who} attached ${a.new ?? 'a file'}`;
    case 'checked checklist item':
      return `${who} checked “${a.new ?? ''}”`;
    case 'unchecked checklist item':
      return `${who} unchecked “${a.new ?? ''}”`;
    default:
      return `${who} ${a.action.replace(/_/g, ' ')}${a.new ? ` ${a.new}` : ''}`;
  }
}
