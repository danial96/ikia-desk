// Shapes of what the Desk server sends. See docs/mobile-api.md in the repo root.

export type UserPayload = {
  id: number;
  name: string;
  first_name: string | null;
  last_name: string | null;
  email: string;
  role: string;
  position: string | null;
  phone: string | null;
  avatar: string;
  time_zone: string;
  /** What to offer in the UI. The server still checks every action. */
  can: {
    view_all_tasks: boolean;
    create_tasks: boolean;
    create_projects: boolean;
    is_admin: boolean;
    is_super_admin: boolean;
  };
  notifications: {
    messages: boolean;
    messages_sound: boolean;
    tasks: boolean;
    tasks_sound: boolean;
  };
};

export type StoreLinks = { android: string | null; ios: string | null };

export type UpdateInfo = {
  minVersion: string | null;
  store: StoreLinks | null;
  message: string;
};

export type AppConfig = {
  min_version: string;
  latest_version: string;
  store: StoreLinks;
  realtime: { key: string; cluster: string } | null;
  server_time: string;
};

export type Person = { id: number; name: string; avatar: string };

export type TaskStatus = 'new' | 'pending' | 'in_progress' | 'reviewing' | 'paused' | 'completed';
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';

export type TaskCard = {
  id: number;
  title: string;
  status: TaskStatus;
  priority: TaskPriority;
  deadline: string | null;
  is_overdue: boolean;
  created_at: string;
  updated_at: string;
  project: { id: number; name: string } | null;
  assignee: Person | null;
  creator: Person | null;
  files_count: number;
  comments_count: number;
};

export type Page<T> = {
  data: T[];
  meta: { page: number; per_page: number; total: number; has_more: boolean };
};

export type NotificationItem = {
  id: number;
  type: string;
  task_id: number | null;
  task_title: string | null;
  message: string;
  read_at: string | null;
  created_at: string;
  actor: { name: string; avatar: string } | null;
};

export type NotificationsResponse = {
  notifications: NotificationItem[];
  unread_count: number;
};

// ── Tasks ──────────────────────────────────────────────────────────────────────────────────────────────────────────

export type Member = { id: number; name: string; avatar: string; position?: string | null };

export type TaskFile = { id: number; name: string; mime?: string | null; size: number | null; downloadUrl: string; isLocal: boolean };

export type Reactions = Record<string, number[]>;

export type CommentItem = {
  type: 'comment';
  at: string;
  id: number;
  isSystem: boolean;
  author: { id: number; name: string; avatar: string };
  text: string;
  editedAt: string | null;
  parentId: number | null;
  parentPreview: { author: string; text: string } | null;
  reactions: Reactions;
  myReactions: string[];
  files: TaskFile[];
};

export type ActivityItem = {
  type: 'activity';
  at: string;
  author: string;
  action: string;
  field: string | null;
  old: string | null;
  new: string | null;
};

export type FeedItem = CommentItem | ActivityItem;

export type ChecklistItem = { id: number; text: string; is_complete: boolean; completed_by: number | null };

export type TaskDetail = {
  task: {
    id: number;
    bitrixId: number | null;
    title: string;
    description: string | null;
    status: TaskStatus;
    priority: TaskPriority;
    assigned_to: number | null;
    deadline: string | null;
    createdAt: string;
    project: { id: number; name: string } | null;
  };
  assignee: Member | null;
  creator: Member | null;
  participants: Member[];
  observers: Member[];
  feed: FeedItem[];
  viewedBy: { id: number; name: string; avatar: string; viewedAt: string }[];
  lastSeenBy: { name: string; viewedAt: string } | null;
  canEdit: boolean;
  checklists: ChecklistItem[];
  localFiles: TaskFile[];
};

export type BoardCard = {
  id: number;
  title: string;
  priority: TaskPriority;
  status: TaskStatus;
  project: string | null;
  assignee: { name: string; avatar: string } | null;
  creator: { name: string; avatar: string } | null;
  members: string[];
  files_count: number;
  can_move: boolean;
  dl: { label: string; kind: 'done' | 'overdue' | 'today' | 'normal' } | null;
  hot: boolean;
};

export type Board = {
  columns: Record<string, BoardCard[]>;
  completedTotal: number;
  unseenTaskIds: number[];
};

export type TrashedTask = TaskCard & { deleted_at: string | null; deleted_by: string | null };

export type ProjectLite = { id: number; name: string };
export type UserLite = { id: number; name: string; position: string | null; avatar: string };

export type TaskInput = {
  title: string;
  description?: string | null;
  project_id?: number | null;
  assigned_to?: number | null;
  priority: TaskPriority;
  status?: TaskStatus;
  /** Wall-clock in the sender's profile zone, e.g. "2026-10-15T18:30", or null to clear. */
  deadline?: string | null;
  members?: number[];
  observers?: number[];
};
