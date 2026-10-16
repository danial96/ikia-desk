import { createCallEngine, type AudioRoute, type RtcLib, type ServerCall } from '@/calls/engine';

const ME = 1;
const SARA = { id: 2, name: 'Sara Khan', avatar: '' };
const MEP = { id: ME, name: 'Me', avatar: '' };

class FakePc {
  static last: FakePc | null = null;
  handlers: Record<string, ((e?: any) => void)[]> = {};
  tracks: any[] = [];
  remote: any = null;
  localDescription: any = null;
  candidates: any[] = [];
  connectionState = 'new';
  iceConnectionState = 'new';
  closed = false;
  offers: any[] = [];
  constructor(public config: any) {
    FakePc.last = this;
  }
  addEventListener(n: string, f: (e?: any) => void) {
    (this.handlers[n] ??= []).push(f);
  }
  emit(n: string, e?: any) {
    (this.handlers[n] ?? []).forEach((f) => f(e));
  }
  addTrack(t: any) {
    this.tracks.push(t);
  }
  async createOffer(opts?: any) {
    this.offers.push(opts);
    return { type: 'offer', sdp: 'OFFER' };
  }
  async createAnswer() {
    return { type: 'answer', sdp: 'ANSWER' };
  }
  async setLocalDescription(d: any) {
    this.localDescription = d;
  }
  async setRemoteDescription(d: any) {
    this.remote = d;
  }
  async addIceCandidate(c: any) {
    this.candidates.push(c);
  }
  close() {
    this.closed = true;
  }
  setState(s: string) {
    this.connectionState = s;
    this.emit('connectionstatechange');
  }
}

function setup(over: { rtc?: RtcLib | null; mic?: boolean; api?: (path: string, opts?: any) => any } = {}) {
  const track = { enabled: true, stop: jest.fn() };
  const stream = { getAudioTracks: () => [track], getTracks: () => [track] };
  const rtc: RtcLib | null = 'rtc' in over ? (over.rtc ?? null) : ({ RTCPeerConnection: FakePc as any, mediaDevices: { getUserMedia: jest.fn(async () => stream) } } as RtcLib);
  const calls: { path: string; opts?: any }[] = [];
  const api = jest.fn(async (path: string, opts?: any) => {
    calls.push({ path, opts });
    if (over.api) {
      const r = await over.api(path, opts);
      if (r !== undefined) return r;
    }
    if (path === 'calls/ice') return { iceServers: [{ urls: ['stun:x'] }] };
    if (path === 'calls' && opts?.method === 'POST') return { call: { id: 50, status: 'ringing', caller: MEP, callee: SARA, conv_id: 9 } };
    if (path.endsWith('/ping')) return { status: 'active' };
    return { ok: true };
  });
  const audio: AudioRoute = { start: jest.fn(), stop: jest.fn(), setSpeaker: jest.fn(), startRingtone: jest.fn(), stopRingtone: jest.fn() };
  const engine = createCallEngine({ api: api as any, rtc, audio, micPermission: async () => over.mic !== false, meId: () => ME });
  const paths = () => calls.map((c) => `${c.opts?.method ?? 'GET'} ${c.path}`);
  return { engine, api, calls, paths, audio, track, rtc };
}

const call = (over: Partial<ServerCall> = {}): ServerCall => ({ id: 50, status: 'ringing', caller: SARA, callee: MEP, conv_id: 9, answered_at: null, duration: null, by: null, ...over });
const tick = (ms = 0) => jest.advanceTimersByTimeAsync(ms);

beforeEach(() => {
  jest.useFakeTimers();
  FakePc.last = null;
});
afterEach(() => jest.useRealTimers());

