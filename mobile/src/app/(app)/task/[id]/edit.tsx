import { Alert } from 'react-native';
import { useLocalSearchParams } from 'expo-router';

import { useTask, useUpdateTask } from '@/api/tasks';
import { useSession } from '@/auth/session';
import { TaskForm, type FormValues } from '@/components/tasks/TaskForm';
import { Loading, Message } from '@/components/ui';
import { errorMessage } from '@/lib/errors';
import { goBack } from '@/lib/nav';
import { isoToNaive } from '@/lib/time';

export default function EditTask() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const taskId = Number(id);
  const { user } = useSession();
  const q = useTask(taskId);
  const update = useUpdateTask(taskId);

  if (!user) return null;
  if (q.isPending) return <Loading />;
  if (q.isError || !q.data) return <Message title="Could not load the task" detail={errorMessage(q.error)} actionTitle="Try again" onAction={() => q.refetch()} />;
  if (!q.data.canEdit) return <Message title="You cannot edit this task" detail="Only its creator or an administrator can." />;

  const t = q.data.task;
  const initial: FormValues = {
    title: t.title,
    description: t.description,
    priority: t.priority,
    status: t.status,
    deadline: isoToNaive(t.deadline, user.time_zone),
    assignedTo: t.assigned_to,
    projectId: t.project?.id ?? null,
    members: q.data.participants.map((p) => p.id),
    observers: q.data.observers.map((p) => p.id),
  };

  return (
    <TaskForm
      mode="edit"
      initial={initial}
      originalDescription={t.description}
      timeZone={user.time_zone}
      submitLabel="Save changes"
      busy={update.isPending}
      onSubmit={(input) =>
        update.mutate(input, {
          onSuccess: goBack,
          onError: (e) => Alert.alert('Could not save', errorMessage(e)),
        })
      }
    />
  );
}
