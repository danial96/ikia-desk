import { useState } from 'react';
import { KeyboardAvoidingView, Platform, Pressable, ScrollView, Text, TextInput, View } from 'react-native';

import { useProjects, useUsers } from '@/api/tasks';
import type { TaskInput, TaskPriority, TaskStatus } from '@/api/types';
import { DeadlineField } from '@/components/DeadlineField';
import { PickerSheet, type PickOption } from '@/components/Sheet';
import { Button, TextField } from '@/components/ui';
import { STATUS_LABEL } from '@/lib/format';
import { hasAttachmentTags, plainText } from '@/lib/richText';
import { spacing, useColors } from '@/theme';

export type FormValues = {
  title: string;
  description: string | null;
  priority: TaskPriority;
  status: TaskStatus;
  /** Wall-clock in the profile zone, or null. */
  deadline: string | null;
  assignedTo: number | null;
  projectId: number | null;
  members: number[];
  observers: number[];
};

export const EMPTY_FORM: FormValues = {
  title: '',
  description: null,
  priority: 'medium',
  status: 'new',
  deadline: null,
  assignedTo: null,
  projectId: null,
  members: [],
  observers: [],
};

const PRIORITIES: TaskPriority[] = ['low', 'medium', 'high', 'urgent'];
const STATUSES: TaskStatus[] = ['new', 'pending', 'in_progress', 'reviewing', 'paused', 'completed'];

type Props = {
  initial: FormValues;
  /** Original description text; if it holds photos/files the plain box would lose them, so it is not editable here. */
  originalDescription?: string | null;
  mode: 'create' | 'edit';
  timeZone: string;
  submitLabel: string;
  busy: boolean;
  onSubmit: (input: TaskInput) => void;
};

