import { FlatList, Image, RefreshControl, Text, View } from 'react-native';

import { useNotifications } from '@/api/queries';
import { Loading, Message, Screen } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { timeAgo } from '@/lib/format';
import { spacing, useColors } from '@/theme';

export default function Notifications() {
  const c = useColors();
  const q = useNotifications();
  const items = q.data?.notifications ?? [];

  return (
    <Screen>
      <Text style={{ color: c.text, fontSize: 26, fontWeight: '800', padding: spacing.md, paddingBottom: 0 }}>Alerts</Text>
      {q.isPending ? (
        <Loading />
      ) : q.isError && items.length === 0 ? (
        <Message title="Could not load alerts" detail={errorMessage(q.error)} actionTitle="Try again" onAction={() => q.refetch()} />
      ) : (
        <FlatList
          data={items}
          keyExtractor={(n) => String(n.id)}
          contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, flexGrow: 1 }}
          refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => q.refetch()} tintColor={c.primary} />}
          ListEmptyComponent={<Message title="No alerts yet" />}
          renderItem={({ item }) => (
            <View
              style={{
                flexDirection: 'row',
                gap: spacing.sm,
                backgroundColor: c.card,
                borderRadius: 14,
                padding: spacing.md,
                borderWidth: 1,
                borderColor: item.read_at ? c.border : c.primary,
              }}>
              {item.actor ? <Image source={{ uri: item.actor.avatar }} style={{ width: 36, height: 36, borderRadius: 18 }} /> : null}
              <View style={{ flex: 1, gap: 2 }}>
                <Text style={{ color: c.text, fontSize: 14, fontWeight: item.read_at ? '400' : '700' }}>{item.message}</Text>
                <Text style={{ color: c.subtext, fontSize: 12 }}>{timeAgo(item.created_at)}</Text>
              </View>
            </View>
          )}
        />
      )}
    </Screen>
  );
}
