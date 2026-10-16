// One-to-one voice calls. The audio flows phone-to-phone (WebRTC); the server (CallController) is only the switchboard:
// it rings the other person, relays the handshake (offer / answer / ICE) through the realtime channel, and writes
// "Voice call · 2:31" into the chat. This is the phone twin of resources/views/partials/call.blade.php, same steps:
//
//   caller:  POST calls            -> ringing ... callee answers -> state "active" -> caller makes the OFFER
//   callee:  state "ringing"       -> incoming screen -> getUserMedia + peer connection READY -> POST accept -> answers the offer
//   both:    ICE candidates in short bursts, ping every 25 s while connected, hang up = POST end

export type Person = { id: number; name: string; avatar: string };

export type ServerCall = {
  id: number;
  status: 'ringing' | 'active' | 'ended' | 'declined' | 'missed' | 'failed' | string;
  caller: Person | null;
  callee: Person | null;
  conv_id: number | null;
  answered_at: number | null;
  duration: number | null;
  by: number | null;
};

export type CallPhase = 'idle' | 'outgoing' | 'incoming' | 'connecting' | 'active' | 'ended';

export type CallState = {
  phase: CallPhase;
  callId: number | null;
  peer: Person | null;
  role: 'caller' | 'callee' | null;
  muted: boolean;
  speaker: boolean;
  /** ms since epoch, when the audio first connected */
  connectedAt: number | null;
  /** what to tell the person ("Sara declined the call.", "Reconnecting…") */
  message: string | null;
};

// ── the pieces the engine needs from the phone, injectable so the flow can be tested ────────────────────────────

export type RtcLib = {
  RTCPeerConnection: new (config: { iceServers: unknown[] }) => any;
  RTCSessionDescription?: new (d: { type: string; sdp: string }) => any;
  RTCIceCandidate?: new (c: any) => any;
  mediaDevices: { getUserMedia(c: { audio: boolean; video: boolean }): Promise<any> };
};

export type AudioRoute = {
  start(ringback: boolean): void;
  stop(): void;
  setSpeaker(on: boolean): void;
  startRingtone(): void;
  stopRingtone(): void;
};

export type Deps = {
  api: <T = any>(path: string, opts?: { method?: string; body?: unknown }) => Promise<T>;
  rtc: RtcLib | null;
  audio: AudioRoute | null;
  micPermission: () => Promise<boolean>;
  meId: () => number | null;
  now?: () => number;
};

const FINAL = ['ended', 'declined', 'missed', 'failed'];
const RING_TIMEOUT_MS = 45_000;
const INCOMING_TIMEOUT_MS = 62_000;
const CONNECT_TIMEOUT_MS = 15_000;
const HEARTBEAT_MS = 25_000;
const END_SCREEN_MS = 2_500;

const IDLE: CallState = { phase: 'idle', callId: null, peer: null, role: null, muted: false, speaker: false, connectedAt: null, message: null };

type Session = {
  id: number;
  role: 'caller' | 'callee';
  peer: Person;
  local: any | null;
  pc: any | null;
  queue: ServerCall[] | any[];
  iceQueue: any[];
  outIce: any[];
  icePromise: Promise<unknown[]>;
  offered: boolean;
  restarted: boolean;
  connectedAt: number | null;
  timers: { ring?: any; flush?: any; connect?: any; hb?: any };
};

