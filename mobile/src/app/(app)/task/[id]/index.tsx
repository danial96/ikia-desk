import { useMemo, useRef, useState } from 'react';
import { Alert, FlatList, KeyboardAvoidingView, Platform, Pressable, RefreshControl, Text, TextInput, View } from 'react-native';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import { ApiError } from '@/api/client';
import { useChecklist, useDeleteTask, useTask, useTaskComments, useUpdateField, useUsers, type TaskField } from '@/api/tasks';
import type { CommentItem, FeedItem, TaskDetail, TaskPriority, TaskStatus, UserPayload } from '@/api/types';
import { useSession } from '@/auth/session';
import { Avatar } from '@/components/Avatar';
import { DeadlineField } from '@/components/DeadlineField';
import { RichText } from '@/components/RichText';
import { ActionSheet, PickerSheet } from '@/components/Sheet';
import { ActivityRow, CommentView } from '@/components/tasks/Comment';
import { Loading, Message } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { STATUS_LABEL } from '@/lib/format';
import { openFile } from '@/lib/files';
import { canDeleteComment, canEditComment, taskPerms } from '@/lib/permissions';
import { hasAttachmentTags, plainText } from '@/lib/richText';
import { goBack } from '@/lib/nav';
import { formatShort, isoToNaive } from '@/lib/time';
import { priorityColor, spacing, useColors } from '@/theme';

const STATUSES: TaskStatus[] = ['new', 'pending', 'in_progress', 'reviewing', 'paused', 'completed'];
const PRIORITIES: TaskPriority[] = ['low', 'medium', 'high', 'urgent'];
const EMOJIS = ['👍', '❤️', '😂', '😮', '🙏', '✅'];

export default function TaskScreen() {
  const c = useColors();
  const { id } = useLocalSearchParams<{ id: string }>();
  const taskId = Number(id);
  const { user } = useSession();
  const q = useTask(taskId);

  if (!user) return null;
  if (q.isPending) return <Loading />;
  if (q.isError || !q.data) {
    const gone = q.error instanceof ApiError && (q.error.status === 404 || q.error.status === 403);
    return (
      <Message
        title={gone ? 'This task is not available' : 'Could not load the task'}
        detail={gone ? 'It may have been deleted, or you no longer have access.' : errorMessage(q.error)}
        actionTitle={gone ? 'Go back' : 'Try again'}
        onAction={() => (gone ? goBack() : q.refetch())}
      />
    );
  }
  return <TaskBody key={taskId} taskId={taskId} detail={q.data} user={user} refreshing={q.isRefetching} onRefresh={() => q.refetch()} />;
}

type BodyProps = { taskId: number; detail: TaskDetail; user: UserPayload; refreshing: boolean; onRefresh: () => void };

