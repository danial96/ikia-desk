import { attachmentTag, isHiddenFor, lastMessagePreview, mergeMessages, mergeWindow, tickState, type ChatMessage } from '@/api/chat';

const msg = (id: number, ts: number, over: Partial<ChatMessage> = {}): ChatMessage => ({
  id,
  text: `m${id}`,
  isMine: false,
  time: '',
  date: '',
  createdTs: ts,
  editedAt: null,
  deletedFor: [],
  reactions: {},
  myReactions: [],
  author: { id: 1, name: 'A', avatar: '' },
  parentId: null,
  parentPreview: null,
  ...over,
});

describe('mergeMessages()', () => {
  it('orders by time then id and lets the newer copy of an id win', () => {
    const merged = mergeMessages([msg(2, 20), msg(1, 10)], [msg(2, 20, { text: 'edited' }), msg(3, 20)]);

    expect(merged.map((m) => m.id)).toEqual([1, 2, 3]);
    expect(merged[1].text).toBe('edited');
  });
});

describe('mergeWindow()', () => {
  it('keeps older messages already scrolled into view, takes the fresh window, drops deleted ones', () => {
    const previous = [msg(1, 10), msg(2, 20), msg(3, 30), msg(4, 40)];
    const fresh = [msg(3, 30, { text: 'edited' })]; // 4 was deleted

    const { messages, hasMore } = mergeWindow(previous, fresh, false);

    expect(messages.map((m) => m.id)).toEqual([1, 2, 3]);
    expect(messages[2].text).toBe('edited');
    expect(hasMore).toBe(true); // older ones are loaded, so there may be more above
  });

  it('keeps a message that is still being sent', () => {
    const sending = msg(-1, 99, { local: 'sending', isMine: true });

    const { messages } = mergeWindow([msg(1, 10), sending], [msg(1, 10)], false);

    expect(messages.map((m) => m.id)).toEqual([1, -1]);
  });

  it('with nothing loaded before, passes the window and its hasMore through', () => {
    expect(mergeWindow([], [msg(1, 10)], true)).toEqual({ messages: [msg(1, 10)], hasMore: true });
    expect(mergeWindow([], [msg(1, 10)], false).hasMore).toBe(false);
  });
});

describe('tickState()', () => {
  const mine = msg(1, 100, { isMine: true });

  it('is nothing for the other person\'s messages', () => {
    expect(tickState(msg(2, 100), 200, 200, true)).toBeNull();
  });

  it('read > delivered > sent in a direct chat', () => {
    expect(tickState(mine, 100, 100, true)).toBe('read');
    expect(tickState(mine, 50, 150, true)).toBe('delivered');
    expect(tickState(mine, 50, 60, true)).toBe('sent');
    expect(tickState(mine, null, null, true)).toBe('sent');
  });

  it('groups only ever show sent', () => {
    expect(tickState(mine, 500, 500, false)).toBe('sent');
  });

  it('shows sending and failed for messages that exist only on the phone', () => {
    expect(tickState({ ...mine, local: 'sending' }, 500, 500, true)).toBe('sending');
    expect(tickState({ ...mine, local: 'failed' }, 500, 500, true)).toBe('failed');
  });
});

describe('small helpers', () => {
  it('hides messages deleted "for me"', () => {
    expect(isHiddenFor(msg(1, 1, { deletedFor: [7] }), 7)).toBe(true);
    expect(isHiddenFor(msg(1, 1, { deletedFor: [7] }), 8)).toBe(false);
  });

  it('turns attachment tags into words for the list preview', () => {
    expect(lastMessagePreview('hi [img]u[/img]')).toBe('hi 📷 Photo');
    expect(lastMessagePreview('[file name="a.pdf"]u[/file]')).toBe('📎 a.pdf');
    expect(lastMessagePreview('[voice dur="0:05"]u[/voice]')).toBe('🎤 Voice message');
  });

  it('writes the same tags as the website', () => {
    const up = { url: 'https://d/uploads/up_1.png/a.png', name: 'a "b".png', mime: 'image/png', ext: 'png' };
    expect(attachmentTag('image', up)).toBe('[img]https://d/uploads/up_1.png/a.png[/img]');
    expect(attachmentTag('file', up)).toBe('[file name="a \'b\'.png"]https://d/uploads/up_1.png/a.png[/file]');
    expect(attachmentTag('voice', up, '0:12')).toBe('[voice dur="0:12"]https://d/uploads/up_1.png/a.png[/voice]');
    expect(attachmentTag('voice', up)).toBe('[voice]https://d/uploads/up_1.png/a.png[/voice]');
  });
});
