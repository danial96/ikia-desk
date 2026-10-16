import { useMemo, useState } from 'react';
import { FlatList, Pressable, RefreshControl, Text, TextInput, View } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import { lastMessagePreview, useConversations, type Conversation } from '@/api/chat';
import { Avatar } from '@/components/Avatar';
import { Loading, Message, Screen } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { spacing, useColors } from '@/theme';

export const openChat = (id: number) => router.push({ pathname: '/chat/[id]', params: { id: String(id) } });

export default function Messenger() {
  const c = useColors();
  const q = useConversations();
  const [search, setSearch] = useState('');
  const list = useMemo(() => {
    const t = search.trim().toLowerCase();
    const all = q.data ?? [];
    return t ? all.filter((x) => x.name.toLowerCase().includes(t)) : all;
  }, [q.data, search]);

  return (
    <Screen>
      <View style={{ paddingHorizontal: spacing.md, paddingTop: spacing.md, gap: spacing.sm }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: spacing.md }}>
          <Text style={{ flex: 1, color: c.text, fontSize: 26, fontWeight: '800' }}>Messenger</Text>
          <Pressable accessibilityRole="button" accessibilityLabel="New chat" onPress={() => router.push('/chat/new')} hitSlop={8}>
            <Ionicons name="create-outline" size={28} color={c.primary} />
          </Pressable>
        </View>
        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder="Search chats"
          placeholderTextColor={c.subtext}
          autoCapitalize="none"
          accessibilityLabel="Search chats"
          style={{ minHeight: 44, borderRadius: 12, borderWidth: 1, borderColor: c.border, paddingHorizontal: 14, color: c.text, backgroundColor: c.inputBg, fontSize: 15 }}
        />
      </View>

      {q.isPending ? (
        <Loading />
      ) : q.isError && !q.data ? (
        <Message title="Could not load chats" detail={errorMessage(q.error)} actionTitle="Try again" onAction={() => q.refetch()} />
      ) : (
        <FlatList
          data={list}
          keyExtractor={(x) => String(x.id)}
          renderItem={({ item }) => <ConvRow conv={item} />}
          contentContainerStyle={{ paddingVertical: spacing.sm, flexGrow: 1 }}
          refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => q.refetch()} tintColor={c.primary} />}
          ListEmptyComponent={<Message title={search ? 'No chat with that name' : 'No chats yet'} detail={search ? undefined : 'Tap the pencil to start one.'} />}
        />
      )}
    </Screen>
  );
}

function ConvRow({ conv }: { conv: Conversation }) {
  const c = useColors();
  const last = conv.lastMsg;
  const body = last ? `${last.byMe ? 'You: ' : conv.type !== 'direct' && last.senderName ? `${last.senderName.split(' ')[0]}: ` : ''}${lastMessagePreview(last.text)}` : 'No messages yet';

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${conv.name}${conv.unread ? `, ${conv.unread} unread` : ''}`}
      onPress={() => openChat(conv.id)}
      style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: spacing.md, paddingVertical: 10, paddingHorizontal: spacing.md, backgroundColor: pressed ? c.card : 'transparent' })}>
      <View>
        {conv.avatar ? (
          <Avatar uri={conv.avatar} name={conv.name} size={48} />
        ) : (
          <View style={{ width: 48, height: 48, borderRadius: 24, backgroundColor: c.primary, alignItems: 'center', justifyContent: 'center' }}>
            <Ionicons name={conv.type === 'notes' ? 'bookmark' : 'people'} size={22} color={c.onPrimary} />
          </View>
        )}
        {conv.online ? <View style={{ position: 'absolute', right: 0, bottom: 0, width: 13, height: 13, borderRadius: 7, backgroundColor: c.success, borderWidth: 2, borderColor: c.bg }} /> : null}
      </View>
      <View style={{ flex: 1, gap: 2 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: spacing.sm }}>
          <Text style={{ flex: 1, color: c.text, fontSize: 16, fontWeight: conv.unread ? '800' : '600' }} numberOfLines={1}>
            {conv.name}
          </Text>
          {last ? <Text style={{ color: conv.unread ? c.primary : c.subtext, fontSize: 12 }}>{last.time}</Text> : null}
        </View>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: spacing.sm }}>
          <Text style={{ flex: 1, color: conv.unread ? c.text : c.subtext, fontSize: 14 }} numberOfLines={1}>
            {body}
          </Text>
          {conv.unread > 0 ? (
            <View style={{ minWidth: 22, height: 22, borderRadius: 11, backgroundColor: c.primary, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 6 }}>
              <Text style={{ color: c.onPrimary, fontSize: 12, fontWeight: '800' }}>{conv.unread > 99 ? '99+' : conv.unread}</Text>
            </View>
          ) : null}
        </View>
      </View>
    </Pressable>
  );
}
