import { useMemo, useState } from 'react';
import { Alert, FlatList, KeyboardAvoidingView, Platform, Pressable, Text, View } from 'react-native';
import { router, Stack, useLocalSearchParams } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import { Ionicons } from '@expo/vector-icons';

import { ApiError, type LocalFile } from '@/api/client';
import { attachmentTag, discardLocal, loadOlder, uploadAttachment, useChat, useMessageActions, useSendMessage, useStartChats, type ChatMessage } from '@/api/chat';
import { useUsers } from '@/api/tasks';
import { useSession } from '@/auth/session';
import { Avatar } from '@/components/Avatar';
import { Bubble } from '@/components/chat/Bubble';
import { MessageComposer } from '@/components/MessageComposer';
import { ActionSheet, PickerSheet } from '@/components/Sheet';
import { Loading, Message, OfflineBanner } from '@/components/ui';
import { buildRows, type ChatRow } from '@/lib/chatRows';
import { errorMessage } from '@/lib/errors';
import { isImage } from '@/lib/media';
import { goBack } from '@/lib/nav';
import { useRealtimeConnected } from '@/realtime/RealtimeProvider';
import { spacing, useColors } from '@/theme';

const EMOJIS = ['👍', '❤️', '😂', '😮', '🙏', '✅'];

export default function ChatScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const convId = Number(id);
  const { user } = useSession();
  const live = useRealtimeConnected();
  const q = useChat(convId, live ? 30_000 : 6_000);

  if (!user) return null;
  if (q.isPending) return <Loading />;
  if (q.isError && !q.data) {
    const gone = q.error instanceof ApiError && (q.error.status === 403 || q.error.status === 404);
    return (
      <Message
        title={gone ? 'This chat is not available' : 'Could not load the chat'}
        detail={gone ? 'You may have left it.' : errorMessage(q.error)}
        actionTitle={gone ? 'Go back' : 'Try again'}
        onAction={() => (gone ? goBack() : q.refetch())}
      />
    );
  }
  return <Chat convId={convId} user={user} data={q.data!} />;
}

type ChatProps = { convId: number; user: NonNullable<ReturnType<typeof useSession>['user']>; data: NonNullable<ReturnType<typeof useChat>['data']> };

