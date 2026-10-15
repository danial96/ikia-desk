import { Alert } from 'react-native';
import { router } from 'expo-router';

import { useCreateTask } from '@/api/tasks';
import { useSession } from '@/auth/session';
import { EMPTY_FORM, TaskForm } from '@/components/tasks/TaskForm';
import { Message } from '@/components/ui';
import { errorMessage } from '@/lib/errors';

export default function NewTask() {
  const { user } = useSession();
  const create = useCreateTask();

  if (!user) return null;
  if (!user.can.create_tasks) return <Message title="You cannot create tasks" detail="Ask an administrator to switch it on for you." />;

  return (
    <TaskForm
      mode="create"
      initial={EMPTY_FORM}
      timeZone={user.time_zone}
      submitLabel="Create task"
      busy={create.isPending}
      onSubmit={(input) =>
        create.mutate(input, {
          onSuccess: (r) => router.replace({ pathname: '/task/[id]', params: { id: String(r.task.id) } }),
          onError: (e) => Alert.alert('Could not create the task', errorMessage(e)),
        })
      }
    />
  );
}
