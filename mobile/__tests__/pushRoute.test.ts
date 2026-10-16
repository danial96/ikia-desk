import { isChatOpen, setActiveChat } from '@/push/activeChat';
import { routeForNotification } from '@/push/route';

describe('routeForNotification()', () => {
  it('opens the chat for a message', () => {
    expect(routeForNotification({ conversation_id: 12, type: 'message', url: '/chat?conv=12' })).toEqual({ pathname: '/chat/[id]', params: { id: '12' } });
  });

  it('opens the task for a task alert', () => {
    expect(routeForNotification({ task_id: 77, type: 'task' })).toEqual({ pathname: '/task/[id]', params: { id: '77' } });
  });

  it('falls back to the website-style url when the ids are missing', () => {
    expect(routeForNotification({ url: '/chat?conv=5' })).toEqual({ pathname: '/chat/[id]', params: { id: '5' } });
    expect(routeForNotification({ url: '/tasks/kanban?task=9&x=1' })).toEqual({ pathname: '/task/[id]', params: { id: '9' } });
  });

  it('accepts ids that arrive as strings and refuses nonsense', () => {
    expect(routeForNotification({ conversation_id: '31' })).toEqual({ pathname: '/chat/[id]', params: { id: '31' } });
    expect(routeForNotification({ conversation_id: 'abc', task_id: -4 })).toEqual({ pathname: '/notifications' });
  });

  it('goes to the alerts list when there is nothing to open', () => {
    expect(routeForNotification({ url: '/chat' })).toEqual({ pathname: '/notifications' });
    expect(routeForNotification(null)).toEqual({ pathname: '/notifications' });
    expect(routeForNotification(undefined)).toEqual({ pathname: '/notifications' });
  });

  it('prefers the chat when a payload names both', () => {
    expect(routeForNotification({ conversation_id: 3, task_id: 4 }).pathname).toBe('/chat/[id]');
  });
});

describe('activeChat', () => {
  it('knows which chat is on screen', () => {
    expect(isChatOpen(7)).toBe(false);
    setActiveChat(7);
    expect(isChatOpen(7)).toBe(true);
    expect(isChatOpen('7')).toBe(true);
    expect(isChatOpen(8)).toBe(false);
    setActiveChat(null);
    expect(isChatOpen(7)).toBe(false);
    expect(isChatOpen(undefined)).toBe(false);
  });
});

import { callIdFromNotification } from '@/push/route';

describe('callIdFromNotification()', () => {
  it('finds the ringing call in a push', () => {
    expect(callIdFromNotification({ call_id: 88, type: 'call' })).toBe(88);
    expect(callIdFromNotification({ url: '/?call=12' })).toBe(12);
    expect(callIdFromNotification({ conversation_id: 3 })).toBeNull();
    expect(callIdFromNotification(null)).toBeNull();
  });
});
