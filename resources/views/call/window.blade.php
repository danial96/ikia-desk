<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Voice call — IKIA Desk</title>
    <link rel="icon" type="image/png" href="{{ asset('icon-192.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        html, body { margin: 0; height: 100%; background: #0f172a; color: #fff; overflow: hidden;
                     font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        #call-root { position: fixed; inset: 0; }
        button:disabled { cursor: default !important; }
    </style>
</head>
<body>
{{-- The call lives in this small window so it survives you clicking around in Desk: every link there is a
     full page load, which would otherwise hang up. It runs the same call module as the main pages. --}}
<div id="call-root"></div>

<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
<script>
@php
    $__rt = \App\Support\Realtime::clientConfig();
    if ($__rt) $__rt['channel'] = \App\Support\Realtime::userChannel((int) auth()->id());
@endphp
window.CALL_POPUP = true;
window.ME_ID = {{ (int) auth()->id() }};
window.RT_CONFIG = @json($__rt);

// Just enough realtime for a call: this user's private channel, and the two call events.
(function () {
    const cfg = window.RT_CONFIG;
    let ready; window.__rtReady = new Promise((r) => { ready = r; });
    window.Realtime = { connected: false, skip: () => false };
    setTimeout(ready, 7000);                                  // never hang on a blocked socket
    if (!cfg || typeof Pusher === 'undefined') return;
    const csrf = () => (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const pusher = new Pusher(cfg.key, {
        cluster: cfg.cluster, forceTLS: true,
        channelAuthorization: { endpoint: '/realtime/auth', transport: 'ajax',
            headersProvider: () => ({ 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }) },
    });
    const ch = pusher.subscribe(cfg.channel);
    const send = (name, d) => window.dispatchEvent(new CustomEvent(name, { detail: d || {} }));
    let subscribedBefore = false;
    ch.bind('pusher:subscription_succeeded', () => {
        Realtime.connected = true; ready();
        if (subscribedBefore) send('rt:resync');              // came back after a drop: catch up
        subscribedBefore = true;
    });
    pusher.connection.bind('state_change', (s) => { if (s.current !== 'connected') Realtime.connected = false; });
    ch.bind('call.state',  (d) => send('rt:call', d));
    ch.bind('call.signal', (d) => send('rt:callsignal', d));
})();
</script>
@include('partials.call')
</body>
</html>
