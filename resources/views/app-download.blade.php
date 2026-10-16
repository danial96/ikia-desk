@extends('layouts.app')

@section('title', 'Download app')
@section('page-title', 'Get the phone app')

@section('content')
@php
    $card = 'background:rgba(255,255,255,.12);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.18);border-radius:16px;padding:22px;';
    $btn  = 'display:flex;align-items:center;justify-content:center;gap:10px;padding:13px 18px;border-radius:12px;font-weight:700;font-size:15px;text-decoration:none;color:#04252b;background:#fff;border:2px solid transparent;';
@endphp

<div style="max-width:880px;margin:0 auto;display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));">

    <div style="{{ $card }}">
        <h2 style="margin:0 0 6px;color:#fff;font-size:22px;font-weight:800;">IKIA Desk on your phone</h2>
        <p style="margin:0 0 18px;color:rgba(255,255,255,.75);font-size:14px;line-height:1.5;">
            Tasks and chats, the same ones you see on this computer. Do something on either and it shows on both.
            Get an alert when someone messages you or changes a task.
        </p>

        <div style="display:grid;gap:10px;">
            @if($links['android'])
                <a id="dl-android" href="{{ $links['android'] }}" target="_blank" rel="noopener" style="{{ $btn }}">Android &middot; Google Play</a>
            @endif
            @if($links['apk'])
                <a id="dl-apk" href="{{ $links['apk'] }}" style="{{ $btn }}">Android &middot; download the app file (.apk)</a>
            @endif
            @if($links['ios'])
                <a id="dl-ios" href="{{ $links['ios'] }}" target="_blank" rel="noopener" style="{{ $btn }}">iPhone &middot; App Store / TestFlight</a>
            @endif
        </div>

        @if($links['apk'])
        <p style="margin:14px 0 0;color:rgba(255,255,255,.6);font-size:12.5px;line-height:1.5;">
            Installing the .apk: open it on your phone, and if Android asks, allow &ldquo;Install unknown apps&rdquo; for your browser (one time).
        </p>
        @endif
    </div>

    <div style="{{ $card }}display:flex;flex-direction:column;align-items:center;text-align:center;gap:12px;">
        <div id="dl-qr" style="background:#fff;padding:12px;border-radius:12px;line-height:0;" aria-label="QR code for this page"></div>
        <p style="margin:0;color:rgba(255,255,255,.8);font-size:13.5px;">On a computer? Scan this with your phone&rsquo;s camera to open this page there.</p>
    </div>

    <div style="{{ $card }}grid-column:1/-1;">
        <h3 style="margin:0 0 10px;color:#fff;font-size:16px;font-weight:800;">After installing</h3>
        <ol style="margin:0;padding-left:22px;list-style:decimal;color:rgba(255,255,255,.85);font-size:14px;line-height:1.8;">
            <li>Open IKIA Desk and sign in with the same email and password you use here.</li>
            <li>Say yes when it asks to send notifications, so you hear about messages and tasks.</li>
            <li>That&rsquo;s it &mdash; your tasks, chats and files are already there.</li>
        </ol>
    </div>
</div>

<script src="{{ asset('vendor/qrcode.js') }}"></script>
<script>
(function () {
    // QR code of this page's own address (made right here in the browser; nothing is sent anywhere)
    try {
        var qr = qrcode(0, 'M');
        qr.addData(@json(route('app.download')));
        qr.make();
        document.getElementById('dl-qr').innerHTML = qr.createSvgTag({ cellSize: 5, margin: 0, scalable: true });
        var svg = document.querySelector('#dl-qr svg');
        if (svg) { svg.style.width = '200px'; svg.style.height = '200px'; }
    } catch (e) { var q = document.getElementById('dl-qr'); if (q) q.style.display = 'none'; }

    // highlight the button that matches this device
    var ua = navigator.userAgent || '';
    var mine = /iPhone|iPad|iPod/i.test(ua) ? 'dl-ios' : (/Android/i.test(ua) ? (document.getElementById('dl-android') ? 'dl-android' : 'dl-apk') : null);
    var el = mine && document.getElementById(mine);
    if (el) { el.style.borderColor = '#3fd0c9'; el.style.boxShadow = '0 0 0 3px rgba(63,208,201,.35)'; el.parentNode.insertBefore(el, el.parentNode.firstChild); }
})();
</script>
@endsection