/** One form for creating and editing a task. */
export function TaskForm({ initial, originalDescription, mode, timeZone, submitLabel, busy, onSubmit }: Props) {
  const c = useColors();
  const users = useUsers();
  const projects = useProjects();
  const [v, setV] = useState<FormValues>(initial);
  const [descText, setDescText] = useState(() => plainText(initial.description));
  const [descTouched, setDescTouched] = useState(false);
  const [titleError, setTitleError] = useState<string | null>(null);
  const [sheet, setSheet] = useState<null | 'assignee' | 'project' | 'members' | 'observers'>(null);

  const locked = hasAttachmentTags(originalDescription);
  const set = <K extends keyof FormValues>(k: K, val: FormValues[K]) => setV((p) => ({ ...p, [k]: val }));

  const people: PickOption[] = (users.data ?? []).map((u) => ({ id: u.id, label: u.name, subtitle: u.position, avatar: u.avatar }));
  const nameOf = (ids: number[]) => ids.map((id) => users.data?.find((u) => u.id === id)?.name).filter(Boolean).join(', ');

  function submit() {
    const title = v.title.trim();
    if (!title) {
      setTitleError('A task needs a title.');
      return;
    }
    setTitleError(null);

    const input: TaskInput = {
      title,
      priority: v.priority,
      status: v.status,
      assigned_to: v.assignedTo,
      project_id: v.projectId,
      members: v.members,
      observers: v.observers,
    };
    if (mode === 'edit') {
      input.deadline = v.deadline; // sending null clears it
      if (descTouched && !locked) input.description = descText.trim() || null; // untouched: not sent, so the original (tags and all) is kept
    } else {
      if (v.deadline) input.deadline = v.deadline;
      if (descText.trim()) input.description = descText.trim();
    }
    onSubmit(input);
  }

  const Field = ({ label, value, placeholder, onPress }: { label: string; value: string; placeholder: string; onPress: () => void }) => (
    <View style={{ gap: 4 }}>
      <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '600' }}>{label}</Text>
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${label}: ${value || placeholder}`}
        onPress={onPress}
        style={{ minHeight: 48, borderRadius: 12, borderWidth: 1, borderColor: c.border, backgroundColor: c.inputBg, justifyContent: 'center', paddingHorizontal: 14 }}>
        <Text style={{ color: value ? c.text : c.subtext, fontSize: 16 }} numberOfLines={2}>
          {value || placeholder}
        </Text>
      </Pressable>
    </View>
  );

  return (
    <KeyboardAvoidingView style={{ flex: 1 }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={{ padding: spacing.md, gap: spacing.md }} keyboardShouldPersistTaps="handled">
        <TextField label="Title" value={v.title} onChangeText={(t) => set('title', t)} placeholder="What needs doing?" error={titleError} maxLength={255} returnKeyType="next" />

        <View style={{ gap: 4 }}>
          <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '600' }}>Description</Text>
          {locked ? (
            <Text style={{ color: c.subtext, fontSize: 14, backgroundColor: c.card, borderRadius: 12, padding: 12, borderWidth: 1, borderColor: c.border }}>
              This description has photos or files in it. Edit it on the website so nothing is lost.
            </Text>
          ) : (
            <TextInput
              value={descText}
              onChangeText={(t) => { setDescText(t); setDescTouched(true); }}
              multiline
              placeholder="Details (optional)"
              placeholderTextColor={c.subtext}
              accessibilityLabel="Description"
              style={{ minHeight: 110, textAlignVertical: 'top', borderRadius: 12, borderWidth: 1, borderColor: c.border, padding: 12, color: c.text, backgroundColor: c.inputBg, fontSize: 16 }}
            />
          )}
        </View>

        <Choice label="Priority" options={PRIORITIES.map((p) => ({ key: p, label: p[0].toUpperCase() + p.slice(1) }))} value={v.priority} onChange={(p) => set('priority', p as TaskPriority)} />
        {mode === 'edit' ? <Choice label="Status" options={STATUSES.map((s) => ({ key: s, label: STATUS_LABEL[s] }))} value={v.status} onChange={(s) => set('status', s as TaskStatus)} /> : null}

        <DeadlineField value={v.deadline} onChange={(d) => set('deadline', d)} timeZone={timeZone} />

        <Field label="Responsible person" value={nameOf(v.assignedTo ? [v.assignedTo] : [])} placeholder="No one" onPress={() => setSheet('assignee')} />
        <Field label="Project" value={projects.data?.find((p) => p.id === v.projectId)?.name ?? ''} placeholder="No project" onPress={() => setSheet('project')} />
        <Field label="Participants" value={nameOf(v.members)} placeholder="None" onPress={() => setSheet('members')} />
        <Field label="Observers" value={nameOf(v.observers)} placeholder="None" onPress={() => setSheet('observers')} />

        <Button title={submitLabel} onPress={submit} loading={busy} />
      </ScrollView>

      <PickerSheet visible={sheet === 'assignee'} onClose={() => setSheet(null)} title="Responsible person" options={people} value={v.assignedTo ? [v.assignedTo] : []} onChange={(ids) => set('assignedTo', ids[0] ?? null)} clearLabel="No one" />
      <PickerSheet
        visible={sheet === 'project'}
        onClose={() => setSheet(null)}
        title="Project"
        options={(projects.data ?? []).map((p) => ({ id: p.id, label: p.name }))}
        value={v.projectId ? [v.projectId] : []}
        onChange={(ids) => set('projectId', ids[0] ?? null)}
        clearLabel="No project"
      />
      <PickerSheet visible={sheet === 'members'} onClose={() => setSheet(null)} title="Participants" options={people} value={v.members} onChange={(ids) => set('members', ids)} multi />
      <PickerSheet visible={sheet === 'observers'} onClose={() => setSheet(null)} title="Observers" options={people} value={v.observers} onChange={(ids) => set('observers', ids)} multi />
    </KeyboardAvoidingView>
  );
}

function Choice({ label, options, value, onChange }: { label: string; options: { key: string; label: string }[]; value: string; onChange: (key: string) => void }) {
  const c = useColors();
  return (
    <View style={{ gap: 6 }}>
      <Text style={{ color: c.subtext, fontSize: 13, fontWeight: '600' }}>{label}</Text>
      <View style={{ flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm }}>
        {options.map((o) => {
          const on = o.key === value;
          return (
            <Pressable
              key={o.key}
              accessibilityRole="button"
              accessibilityState={{ selected: on }}
              onPress={() => onChange(o.key)}
              style={{ paddingVertical: 8, paddingHorizontal: 14, borderRadius: 20, borderWidth: 1, backgroundColor: on ? c.primary : c.card, borderColor: on ? c.primary : c.border }}>
              <Text style={{ color: on ? c.onPrimary : c.text, fontWeight: '600' }}>{o.label}</Text>
            </Pressable>
          );
        })}
      </View>
    </View>
  );
}