function TaskBody({ taskId, detail, user, refreshing, onRefresh }: BodyProps) {
  const c = useColors();
  const tz = user.time_zone;
  const perms = taskPerms(user, detail);
  const field = useUpdateField(taskId);
  const comments = useTaskComments(taskId);
  const del = useDeleteTask();
  const users = useUsers();
  const list = useRef<FlatList<FeedItem>>(null);

  const [sheet, setSheet] = useState<null | 'status' | 'priority' | 'assignee' | 'menu'>(null);
  const [commentMenu, setCommentMenu] = useState<CommentItem | null>(null);
  const [replyTo, setReplyTo] = useState<CommentItem | null>(null);
  const [editing, setEditing] = useState<CommentItem | null>(null);
  const [draft, setDraft] = useState('');
  const [mentions, setMentions] = useState<number[]>([]);
  const [mentionSheet, setMentionSheet] = useState(false);

  const t = detail.task;
  const fail = (e: unknown) => Alert.alert('Could not save', errorMessage(e));
  const setField = (f: TaskField, value: string | number | null) => field.mutate({ field: f, value }, { onError: fail });

  function confirmDelete() {
    Alert.alert('Move to Trash?', 'You can restore it from Trash later.', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Move to Trash', style: 'destructive', onPress: () => del.mutate(taskId, { onSuccess: goBack, onError: fail }) },
    ]);
  }

  function send() {
    const text = draft.trim();
    if (!text || comments.add.isPending || comments.edit.isPending) return;
    if (editing) {
      comments.edit.mutate(
        { id: editing.id, content: text },
        { onSuccess: () => { setEditing(null); setDraft(''); }, onError: fail },
      );
      return;
    }
    // only keep the @mentions whose names are still in the text
    const names = new Map((users.data ?? []).map((u) => [u.id, u.name]));
    const kept = mentions.filter((m) => names.has(m) && text.includes(`@${names.get(m)}`));
    comments.add.mutate(
      { content: text, parent_id: replyTo?.id ?? null, mentions: kept },
      {
        onSuccess: () => {
          setDraft('');
          setReplyTo(null);
          setMentions([]);
          setTimeout(() => list.current?.scrollToEnd({ animated: true }), 400);
        },
        onError: fail,
      },
    );
  }

  const commentActions = commentMenu
    ? [
        { label: 'Reply', onPress: () => { setEditing(null); setReplyTo(commentMenu); } },
        ...EMOJIS.map((e) => ({ label: `React ${e}`, onPress: () => comments.react.mutate({ id: commentMenu.id, emoji: e }, { onError: fail }) })),
        ...(canEditComment(commentMenu, user)
          ? [{ label: 'Edit', onPress: () => { setReplyTo(null); setEditing(commentMenu); setDraft(plainEditText(commentMenu.text)); } }]
          : []),
        ...(canDeleteComment(commentMenu, user)
          ? [{
              label: 'Delete',
              destructive: true,
              onPress: () =>
                Alert.alert('Delete this comment?', undefined, [
                  { text: 'Cancel', style: 'cancel' },
                  { text: 'Delete', style: 'destructive', onPress: () => comments.remove.mutate(commentMenu.id, { onError: fail }) },
                ]),
            }]
          : []),
      ]
    : [];

  const header = (
    <Header
      detail={detail}
      user={user}
      perms={perms}
      onStatus={() => setSheet('status')}
      onPriority={() => setSheet('priority')}
      onAssignee={() => setSheet('assignee')}
      onDeadline={(naive) => setField('deadline', naive)}
      saving={field.isPending}
    />
  );

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}>
      <Stack.Screen
        options={{
          title: `Task #${taskId}`,
          headerRight: () =>
            perms.canEdit || perms.canDelete ? (
              <Pressable accessibilityRole="button" accessibilityLabel="Task actions" onPress={() => setSheet('menu')} hitSlop={10}>
                <Ionicons name="ellipsis-horizontal-circle" size={26} color={c.primary} />
              </Pressable>
            ) : null,
        }}
      />

      <FlatList
        ref={list}
        data={detail.feed}
        keyExtractor={(f, i) => (f.type === 'comment' ? `c${f.id}` : `a${i}`)}
        ListHeaderComponent={header}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} tintColor={c.primary} />}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{ paddingBottom: spacing.lg }}
        renderItem={({ item }) =>
          item.type === 'comment' ? (
            <CommentView
              item={item}
              meId={user.id}
              timeZone={tz}
              onReply={() => { setEditing(null); setReplyTo(item); }}
              onReact={(emoji) => comments.react.mutate({ id: item.id, emoji }, { onError: fail })}
              onMore={() => setCommentMenu(item)}
            />
          ) : (
            <ActivityRow item={item} timeZone={tz} />
          )
        }
        ListFooterComponent={
          detail.lastSeenBy ? (
            <Text style={{ color: c.subtext, fontSize: 12, textAlign: 'right', paddingHorizontal: spacing.md, paddingTop: spacing.sm }}>
              ✓✓ Seen by {detail.lastSeenBy.name} · {formatShort(detail.lastSeenBy.viewedAt, tz)}
            </Text>
          ) : null
        }
      />

      <Composer
        draft={draft}
        onChange={setDraft}
        onSend={send}
        busy={comments.add.isPending || comments.edit.isPending}
        replyTo={replyTo}
        editing={editing}
        onCancel={() => { setReplyTo(null); setEditing(null); if (editing) setDraft(''); }}
        onMention={() => setMentionSheet(true)}
      />

      <ActionSheet
        visible={sheet === 'status'}
        onClose={() => setSheet(null)}
        title="Status"
        actions={STATUSES.map((s) => ({ label: STATUS_LABEL[s], selected: s === t.status, onPress: () => s !== t.status && setField('status', s) }))}
      />
      <ActionSheet
        visible={sheet === 'priority'}
        onClose={() => setSheet(null)}
        title="Priority"
        actions={PRIORITIES.map((p) => ({ label: p[0].toUpperCase() + p.slice(1), selected: p === t.priority, onPress: () => p !== t.priority && setField('priority', p) }))}
      />
      <PickerSheet
        visible={sheet === 'assignee'}
        onClose={() => setSheet(null)}
        title="Responsible person"
        options={(users.data ?? []).map((u) => ({ id: u.id, label: u.name, subtitle: u.position, avatar: u.avatar }))}
        value={t.assigned_to ? [t.assigned_to] : []}
        onChange={(ids) => setField('assigned_to', ids[0] ?? null)}
        clearLabel="No one"
      />
      <ActionSheet
        visible={sheet === 'menu'}
        onClose={() => setSheet(null)}
        actions={[
          ...(perms.canEdit ? [{ label: 'Edit task', onPress: () => router.push({ pathname: '/task/[id]/edit', params: { id: String(taskId) } }) }] : []),
          ...(perms.canDelete ? [{ label: 'Move to Trash', destructive: true, onPress: confirmDelete }] : []),
        ]}
      />
      <ActionSheet visible={!!commentMenu} onClose={() => setCommentMenu(null)} title="Comment" actions={commentActions} />
      <PickerSheet
        visible={mentionSheet}
        onClose={() => setMentionSheet(false)}
        title="Mention someone"
        options={(users.data ?? []).filter((u) => u.id !== user.id).map((u) => ({ id: u.id, label: u.name, subtitle: u.position, avatar: u.avatar }))}
        value={[]}
        onChange={(ids) => {
          const u = users.data?.find((x) => x.id === ids[0]);
          if (!u) return;
          setDraft((d) => `${d}${d && !d.endsWith(' ') ? ' ' : ''}@${u.name} `);
          setMentions((m) => (m.includes(u.id) ? m : [...m, u.id]));
        }}
      />
    </KeyboardAvoidingView>
  );
}