describe('making a call', () => {
  it('rings, then makes the offer once the other person answers, then runs until connected', async () => {
    const t = setup();

    await t.engine.start(SARA);

    expect(t.paths()).toContain('POST calls');
    expect(t.calls.find((c) => c.path === 'calls')!.opts.body).toEqual({ callee_id: 2 });
    expect(t.engine.getState()).toMatchObject({ phase: 'outgoing', callId: 50, role: 'caller', peer: { id: 2 } });
    expect(t.audio.start).toHaveBeenCalledWith(true); // ring-back

    t.engine.onState(call({ status: 'active', caller: MEP, callee: SARA }));
    await tick();

    expect(t.engine.getState().phase).toBe('connecting');
    expect(FakePc.last!.config.iceServers).toEqual([{ urls: ['stun:x'] }]);
    expect(FakePc.last!.tracks).toHaveLength(1);
    expect(t.calls.filter((c) => c.path === 'calls/50/signal' && c.opts.body.type === 'offer')).toHaveLength(1);

    await t.engine.onSignal({ id: 50, type: 'answer', data: { type: 'answer', sdp: 'ANSWER' } });
    FakePc.last!.setState('connected');
    expect(t.engine.getState()).toMatchObject({ phase: 'active' });
    expect(t.engine.getState().connectedAt).not.toBeNull();
  });

  it('hanging up ends the call on the server and goes back to idle, closing the microphone and the connection', async () => {
    const t = setup();
    await t.engine.start(SARA);
    t.engine.onState(call({ status: 'active', caller: MEP, callee: SARA }));
    await tick();

    await t.engine.hangup();

    expect(t.paths()).toContain('POST calls/50/end');
    expect(t.engine.getState().phase).toBe('idle');
    expect(FakePc.last!.closed).toBe(true);
    expect(t.track.stop).toHaveBeenCalled();
    expect(t.audio.stop).toHaveBeenCalled();
  });

  it('gives up after 45 seconds of ringing', async () => {
    const t = setup();
    await t.engine.start(SARA);

    await tick(45_000);

    expect(t.paths()).toContain('POST calls/50/end');
    expect(t.engine.getState()).toMatchObject({ phase: 'ended', message: "Sara Khan didn't answer." });
    await tick(2_600);
    expect(t.engine.getState().phase).toBe('idle');
  });

  it('says so when the other person declines', async () => {
    const t = setup();
    await t.engine.start(SARA);

    t.engine.onState(call({ status: 'declined', caller: MEP, callee: SARA, by: 2 }));

    expect(t.engine.getState()).toMatchObject({ phase: 'ended', message: 'Sara Khan declined the call.' });
  });

  it('shows the server\'s reason when the call cannot be placed (busy) and frees the microphone', async () => {
    const t = setup({ api: (p, o) => { if (p === 'calls' && o?.method === 'POST') throw Object.assign(new Error('Sara Khan is on another call.'), { status: 409 }); } });

    await t.engine.start(SARA);

    expect(t.engine.getState()).toMatchObject({ phase: 'ended', message: 'Sara Khan is on another call.' });
    expect(t.track.stop).toHaveBeenCalled();
  });

  it('does not even ask the server when the microphone is refused', async () => {
    const t = setup({ mic: false });

    await t.engine.start(SARA);

    expect(t.paths()).not.toContain('POST calls');
    expect(t.engine.getState().message).toMatch(/microphone/i);
  });

  it('explains calls need the installed app when WebRTC is not available (Expo Go)', async () => {
    const t = setup({ rtc: null });

    await t.engine.start(SARA);

    expect(t.engine.available()).toBe(false);
    expect(t.paths()).toEqual([]);
    expect(t.engine.getState().message).toMatch(/installed IKIA Desk app/);
  });

  it('refuses a second call while one is going', async () => {
    const t = setup();
    await t.engine.start(SARA);

    await t.engine.start({ id: 3, name: 'Omar', avatar: '' });

    expect(t.calls.filter((c) => c.path === 'calls' && c.opts?.method === 'POST')).toHaveLength(1);
    expect(t.engine.getState().message).toBe('You are already on a call.');
  });
});

describe('receiving a call', () => {
  it('rings for a call addressed to me, and ignores one that is not', () => {
    const t = setup();

    t.engine.onState(call({ callee: SARA, caller: MEP }));
    expect(t.engine.getState().phase).toBe('idle');

    t.engine.onState(call());
    expect(t.engine.getState()).toMatchObject({ phase: 'incoming', callId: 50, peer: { id: 2 } });
    expect(t.audio.startRingtone).toHaveBeenCalled();
  });

  it('stops ringing when the caller gives up', () => {
    const t = setup();
    t.engine.onState(call());

    t.engine.onState(call({ status: 'missed' }));

    expect(t.engine.getState().phase).toBe('idle');
    expect(t.audio.stopRingtone).toHaveBeenCalled();
  });

  it('answering: the connection is ready BEFORE the server is told, then the offer is answered', async () => {
    const t = setup();
    t.engine.onState(call());

    await t.engine.accept();

    const order = t.paths();
    expect(FakePc.last).not.toBeNull();
    expect(order).toContain('POST calls/50/accept');
    expect(t.engine.getState().phase).toBe('connecting');

    await t.engine.onSignal({ id: 50, type: 'offer', data: { type: 'offer', sdp: 'OFFER' } });

    expect(FakePc.last!.remote.sdp).toBe('OFFER\r\n'); // CRLF restored
    const answer = t.calls.find((c) => c.path === 'calls/50/signal' && c.opts.body.type === 'answer');
    expect(answer!.opts.body.data).toEqual({ type: 'answer', sdp: 'ANSWER' });
  });

  it('declining tells the server and stops the ringtone', async () => {
    const t = setup();
    t.engine.onState(call());

    await t.engine.decline();

    expect(t.paths()).toContain('POST calls/50/end');
    expect(t.engine.getState().phase).toBe('idle');
    expect(t.audio.stopRingtone).toHaveBeenCalled();
  });

  it('declines cleanly when I cannot give the microphone', async () => {
    const t = setup({ mic: false });
    t.engine.onState(call());

    await t.engine.accept();

    expect(t.paths()).toContain('POST calls/50/end');
    expect(t.paths()).not.toContain('POST calls/50/accept');
  });

  it('a push tap opens the ringing screen only if it is still ringing for me', async () => {
    const live = setup({ api: (p) => (p === 'calls/50' ? { call: call() } : undefined) });
    await live.engine.openFromPush(50);
    expect(live.engine.getState().phase).toBe('incoming');

    const old = setup({ api: (p) => (p === 'calls/50' ? { call: call({ status: 'missed' }) } : undefined) });
    await old.engine.openFromPush(50);
    expect(old.engine.getState().phase).toBe('idle');
  });
});

