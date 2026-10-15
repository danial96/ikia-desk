import { useMemo, useState } from 'react';
import { FlatList, Pressable, RefreshControl, Text, TextInput, View } from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import { useTasks, type TaskFilters } from '@/api/tasks';
import { useSession } from '@/auth/session';
import { BoardView } from '@/components/tasks/BoardView';
import { TaskRow } from '@/components/tasks/TaskRow';
import { Loading, Message, Screen } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { useDebounced } from '@/lib/hooks';
import { spacing, useColors } from '@/theme';

const TABS = [
  { key: 'in_progress', label: 'Active' },
  { key: 'paused', label: 'Paused' },
  { key: 'completed', label: 'Done' },
] as const;

export default function Tasks() {
  const c = useColors();
  const { user } = useSession();
  const [view, setView] = useState<'list' | 'board'>('list');
  const [status, setStatus] = useState<(typeof TABS)[number]['key']>('in_progress');
  const [mine, setMine] = useState(false);
  const [search, setSearch] = useState('');
  const term = useDebounced(search.trim());

  const base: TaskFilters = { search: term || undefined, assignee_id: mine ? user?.id : undefined };
  const tz = user?.time_zone ?? 'UTC';

  return (
    <Screen>
      <View style={{ paddingHorizontal: spacing.md, paddingTop: spacing.md, gap: spacing.sm }}>
        <View style={{ flexDirection: 'row', alignItems: 'center', gap: spacing.md }}>
          <Text style={{ flex: 1, color: c.text, fontSize: 26, fontWeight: '800' }}>Tasks</Text>
          <IconButton label={view === 'list' ? 'Show board' : 'Show list'} icon={view === 'list' ? 'grid-outline' : 'list-outline'} onPress={() => setView(view === 'list' ? 'board' : 'list')} />
          <IconButton label="Trash" icon="trash-outline" onPress={() => router.push('/trash')} />
          {user?.can.create_tasks ? <IconButton label="New task" icon="add-circle" size={30} onPress={() => router.push('/task/new')} /> : null}
        </View>

        <TextInput
          value={search}
          onChangeText={setSearch}
          placeholder="Search tasks"
          placeholderTextColor={c.subtext}
          returnKeyType="search"
          autoCapitalize="none"
          autoCorrect={false}
          accessibilityLabel="Search tasks"
          style={{ minHeight: 44, borderRadius: 12, borderWidth: 1, borderColor: c.border, paddingHorizontal: 14, color: c.text, backgroundColor: c.inputBg, fontSize: 15 }}
        />

        <View style={{ flexDirection: 'row', gap: spacing.sm, flexWrap: 'wrap' }}>
          {view === 'list'
            ? TABS.map((t) => <Chip key={t.key} label={t.label} on={status === t.key} onPress={() => setStatus(t.key)} />)
            : null}
          <Chip label="Mine" on={mine} onPress={() => setMine(!mine)} />
        </View>
      </View>

      {view === 'list' ? <ListView filters={{ ...base, status }} timeZone={tz} /> : <BoardView filters={base} timeZone={tz} />}
    </Screen>
  );
}

function ListView({ filters, timeZone }: { filters: TaskFilters; timeZone: string }) {
  const c = useColors();
  const q = useTasks(filters);
  const items = useMemo(() => q.data?.pages.flatMap((p) => p.data) ?? [], [q.data]);

  if (q.isPending) return <Loading />;
  if (q.isError && items.length === 0) return <Message title="Could not load tasks" detail={errorMessage(q.error)} actionTitle="Try again" onAction={() => q.refetch()} />;

  return (
    <FlatList
      data={items}
      keyExtractor={(t) => String(t.id)}
      renderItem={({ item }) => <TaskRow task={item} timeZone={timeZone} />}
      contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, flexGrow: 1 }}
      refreshControl={<RefreshControl refreshing={q.isRefetching && !q.isFetchingNextPage} onRefresh={() => q.refetch()} tintColor={c.primary} />}
      onEndReachedThreshold={0.4}
      onEndReached={() => q.hasNextPage && !q.isFetchingNextPage && q.fetchNextPage()}
      ListEmptyComponent={<Message title="No tasks here" detail={filters.search ? 'Nothing matches your search.' : 'Pull down to refresh.'} />}
      ListFooterComponent={q.isFetchingNextPage ? <Loading /> : null}
    />
  );
}

function Chip({ label, on, onPress }: { label: string; on: boolean; onPress: () => void }) {
  const c = useColors();
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      accessibilityState={{ selected: on }}
      style={{ paddingVertical: 8, paddingHorizontal: 16, borderRadius: 20, backgroundColor: on ? c.primary : c.card, borderWidth: 1, borderColor: on ? c.primary : c.border }}>
      <Text style={{ color: on ? c.onPrimary : c.text, fontWeight: '600' }}>{label}</Text>
    </Pressable>
  );
}

function IconButton({ label, icon, onPress, size = 24 }: { label: string; icon: React.ComponentProps<typeof Ionicons>['name']; onPress: () => void; size?: number }) {
  const c = useColors();
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={label} onPress={onPress} hitSlop={8}>
      <Ionicons name={icon} size={size} color={c.primary} />
    </Pressable>
  );
}