/** What goes in the edit box: the text, with attachment tags kept so editing a comment never loses its photo. */
const plainEditText = (raw: string) => raw;

// ── Header: everything above the feed ─────────────────────────────────────────────────────────────────────────────

type HeaderProps = {
  detail: TaskDetail;
  user: UserPayload;
  perms: ReturnType<typeof taskPerms>;
  onStatus: () => void;
  onPriority: () => void;
  onAssignee: () => void;
  onDeadline: (naive: string | null) => void;
  saving: boolean;
};

function Header({ detail, user, perms, onStatus, onPriority, onAssignee, onDeadline, saving }: HeaderProps) {
  const c = useColors();
  const t = detail.task;
  const tz = user.time_zone;
  const [more, setMore] = useState(false);
  const long = plainText(t.description).length > 280 || hasAttachmentTags(t.description);
  const check = useChecklist(t.id);
  const [item, setItem] = useState('');

  const card = { backgroundColor: c.card, borderRadius: 14, borderWidth: 1, borderColor: c.border, padding: spacing.md, gap: spacing.sm, marginHorizontal: spacing.md } as const;

  return (
    <View style={{ gap: spacing.md, paddingVertical: spacing.md }}>
      <View style={{ paddingHorizontal: spacing.md, gap: spacing.sm }}>
        <Text style={{ color: c.text, fontSize: 22, fontWeight: '800' }} selectable>
          {t.title}
        </Text>
        <View style={{ flexDirection: 'row', gap: spacing.sm, flexWrap: 'wrap', alignItems: 'center' }}>
          <Pill label={STATUS_LABEL[t.status] ?? t.status} color={c.primary} onPress={perms.canChangeStatus ? onStatus : undefined} />
          <Pill label={t.priority} color={priorityColor(c, t.priority)} onPress={perms.canChangeFields ? onPriority : undefined} upper />
          {saving ? <Text style={{ color: c.subtext, fontSize: 12 }}>Saving…</Text> : null}
        </View>
      </View>

      <View style={card}>
        <DeadlineField value={isoToNaive(t.deadline, tz)} onChange={onDeadline} timeZone={tz} disabled={!perms.canChangeFields} />
        <Row label="Responsible" onPress={perms.canChangeFields ? onAssignee : undefined}>
          {detail.assignee ? <Person name={detail.assignee.name} avatar={detail.assignee.avatar} /> : <Muted>No one</Muted>}
        </Row>
        <Row label="Created by">{detail.creator ? <Person name={detail.creator.name} avatar={detail.creator.avatar} /> : <Muted>—</Muted>}</Row>
        {t.project ? <Row label="Project"><Text style={{ color: c.text, fontSize: 14 }}>{t.project.name}</Text></Row> : null}
        {detail.participants.length > 0 ? <Row label="Participants"><Names list={detail.participants.map((p) => p.name)} /></Row> : null}
        {detail.observers.length > 0 ? <Row label="Observers"><Names list={detail.observers.map((p) => p.name)} /></Row> : null}
        <Row label="Created"><Text style={{ color: c.text, fontSize: 14 }}>{formatShort(t.createdAt, tz)}</Text></Row>
      </View>

      {t.description?.trim() ? (
        <View style={card}>
          <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '700' }}>Description</Text>
          <View style={{ maxHeight: more || !long ? undefined : 150, overflow: 'hidden' }}>
            <RichText text={t.description} />
          </View>
          {long ? (
            <Pressable accessibilityRole="button" onPress={() => setMore(!more)}>
              <Text style={{ color: c.primary, fontWeight: '700' }}>{more ? 'Show less' : 'Show more'}</Text>
            </Pressable>
          ) : null}
        </View>
      ) : null}

      {detail.checklists.length > 0 || perms.canChecklist ? (
        <View style={card}>
          <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '700' }}>
            Checklist{detail.checklists.length ? ` · ${detail.checklists.filter((i) => i.is_complete).length}/${detail.checklists.length}` : ''}
          </Text>
          {detail.checklists.map((i) => (
            <Pressable
              key={i.id}
              accessibilityRole="checkbox"
              accessibilityState={{ checked: i.is_complete, disabled: !perms.canChecklist }}
              disabled={!perms.canChecklist}
              onPress={() => check.toggle.mutate(i.id, { onError: (e) => Alert.alert('Could not save', errorMessage(e)) })}
              onLongPress={() =>
                perms.canChecklist &&
                Alert.alert('Remove this item?', i.text, [
                  { text: 'Cancel', style: 'cancel' },
                  { text: 'Remove', style: 'destructive', onPress: () => check.remove.mutate(i.id) },
                ])
              }
              style={{ flexDirection: 'row', gap: spacing.sm, alignItems: 'center', paddingVertical: 4 }}>
              <Ionicons name={i.is_complete ? 'checkbox' : 'square-outline'} size={22} color={i.is_complete ? c.primary : c.subtext} />
              <Text style={{ flex: 1, color: c.text, fontSize: 15, textDecorationLine: i.is_complete ? 'line-through' : 'none', opacity: i.is_complete ? 0.6 : 1 }}>{i.text}</Text>
            </Pressable>
          ))}
          {perms.canChecklist ? (
            <View style={{ flexDirection: 'row', gap: spacing.sm, alignItems: 'center' }}>
              <TextInput
                value={item}
                onChangeText={setItem}
                placeholder="Add an item"
                placeholderTextColor={c.subtext}
                returnKeyType="done"
                onSubmitEditing={() => addItem()}
                accessibilityLabel="Add a checklist item"
                style={{ flex: 1, minHeight: 40, borderRadius: 10, borderWidth: 1, borderColor: c.border, paddingHorizontal: 12, color: c.text, backgroundColor: c.inputBg }}
              />
              <Pressable accessibilityRole="button" accessibilityLabel="Add item" onPress={addItem} disabled={!item.trim()} hitSlop={8}>
                <Ionicons name="add-circle" size={30} color={item.trim() ? c.primary : c.border} />
              </Pressable>
            </View>
          ) : null}
        </View>
      ) : null}

      {detail.localFiles.length > 0 ? (
        <View style={card}>
          <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '700' }}>Files · {detail.localFiles.length}</Text>
          {detail.localFiles.map((f) => (
            <Pressable
              key={f.id}
              accessibilityRole="button"
              accessibilityLabel={`Open ${f.name}`}
              onPress={() => openFile(f.downloadUrl, f.name).catch((e) => Alert.alert('Could not open the file', e instanceof Error ? e.message : 'Try again.'))}
              style={{ flexDirection: 'row', gap: spacing.sm, alignItems: 'center', paddingVertical: 4 }}>
              <Text style={{ fontSize: 18 }}>📎</Text>
              <Text style={{ flex: 1, color: c.primary, fontSize: 14, fontWeight: '600' }} numberOfLines={1}>
                {f.name}
              </Text>
              {f.size ? <Text style={{ color: c.subtext, fontSize: 12 }}>{formatSize(f.size)}</Text> : null}
            </Pressable>
          ))}
        </View>
      ) : null}

      <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '700', paddingHorizontal: spacing.md }}>Comments & history</Text>
    </View>
  );

  function addItem() {
    const text = item.trim();
    if (!text) return;
    check.add.mutate(text, {
      onSuccess: () => setItem(''),
      onError: (e) => Alert.alert('Could not add the item', errorMessage(e)),
    });
  }
}