describe('the handshake', () => {
  async function connectedCaller() {
    const t = setup();
    await t.engine.start(SARA);
    t.engine.onState(call({ status: 'active', caller: MEP, callee: SARA }));
    await tick();
    return t;
  }

  it('sends candidates in one burst, and holds incoming ones until the remote description is set', async () => {
    const t = await connectedCaller();
    const pc = FakePc.last!;

    pc.emit('icecandidate', { candidate: { toJSON: () => ({ candidate: 'a' }) } });
    pc.emit('icecandidate', { candidate: { toJSON: () => ({ candidate: 'b' }) } });
    await tick(100);
    const ice = t.calls.filter((c) => c.path === 'calls/50/signal' && c.opts.body.type === 'ice');
    expect(ice).toHaveLength(1);
    expect(ice[0].opts.body.data.list).toEqual([{ candidate: 'a' }, { candidate: 'b' }]);

    await t.engine.onSignal({ id: 50, type: 'ice', data: { list: [{ candidate: 'x' }] } });
    expect(pc.candidates).toEqual([]); // no remote description yet
    await t.engine.onSignal({ id: 50, type: 'answer', data: { type: 'answer', sdp: 'A' } });
    expect(pc.candidates).toEqual([{ candidate: 'x' }]);
  });

  it('keeps handshake messages that arrive before the connection exists, and applies them in order once it does', async () => {
    const t = setup();
    await t.engine.start(SARA);
    t.engine.onState(call({ status: 'active', caller: MEP, callee: SARA }));   // the connection is still being made here
    await t.engine.onSignal({ id: 50, type: 'answer', data: { type: 'answer', sdp: 'EARLY' } });

    await tick();

    expect(FakePc.last!.remote?.sdp).toBe('EARLY\r\n');
  });

  it('ignores handshake messages for some other call', async () => {
    const t = await connectedCaller();

    await t.engine.onSignal({ id: 999, type: 'answer', data: { type: 'answer', sdp: 'X' } });

    expect(FakePc.last!.remote).toBeNull();
  });

  it('one ICE restart on failure, then gives up', async () => {
    const t = await connectedCaller();
    const pc = FakePc.last!;

    pc.setState('failed');
    await tick();
    expect(pc.offers).toContainEqual({ iceRestart: true });
    expect(t.engine.getState().message).toBe('Reconnecting…');

    pc.setState('failed');
    await tick();
    expect(t.engine.getState()).toMatchObject({ phase: 'ended' });
    expect(t.paths()).toContain('POST calls/50/end');
  });

  it('pings while connected so a vanished phone can be noticed', async () => {
    const t = await connectedCaller();
    FakePc.last!.setState('connected');

    await tick(25_000);

    expect(t.paths()).toContain('POST calls/50/ping');
  });

  it('the server closing the call while connected ends it quietly when I hung up, with a note otherwise', async () => {
    const t = await connectedCaller();
    FakePc.last!.setState('connected');

    t.engine.onState(call({ status: 'ended', caller: MEP, callee: SARA, by: 2 }));

    expect(t.engine.getState()).toMatchObject({ phase: 'ended', message: 'Call ended' });
  });
});

describe('in the call', () => {
  it('mute switches the microphone track, the speaker switches the audio route', async () => {
    const t = setup();
    await t.engine.start(SARA);

    t.engine.toggleMute();
    expect(t.track.enabled).toBe(false);
    expect(t.engine.getState().muted).toBe(true);
    t.engine.toggleMute();
    expect(t.track.enabled).toBe(true);

    t.engine.toggleSpeaker();
    expect(t.audio.setSpeaker).toHaveBeenCalledWith(true);
    expect(t.engine.getState().speaker).toBe(true);
  });

  it('resync after a dropped connection learns that the call is over', async () => {
    const t = setup({ api: (p, o) => (p === 'calls/50' && !o ? { call: call({ status: 'ended', caller: MEP, callee: SARA }) } : undefined) });
    await t.engine.start(SARA);

    await t.engine.resync();

    expect(t.engine.getState().phase).toBe('ended');
  });

  it('notifies subscribers of every change', async () => {
    const t = setup();
    const seen: string[] = [];
    t.engine.subscribe(() => seen.push(t.engine.getState().phase));

    await t.engine.start(SARA);
    await t.engine.hangup();

    expect(seen).toContain('outgoing');
    expect(seen[seen.length - 1]).toBe('idle');
  });
});
