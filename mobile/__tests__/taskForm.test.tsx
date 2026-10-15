import { fireEvent, render, screen } from '@testing-library/react-native';

import { EMPTY_FORM, TaskForm, type FormValues } from '@/components/tasks/TaskForm';

jest.mock('@/api/tasks', () => ({
  useUsers: () => ({ data: [{ id: 7, name: 'Sara Khan', position: 'Designer', avatar: '' }] }),
  useProjects: () => ({ data: [{ id: 3, name: 'Website revamp' }] }),
}));
jest.mock('@/components/DeadlineField', () => ({ DeadlineField: () => null }));
jest.mock('@/components/Sheet', () => ({ PickerSheet: () => null }));

const base: FormValues = { ...EMPTY_FORM, title: 'Existing', description: 'Plain text', priority: 'high', status: 'in_progress', deadline: '2026-10-20T17:00', assignedTo: 7, projectId: 3, members: [7], observers: [] };

function setup(over: Partial<React.ComponentProps<typeof TaskForm>> = {}) {
  const onSubmit = jest.fn();
  render(<TaskForm mode="edit" initial={base} originalDescription={base.description} timeZone="Asia/Karachi" submitLabel="Save changes" busy={false} onSubmit={onSubmit} {...over} />);
  return onSubmit;
}

describe('TaskForm', () => {
  it('refuses an empty title and shows why', () => {
    const onSubmit = setup({ mode: 'create', initial: EMPTY_FORM, originalDescription: null, submitLabel: 'Create task' });

    fireEvent.press(screen.getByText('Create task'));

    expect(onSubmit).not.toHaveBeenCalled();
    expect(screen.getByText('A task needs a title.')).toBeTruthy();
  });

  it('create: sends what was filled in and leaves out an empty deadline and description', () => {
    const onSubmit = setup({ mode: 'create', initial: EMPTY_FORM, originalDescription: null, submitLabel: 'Create task' });

    fireEvent.changeText(screen.getByLabelText('Title'), '  Call the vendor  ');
    fireEvent.press(screen.getByText('High'));
    fireEvent.press(screen.getByText('Create task'));

    expect(onSubmit).toHaveBeenCalledWith({
      title: 'Call the vendor',
      priority: 'high',
      status: 'new',
      assigned_to: null,
      project_id: null,
      members: [],
      observers: [],
    });
  });

  it('edit: an untouched description is not sent, so the stored one is kept as it was', () => {
    const onSubmit = setup();

    fireEvent.changeText(screen.getByLabelText('Title'), 'Renamed');
    fireEvent.press(screen.getByText('Save changes'));

    const sent = onSubmit.mock.calls[0][0];
    expect(sent.title).toBe('Renamed');
    expect('description' in sent).toBe(false);
    // the rest of the task travels along, the server requires status and priority on every save
    expect(sent).toMatchObject({ priority: 'high', status: 'in_progress', deadline: '2026-10-20T17:00', assigned_to: 7, project_id: 3, members: [7], observers: [] });
  });

  it('edit: a changed description is sent, and clearing it sends null', () => {
    const onSubmit = setup();

    fireEvent.changeText(screen.getByLabelText('Description'), 'New details');
    fireEvent.press(screen.getByText('Save changes'));
    expect(onSubmit.mock.calls[0][0].description).toBe('New details');

    fireEvent.changeText(screen.getByLabelText('Description'), '   ');
    fireEvent.press(screen.getByText('Save changes'));
    expect(onSubmit.mock.calls[1][0].description).toBeNull();
  });

  it('edit: a description with photos or files cannot be edited here and is never sent', () => {
    const withImage = 'Look [img]/uploads/a.png[/img]';
    const onSubmit = setup({ initial: { ...base, description: withImage }, originalDescription: withImage });

    expect(screen.queryByLabelText('Description')).toBeNull();
    expect(screen.getByText(/Edit it on the website/)).toBeTruthy();

    fireEvent.press(screen.getByText('Save changes'));
    expect('description' in onSubmit.mock.calls[0][0]).toBe(false);
  });

  it('edit: clearing the deadline is sent as null so the server removes it', () => {
    const onSubmit = setup({ initial: { ...base, deadline: null } });

    fireEvent.press(screen.getByText('Save changes'));

    expect(onSubmit.mock.calls[0][0].deadline).toBeNull();
  });

  it('shows the status choices only when editing', () => {
    setup({ mode: 'create', initial: EMPTY_FORM, originalDescription: null });
    expect(screen.queryByText('Reviewing')).toBeNull();
  });
});
