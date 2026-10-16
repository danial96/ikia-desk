import { QueryClient } from '@tanstack/react-query';

import { handleRealtime } from '@/realtime/events';

function setup() {
  const qc = new QueryClient();
  const spy = jest.spyOn(qc, 'invalidateQueries');
  const keys = () => spy.mock.calls.map((c) => JSON.stringify((c[0] as { queryKey: unknown }).queryKey));
  return { qc, keys };
}

describe('handleRealtime()', () => {
  it('a new chat message refreshes the list and that conversation only', () => {
    const { qc, keys } = setup();
    handleRealtime(qc, 'chat.changed', { c: 231, k: 'new', m: 5, by: 2 });
    expect(keys()).toEqual(['["convs"]', '["chat",231]']);
  });

  it('a task change refreshes lists, the board and that task', () => {
    const { qc, keys } = setup();
    handleRealtime(qc, 'task.changed', { t: 9, k: 'comment', by: 2 });
    expect(keys()).toEqual(['["tasks"]', '["board"]', '["task",9]']);
  });

  it('a notification refreshes the alerts', () => {
    const { qc, keys } = setup();
    handleRealtime(qc, 'notif', undefined);
    expect(keys()).toEqual(['["notifications"]']);
  });

  it('survives odd payloads and unknown events', () => {
    const { qc, keys } = setup();
    handleRealtime(qc, 'chat.changed', null);
    handleRealtime(qc, 'task.changed', { t: 'x' });
    handleRealtime(qc, 'call.state', { id: 1 });
    expect(keys()).toEqual(['["convs"]', '["tasks"]', '["board"]']);
  });
});
