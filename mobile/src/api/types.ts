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
