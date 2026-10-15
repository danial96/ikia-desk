import { Alert, FlatList, Pressable, RefreshControl, Text, View } from 'react-native';

import { useForceDeleteTask, useRestoreTask, useTrash } from '@/api/tasks';
import type { TrashedTask } from '@/api/types';
import { Loading, Message } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { timeAgo } from '@/lib/format';
import { spacing, useColors } from '@/theme';

export default function Trash() {
  const c = useColors();
  const q = useTrash();
  const restore = useRestoreTask();
  const purge = useForceDeleteTask();
  const items = q.data?.data ?? [];
  const fail = (e: unknown) => Alert.alert('Could not do that', errorMessage(e));

  if (q.isPending) return <Loading />;
  if (q.isError && items.length === 0) return <Message title="Could not load the trash" detail={errorMessage(q.error)} actionTitle="Try again" onAction={() => q.refetch()} />;

  function confirmPurge(t: TrashedTask) {
    Alert.alert('Delete forever?', `“${t.title}” and its comments and files will be gone for good.`, [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Delete forever', style: 'destructive', onPress: () => purge.mutate(t.id, { onError: fail }) },
    ]);
  }

  return (
    <FlatList
      data={items}
      keyExtractor={(t) => String(t.id)}
      contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, flexGrow: 1 }}
      refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => q.refetch()} tintColor={c.primary} />}
      ListEmptyComponent={<Message title="Trash is empty" detail="Deleted tasks show up here." />}
      renderItem={({ item }) => (
        <View style={{ backgroundColor: c.card, borderRadius: 14, padding: spacing.md, gap: 6, borderWidth: 1, borderColor: c.border }}>
          <Text style={{ color: c.text, fontSize: 16, fontWeight: '700' }} numberOfLines={2}>
            {item.title}
          </Text>
          <Text style={{ color: c.subtext, fontSize: 12 }}>
            Deleted {item.deleted_at ? timeAgo(item.deleted_at) : ''}
            {item.deleted_by ? ` by ${item.deleted_by}` : ''}
          </Text>
          <View style={{ flexDirection: 'row', gap: spacing.lg, marginTop: 4 }}>
            <Pressable accessibilityRole="button" accessibilityLabel={`Restore ${item.title}`} onPress={() => restore.mutate(item.id, { onError: fail })} hitSlop={8}>
              <Text style={{ color: c.primary, fontWeight: '800' }}>Restore</Text>
            </Pressable>
            <Pressable accessibilityRole="button" accessibilityLabel={`Delete ${item.title} forever`} onPress={() => confirmPurge(item)} hitSlop={8}>
              <Text style={{ color: c.danger, fontWeight: '800' }}>Delete forever</Text>
            </Pressable>
          </View>
        </View>
      )}
    />
  );
}