function Chat({ convId, user, data }: ChatProps) {
  const c = useColors();
  const qc = useQueryClient();
  const tz = user.time_zone;
  const { conv } = data;
  const direct = conv.type === 'direct';
  const me = useMemo(() => ({ id: user.id, name: user.name, avatar: user.avatar }), [user.id, user.name, user.avatar]);
  const send = useSendMessage(convId, me);
  const actions = useMessageActions(convId);
  const { leave } = useStartChats();
  const users = useUsers();

  const [draft, setDraft] = useState('');
  const [replyTo, setReplyTo] = useState<ChatMessage | null>(null);
  const [editing, setEditing] = useState<ChatMessage | null>(null);
  const [mentions, setMentions] = useState<number[]>([]);
  const [mentionSheet, setMentionSheet] = useState(false);
  const [menuMsg, setMenuMsg] = useState<ChatMessage | null>(null);
  const [retryMsg, setRetryMsg] = useState<ChatMessage | null>(null);
  const [menu, setMenu] = useState(false);
  const [uploading, setUploading] = useState(0);
  const [loadingOlder, setLoadingOlder] = useState(false);

  const rows = useMemo(() => buildRows(data.messages, user.id, direct, tz), [data.messages, user.id, direct, tz]);
  const fail = (e: unknown) => Alert.alert('Could not do that', errorMessage(e));

  const preview = (m: ChatMessage) => ({ author: m.author.name, text: m.text.replace(/\[[^\]]*\]/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 100) });

  function sendText() {
    const text = draft.trim();
    if (!text) return;
    if (editing) {
      actions.edit.mutate({ id: editing.id, content: text }, { onSuccess: () => { setEditing(null); setDraft(''); }, onError: fail });
      return;
    }
    const names = new Map((users.data ?? []).map((u) => [u.id, u.name]));
    const kept = mentions.filter((m) => names.has(m) && text.includes(`@${names.get(m)}`));
    const parent = replyTo;
    setDraft('');
    setReplyTo(null);
    setMentions([]);
    send({ content: text, parent_id: parent?.id ?? null, mentions: kept, parentPreview: parent ? preview(parent) : null }).catch(() => {
      /* the bubble shows "Not sent. Tap to retry." */
    });
  }

  /** Photos go out together in one message; every other file or voice message gets its own, like the website. */
  async function sendFiles(files: LocalFile[]) {
    const parent = replyTo;
    setReplyTo(null);
    setUploading((n) => n + files.length);
    try {
      const images = files.filter(isImage);
      const others = files.filter((f) => !isImage(f));
      const tags: string[] = [];
      for (const f of images) {
        try {
          tags.push(attachmentTag('image', await uploadAttachment(f)));
        } finally {
          setUploading((n) => n - 1);
        }
      }
      if (tags.length) await send({ content: tags.join('\n'), parent_id: parent?.id ?? null, parentPreview: parent ? preview(parent) : null });
      for (const f of others) {
        try {
          const up = await uploadAttachment(f);
          await send({ content: attachmentTag('file', up) });
        } finally {
          setUploading((n) => n - 1);
        }
      }
    } catch (e) {
      Alert.alert('Could not send the file', errorMessage(e));
      setUploading(0);
    }
  }

  async function sendVoice(file: LocalFile, duration: string) {
    setUploading((n) => n + 1);
    try {
      const up = await uploadAttachment(file);
      await send({ content: attachmentTag('voice', up, duration) });
    } catch (e) {
      Alert.alert('Could not send the voice message', errorMessage(e));
    } finally {
      setUploading((n) => n - 1);
    }
  }

  async function olderPage() {
    if (loadingOlder || !data.hasMore) return;
    setLoadingOlder(true);
    try {
      await loadOlder(convId, qc);
    } finally {
      setLoadingOlder(false);
    }
  }

  const mine = menuMsg?.isMine && !menuMsg.local;
  const canEdit = !!menuMsg && mine && Date.now() / 1000 - menuMsg.createdTs < 24 * 3600;
  const subtitle = direct ? (conv.online ? 'online' : conv.last_seen ? `last seen ${conv.last_seen}` : '') : conv.type === 'notes' ? 'Only you' : `${conv.members} members`;

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined} keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}>
      <Stack.Screen
        options={{
          headerTitle: () => (
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: spacing.sm }}>
              {conv.avatar ? <Avatar uri={conv.avatar} name={conv.name} size={32} /> : <GroupIcon />}
              <View>
                <Text style={{ color: c.text, fontSize: 16, fontWeight: '800' }} numberOfLines={1}>
                  {conv.name}
                </Text>
                {subtitle ? (
                  <Text style={{ color: direct && conv.online ? c.success : c.subtext, fontSize: 12 }} numberOfLines={1}>
                    {subtitle}
                  </Text>
                ) : null}
              </View>
            </View>
          ),
          headerRight: () => (
            <View style={{ flexDirection: 'row', gap: spacing.md }}>
              <Pressable accessibilityRole="button" accessibilityLabel="Search in this chat" onPress={() => router.push({ pathname: '/chat/[id]/search', params: { id: String(convId) } })} hitSlop={8}>
                <Ionicons name="search" size={22} color={c.primary} />
              </Pressable>
              {conv.type === 'group' ? (
                <Pressable accessibilityRole="button" accessibilityLabel="Chat options" onPress={() => setMenu(true)} hitSlop={8}>
                  <Ionicons name="ellipsis-horizontal-circle" size={24} color={c.primary} />
                </Pressable>
              ) : null}
            </View>
          ),
        }}
      />
      <OfflineBanner />

      <FlatList<ChatRow>
        inverted
        data={rows}
        keyExtractor={(r) => r.key}
        keyboardShouldPersistTaps="handled"
        onEndReachedThreshold={0.5}
        onEndReached={olderPage}
        contentContainerStyle={{ paddingVertical: spacing.sm }}
        ListFooterComponent={loadingOlder ? <Loading /> : null}
        ListEmptyComponent={
          <View style={{ transform: [{ scaleY: -1 }] }}>
            <Message title="No messages yet" detail="Say salam 👋" />
          </View>
        }
        renderItem={({ item }) =>
          item.type === 'day' ? (
            <View style={{ alignItems: 'center', paddingVertical: spacing.sm }}>
              <Text style={{ color: c.subtext, fontSize: 12, fontWeight: '700', backgroundColor: c.card, paddingHorizontal: 12, paddingVertical: 4, borderRadius: 10, overflow: 'hidden' }}>{item.label}</Text>
            </View>
          ) : (
            <Bubble
              msg={item.msg}
              meId={user.id}
              showAuthor={item.showAuthor}
              direct={direct}
              otherLastReadTs={data.otherLastReadTs}
              otherLastSeenTs={data.otherLastSeenTs}
              timeZone={tz}
              onLongPress={setMenuMsg}
              onReact={(m, emoji) => actions.react.mutate({ id: m.id, emoji }, { onError: fail })}
              onRetry={setRetryMsg}
            />
          )
        }
      />

      {uploading > 0 ? (
        <Text style={{ color: c.subtext, fontSize: 12, paddingHorizontal: spacing.md, paddingVertical: 4, backgroundColor: c.bg }}>Sending {uploading} {uploading === 1 ? 'file' : 'files'}…</Text>
      ) : null}

      <MessageComposer
        draft={draft}
        onChange={setDraft}
        onSend={sendText}
        busy={actions.edit.isPending}
        placeholder="Message"
        editing={!!editing}
        banner={editing ? 'Editing your message' : replyTo ? `Replying to ${replyTo.author.name}` : null}
        onCancelBanner={() => { setReplyTo(null); if (editing) { setEditing(null); setDraft(''); } }}
        onMention={!direct && conv.type !== 'notes' ? () => setMentionSheet(true) : undefined}
        onAttach={sendFiles}
        onVoice={sendVoice}
      />

      <ActionSheet
        visible={!!menuMsg}
        onClose={() => setMenuMsg(null)}
        title="Message"
        actions={
          menuMsg
            ? [
                { label: 'Reply', onPress: () => { setEditing(null); setReplyTo(menuMsg); } },
                ...EMOJIS.map((e) => ({ label: `React ${e}`, onPress: () => actions.react.mutate({ id: menuMsg.id, emoji: e }, { onError: fail }) })),
                ...(canEdit ? [{ label: 'Edit', onPress: () => { setReplyTo(null); setEditing(menuMsg); setDraft(menuMsg.text); } }] : []),
                { label: 'Delete for me', destructive: true, onPress: () => actions.remove.mutate({ id: menuMsg.id, scope: 'me' }, { onError: fail }) },
                ...(mine ? [{ label: 'Delete for everyone', destructive: true, onPress: () => Alert.alert('Delete for everyone?', 'Everyone in this chat will no longer see it.', [{ text: 'Cancel', style: 'cancel' as const }, { text: 'Delete', style: 'destructive' as const, onPress: () => actions.remove.mutate({ id: menuMsg.id, scope: 'everyone' }, { onError: fail }) }]) }] : []),
              ]
            : []
        }
      />
      <ActionSheet
        visible={!!retryMsg}
        onClose={() => setRetryMsg(null)}
        title="This message was not sent"
        actions={
          retryMsg
            ? [
                { label: 'Try again', onPress: () => send({ content: retryMsg.text, parent_id: retryMsg.parentId, retryOf: retryMsg.id, parentPreview: retryMsg.parentPreview }).catch(() => {}) },
                { label: 'Discard', destructive: true, onPress: () => discardLocal(qc, convId, retryMsg.id) },
              ]
            : []
        }
      />
      <ActionSheet
        visible={menu}
        onClose={() => setMenu(false)}
        actions={[
          {
            label: 'Leave this group',
            destructive: true,
            onPress: () =>
              Alert.alert('Leave this group?', 'You will stop receiving its messages.', [
                { text: 'Cancel', style: 'cancel' },
                { text: 'Leave', style: 'destructive', onPress: () => leave.mutate(convId, { onSuccess: goBack, onError: fail }) },
              ]),
          },
        ]}
      />
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

function GroupIcon() {
  const c = useColors();
  return (
    <View style={{ width: 32, height: 32, borderRadius: 16, backgroundColor: c.primary, alignItems: 'center', justifyContent: 'center' }}>
      <Ionicons name="people" size={18} color={c.onPrimary} />
    </View>
  );
}
