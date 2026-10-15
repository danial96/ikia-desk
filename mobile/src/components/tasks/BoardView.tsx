import { useEffect, useMemo, useState } from 'react';
import { Alert, FlatList, Pressable, RefreshControl, ScrollView, Text, View } from 'react-native';

import { useBoard, useMoveTask, type TaskFilters } from '@/api/tasks';
import type { BoardCard } from '@/api/types';
import { ActionSheet } from '@/components/Sheet';
import { Avatar } from '@/components/Avatar';
import { Loading, Message } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { BOARD_COLUMNS, moveBody, nowWall, type BoardColumn } from '@/lib/time';
import { priorityColor, spacing, useColors } from '@/theme';
import { openTask } from './TaskRow';

/** The website's board: tasks grouped by deadline. One column at a time; long-press a card you may move to send it elsewhere. */
export function BoardView({ filters, timeZone }: { filters: TaskFilters; timeZone: string }) {
  const c = useColors();
  const q = useBoard(filters);
  const move = useMoveTask();
  const [col, setCol] = useState<BoardColumn | null>(null);
  const [moving, setMoving] = useState<{ card: BoardCard; from: BoardColumn } | null>(null);

  const columns = q.data?.columns;
  const count = (k: BoardColumn) => columns?.[k]?.length ?? 0;

  // open on the first column that has something in it
  const current: BoardColumn = useMemo(() => col ?? BOARD_COLUMNS.find((b) => count(b.key) > 0)?.key ?? 'due_today', [col, columns]); // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => {
    if (col && columns && count(col) === 0 && BOARD_COLUMNS.some((b) => count(b.key) > 0)) setCol(null);
  }, [columns]); // eslint-disable-line react-hooks/exhaustive-deps

  function doMove(card: BoardCard, from: BoardColumn, to: BoardColumn) {
    move.mutate(
      { id: card.id, body: moveBody(from, to, nowWall(timeZone)) },
      {
        onSuccess: (r) => {
          if (r.overdue && to !== 'completed') Alert.alert('Still overdue', 'The deadline time of day has already passed, so the task stays in Overdue. Open it to set a later time.');
        },
        onError: (e) => Alert.alert('Could not move the task', errorMessage(e)),
      },
    );
  }

  if (q.isPending) return <Loading />;
  if (q.isError && !columns) return <Message title="Could not load the board" detail={errorMessage(q.error)} actionTitle="Try again" onAction={() => q.refetch()} />;

  const cards = columns?.[current] ?? [];

  return (
    <View style={{ flex: 1 }}>
      <View>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ paddingHorizontal: spacing.md, gap: spacing.sm, paddingVertical: spacing.sm }}>
          {BOARD_COLUMNS.map((b) => {
            const on = b.key === current;
            return (
              <Pressable
                key={b.key}
                accessibilityRole="tab"
                accessibilityState={{ selected: on }}
                onPress={() => setCol(b.key)}
                style={{ paddingVertical: 8, paddingHorizontal: 14, borderRadius: 20, borderWidth: 1, backgroundColor: on ? c.primary : c.card, borderColor: on ? c.primary : c.border }}>
                <Text style={{ color: on ? c.onPrimary : b.key === 'overdue' && count(b.key) > 0 ? c.danger : c.text, fontWeight: '700', fontSize: 13 }}>
                  {b.label} · {b.key === 'completed' ? (q.data?.completedTotal ?? count(b.key)) : count(b.key)}
                </Text>
              </Pressable>
            );
          })}
        </ScrollView>
      </View>

      <FlatList
        data={cards}
        keyExtractor={(t) => String(t.id)}
        contentContainerStyle={{ padding: spacing.md, gap: spacing.sm, flexGrow: 1 }}
        refreshControl={<RefreshControl refreshing={q.isRefetching} onRefresh={() => q.refetch()} tintColor={c.primary} />}
        ListEmptyComponent={<Message title="No tasks here" detail="Pull down to refresh." />}
        renderItem={({ item }) => (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={`Task: ${item.title}`}
            onPress={() => openTask(item.id)}
            onLongPress={item.can_move ? () => setMoving({ card: item, from: current }) : undefined}
            style={({ pressed }) => ({ backgroundColor: c.card, borderRadius: 14, padding: spacing.md, gap: 6, borderWidth: 1, borderColor: c.border, opacity: pressed ? 0.85 : 1 })}>
            <Text style={{ color: c.text, fontSize: 16, fontWeight: '700' }} numberOfLines={2}>
              {item.title}
            </Text>
            <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm }}>
              <Text style={{ color: priorityColor(c, item.priority), fontSize: 12, fontWeight: '700', textTransform: 'uppercase' }}>{item.priority}</Text>
              {item.project ? <Text style={{ color: c.subtext, fontSize: 12 }}>· {item.project}</Text> : null}
            </View>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: spacing.sm }}>
              <Text style={{ flex: 1, fontSize: 13, color: item.dl?.kind === 'overdue' ? c.danger : item.dl?.kind === 'today' ? c.warning : c.subtext, fontWeight: item.dl?.kind === 'overdue' ? '700' : '400' }} numberOfLines={1}>
                {item.dl?.label ?? 'No deadline'}
              </Text>
              {item.files_count > 0 ? <Text style={{ color: c.subtext, fontSize: 12 }}>📎 {item.files_count}</Text> : null}
              {item.assignee ? <Avatar uri={item.assignee.avatar} name={item.assignee.name} size={24} /> : null}
            </View>
            {item.can_move ? <Text style={{ color: c.subtext, fontSize: 11 }}>Hold to move</Text> : null}
          </Pressable>
        )}
      />

      <ActionSheet
        visible={!!moving}
        onClose={() => setMoving(null)}
        title={moving ? `Move “${moving.card.title.slice(0, 40)}”` : undefined}
        actions={BOARD_COLUMNS.filter((b) => b.key !== 'overdue' && b.key !== moving?.from).map((b) => ({
          label: b.label,
          onPress: () => moving && doMove(moving.card, moving.from, b.key),
        }))}
      />
    </View>
  );
}