const formatSize = (n: number) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

function Pill({ label, color, onPress, upper }: { label: string; color: string; onPress?: () => void; upper?: boolean }) {
  const inner = (
    <View style={{ borderWidth: 1, borderColor: color, borderRadius: 14, paddingVertical: 4, paddingHorizontal: 12, flexDirection: 'row', gap: 4, alignItems: 'center' }}>
      <Text style={{ color, fontWeight: '800', fontSize: 12, textTransform: upper ? 'uppercase' : 'none' }}>{label}</Text>
      {onPress ? <Text style={{ color, fontSize: 10 }}>▼</Text> : null}
    </View>
  );
  return onPress ? (
    <Pressable accessibilityRole="button" accessibilityLabel={`Change: ${label}`} onPress={onPress}>
      {inner}
    </Pressable>
  ) : (
    inner
  );
}

function Row({ label, children, onPress }: { label: string; children: React.ReactNode; onPress?: () => void }) {
  const c = useColors();
  const body = (
    <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: spacing.md, minHeight: 32 }}>
      <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '600' }}>{label}</Text>
      <View style={{ flexShrink: 1, flexDirection: 'row', alignItems: 'center', gap: 4 }}>
        {children}
        {onPress ? <Text style={{ color: c.subtext, fontSize: 10 }}>▼</Text> : null}
      </View>
    </View>
  );
  return onPress ? (
    <Pressable accessibilityRole="button" accessibilityLabel={`Change ${label}`} onPress={onPress}>
      {body}
    </Pressable>
  ) : (
    body
  );
}

