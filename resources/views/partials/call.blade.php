{{-- Voice calls (WebRTC). Signalling rides on the realtime channel (rt:call / rt:callsignal events from the layout),
     so this whole module is only included when realtime is configured. The audio goes browser-to-browser. --}}
<script>
if (typeof window.Call === 'undefined') {
(function () {
    const ME = {{ (int) auth()->id() }};
    // Inside the small call window (call/window.blade.php) this is true. Calls live there, not in the page you
    // browse, because every link in Desk is a full page load and would otherwise hang up on you.
    const POPUP = !!window.CALL_POPUP;
    const RING_TIMEOUT_MS = 45000;
    const FINAL = ['ended', 'declined', 'missed'];

    const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const api = async (method, url, body) => {
        try {
            const r = await fetch(url, {
                method, credentials: 'same-origin', keepalive: true,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: body ? JSON.stringify(body) : undefined,
            });
            let json = null; try { json = await r.json(); } catch (e) {}
            return { ok: r.ok, status: r.status, json };
        } catch (e) { return { ok: false, status: 0, json: null }; }
    };
    const toast = (m) => { try { (window.showToast || alert)(m); } catch (e) {} };

    let S = null;            // the call this tab is part of: { id, role, peer, local, pc, queue, iceQueue, ... }
    let incoming = null;     // { id, caller, el, timer } while this tab is ringing

    // ── tones (WebAudio, nothing to download) ─────────────────────────────────
    let actx = null, toneTimer = null;
    const ctx = () => { try { actx = actx || new (window.AudioContext || window.webkitAudioContext)(); if (actx.state === 'suspended') actx.resume(); } catch (e) {} return actx; };
    const burst = (freqs, ms, vol) => {
        const c = ctx(); if (!c) return;
        const g = c.createGain(); g.gain.value = 0; g.connect(c.destination);
        freqs.forEach((f) => { const o = c.createOscillator(); o.frequency.value = f; o.connect(g); o.start(); o.stop(c.currentTime + ms / 1000 + .05); });
        g.gain.setValueAtTime(0, c.currentTime);
        g.gain.linearRampToValueAtTime(vol, c.currentTime + .03);
        g.gain.setValueAtTime(vol, c.currentTime + ms / 1000 - .05);
        g.gain.linearRampToValueAtTime(0, c.currentTime + ms / 1000);
    };
    const stopTone = () => { clearInterval(toneTimer); toneTimer = null; };
    const startTone = (kind) => {
        stopTone();
        if (kind === 'ring') {            // incoming: two quick double-beeps
            const once = () => { burst([440, 480], 400, .12); setTimeout(() => burst([440, 480], 400, .12), 600); };
            once(); toneTimer = setInterval(once, 3000);
        } else {                          // outgoing ringback: one long soft tone
            const once = () => burst([425], 1000, .08);
            once(); toneTimer = setInterval(once, 4000);
        }
    };
    const blip = (f) => burst([f], 160, .1);

    // ── UI ─────────────────────────────────────────────────────────────────
    const el = (tag, css, html) => { const e = document.createElement(tag); if (css) e.style.cssText = css; if (html != null) e.innerHTML = html; return e; };
    const initials = (n) => (n || '?').split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
    const avatarEl = (p, size) => {
        const box = el('div', `width:${size}px;height:${size}px;border-radius:50%;overflow:hidden;flex-shrink:0;background:#1e3a5f;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:${Math.round(size / 2.6)}px;`);
        if (p && p.avatar) { const i = el('img', 'width:100%;height:100%;object-fit:cover;'); i.src = p.avatar; i.onerror = () => { box.textContent = initials(p.name); }; box.appendChild(i); }
        else box.textContent = initials(p && p.name);
        return box;
    };
    const roundBtn = (bg, icon, title, fn) => {
        const b = el('button', `width:46px;height:46px;border-radius:50%;border:none;background:${bg};color:#fff;font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 2px 8px rgba(0,0,0,.25);`, `<i class="fas ${icon}"></i>`);
        b.type = 'button'; b.title = title; b.onclick = fn; return b;
    };

    let titleTimer = null, origTitle = null;
    const flashTitle = (on) => {
        clearInterval(titleTimer); titleTimer = null;
        if (origTitle !== null) { document.title = origTitle; origTitle = null; }
        if (!on) return;
        origTitle = document.title; let t = false;
        titleTimer = setInterval(() => { document.title = (t = !t) ? '📞 Incoming call…' : origTitle; }, 900);
    };

    function showIncoming(st) {
        hideIncoming();
        const card = el('div', 'position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:100000;background:#0f172a;color:#fff;border-radius:16px;padding:14px 18px;display:flex;align-items:center;gap:14px;min-width:300px;max-width:92vw;box-shadow:0 12px 40px rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.1);font-family:inherit;');
        card.id = 'call-incoming';
        card.appendChild(avatarEl(st.caller, 52));
        const info = el('div', 'flex:1;min-width:0;');
        const nm = el('div', 'font-size:16px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'); nm.textContent = st.caller.name;
        info.appendChild(nm); info.appendChild(el('div', 'font-size:13px;color:#94a3b8;margin-top:2px;', 'Incoming voice call…'));
        card.appendChild(info);
        card.appendChild(roundBtn('#ef4444', 'fa-phone-slash', 'Decline', () => decline(st.id)));
        card.appendChild(roundBtn('#22c55e', 'fa-phone', 'Accept', () => accept(st)));
        document.body.appendChild(card);
        incoming = { id: st.id, caller: st.caller, el: card, timer: setTimeout(hideIncoming, 62000) };
        startTone('ring'); flashTitle(true);
    }
    function hideIncoming() {
        if (!incoming) return;
        clearTimeout(incoming.timer); incoming.el.remove(); incoming = null;
        stopTone(); flashTitle(false);
    }

    function showBar() {
        removeBar();
        if (POPUP) return showPopupCard();
        const bar = el('div', 'position:fixed;right:22px;bottom:22px;z-index:100000;background:#0f172a;color:#fff;border-radius:18px;padding:12px 14px;display:flex;align-items:center;gap:12px;min-width:290px;box-shadow:0 12px 40px rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.1);font-family:inherit;');
        bar.id = 'call-bar';
        bar.appendChild(avatarEl(S.peer, 44));
        const info = el('div', 'flex:1;min-width:0;');
        const nm = el('div', 'font-size:14.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'); nm.textContent = S.peer.name;
        const st = el('div', 'font-size:12.5px;color:#94a3b8;margin-top:2px;'); st.id = 'call-status';
        info.appendChild(nm); info.appendChild(st);
        bar.appendChild(info);
        const mute = roundBtn('#334155', 'fa-microphone', 'Mute', toggleMute); mute.id = 'call-mute'; mute.style.width = mute.style.height = '40px';
        bar.appendChild(mute);
        const end = roundBtn('#ef4444', 'fa-phone-slash', 'End call', () => hangup()); end.style.width = end.style.height = '40px';
        bar.appendChild(end);
        document.body.appendChild(bar);
        const a = el('audio'); a.id = 'call-audio'; a.autoplay = true; a.setAttribute('playsinline', ''); document.body.appendChild(a);
    }
    function showPopupCard() {
        const root = document.getElementById('call-root') || document.body;
        root.innerHTML = '';
        const card = el('div', 'height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:18px;text-align:center;');
        card.id = 'call-bar';
        card.appendChild(avatarEl(S.peer, 84));
        const nm = el('div', 'font-size:20px;font-weight:600;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'); nm.textContent = S.peer.name;
        const st = el('div', 'font-size:15px;color:#94a3b8;min-height:22px;'); st.id = 'call-status';
        card.appendChild(nm); card.appendChild(st);
        const row = el('div', 'display:flex;gap:18px;margin-top:10px;');
        const mute = roundBtn('#334155', 'fa-microphone', 'Mute', toggleMute); mute.id = 'call-mute';
        const end = roundBtn('#ef4444', 'fa-phone-slash', 'End call', () => hangup()); end.id = 'call-end';
        [mute, end].forEach((b) => { b.style.width = b.style.height = '54px'; row.appendChild(b); });
        card.appendChild(row);
        root.appendChild(card);
        const a = el('audio'); a.id = 'call-audio'; a.autoplay = true; a.setAttribute('playsinline', ''); document.body.appendChild(a);
    }
    function removeBar() {
        ['call-bar', 'call-audio'].forEach((id) => { const e = document.getElementById(id); if (e) e.remove(); });
    }
    const setStatus = (t) => { const e = document.getElementById('call-status'); if (e) e.textContent = t; };

    function toggleMute() {
        if (!S || !S.local) return;
        S.muted = !S.muted;
        S.local.getAudioTracks().forEach((t) => { t.enabled = !S.muted; });
        const b = document.getElementById('call-mute');
        if (b) { b.innerHTML = `<i class="fas ${S.muted ? 'fa-microphone-slash' : 'fa-microphone'}"></i>`; b.style.background = S.muted ? '#b45309' : '#334155'; b.title = S.muted ? 'Unmute' : 'Mute'; }
    }

    // ── media + connection ─────────────────────────────────────────────────
    async function getMic() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { toast('Calls need a browser with microphone support (and https).'); return null; }
        try {
            return await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true }, video: false });
        } catch (e) {
            toast('Microphone access is blocked. Allow the microphone for this site and try again.');
            return null;
        }
    }
    async function fetchIce() {
        const r = await api('GET', '/api/calls/ice');
        return (r.ok && r.json && r.json.iceServers) || [{ urls: ['stun:stun.l.google.com:19302'] }];
    }
    const sendSignal = (type, data) => {
        if (!S) return Promise.resolve();
        return api('POST', `/api/calls/${S.id}/signal`, { type, data });
    };

    async function createPc() {
        const iceServers = await S.icePromise;
        if (!S) return null;
        S.hasTurn = iceServers.some((x) => x.username);
        const pc = new RTCPeerConnection({ iceServers });
        S.pc = pc;
        S.local.getAudioTracks().forEach((t) => pc.addTrack(t, S.local));
        // Candidates are found in a quick burst; one request for the burst instead of one each keeps setup fast.
        pc.onicecandidate = (e) => {
            if (!e.candidate || !S) return;
            S.outIce.push(e.candidate.toJSON());
            if (!S.iceFlush) S.iceFlush = setTimeout(flushOutIce, 60);
        };
        pc.ontrack = (e) => {
            const a = document.getElementById('call-audio');
            if (a) { a.srcObject = e.streams[0] || new MediaStream([e.track]); a.play().catch(() => {}); }
        };
        pc.onconnectionstatechange = () => onConn(pc);
        // Not connected after a while: say so honestly (and let the server know why) instead of sitting on "Connecting…".
        S.connTimer = setTimeout(() => {
            if (S && S.pc === pc && !S.connectedAt) {
                setStatus("Can't connect directly. Your network may be blocking calls…");
                sendDiag('timeout');
            }
        }, 15000);
        // anything that arrived before the connection object existed
        const q = S.queue; S.queue = [];
        for (const m of q) await onSignal(m);
        return pc;
    }

    function onConn(pc) {
        if (!S || S.pc !== pc) return;
        const st = pc.connectionState;
        if (st === 'connected') {
            if (!S.connectedAt) { S.connectedAt = Date.now(); clearTimeout(S.connTimer); blip(880); startTimer(); startHeartbeat(); setTimeout(() => sendDiag('connected'), 1500); }
            setStatus(fmt(0));
        } else if (st === 'disconnected') {
            setStatus('Reconnecting…');
        } else if (st === 'failed') {
            sendDiag('failed');
            if (S.role === 'caller' && !S.restarted) {          // one ICE restart before giving up
                S.restarted = true; setStatus('Reconnecting…');
                pc.createOffer({ iceRestart: true }).then((o) => pc.setLocalDescription(o))
                    .then(() => sendSignal('offer', { type: pc.localDescription.type, sdp: pc.localDescription.sdp }));
            } else {
                toast("Couldn't keep the call connected. The network may be blocking voice calls.");
                hangup();
            }
        }
    }

    const fmt = (s) => Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    function startTimer() {
        clearInterval(S.timer);
        S.timer = setInterval(() => { if (S && S.connectedAt && S.pc && S.pc.connectionState === 'connected') setStatus(fmt(Math.floor((Date.now() - S.connectedAt) / 1000))); }, 500);
    }
    function startHeartbeat() {
        clearInterval(S.hb);
        const id = S.id;
        S.hb = setInterval(async () => {
            const r = await api('POST', `/api/calls/${id}/ping`);
            if (r.ok && r.json && FINAL.includes(r.json.status)) onState({ id, status: r.json.status, by: null });
        }, 25000);
    }

    async function onSignal(m) {
        if (!S || S.id !== m.id) return;
        if (!S.pc) { S.queue.push(m); return; }
        const pc = S.pc;
        // The server's request pipeline trims trailing whitespace from every string, which eats the final
        // CRLF an SDP must end with ("Invalid SDP line"), so put it back.
        if (m.data && typeof m.data.sdp === 'string' && !m.data.sdp.endsWith(String.fromCharCode(10))) m.data.sdp += String.fromCharCode(13, 10);
        try {
            if (m.type === 'offer') {
                await pc.setRemoteDescription(m.data);
                await flushIce();
                const ans = await pc.createAnswer();
                await pc.setLocalDescription(ans);
                await sendSignal('answer', { type: pc.localDescription.type, sdp: pc.localDescription.sdp });
            } else if (m.type === 'answer') {
                await pc.setRemoteDescription(m.data);
                await flushIce();
            } else if (m.type === 'ice') {
                for (const cand of (m.data.list || [m.data])) {
                    if (pc.remoteDescription) await pc.addIceCandidate(cand).catch(() => {});
                    else S.iceQueue.push(cand);
                }
            }
        } catch (e) { console.warn('call signal failed', m.type, e); }
    }
    function flushOutIce() {
        if (!S) return;
        S.iceFlush = null;
        const list = S.outIce; S.outIce = [];
        if (list.length) sendSignal('ice', { list });
    }
    async function flushIce() {
        const q = S.iceQueue; S.iceQueue = [];
        for (const c of q) await S.pc.addIceCandidate(c).catch(() => {});
    }

    // What the connection actually looks like (direct vs relayed, packets flowing?) — for troubleshooting a bad call.
    async function statsNow() {
        if (!S || !S.pc) return null;
        const rep = await S.pc.getStats(), by = {};
        rep.forEach((r) => { by[r.id] = r; });
        const out = { connection: S.pc.connectionState, ice: S.pc.iceConnectionState };
        rep.forEach((r) => {
            if (r.type === 'transport' && r.selectedCandidatePairId && by[r.selectedCandidatePairId]) {
                const pair = by[r.selectedCandidatePairId];
                out.path = (by[pair.localCandidateId] || {}).candidateType + ' -> ' + (by[pair.remoteCandidateId] || {}).candidateType;
                out.rttMs = pair.currentRoundTripTime != null ? Math.round(pair.currentRoundTripTime * 1000) : null;
            }
            if (r.type === 'inbound-rtp' && r.kind === 'audio') Object.assign(out, { received: r.packetsReceived, lost: r.packetsLost, energy: r.totalAudioEnergy, jitterMs: Math.round((r.jitter || 0) * 1000) });
            if (r.type === 'outbound-rtp' && r.kind === 'audio') out.sent = r.packetsSent;
        });
        return out;
    }

    // Tell the server how a call went on this side, so a call that won't connect can be diagnosed afterwards.
    async function sendDiag(event) {
        if (!S) return;
        try {
            const st = (await statsNow()) || {};
            await api('POST', `/api/calls/${S.id}/diag`, { event, detail: Object.assign({ role: S.role, turn: !!S.hasTurn }, st) });
        } catch (e) {}
    }

    // ── call lifecycle ─────────────────────────────────────────────────────
    function cleanup(message) {
        stopTone(); flashTitle(false); hideIncoming();
        if (S) {
            clearInterval(S.timer); clearInterval(S.hb); clearTimeout(S.ringTimer); clearTimeout(S.iceFlush); clearTimeout(S.connTimer);
            try { S.pc && S.pc.close(); } catch (e) {}
            try { S.local && S.local.getTracks().forEach((t) => t.stop()); } catch (e) {}
            S = null;
        }
        if (POPUP) {
            const st = document.getElementById('call-status');
            if (st) st.textContent = message || 'Call ended';
            else toast(message || 'Call ended');
            ['call-mute', 'call-end'].forEach((id) => { const b = document.getElementById(id); if (b) { b.disabled = true; b.style.opacity = '.4'; } });
            setTimeout(() => window.close(), 1600);
            return;
        }
        removeBar();
        if (message) toast(message);
    }

    async function startInPage(userId, name, avatar) {
        if (S || incoming) { toast('You are already on a call.'); return; }
        if (!window.Call.available()) { toast('Calls are not available right now.'); return; }
        const stream = await getMic();
        if (!stream) return;
        const icePromise = fetchIce();
        const r = await api('POST', '/api/calls', { callee_id: userId });
        if (!r.ok) {
            stream.getTracks().forEach((t) => t.stop());
            toast((r.json && (r.json.error || (r.json.errors && Object.values(r.json.errors)[0][0]))) || 'Could not start the call.');
            return;
        }
        const st = r.json.call;
        S = { id: st.id, role: 'caller', peer: st.callee || { id: userId, name, avatar }, local: stream, queue: [], iceQueue: [], outIce: [], icePromise, status: 'ringing' };
        showBar(); setStatus('Calling…'); startTone('ringback');
        S.ringTimer = setTimeout(() => { if (S && S.status === 'ringing') { toast(`${S.peer.name} didn't answer.`); hangup(); } }, RING_TIMEOUT_MS);
    }

    async function acceptInPage(st) {
        if (S) return;
        stopTone(); flashTitle(false);
        const stream = await getMic();
        if (!stream) { hideIncoming(); await api('POST', `/api/calls/${st.id}/end`); return; }   // can't talk: decline cleanly
        hideIncoming();
        S = { id: st.id, role: 'callee', peer: st.caller, local: stream, queue: [], iceQueue: [], outIce: [], icePromise: fetchIce(), status: 'active' };
        showBar(); setStatus('Connecting…');
        await createPc();                                           // ready for the offer BEFORE we tell the caller
        const r = await api('POST', `/api/calls/${st.id}/accept`);
        if (!r.ok) { cleanup('This call has already ended.'); }
    }

    // Open the call window. It must happen synchronously inside the click, or the browser blocks it.
    function openPopup(params) {
        const w = window.open('/call/window?' + new URLSearchParams(params), 'ikia-call-' + Date.now(),
            'popup=yes,width=340,height=320,left=' + Math.max(0, (screen.availWidth || 1200) - 380) + ',top=80');
        return w && !w.closed ? w : null;
    }
    function start(userId, name, avatar) {
        if (S || incoming) { toast('You are already on a call.'); return; }
        if (!window.Call.available()) { toast('Calls are not available right now.'); return; }
        if (POPUP || openPopup({ mode: 'start', uid: userId, name: name || '', avatar: avatar || '' })) return;
        toast('Allow pop-ups for Desk and the call will stay open while you browse. Calling in this tab for now.');
        return startInPage(userId, name, avatar);
    }
    function accept(st) {
        stopTone(); flashTitle(false);
        if (!POPUP && openPopup({ mode: 'accept', call: st.id })) { hideIncoming(); return; }
        hideIncoming();
        return acceptInPage(st);
    }

    async function decline(id) {
        hideIncoming();
        await api('POST', `/api/calls/${id}/end`);
    }

    async function hangup() {
        if (!S) return;
        const id = S.id;
        cleanup();
        await api('POST', `/api/calls/${id}/end`);
    }

    // The server's word on a call (pushed to both people on every change).
    function onState(st) {
        if (!S || S.id !== st.id) {
            if (incoming && incoming.id === st.id && st.status !== 'ringing') hideIncoming();      // answered / cancelled elsewhere
            else if (!POPUP && !S && !incoming && st.status === 'ringing' && st.callee && st.callee.id === ME) showIncoming(st);
            return;
        }
        S.status = st.status;
        if (st.status === 'active') {
            if (S.role === 'caller' && !S.offered) {
                S.offered = true; clearTimeout(S.ringTimer); stopTone(); setStatus('Connecting…');
                (async () => {
                    const pc = await createPc(); if (!pc || !S) return;
                    const offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    await sendSignal('offer', { type: offer.type, sdp: offer.sdp });
                })();
            }
        } else if (FINAL.includes(st.status)) {
            const wasActive = !!S.connectedAt;
            const name = S.peer && S.peer.name;
            let msg = 'Call ended';
            if (!wasActive) msg = st.status === 'declined' ? `${name} declined the call.` : st.status === 'missed' ? `${name} didn't answer.` : 'Call ended';
            const mine = st.by === ME;
            cleanup(mine && wasActive ? null : msg);
        }
    }

    window.addEventListener('rt:call', (e) => { if (e.detail && e.detail.id) onState(e.detail); });
    window.addEventListener('rt:callsignal', (e) => { if (e.detail) onSignal(e.detail); });
    // Socket dropped and came back: signals may have been missed, so ask where the call stands.
    window.addEventListener('rt:resync', async () => {
        if (!S) return;
        const r = await api('GET', `/api/calls/${S.id}`);
        if (r.ok && r.json && FINAL.includes(r.json.call.status)) onState(Object.assign({}, r.json.call, { by: null }));
    });
    window.addEventListener('beforeunload', () => {
        if (!S) return;
        const fd = new FormData(); fd.append('_token', csrf());
        try { navigator.sendBeacon(`/api/calls/${S.id}/end`, fd); } catch (e) {}
    });

    // Opened from a "Incoming voice call" push notification: /?call=ID
    (async function openFromLink() {
        if (POPUP) return;
        const p = new URLSearchParams(location.search), id = p.get('call');
        if (!id) return;
        p.delete('call');
        try { history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : '') + location.hash); } catch (e) {}
        const r = await api('GET', `/api/calls/${id}`);
        if (r.ok && r.json && r.json.call.status === 'ringing' && r.json.call.callee.id === ME) onState(r.json.call);
        else if (r.ok) toast('That call has already ended.');
    })();

    // The call window: do what the URL says (place a call, or answer the one that was ringing).
    async function bootPopup() {
        const p = new URLSearchParams(location.search);
        const say = (t) => { const m = document.getElementById('call-root'); if (m && !S) m.innerHTML = '<div style="height:100%;display:flex;align-items:center;justify-content:center;padding:20px;text-align:center;color:#cbd5e1;font-size:15px;">' + t.replace(/</g, '&lt;') + '</div>'; };
        window.showToast = (m) => { say(m); setTimeout(() => window.close(), 10000); };
        say('Connecting…');
        try { await window.__rtReady; } catch (e) {}
        if (p.get('mode') === 'start') return startInPage(parseInt(p.get('uid'), 10), p.get('name'), p.get('avatar'));
        const r = await api('GET', '/api/calls/' + parseInt(p.get('call'), 10));
        const st = r.ok && r.json && r.json.call;
        if (st && st.status === 'ringing' && st.callee.id === ME) return acceptInPage(st);
        window.showToast('That call has already ended.');
    }
    if (POPUP) bootPopup();

    // ── public bits for the chat headers ───────────────────────────────────
    window.Call = {
        available: () => !!(window.RTCPeerConnection && navigator.mediaDevices && window.isSecureContext && window.Realtime && window.RT_CONFIG),
        start,
        active: () => !!S,
        popupBoot: bootPopup,
        // What the connection actually looks like (direct vs relayed, packets flowing?) — for troubleshooting a bad call.
        stats: statsNow,

        // Show the phone button in a chat header only for a one-to-one chat.
        updateButton(btnId, conv) {
            const b = document.getElementById(btnId);
            if (!b) return;
            const ok = conv && conv.type === 'direct' && conv.other_user_id && window.Call.available();
            b.style.display = ok ? '' : 'none';
            if (ok) { b.dataset.uid = conv.other_user_id; b.dataset.name = conv.name || ''; b.dataset.avatar = conv.avatar || ''; }
        },
        fromButton(b) { start(parseInt(b.dataset.uid, 10), b.dataset.name, b.dataset.avatar); },
    };
})();
}
</script>
