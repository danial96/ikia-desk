import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';

import { api, uploadFile, type LocalFile } from './client';
import type { Reactions } from './types';

export type ConvType = 'direct' | 'group' | 'general' | 'notes';

export type Conversation = {
  id: number;
  type: ConvType;
  name: string;
  other_user_id: number | null;
  avatar: string | null;
  position: string | null;
  members: number;
  unread: number;
  online: boolean;
  lastMsg: { text: string; byMe: boolean; senderName: string | null; ts?: number; time: string } | null;
};

export type ChatMessage = {
  id: number;
  text: string;
  isMine: boolean;
  time: string;
  date: string;
  createdTs: number;
  editedAt: string | null;
  deletedFor: number[];
  reactions: Reactions;
  myReactions: string[];
  author: { id: number; name: string; avatar: string };
  parentId: number | null;
  parentPreview: { author: string; text: string } | null;
  /** Only on messages that exist on this phone alone: still sending, or failed. */
  local?: 'sending' | 'failed';
};

export type ConvInfo = { id: number; type: ConvType; name: string; avatar: string | null; position: string | null; members: number; online: boolean; last_seen: string | null };

export type ChatData = {
  conv: ConvInfo;
  messages: ChatMessage[];
  hasMore: boolean;
  otherLastReadTs: number | null;
  otherLastSeenTs: number | null;
};

// ── Pure helpers (unit-tested) ────────────────────────────────────────────────────────────────────────────────────

/** Messages by id (a later copy wins), oldest first. */
export function mergeMessages(...lists: ChatMessage[][]): ChatMessage[] {
  const byId = new Map<number, ChatMessage>();
  for (const l of lists) for (const m of l) byId.set(m.id, m);
  return [...byId.values()].sort((a, b) => a.createdTs - b.createdTs || a.id - b.id);
}

/**
 * Fresh latest window from the server + the older messages already scrolled into view + anything still being sent.
 * Messages that vanished from the window (deleted) disappear; older ones are kept as they were.
 */
export function mergeWindow(previous: ChatMessage[], windowMsgs: ChatMessage[], hasMoreInWindow: boolean): { messages: ChatMessage[]; hasMore: boolean } {
  const firstTs = windowMsgs.length ? windowMsgs[0].createdTs : Infinity;
  const older = previous.filter((m) => !m.local && m.createdTs < firstTs);
  const local = previous.filter((m) => m.local);
  return { messages: mergeMessages(older, windowMsgs, local), hasMore: older.length > 0 ? true : hasMoreInWindow };
}

/** "✓" sent, "✓✓" delivered, blue-style read: the same rule as the website. */
export function tickState(m: ChatMessage, otherLastReadTs: number | null, otherLastSeenTs: number | null, direct: boolean): 'sending' | 'failed' | 'sent' | 'delivered' | 'read' | null {
  if (!m.isMine) return null;
  if (m.local) return m.local;
  if (!direct) return 'sent';
  if (otherLastReadTs !== null && otherLastReadTs >= m.createdTs) return 'read';
  if (otherLastSeenTs !== null && otherLastSeenTs >= m.createdTs) return 'delivered';
  return 'sent';
}

export function isHiddenFor(m: ChatMessage, meId: number): boolean {
  return (m.deletedFor ?? []).includes(meId);
}

export function lastMessagePreview(text: string): string {
  return text
    .replace(/\[img\].*?\[\/img\]/gis, '📷 Photo')
    .replace(/\[file name="([^"]*)"\].*?\[\/file\]/gis, '📎 $1')
    .replace(/\[voice[^\]]*\].*?\[\/voice\]/gis, '🎤 Voice message')
    .replace(/\s+/g, ' ')
    .trim();
}

// ── Reading ───────────────────────────────────────────────────────────────────────────────────────────────────────

/** The conversation list. Calling it is also what tells the server this person is online (the "delivered" tick and green dot). */
export function useConversations() {
  return useQuery({
    queryKey: ['convs'],
    queryFn: ({ signal }) => api<{ convs: Conversation[] }>('chat/convs', { signal }).then((r) => r.convs),
    refetchInterval: 20_000,
  });
}

export const chatKey = (convId: number) => ['chat', convId] as const;

/** Fresh window; with `qc` it is merged into what is already on screen. */
export async function loadChat(convId: number, qc: QueryClient, signal?: AbortSignal): Promise<ChatData> {
  const res = await api<ChatData>(`chat/convs/${convId}/msgs`, { signal });
  const prev = qc.getQueryData<ChatData>(chatKey(convId));
  const merged = prev ? mergeWindow(prev.messages, res.messages, res.hasMore) : { messages: res.messages, hasMore: res.hasMore };
  return { ...res, ...merged };
}

export function useChat(convId: number, pollMs: number) {
  const qc = useQueryClient();
  return useQuery({
    queryKey: chatKey(convId),
    queryFn: ({ signal }) => loadChat(convId, qc, signal),
    refetchInterval: pollMs,
    staleTime: 0,
  });
}

/** Older messages (scrolling up). */
export async function loadOlder(convId: number, qc: QueryClient): Promise<void> {
  const data = qc.getQueryData<ChatData>(chatKey(convId));
  const first = data?.messages.find((m) => !m.local);
  if (!data || !first) return;
  const res = await api<{ messages: ChatMessage[]; hasMore: boolean }>(`chat/convs/${convId}/msgs`, { query: { before_ts: first.createdTs, before_id: first.id } });
  qc.setQueryData<ChatData>(chatKey(convId), (cur) => (cur ? { ...cur, messages: mergeMessages(res.messages, cur.messages), hasMore: res.hasMore } : cur));
}

