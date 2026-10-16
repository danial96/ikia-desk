import { useState } from 'react';
import { FlatList, Text, TextInput, View } from 'react-native';
import { useLocalSearchParams } from 'expo-router';

import { useSearch } from '@/api/chat';
import { Avatar } from '@/components/Avatar';
import { Loading, Message } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { useDebounced } from '@/lib/hooks';
import { spacing, useColors } from '@/theme';

/** Find a word in one chat. Newest matches first. */
export default function ChatSearch() {
  const c = useColors();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [text, setText] = useState('');
  const q = useDebounced(text.trim(), 400);
  const res = useSearch(Number(id), q);

  return (
    <View style={{ flex: 1 }}>
      <View style={{ padding: spacing.md }}>
        <TextInput
          value={text}
          onChangeText={setText}
          placeholder="Search this chat"
          placeholderTextColor={c.subtext}
          autoFocus
          autoCapitalize="none"
          accessibilityLabel="Search this chat"
          style={{ minHeight: 44, borderRadius: 12, borderWidth: 1, borderColor: c.border, paddingHorizontal: 14, color: c.text, backgroundColor: c.inputBg, fontSize: 15 }}
        />
      </View>
      {q.length < 2 ? (
        <Message title="Type at least 2 letters" />
      ) : res.isFetching && !res.data ? (
        <Loading />
      ) : res.isError ? (
        <Message title="Search failed" detail={errorMessage(res.error)} actionTitle="Try again" onAction={() => res.refetch()} />
      ) : (
        <FlatList
          data={res.data?.results ?? []}
          keyExtractor={(r) => String(r.id)}
          contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, flexGrow: 1 }}
          ListEmptyComponent={<Message title="Nothing found" />}
          renderItem={({ item }) => (
            <View style={{ flexDirection: 'row', gap: spacing.sm, backgroundColor: c.card, borderRadius: 12, padding: spacing.md, borderWidth: 1, borderColor: c.border }}>
              <Avatar uri={item.avatar} name={item.author} size={32} />
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={{ color: c.text, fontWeight: '800', fontSize: 14 }}>
                  {item.isMine ? 'You' : item.author} <Text style={{ color: c.subtext, fontWeight: '400', fontSize: 12 }}>· {item.date}, {item.time}</Text>
                </Text>
                <Text style={{ color: c.text, fontSize: 14 }}>{item.text}</Text>
              </View>
            </View>
          )}
        />
      )}
    </View>
  );
}