const Muted = ({ children }: { children: React.ReactNode }) => {
  const c = useColors();
  return <Text style={{ color: c.subtext, fontSize: 14 }}>{children}</Text>;
};

function Person({ name, avatar }: { name: string; avatar: string }) {
  const c = useColors();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flexShrink: 1 }}>
      <Avatar uri={avatar} name={name} size={24} />
      <Text style={{ color: c.text, fontSize: 14, flexShrink: 1 }} numberOfLines={1}>
        {name}
      </Text>
    </View>
  );
}

function Names({ list }: { list: string[] }) {
  const c = useColors();
  return (
    <Text style={{ color: c.text, fontSize: 14, textAlign: 'right', flexShrink: 1 }} numberOfLines={2}>
      {list.join(', ')}
    </Text>
  );
}

// ── Composer ──────────────────────────────────────────────────────────────────────────────────────────────────────

type ComposerProps = {
  draft: string;
  onChange: (t: string) => void;
  onSend: () => void;
  busy: boolean;
  replyTo: CommentItem | null;
  editing: CommentItem | null;
  onCancel: () => void;
  onMention: () => void;
};

function Composer({ draft, onChange, onSend, busy, replyTo, editing, onCancel, onMention }: ComposerProps) {
  const c = useColors();
  const banner = editing ? 'Editing your comment' : replyTo ? `Replying to ${replyTo.author.name}` : null;
  return (
    <View style={{ backgroundColor: c.card, borderTopWidth: 1, borderTopColor: c.border, padding: spacing.sm, gap: 6 }}>
      {banner ? (
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingHorizontal: 4 }}>
          <Text style={{ color: c.primary, fontSize: 12, fontWeight: '700' }} numberOfLines={1}>
            {banner}
          </Text>
          <Pressable accessibilityRole="button" accessibilityLabel="Cancel" onPress={onCancel} hitSlop={8}>
            <Text style={{ color: c.subtext, fontSize: 12 }}>✕ Cancel</Text>
          </Pressable>
        </View>
      ) : null}
      <View style={{ flexDirection: 'row', alignItems: 'flex-end', gap: spacing.sm }}>
        {!editing ? (
          <Pressable accessibilityRole="button" accessibilityLabel="Mention someone" onPress={onMention} hitSlop={6} style={{ paddingBottom: 10 }}>
            <Text style={{ color: c.primary, fontSize: 22, fontWeight: '800' }}>@</Text>
          </Pressable>
        ) : null}
        <TextInput
          value={draft}
          onChangeText={onChange}
          placeholder="Write a comment"
          placeholderTextColor={c.subtext}
          multiline
          accessibilityLabel="Write a comment"
          style={{ flex: 1, minHeight: 42, maxHeight: 120, borderRadius: 20, borderWidth: 1, borderColor: c.border, paddingHorizontal: 14, paddingTop: 10, paddingBottom: 10, color: c.text, backgroundColor: c.inputBg, fontSize: 15 }}
        />
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={editing ? 'Save comment' : 'Send comment'}
          accessibilityState={{ disabled: !draft.trim() || busy }}
          onPress={onSend}
          disabled={!draft.trim() || busy}
          style={{ width: 42, height: 42, borderRadius: 21, backgroundColor: draft.trim() && !busy ? c.primary : c.border, alignItems: 'center', justifyContent: 'center' }}>
          <Ionicons name={editing ? 'checkmark' : 'send'} size={20} color={c.onPrimary} />
        </Pressable>
      </View>
    </View>
  );
}