export function useSearch(convId: number, q: string) {
  return useQuery({
    queryKey: ['chat-search', convId, q],
    enabled: q.trim().length >= 2,
    queryFn: ({ signal }) =>
      api<{ q: string; results: { id: number; text: string; author: string; avatar: string; isMine: boolean; date: string; time: string; createdTs: number }[] }>(`chat/convs/${convId}/search`, { query: { q }, signal }),
  });
}

// ── Writing ───────────────────────────────────────────────────────────────────────────────────────────────────────

let tempId = -1;

export function useSendMessage(convId: number, me: { id: number; name: string; avatar: string }) {
  const qc = useQueryClient();
  const key = chatKey(convId);

  const patch = (fn: (msgs: ChatMessage[]) => ChatMessage[]) =>
    qc.setQueryData<ChatData>(key, (cur) => (cur ? { ...cur, messages: fn(cur.messages) } : cur));

  const send = async (v: { content: string; parent_id?: number | null; mentions?: number[]; retryOf?: number; parentPreview?: ChatMessage['parentPreview'] }) => {
    const id = v.retryOf ?? tempId--;
    const now = Math.floor(Date.now() / 1000);
    const optimistic: ChatMessage = {
      id,
      text: v.content,
      isMine: true,
      time: '',
      date: '',
      createdTs: now,
      editedAt: null,
      deletedFor: [],
      reactions: {},
      myReactions: [],
      author: me,
      parentId: v.parent_id ?? null,
      parentPreview: v.parentPreview ?? null,
      local: 'sending',
    };
    patch((msgs) => mergeMessages(msgs.filter((m) => m.id !== id), [optimistic]));
    try {
      const res = await api<{ ok: boolean; message: ChatMessage }>(`chat/convs/${convId}/send`, {
        method: 'POST',
        body: { content: v.content, parent_id: v.parent_id ?? null, mentions: v.mentions ?? [] },
      });
      patch((msgs) => mergeMessages(msgs.filter((m) => m.id !== id), [res.message]));
      qc.invalidateQueries({ queryKey: ['convs'] });
      return res.message;
    } catch (e) {
      patch((msgs) => msgs.map((m) => (m.id === id ? { ...m, local: 'failed' as const } : m)));
      throw e;
    }
  };
  return send;
}

export function discardLocal(qc: QueryClient, convId: number, id: number) {
  qc.setQueryData<ChatData>(chatKey(convId), (cur) => (cur ? { ...cur, messages: cur.messages.filter((m) => m.id !== id) } : cur));
}

export function useMessageActions(convId: number) {
  const qc = useQueryClient();
  const refresh = () => loadChat(convId, qc).then((d) => qc.setQueryData(chatKey(convId), d));
  return {
    edit: useMutation({
      mutationFn: (v: { id: number; content: string }) => api(`chat/msgs/${v.id}`, { method: 'PATCH', body: { content: v.content } }),
      onSuccess: refresh,
    }),
    remove: useMutation({
      mutationFn: (v: { id: number; scope: 'everyone' | 'me' }) => api(`chat/msgs/${v.id}`, { method: 'DELETE', query: { scope: v.scope } }),
      onSuccess: () => {
        refresh();
        qc.invalidateQueries({ queryKey: ['convs'] });
      },
    }),
    react: useMutation({
      mutationFn: (v: { id: number; emoji: string }) => api(`chat/msgs/${v.id}/react`, { method: 'POST', body: { emoji: v.emoji } }),
      onSuccess: refresh,
    }),
  };
}

export function useStartChats() {
  const qc = useQueryClient();
  return {
    direct: useMutation({
      mutationFn: (userId: number) => api<{ conv_id: number }>('chat/direct', { method: 'POST', body: { user_id: userId } }),
      onSuccess: () => qc.invalidateQueries({ queryKey: ['convs'] }),
    }),
    group: useMutation({
      mutationFn: (v: { name: string; members: number[] }) => api<{ conv_id: number }>('chat/group', { method: 'POST', body: v }),
      onSuccess: () => qc.invalidateQueries({ queryKey: ['convs'] }),
    }),
    leave: useMutation({
      mutationFn: (id: number) => api(`chat/convs/${id}/leave`, { method: 'POST' }),
      onSuccess: () => qc.invalidateQueries({ queryKey: ['convs'] }),
    }),
  };
}

// ── Attachments ───────────────────────────────────────────────────────────────────────────────────────────────────

export type Uploaded = { url: string; name: string; mime: string; ext: string };

export const uploadAttachment = (file: LocalFile) => uploadFile<Uploaded>('upload', file);

/** The tag the website uses in message text for an uploaded file. */
export function attachmentTag(kind: 'image' | 'file' | 'voice', up: Uploaded, duration?: string): string {
  if (kind === 'image') return `[img]${up.url}[/img]`;
  if (kind === 'voice') return `[voice${duration ? ` dur="${duration}"` : ''}]${up.url}[/voice]`;
  return `[file name="${up.name.replace(/"/g, "'")}"]${up.url}[/file]`;
}
