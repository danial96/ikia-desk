import { useMemo, useState } from 'react';
import { Alert, FlatList, Pressable, Text, TextInput, View } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import { useStartChats } from '@/api/chat';
import { useUsers } from '@/api/tasks';
import { useSession } from '@/auth/session';
import { Avatar } from '@/components/Avatar';
import { Button, Loading, Message } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { spacing, useColors } from '@/theme';

/** Pick a person to message, or make a group. */
export default function NewChat() {
  const c = useColors();
  const { user } = useSession();
  const users = useUsers();
  const { direct, group } = useStartChats();
  const [mode, setMode] = useState<'direct' | 'group'>('direct');
  const [q, setQ] = useState('');
  const [picked, setPicked] = useState<number[]>([]);
  const [name, setName] = useState('');

  const list = useMemo(() => {
    const t = q.trim().toLowerCase();
    return (users.data ?? []).filter((u) => u.id !== user?.id && (!t || u.name.toLowerCase().includes(t) || (u.position ?? '').toLowerCase().includes(t)));
  }, [users.data, q, user?.id]);

  const open = (convId: number) => router.replace({ pathname: '/chat/[id]', params: { id: String(convId) } });
  const fail = (e: unknown) => Alert.alert('Could not start the chat', errorMessage(e));

  function tap(id: number) {
    if (mode === 'direct') direct.mutate(id, { onSuccess: (r) => open(r.conv_id), onError: fail });
    else setPicked((p) => (p.includes(id) ? p.filter((x) => x !== id) : [...p, id]));
  }

  function createGroup() {
    if (!name.trim()) return Alert.alert('Name the group', 'Give the group a name first.');
    if (picked.length === 0) return Alert.alert('Add people', 'Pick at least one person.');
    group.mutate({ name: name.trim(), members: picked }, { onSuccess: (r) => open(r.conv_id), onError: fail });
  }

  return (
    <View style={{ flex: 1 }}>
      <View style={{ flexDirection: 'row', gap: spacing.sm, padding: spacing.md, paddingBottom: 0 }}>
        {(['direct', 'group'] as const).map((m) => (
          <Pressable key={m} accessibilityRole="button" accessibilityState={{ selected: mode === m }} onPress={() => setMode(m)} style={{ paddingVertical: 8, paddingHorizontal: 16, borderRadius: 20, borderWidth: 1, backgroundColor: mode === m ? c.primary : c.card, borderColor: mode === m ? c.primary : c.border }}>
            <Text style={{ color: mode === m ? c.onPrimary : c.text, fontWeight: '700' }}>{m === 'direct' ? 'Message someone' : 'New group'}</Text>
          </Pressable>
        ))}
      </View>
      <View style={{ padding: spacing.md, gap: spacing.sm }}>
        {mode === 'group' ? (
          <TextInput value={name} onChangeText={setName} placeholder="Group name" placeholderTextColor={c.subtext} maxLength={100} accessibilityLabel="Group name" style={{ minHeight: 44, borderRadius: 12, borderWidth: 1, borderColor: c.border, paddingHorizontal: 14, color: c.text, backgroundColor: c.inputBg, fontSize: 15 }} />
        ) : null}
        <TextInput value={q} onChangeText={setQ} placeholder="Search people" placeholderTextColor={c.subtext} autoCapitalize="none" accessibilityLabel="Search people" style={{ minHeight: 44, borderRadius: 12, borderWidth: 1, borderColor: c.border, paddingHorizontal: 14, color: c.text, backgroundColor: c.inputBg, fontSize: 15 }} />
      </View>

      {users.isPending ? (
        <Loading />
      ) : users.isError ? (
        <Message title="Could not load people" detail={errorMessage(users.error)} actionTitle="Try again" onAction={() => users.refetch()} />
      ) : (
        <FlatList
          data={list}
          keyExtractor={(u) => String(u.id)}
          keyboardShouldPersistTaps="handled"
          ListEmptyComponent={<Message title="No one found" />}
          renderItem={({ item }) => {
            const on = picked.includes(item.id);
            return (
              <Pressable accessibilityRole="button" accessibilityState={{ selected: on }} onPress={() => tap(item.id)} style={({ pressed }) => ({ flexDirection: 'row', alignItems: 'center', gap: spacing.md, paddingVertical: 10, paddingHorizontal: spacing.md, backgroundColor: pressed ? c.card : 'transparent' })}>
                <Avatar uri={item.avatar} name={item.name} size={40} />
                <View style={{ flex: 1 }}>
                  <Text style={{ color: c.text, fontSize: 16, fontWeight: '600' }}>{item.name}</Text>
                  {item.position ? <Text style={{ color: c.subtext, fontSize: 12 }}>{item.position}</Text> : null}
                </View>
                {mode === 'group' ? <Ionicons name={on ? 'checkbox' : 'square-outline'} size={24} color={on ? c.primary : c.subtext} /> : null}
              </Pressable>
            );
          }}
        />
      )}

      {mode === 'group' ? (
        <View style={{ padding: spacing.md }}>
          <Button title={`Create group${picked.length ? ` (${picked.length})` : ''}`} onPress={createGroup} loading={group.isPending} />
        </View>
      ) : null}
    </View>
  );
}
