import { useMemo, useState } from 'react';
import { FlatList, Pressable, RefreshControl, Text, View } from 'react-native';

import { useTasks } from '@/api/queries';
import type { TaskCard } from '@/api/types';
import { Loading, Message, Screen } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { formatDeadline, STATUS_LABEL } from '@/lib/format';
import { priorityColor, spacing, useColors, type Colors } from '@/theme';

const TABS = [
  { key: 'in_progress', label: 'Active' },
  { key: 'paused', label: 'Paused' },
  { key: 'completed', label: 'Done' },
] as const;

export default function Tasks() {
  const c = useColors();
  const [status, setStatus] = useState<(typeof TABS)[number]['key']>('in_progress');
  const q = useTasks({ status });
  const items = useMemo(() => q.data?.pages.flatMap((p) => p.data) ?? [], [q.data]);

  return (
    <Screen>
      <View style={{ paddingHorizontal: spacing.md, paddingTop: spacing.md, gap: spacing.sm }}>
        <Text style={{ color: c.text, fontSize: 26, fontWeight: '800' }}>Tasks</Text>
        <View style={{ flexDirection: 'row', gap: spacing.sm }}>
          {TABS.map((t) => (
            <Pressable
              key={t.key}
              onPress={() => setStatus(t.key)}
              accessibilityRole="button"
              accessibilityState={{ selected: status === t.key }}
              style={{
                paddingVertical: 8,
                paddingHorizontal: 16,
                borderRadius: 20,
                backgroundColor: status === t.key ? c.primary : c.card,
                borderWidth: 1,
                borderColor: status === t.key ? c.primary : c.border,
              }}>
              <Text style={{ color: status === t.key ? c.onPrimary : c.text, fontWeight: '600' }}>{t.label}</Text>
            </Pressable>
          ))}
        </View>
      </View>

      {q.isPending ? (
        <Loading />
      ) : q.isError && items.length === 0 ? (
        <Message title="Could not load tasks" detail={errorMessage(q.error)} actionTitle="Try again" onAction={() => q.refetch()} />
      ) : (
        <FlatList
          data={items}
          keyExtractor={(t) => String(t.id)}
          renderItem={({ item }) => <TaskRow task={item} c={c} />}
          contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, flexGrow: 1 }}
          refreshControl={<RefreshControl refreshing={q.isRefetching && !q.isFetchingNextPage} onRefresh={() => q.refetch()} tintColor={c.primary} />}
          onEndReachedThreshold={0.4}
          onEndReached={() => q.hasNextPage && !q.isFetchingNextPage && q.fetchNextPage()}
          ListEmptyComponent={<Message title="No tasks here" detail="Pull down to refresh." />}
          ListFooterComponent={q.isFetchingNextPage ? <Loading /> : null}
        />
      )}
    </Screen>
  );
}

function TaskRow({ task, c }: { task: TaskCard; c: Colors }) {
  return (
    <View style={{ backgroundColor: c.card, borderRadius: 14, padding: spacing.md, gap: 6, borderWidth: 1, borderColor: c.border }}>
      <Text style={{ color: c.text, fontSize: 16, fontWeight: '700' }} numberOfLines={2}>
        {task.title}
      </Text>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, alignItems: 'center' }}>
        <Text style={{ color: priorityColor(c, task.priority), fontSize: 12, fontWeight: '700', textTransform: 'uppercase' }}>{task.priority}</Text>
        <Text style={{ color: c.subtext, fontSize: 12 }}>{STATUS_LABEL[task.status] ?? task.status}</Text>
        {task.project ? <Text style={{ color: c.subtext, fontSize: 12 }}>· {task.project.name}</Text> : null}
      </View>
      <Text style={{ color: task.is_overdue ? c.danger : c.subtext, fontSize: 13, fontWeight: task.is_overdue ? '700' : '400' }}>
        {task.is_overdue ? 'Overdue · ' : ''}
        {formatDeadline(task.deadline)}
        {task.assignee ? `  ·  ${task.assignee.name}` : ''}
      </Text>
    </View>
  );
}