export function createCallEngine(deps: Deps) {
  let state: CallState = IDLE;
  let S: Session | null = null;
  let incoming: { id: number; timer: any } | null = null;
  let endTimer: any = null;
  const listeners = new Set<() => void>();

  const now = deps.now ?? Date.now;
  const set = (patch: Partial<CallState>) => {
    state = { ...state, ...patch };
    listeners.forEach((l) => l());
  };

  const available = () => !!deps.rtc;

  async function fetchIce(): Promise<unknown[]> {
    try {
      const r = await deps.api<{ iceServers?: unknown[] }>('calls/ice');
      return r.iceServers?.length ? r.iceServers : [{ urls: ['stun:stun.l.google.com:19302'] }];
    } catch {
      return [{ urls: ['stun:stun.l.google.com:19302'] }];
    }
  }

  const sendSignal = (type: 'offer' | 'answer' | 'ice', data: unknown) =>
    S ? deps.api(`calls/${S.id}/signal`, { method: 'POST', body: { type, data } }).catch(() => undefined) : Promise.resolve(undefined);

  async function getMic(): Promise<any | null> {
    if (!deps.rtc) return null;
    if (!(await deps.micPermission())) {
      finish('Allow the microphone in the phone\'s Settings to make calls.');
      return null;
    }
    try {
      return await deps.rtc.mediaDevices.getUserMedia({ audio: true, video: false });
    } catch {
      finish('The microphone could not be used.');
      return null;
    }
  }

  async function createPc(): Promise<any | null> {
    const s = S;
    if (!s || !deps.rtc) return null;
    const iceServers = await s.icePromise;
    if (S !== s) return null;
    const pc = new deps.rtc.RTCPeerConnection({ iceServers });
    s.pc = pc;
    s.local?.getAudioTracks().forEach((t: any) => pc.addTrack(t, s.local));

    // candidates are found in a quick burst: one request for the burst, not one each
    pc.addEventListener('icecandidate', (e: any) => {
      if (!e?.candidate || S !== s) return;
      s.outIce.push(typeof e.candidate.toJSON === 'function' ? e.candidate.toJSON() : e.candidate);
      if (!s.timers.flush) s.timers.flush = setTimeout(flushOutIce, 60);
    });
    const onConn = () => connectionChanged(pc);
    pc.addEventListener('connectionstatechange', onConn);
    pc.addEventListener('iceconnectionstatechange', onConn);

    // not connected after a while: say so honestly instead of sitting on "Connecting…"
    s.timers.connect = setTimeout(() => {
      if (S === s && !s.connectedAt) {
        set({ message: "Can't connect directly. Your network may be blocking calls…" });
        diag('timeout');
      }
    }, CONNECT_TIMEOUT_MS);

    const q = s.queue as any[];
    s.queue = [];
    for (const m of q) await onSignal(m);
    return pc;
  }

  const connState = (pc: any): string => {
    const c = pc.connectionState;
    if (c && c !== 'new') return c;
    const i = pc.iceConnectionState;
    return i === 'completed' ? 'connected' : i;
  };

  function connectionChanged(pc: any) {
    const s = S;
    if (!s || s.pc !== pc) return;
    const st = connState(pc);
    if (st === 'connected') {
      if (!s.connectedAt) {
        s.connectedAt = now();
        clearTimeout(s.timers.connect);
        startHeartbeat(s);
        setTimeout(() => diag('connected'), 1500);
        deps.audio?.stopRingtone();
      }
      set({ phase: 'active', connectedAt: s.connectedAt, message: null });
    } else if (st === 'disconnected') {
      set({ message: 'Reconnecting…' });
    } else if (st === 'failed') {
      diag('failed');
      if (s.role === 'caller' && !s.restarted) {
        s.restarted = true;
        set({ message: 'Reconnecting…' });
        pc.createOffer({ iceRestart: true })
          .then((o: any) => pc.setLocalDescription(o))
          .then(() => sendSignal('offer', { type: pc.localDescription.type, sdp: pc.localDescription.sdp }))
          .catch(() => undefined);
      } else {
        finish("Couldn't keep the call connected. The network may be blocking voice calls.");
        void deps.api(`calls/${s.id}/end`, { method: 'POST' }).catch(() => undefined);
      }
    }
  }

  function startHeartbeat(s: Session) {
    clearInterval(s.timers.hb);
    s.timers.hb = setInterval(async () => {
      try {
        const r = await deps.api<{ status: string }>(`calls/${s.id}/ping`, { method: 'POST' });
        if (FINAL.includes(r.status)) onState({ id: s.id, status: r.status, caller: null, callee: null, conv_id: null, answered_at: null, duration: null, by: null });
      } catch {
        /* a dropped ping is fine, the connection state tells the truth */
      }
    }, HEARTBEAT_MS);
  }

  function diag(event: 'connected' | 'timeout' | 'failed') {
    if (!S) return;
    void deps.api(`calls/${S.id}/diag`, { method: 'POST', body: { event, detail: { role: S.role, path: 'phone' } } }).catch(() => undefined);
  }

  function flushOutIce() {
    const s = S;
    if (!s) return;
    s.timers.flush = undefined;
    const list = s.outIce;
    s.outIce = [];
    if (list.length) void sendSignal('ice', { list });
  }

  // ── incoming messages from the server ───────────────────────────────────────────────────────────────────────

  /** A "call.signal" event: one handshake message from the other phone. */
  async function onSignal(m: { id: number; type: 'offer' | 'answer' | 'ice'; data: any }) {
    const s = S;
    if (!s || s.id !== m.id) return;
    if (!s.pc) {
      (s.queue as any[]).push(m);
      return;
    }
    const pc = s.pc;
    const data = m.data;
    // the server trims trailing whitespace from every string, which eats the CRLF an SDP must end with
    if (data && typeof data.sdp === 'string' && !data.sdp.endsWith('\n')) data.sdp += '\r\n';
    const desc = () => (deps.rtc?.RTCSessionDescription ? new deps.rtc.RTCSessionDescription(data) : data);
    try {
      if (m.type === 'offer') {
        await pc.setRemoteDescription(desc());
        await flushIce(s);
        const answer = await pc.createAnswer();
        await pc.setLocalDescription(answer);
        await sendSignal('answer', { type: pc.localDescription.type, sdp: pc.localDescription.sdp });
      } else if (m.type === 'answer') {
        await pc.setRemoteDescription(desc());
        await flushIce(s);
      } else if (m.type === 'ice') {
        for (const cand of data.list ?? [data]) {
          if (pc.remoteDescription) await addCandidate(pc, cand);
          else s.iceQueue.push(cand);
        }
      }
    } catch {
      /* a bad message must not kill the call; the connection timeout reports real trouble */
    }
  }

  const addCandidate = (pc: any, c: any) => pc.addIceCandidate(deps.rtc?.RTCIceCandidate ? new deps.rtc.RTCIceCandidate(c) : c).catch(() => undefined);

  async function flushIce(s: Session) {
    const q = s.iceQueue;
    s.iceQueue = [];
    for (const c of q) await addCandidate(s.pc, c);
  }

  /** A "call.state" event (or the answer to GET calls/{id}). */
  function onState(st: ServerCall) {
    const me = deps.meId();
    const s = S;

    if (!s || s.id !== st.id) {
      if (incoming && incoming.id === st.id && st.status !== 'ringing') dismissIncoming(); // answered elsewhere, or the caller gave up
      else if (!s && !incoming && state.phase === 'idle' && st.status === 'ringing' && st.callee && st.callee.id === me && st.caller) showIncoming(st);
      return;
    }

    if (st.status === 'active') {
      if (s.role === 'caller' && !s.offered) {
        s.offered = true;
        clearTimeout(s.timers.ring);
        deps.audio?.stop(); // the ring-back ends; the call audio route starts below
        deps.audio?.start(false);
        deps.audio?.setSpeaker(state.speaker);
        set({ phase: 'connecting', message: null });
        void (async () => {
          const pc = await createPc();
          if (!pc || S !== s) return;
          const offer = await pc.createOffer();
          await pc.setLocalDescription(offer);
          await sendSignal('offer', { type: offer.type, sdp: offer.sdp });
        })();
      }
    } else if (FINAL.includes(st.status)) {
      const wasConnected = !!s.connectedAt;
      const name = s.peer.name;
      let msg: string | null = 'Call ended';
      if (!wasConnected) msg = st.status === 'declined' ? `${name} declined the call.` : st.status === 'missed' ? `${name} didn't answer.` : 'Call ended';
      else if (st.by === me) msg = null; // I hung up: just close
      finish(msg);
    }
  }

  // ── actions ─────────────────────────────────────────────────────────────────────────────────────────────────

  async function start(peer: Person) {
    if (S || incoming || state.phase !== 'idle') {
      set({ message: 'You are already on a call.' });
      return;
    }
    if (!available()) {
      set({ phase: 'ended', message: 'Calls need the installed IKIA Desk app (they do not work inside Expo Go).', peer });
      scheduleIdle();
      return;
    }
    clearTimeout(endTimer);
    set({ ...IDLE, phase: 'outgoing', peer, role: 'caller', message: 'Calling…' });
    const stream = await getMic();
    if (!stream) return;
    const icePromise = fetchIce();
    let call: ServerCall;
    try {
      call = (await deps.api<{ call: ServerCall }>('calls', { method: 'POST', body: { callee_id: peer.id } })).call;
    } catch (e: any) {
      stream.getTracks().forEach((t: any) => t.stop());
      finish(e?.message || 'Could not start the call.');
      return;
    }
    const s: Session = { id: call.id, role: 'caller', peer: call.callee ?? peer, local: stream, pc: null, queue: [], iceQueue: [], outIce: [], icePromise, offered: false, restarted: false, connectedAt: null, timers: {} };
    S = s;
    deps.audio?.start(true); // ring-back tone while it rings
    set({ callId: call.id, peer: s.peer });
    s.timers.ring = setTimeout(() => {
      if (S === s && !s.offered) {
        finish(`${s.peer.name} didn't answer.`);
        void deps.api(`calls/${s.id}/end`, { method: 'POST' }).catch(() => undefined);
      }
    }, RING_TIMEOUT_MS);
  }

  function showIncoming(st: ServerCall) {
    clearTimeout(endTimer);
    deps.audio?.startRingtone();
    incoming = { id: st.id, timer: setTimeout(dismissIncoming, INCOMING_TIMEOUT_MS) };
    set({ ...IDLE, phase: 'incoming', callId: st.id, peer: st.caller, role: 'callee', message: null });
  }

  function dismissIncoming() {
    if (!incoming) return;
    clearTimeout(incoming.timer);
    incoming = null;
    deps.audio?.stopRingtone();
    if (state.phase === 'incoming') set({ ...IDLE });
  }

  async function accept() {
    if (!incoming || state.phase !== 'incoming' || !state.peer || S) return;
    const id = incoming.id;
    const peer = state.peer;
    deps.audio?.stopRingtone();
    clearTimeout(incoming.timer);
    incoming = null;
    set({ phase: 'connecting', message: 'Connecting…' });
    const stream = await getMic();
    if (!stream) {
      void deps.api(`calls/${id}/end`, { method: 'POST' }).catch(() => undefined); // cannot talk: decline cleanly
      return;
    }
    const s: Session = { id, role: 'callee', peer, local: stream, pc: null, queue: [], iceQueue: [], outIce: [], icePromise: fetchIce(), offered: false, restarted: false, connectedAt: null, timers: {} };
    S = s;
    deps.audio?.start(false);
    deps.audio?.setSpeaker(state.speaker);
    set({ callId: id });
    await createPc(); // ready for the offer BEFORE the caller is told we answered
    try {
      await deps.api(`calls/${id}/accept`, { method: 'POST' });
    } catch {
      finish('This call has already ended.');
    }
  }

  async function decline() {
    if (!incoming) return;
    const id = incoming.id;
    dismissIncoming();
    await deps.api(`calls/${id}/end`, { method: 'POST' }).catch(() => undefined);
  }

  async function hangup() {
    const id = S?.id ?? (state.phase === 'incoming' ? incoming?.id : null) ?? state.callId;
    if (state.phase === 'incoming') return decline();
    finish(null);
    if (id) await deps.api(`calls/${id}/end`, { method: 'POST' }).catch(() => undefined);
  }

  function toggleMute() {
    const muted = !state.muted;
    S?.local?.getAudioTracks().forEach((t: any) => (t.enabled = !muted));
    set({ muted });
  }

  function toggleSpeaker() {
    const speaker = !state.speaker;
    deps.audio?.setSpeaker(speaker);
    set({ speaker });
  }

  /** Back from the background / socket reconnected: signals may have been missed, ask where the call stands. */
  async function resync() {
    const id = S?.id ?? incoming?.id;
    if (!id) return;
    try {
      const r = await deps.api<{ call: ServerCall }>(`calls/${id}`);
      onState({ ...r.call, by: null });
    } catch {
      /* offline: nothing to learn */
    }
  }

  /** Opened from a push notification: ring if that call is still ringing for me. */
  async function openFromPush(callId: number) {
    if (S || incoming) return;
    try {
      const r = await deps.api<{ call: ServerCall }>(`calls/${callId}`);
      onState(r.call);
    } catch {
      /* no longer available */
    }
  }

  function finish(message: string | null) {
    const s = S;
    S = null;
    if (incoming) {
      clearTimeout(incoming.timer);
      incoming = null;
    }
    if (s) {
      Object.values(s.timers).forEach((t) => {
        clearTimeout(t);
        clearInterval(t);
      });
      try {
        s.pc?.close();
      } catch {
        /* already closed */
      }
      s.local?.getTracks().forEach((t: any) => t.stop());
    }
    deps.audio?.stopRingtone();
    deps.audio?.stop();
    if (message === null) {
      clearTimeout(endTimer);
      set({ ...IDLE });
      return;
    }
    set({ phase: 'ended', message, muted: false, connectedAt: null });
    scheduleIdle();
  }

  function scheduleIdle() {
    clearTimeout(endTimer);
    endTimer = setTimeout(() => {
      if (state.phase === 'ended') set({ ...IDLE });
    }, END_SCREEN_MS);
  }

  return {
    available,
    getState: () => state,
    subscribe(fn: () => void) {
      listeners.add(fn);
      return () => listeners.delete(fn);
    },
    start,
    accept,
    decline,
    hangup,
    toggleMute,
    toggleSpeaker,
    onState,
    onSignal,
    resync,
    openFromPush,
    /** Everything is torn down (sign out). */
    reset() {
      finish(null);
    },
  };
}

export type CallEngine = ReturnType<typeof createCallEngine>;
