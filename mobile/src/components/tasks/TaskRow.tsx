import { memo } from 'react';
import { Pressable, Text, View } from 'react-native';
import { router } from 'expo-router';

import type { TaskCard } from '@/api/types';
import { Avatar } from '@/components/Avatar';
import { formatDateTime } from '@/lib/time';
import { STATUS_LABEL } from '@/lib/format';
import { priorityColor, spacing, useColors } from '@/theme';

export const openTask = (id: number) => router.push({ pathname: '/task/[id]', params: { id: String(id) } });

export const TaskRow = memo(function TaskRow({ task, timeZone }: { task: TaskCard; timeZone: string }) {
  const c = useColors();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`Task: ${task.title}`}
      onPress={() => openTask(task.id)}
      style={({ pressed }) => ({
        backgroundColor: c.card,
        borderRadius: 14,
        padding: spacing.md,
        gap: 6,
        borderWidth: 1,
        borderColor: c.border,
        opacity: pressed ? 0.85 : 1,
      })}>
      <Text style={{ color: c.text, fontSize: 16, fontWeight: '700' }} numberOfLines={2}>
        {task.title}
      </Text>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, alignItems: 'center' }}>
        <Text style={{ color: priorityColor(c, task.priority), fontSize: 12, fontWeight: '700', textTransform: 'uppercase' }}>{task.priority}</Text>
        <Text style={{ color: c.subtext, fontSize: 12 }}>{STATUS_LABEL[task.status] ?? task.status}</Text>
        {task.project ? <Text style={{ color: c.subtext, fontSize: 12 }}>· {task.project.name}</Text> : null}
      </View>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: spacing.sm }}>
        <Text style={{ flex: 1, color: task.is_overdue ? c.danger : c.subtext, fontSize: 13, fontWeight: task.is_overdue ? '700' : '400' }} numberOfLines={1}>
          {task.is_overdue ? 'Overdue · ' : ''}
          {formatDateTime(task.deadline, timeZone)}
        </Text>
        {task.comments_count > 0 ? <Text style={{ color: c.subtext, fontSize: 12 }}>💬 {task.comments_count}</Text> : null}
        {task.files_count > 0 ? <Text style={{ color: c.subtext, fontSize: 12 }}>📎 {task.files_count}</Text> : null}
        {task.assignee ? <Avatar uri={task.assignee.avatar} name={task.assignee.name} size={24} /> : null}
      </View>
    </Pressable>
  );
});
