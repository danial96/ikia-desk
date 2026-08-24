<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'IKIA Desk') — IKIA Desk</title>
    <link rel="icon" type="image/png" href="{{ asset('logo-dark.png') }}">
    <link rel="shortcut icon" href="{{ asset('logo-dark.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo-dark.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap">
    <style>
        * { box-sizing: border-box; }
        [x-cloak] { display: none !important; }

        /* ─── Full-screen background ─── */
        body {
            margin: 0;
            font-family: 'Open Sans', 'Segoe UI', ui-sans-serif, system-ui, sans-serif;
            background: #0b0f35;
            min-height: 100vh;
            overflow-x: hidden;
        }

        #bg-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            background: #0b0f35;
            overflow: hidden;
        }

        /* Layer 1 — rich blue/purple base sweep */
        #bg-canvas .bg-base {
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 90% 70% at 10% 50%,  rgba(0,120,255,.75)   0%, transparent 55%),
                radial-gradient(ellipse 70% 80% at 90% 40%,  rgba(130,0,240,.70)   0%, transparent 55%),
                radial-gradient(ellipse 60% 50% at 50% 100%, rgba(0,60,180,.55)    0%, transparent 55%);
        }

        /* Layer 2 — bright highlights */
        #bg-canvas::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 50% 40% at 20% 20%,  rgba(0,200,255,.55)   0%, transparent 55%),
                radial-gradient(ellipse 40% 35% at 75% 70%,  rgba(180,40,255,.45)  0%, transparent 50%),
                radial-gradient(ellipse 35% 30% at 55% 45%,  rgba(0,140,255,.30)   0%, transparent 50%);
        }

        /* Layer 3 — wave / sweep shapes */
        #bg-canvas::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 120% 25% at 50% 60%, rgba(60,0,180,.35)    0%, transparent 60%),
                radial-gradient(ellipse 30% 50% at 5%  80%,  rgba(0,220,200,.30)   0%, transparent 55%);
        }

        /* Animated floating accent */
        .bg-orb3 {
            position: absolute;
            top: 15%;
            left: 30%;
            width: 500px;
            height: 300px;
            background: radial-gradient(ellipse, rgba(0,180,255,.22) 0%, transparent 65%);
            border-radius: 50%;
            animation: float3 18s ease-in-out infinite;
        }
        .bg-orb4 {
            position: absolute;
            bottom: 5%;
            right: 20%;
            width: 400px;
            height: 250px;
            background: radial-gradient(ellipse, rgba(160,0,255,.18) 0%, transparent 65%);
            border-radius: 50%;
            animation: float1 22s ease-in-out infinite reverse;
        }

        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes float1 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(30px,-40px) scale(1.06)} }
        @keyframes float2 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(-35px,30px) scale(1.08)} }
        @keyframes float3 { 0%,100%{transform:translate(0,0) scale(1)} 50%{transform:translate(20px,35px) scale(0.96)} }
        @keyframes ikiaFadeUp   { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        @keyframes ikiaFadeIn   { from{opacity:0} to{opacity:1} }
        @keyframes ikiaSlideIn  { from{opacity:0;transform:translateX(-12px)} to{opacity:1;transform:translateX(0)} }
        @keyframes ikirPulseRing{ 0%{box-shadow:0 0 0 0 rgba(0,212,232,.45)} 70%{box-shadow:0 0 0 8px rgba(0,212,232,0)} 100%{box-shadow:0 0 0 0 rgba(0,212,232,0)} }
        @keyframes shimmer      { 0%{background-position:-400px 0} 100%{background-position:400px 0} }
        @keyframes vnPulse      { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }

        /* ── Global smooth interactions ── */
        *, *::before, *::after { box-sizing: border-box; }
        html { scroll-behavior: smooth; }

        /* Page fade-in on load */
        #app-shell { animation: ikiaFadeIn .35s ease both; }

        /* GPU layer for backdrop-filter elements */
        #sidebar, #topbar { transform: translateZ(0); will-change: transform; }

        /* Card hover lift */
        .ikia-card-hover {
            transition: transform .22s cubic-bezier(.34,1.56,.64,1), box-shadow .22s ease !important;
        }
        .ikia-card-hover:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 8px 28px rgba(0,0,0,.28) !important;
        }

        /* Button active press */
        .ikia-btn { transition: opacity .18s, box-shadow .18s, transform .12s !important; }
        .ikia-btn:active { transform: scale(.96) !important; }

        /* Nav link smooth */
        .nav-link { transition: background .18s cubic-bezier(.4,0,.2,1), color .18s, padding-left .18s !important; }
        .nav-link:hover { padding-left: 22px !important; }

        /* Smooth avatar ring pulse on online */
        .avatar-online { animation: ikirPulseRing 2.4s cubic-bezier(.455,.03,.515,.955) infinite; }

        /* Content section staggered fade-in */
        .ikia-fade-1 { animation: ikiaFadeUp .38s .05s ease both; }
        .ikia-fade-2 { animation: ikiaFadeUp .38s .12s ease both; }
        .ikia-fade-3 { animation: ikiaFadeUp .38s .19s ease both; }
        .ikia-fade-4 { animation: ikiaFadeUp .38s .26s ease both; }

        /* Smooth scrollbar */
        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:rgba(255,255,255,.15); border-radius:99px; }
        ::-webkit-scrollbar-thumb:hover { background:rgba(255,255,255,.28); }

        /* Ripple button */
        .ikia-btn { position:relative; overflow:hidden; }
        .ikia-btn .ripple {
            position:absolute; border-radius:50%; transform:scale(0);
            background:rgba(255,255,255,.3); animation:rippleAnim .5s linear;
            pointer-events:none;
        }
        @keyframes rippleAnim { to { transform:scale(4); opacity:0; } }

        /* Smooth focus ring */
        input:focus, textarea:focus, select:focus {
            outline: none;
            box-shadow: 0 0 0 2.5px rgba(0,212,232,.4);
            transition: box-shadow .18s;
        }

        /* ─── Layout skeleton ─── */
        #app-shell {
            position: relative;
            z-index: 1;
            display: flex;
            min-height: 100vh;
        }

        /* ─── LEFT SIDEBAR ─── */
        #sidebar {
            width: 220px;
            flex-shrink: 0;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            background: rgba(10, 15, 60, 0.10);
            backdrop-filter: blur(28px) saturate(1.8) brightness(0.95);
            -webkit-backdrop-filter: blur(28px) saturate(1.8) brightness(0.95);
            border-right: 1px solid rgba(255,255,255,0.10);
            transition: transform .28s cubic-bezier(.4,0,.2,1);
            z-index: 50;
        }
        #sidebar.hidden-sidebar { transform: translateX(-220px); }

        .sidebar-logo {
            padding: 14px 16px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .nav-section {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.35);
            padding: 14px 16px 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 12px;
            margin: 1px 6px;
            border-radius: 8px;
            color: rgba(255,255,255,.85);
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: background .15s, color .15s;
            cursor: pointer;
        }
        .nav-link:hover {
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.9);
        }
        .nav-link.active {
            background: rgba(0,212,232,.12);
            color: #00D4E8;
            border-left: 2px solid #00D4E8;
            margin-left: 6px;
            padding-left: 10px;
        }
        .nav-link svg { width: 16px; height: 16px; flex-shrink: 0; }

        /* ─── RIGHT USER PANEL ─── */
        #right-panel {
            width: 52px;
            flex-shrink: 0;
            position: fixed;
            top: 0; right: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 14px 0;
            gap: 8px;
            background: rgba(10, 15, 60, 0.08);
            backdrop-filter: blur(28px) saturate(1.8) brightness(0.95);
            -webkit-backdrop-filter: blur(28px) saturate(1.8) brightness(0.95);
            border-left: 1px solid rgba(255,255,255,0.09);
            z-index: 50;
            overflow-y: auto;
        }
        .user-avatar-btn {
            position: relative;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid transparent;
            transition: border-color .2s, transform .2s;
            cursor: pointer;
            flex-shrink: 0;
        }
        .user-avatar-btn:hover {
            border-color: #00D4E8;
            transform: scale(1.08);
        }
        .user-avatar-btn img { width: 100%; height: 100%; object-fit: cover; }
        .online-dot {
            position: absolute;
            bottom: 1px; right: 1px;
            width: 9px; height: 9px;
            background: #22c55e;
            border-radius: 50%;
            border: 1.5px solid #080c1f;
        }

        /* ─── MAIN CONTENT ─── */
        #main-content {
            flex: 1;
            min-width: 0;
            overflow-x: hidden;
            margin-left: 220px;
            margin-right: 52px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left .28s cubic-bezier(.4,0,.2,1);
        }
        #main-content.sidebar-collapsed { margin-left: 0; }

        /* ─── TOPBAR ─── */
        #topbar {
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 16px;
            height: 50px;
            background: rgba(10, 15, 60, 0.12);
            backdrop-filter: blur(28px) saturate(1.8) brightness(0.95);
            -webkit-backdrop-filter: blur(28px) saturate(1.8) brightness(0.95);
            border-bottom: 1px solid rgba(255,255,255,0.10);
            flex-shrink: 0;
        }

        .topbar-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            color: rgba(255,255,255,.5);
            transition: background .15s, color .15s;
            cursor: pointer;
            text-decoration: none;
        }
        .topbar-btn:hover {
            background: rgba(255,255,255,.1);
            color: rgba(255,255,255,.9);
        }

        .page-title {
            font-size: 14px;
            font-weight: 600;
            color: rgba(255,255,255,.85);
        }

        /* ─── IKIA Button ─── */
        .ikia-btn {
            background: linear-gradient(135deg, #00C4D8 0%, #1B72E8 100%);
            color: #fff;
            border: none;
            border-radius: 9px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s, box-shadow .2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .ikia-btn:hover { opacity: .9; box-shadow: 0 4px 16px rgba(27,114,232,.4); }

        /* ─── Cards ─── */
        .glass-card {
            background: rgba(255,255,255,.06);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px;
        }
        .white-card {
            background: rgba(255,255,255,.94);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.2);
            box-shadow: 0 2px 12px rgba(0,0,0,.25);
        }

        /* ─── Flash ─── */
        .flash-ok  { background:rgba(34,197,94,.15);  border:1px solid rgba(34,197,94,.3);  color:#86efac; border-radius:9px; padding:10px 14px; font-size:13px; }
        .flash-err { background:rgba(239,68,68,.15);  border:1px solid rgba(239,68,68,.3);  color:#fca5a5; border-radius:9px; padding:10px 14px; font-size:13px; }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 4px; }

        /* ─── Notification panel ─── */
        #notif-panel {
            position: fixed;
            top: 54px;
            right: 60px;
            width: 380px;
            max-height: 540px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 40px rgba(0,0,0,.28);
            z-index: 2000;
            overflow: hidden;
            display: none;
            flex-direction: column;
            animation: notifSlide .18s ease;
        }
        @keyframes notifSlide { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
        #notif-list { overflow-y: auto; flex: 1; }
        #notif-list::-webkit-scrollbar { width: 3px; }
        #notif-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
        .notif-item { display:flex;align-items:flex-start;gap:12px;padding:14px 18px;cursor:pointer;border-bottom:1px solid #f8fafc;transition:background .12s; }
        .notif-item:hover { background: #f8fafc; }
        .notif-item.unread { background: #f0f9ff; }
        .notif-item.unread:hover { background: #e0f2fe; }

        /* Override Tailwind text colors for dark bg pages */
        .page-content { color: rgba(255,255,255,.85); }

        /* Stat cards on dark bg */
        .stat-card {
            background: rgba(255,255,255,.07);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 14px;
            padding: 20px;
        }
    </style>
</head>
<body x-data="appShell()" x-init="init()">

{{-- Background --}}
<div id="bg-canvas">
    <div class="bg-base"></div>
    <div class="bg-orb3"></div>
    <div class="bg-orb4"></div>
</div>

<div id="app-shell">

    {{-- ═══ LEFT SIDEBAR ═══ --}}
    <aside id="sidebar" :class="{ 'hidden-sidebar': !sidebarOpen }">

        <div class="sidebar-logo">
            <img src="{{ asset('logo-white.png') }}" alt="IKIA Desk" style="height:36px;object-fit:contain;">
        </div>

        <nav style="flex:1;overflow-y:auto;padding:8px 0;">

            <a href="{{ route('dashboard') }}"
               class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Dashboard
            </a>

            <div class="nav-section">Work</div>

            <a href="{{ route('projects.index') }}"
               class="nav-link {{ request()->routeIs('projects.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                Projects
            </a>

            <a href="{{ route('tasks.index') }}"
               class="nav-link {{ request()->routeIs('tasks.index') || request()->routeIs('tasks.show') || request()->routeIs('tasks.kanban') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Tasks
            </a>

            <div class="nav-section">Communication</div>

            <a href="{{ route('chat.index') }}"
               class="nav-link {{ request()->routeIs('chat.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                Messenger
            </a>

            @if(auth()->user()->isAdmin())
            <div class="nav-section">People</div>

            <a href="{{ route('employees.index') }}"
               class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Employees
            </a>

            <a href="{{ route('permissions.index') }}"
               class="nav-link {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Permissions
            </a>
            @endif
        </nav>

        {{-- User info --}}
        <div style="padding:12px;border-top:1px solid rgba(255,255,255,.07);">
            <div style="display:flex;align-items:center;gap:10px;">
                <a href="{{ route('profile.show') }}" style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;text-decoration:none;" title="My Profile">
                    <img src="{{ auth()->user()->avatar_url }}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid rgba(255,255,255,.15);transition:border-color .15s;" alt="">
                    <div style="flex:1;min-width:0;">
                        <p style="color:rgba(255,255,255,.85);font-size:12.5px;font-weight:600;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ auth()->user()->name }}</p>
                        <p style="color:rgba(255,255,255,.35);font-size:11px;margin:0;text-transform:capitalize;">{{ str_replace('_',' ',auth()->user()->role) }}</p>
                    </div>
                </a>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" style="background:none;border:none;color:rgba(255,255,255,.3);cursor:pointer;padding:4px;" title="Logout">
                        <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- ═══ MAIN CONTENT ═══ --}}
    <div id="main-content" :class="{ 'sidebar-collapsed': !sidebarOpen }">

        {{-- Topbar --}}
        <div id="topbar">
            <button @click="sidebarOpen = !sidebarOpen" class="topbar-btn">
                <svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <span class="page-title">@yield('page-title', 'Dashboard')</span>

            <div style="flex:1;"></div>

            {{-- Quick actions --}}
            <button onclick="chatToggle()" class="topbar-btn" id="chat-topbar-btn" title="Messenger" style="position:relative;">
                <i class="fas fa-comment-dots" style="font-size:16px;"></i>
                <span id="chat-unread-badge" style="display:none;position:absolute;top:-5px;right:-5px;min-width:16px;height:16px;background:#ef4444;color:#fff;border-radius:8px;font-size:9px;font-weight:700;align-items:center;justify-content:center;padding:0 3px;border:2px solid var(--topbar-bg,#1a1a2e);line-height:1;"></span>
            </button>

            {{-- Bell notifications --}}
            <button id="notif-btn" onclick="notifToggle()" class="topbar-btn" style="position:relative;" title="Notifications">
                <i class="fas fa-bell" style="font-size:15px;"></i>
                <span id="notif-badge" style="display:none;position:absolute;top:3px;right:3px;min-width:16px;height:16px;background:#ef4444;border-radius:8px;font-size:10px;font-weight:700;color:#fff;line-height:16px;text-align:center;padding:0 3px;border:1.5px solid rgba(10,15,60,.5);transition:transform .3s cubic-bezier(.34,1.56,.64,1);"></span>
            </button>

            <div style="width:1px;height:20px;background:rgba(255,255,255,.1);"></div>

            <a href="{{ route('profile.show') }}" style="display:flex;align-items:center;gap:8px;text-decoration:none;padding:4px 8px;border-radius:8px;transition:background .2s;" onmouseover="this.style.background='rgba(255,255,255,.08)'" onmouseout="this.style.background='transparent'">
                <img src="{{ auth()->user()->avatar_url }}"
                     style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,212,232,.4);" alt="">
                <span style="color:rgba(255,255,255,.7);font-size:13px;font-weight:500;">{{ explode(' ', auth()->user()->name)[0] }}</span>
            </a>
        </div>

        {{-- Flash messages --}}
        @if(session('success'))
        <div class="flash-ok" style="margin:12px 16px 0;" x-data x-init="setTimeout(()=>$el.remove(),4000)">
            ✓ {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="flash-err" style="margin:12px 16px 0;" x-data x-init="setTimeout(()=>$el.remove(),4000)">
            {{ session('error') }}
        </div>
        @endif

        {{-- Page content --}}
        <main style="padding:20px;flex:1;animation:ikiaFadeUp .32s ease both;">
            @yield('content')
        </main>
    </div>

    {{-- ═══ RIGHT USER PANEL ═══ --}}
    <aside id="right-panel">
        {{-- Chat icon --}}
        <button onclick="chatToggle()" title="Messenger"
                style="width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:rgba(0,212,232,.15);color:#00D4E8;margin-bottom:4px;border:none;cursor:pointer;">
            <i class="fas fa-comment-dots" style="font-size:17px;"></i>
        </button>

        <div style="width:28px;height:1px;background:rgba(255,255,255,.08);margin:4px 0;"></div>

        {{-- Team members --}}
        @isset($onlineUsers)
        @foreach($onlineUsers as $u)
        <div class="user-avatar-wrap" style="position:relative;flex-shrink:0;">
            <button type="button" class="user-avatar-btn" title="{{ $u->name }}" data-uid="{{ $u->id }}"
                    onclick="chatOpenDirect({{ $u->id }})">
                <img src="{{ $u->avatar_url }}" alt="{{ $u->name }}">
                <div class="online-dot" style="background:{{ $u->is_online ? '#22c55e' : '#6b7280' }};"></div>
            </button>
            <span class="user-msg-badge" data-uid="{{ $u->id }}"
                  style="display:none;position:absolute;top:-4px;right:-4px;min-width:17px;height:17px;background:#ef4444;color:#fff;border-radius:9px;font-size:9px;font-weight:700;align-items:center;justify-content:center;padding:0 3px;z-index:10;line-height:1;pointer-events:none;border:2px solid #0a0f3c;box-sizing:border-box;"></span>
        </div>
        @endforeach
        @endisset
    </aside>

</div>{{-- /app-shell --}}

{{-- ═══ NOTIFICATION PANEL ═══ --}}
<div id="notif-panel">
    <div style="padding:14px 18px 12px;border-bottom:1px solid #f1f3f5;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
        <span style="font-size:14px;font-weight:700;color:#111827;">Notifications</span>
        <button onclick="notifMarkAllRead()" style="background:none;border:none;color:#0ea5e9;font-size:12px;font-weight:600;cursor:pointer;padding:0;">Mark all read</button>
    </div>
    <div id="notif-list">
        <div style="padding:40px;text-align:center;color:#9ca3af;font-size:13px;">Loading...</div>
    </div>
</div>

{{-- ═══ CHAT PANEL ═══ --}}
{{-- Chat backdrop overlay (same as task panel) --}}
<div id="chat-overlay"
     style="display:none;opacity:0;position:fixed;inset:0;z-index:249;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);transition:opacity .22s ease;"
     onclick="chatClose()"></div>

{{-- Chat panel floating close button (same style as task panel) --}}
<button id="chat-close-btn" onclick="chatClose()"
    style="display:none;position:fixed;z-index:251;top:70px;width:42px;height:42px;border-radius:50%;background:#00C4D8;border:none;color:#fff;cursor:pointer;align-items:center;justify-content:center;box-shadow:0 4px 18px rgba(0,196,216,.45);transition:background .15s,transform .15s;"
    onmouseover="this.style.background='#0099aa';this.style.transform='scale(1.08)'"
    onmouseout="this.style.background='#00C4D8';this.style.transform='scale(1)'">
    <i class="fas fa-times" style="font-size:14px;"></i>
</button>

<div id="chat-panel" style="display:none;position:fixed;top:0;right:52px;bottom:0;width:calc(90% - 52px);z-index:250;display:none;flex-direction:row;box-shadow:-8px 0 40px rgba(0,0,0,.45);animation:chatSlideIn .25s cubic-bezier(.22,1,.36,1);">

    {{-- LEFT: Conversation list --}}
    <div id="chat-left" style="width:280px;flex-shrink:0;display:flex;flex-direction:column;background:#f0f4f8;border-right:1px solid #e2e8f0;">

        {{-- Header --}}
        <div style="padding:14px 14px 10px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:8px;flex-shrink:0;background:#fff;">
            <i class="fas fa-comment-dots" style="color:#0891b2;font-size:16px;"></i>
            <span style="color:#1e293b;font-size:14px;font-weight:700;flex:1;">Messenger</span>
        </div>

        {{-- Search --}}
        <div style="padding:10px 12px 8px;flex-shrink:0;">
            <div style="display:flex;align-items:center;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:0 10px;gap:6px;">
                <i class="fas fa-search" style="font-size:11px;color:#94a3b8;"></i>
                <input type="text" id="chat-search-input" placeholder="Search chats..."
                       oninput="chatFilterConvs(this.value)"
                       style="background:none;border:none;outline:none;color:#1e293b;font-size:12.5px;padding:7px 0;width:100%;font-family:inherit;">
            </div>
        </div>

        {{-- New chat actions --}}
        <div style="padding:0 12px 8px;display:flex;gap:6px;flex-shrink:0;">
            <button onclick="chatNewDirect()" style="flex:1;background:#e0f7fa;border:1px solid #b2ebf2;color:#0891b2;border-radius:7px;padding:5px 0;font-size:11.5px;font-weight:600;cursor:pointer;">
                <i class="fas fa-user-plus" style="font-size:10px;margin-right:3px;"></i>Direct
            </button>
            <button onclick="chatNewGroup()" style="flex:1;background:#ede9fe;border:1px solid #ddd6fe;color:#7c3aed;border-radius:7px;padding:5px 0;font-size:11.5px;font-weight:600;cursor:pointer;">
                <i class="fas fa-users" style="font-size:10px;margin-right:3px;"></i>Group
            </button>
        </div>

        {{-- Conversation list --}}
        <div id="chat-conv-list" style="flex:1;overflow-y:auto;">
            <div style="padding:30px 16px;text-align:center;color:rgba(255,255,255,.6);font-size:12px;">
                <i class="fas fa-spinner fa-spin" style="display:block;font-size:20px;margin-bottom:8px;"></i>Loading...
            </div>
        </div>
    </div>

    {{-- RIGHT: Active conversation --}}
    <div id="chat-right" style="flex:1;display:flex;flex-direction:column;background:#f8fafc;">

        {{-- Placeholder when no conv selected --}}
        <div id="chat-right-empty" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#cbd5e1;">
            <i class="fas fa-comments" style="font-size:48px;margin-bottom:14px;"></i>
            <p style="font-size:13px;margin:0;">Select a conversation to start chatting</p>
        </div>

        {{-- Header (hidden until conv selected) --}}
        <div id="chat-right-head" style="display:none;padding:12px 16px;border-bottom:1px solid #e2e8f0;display:none;align-items:center;gap:10px;background:#fff;flex-shrink:0;">
            <div id="chat-rh-avatar" style="width:34px;height:34px;border-radius:50%;overflow:hidden;flex-shrink:0;background:#e0f7fa;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-user" style="font-size:14px;color:#0891b2;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <p id="chat-rh-name" style="margin:0;color:#1e293b;font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
                <p id="chat-rh-sub" style="margin:0;color:#94a3b8;font-size:11px;"></p>
            </div>
        </div>

        {{-- Messages --}}
        <div id="chat-msg-area" style="flex:1;overflow-y:auto;padding:16px 24px;display:none;flex-direction:column;gap:2px;background-image:url('{{ asset('pattern-chat.svg') }}');background-size:cover;background-position:center;background-color:#e8f4f8;"></div>

        {{-- Input --}}
        <div id="chat-input-area" style="display:none;padding:8px 12px 10px;border-top:1px solid #e2e8f0;background:#fff;flex-shrink:0;">
            {{-- Normal input --}}
            <div id="chat-normal-input" style="display:flex;align-items:flex-end;gap:8px;">
                <div style="flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                    <textarea id="chat-textarea" rows="1" placeholder="Type a message..."
                              style="display:block;width:100%;background:none;border:none;color:#1e293b;font-size:13px;padding:9px 12px 6px;outline:none;resize:none;font-family:inherit;line-height:1.45;max-height:120px;overflow-y:auto;box-sizing:border-box;"
                              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();chatSend();}"
                              oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"></textarea>
                    <div style="display:flex;align-items:center;gap:2px;padding:4px 8px;border-top:1px solid #e2e8f0;">
                        <button type="button" onclick="emojiToggle('chat-textarea',this)"
                                title="Emoji" style="background:none;border:none;color:#94a3b8;cursor:pointer;padding:3px 5px;border-radius:6px;font-size:15px;line-height:1;transition:color .12s;"
                                onmouseover="this.style.color='#0891b2'" onmouseout="this.style.color='#94a3b8'">
                            <i class="far fa-smile-beam"></i>
                        </button>
                        <button type="button" onclick="document.getElementById('chat-file-input').click()"
                                title="Attach file" style="background:none;border:none;color:#94a3b8;cursor:pointer;padding:3px 5px;border-radius:6px;font-size:14px;line-height:1;transition:color .12s;"
                                onmouseover="this.style.color='#0891b2'" onmouseout="this.style.color='#94a3b8'">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input type="file" id="chat-file-input" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                               onchange="uploadAndInsert('chat-textarea','chat-file-input','chat-attach-preview')">
                    </div>
                    <div id="chat-attach-preview" style="display:none;padding:6px 8px 4px;gap:8px;flex-wrap:wrap;border-top:1px solid #e2e8f0;"></div>
                </div>
                <button onclick="vnStart('chat')" title="Voice note"
                        style="width:38px;height:38px;border-radius:10px;background:#e2e8f0;border:1px solid #cbd5e1;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;"
                        onmouseover="this.style.background='#cbd5e1';this.style.color='#334155'" onmouseout="this.style.background='#e2e8f0';this.style.color='#64748b'">
                    <i class="fas fa-microphone" style="font-size:14px;"></i>
                </button>
                <button onclick="chatSend()" style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#00C4D8,#1B72E8);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-paper-plane" style="font-size:13px;"></i>
                </button>
            </div>
            {{-- Recording UI --}}
            <div id="chat-vn-rec" style="display:none;align-items:center;gap:8px;">
                <button onclick="vnCancel('chat')" title="Cancel"
                        style="width:38px;height:38px;border-radius:10px;background:#fee2e2;border:1px solid #fca5a5;color:#ef4444;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;"
                        onmouseover="this.style.background='#fca5a5'" onmouseout="this.style.background='#fee2e2'">
                    <i class="fas fa-trash" style="font-size:13px;"></i>
                </button>
                <div style="flex:1;display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:9px 14px;">
                    <div style="width:8px;height:8px;border-radius:50%;background:#ef4444;animation:vnPulse 1.2s ease-in-out infinite;flex-shrink:0;"></div>
                    <span style="color:#64748b;font-size:12px;">Recording</span>
                    <span id="chat-vn-timer" style="color:#1e293b;font-weight:700;font-size:13px;min-width:30px;margin-left:auto;">0:00</span>
                </div>
                <button onclick="vnSend('chat')" title="Send"
                        style="width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#00C4D8,#1B72E8);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .15s;"
                        onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-check" style="font-size:14px;"></i>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Message context menu --}}
<div id="chat-msg-ctx"></div>

{{-- New Direct Chat overlay (inside panel) --}}
<div id="chat-new-direct-modal" style="display:none;position:fixed;top:0;right:52px;bottom:0;width:calc(90% - 52px);z-index:251;background:rgba(8,12,45,.97);backdrop-filter:blur(28px);flex-direction:column;">
    <div style="padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;">
        <button onclick="chatCloseNewDirect()" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:14px;"><i class="fas fa-arrow-left"></i></button>
        <span style="color:#fff;font-size:14px;font-weight:700;">New Direct Message</span>
    </div>
    <div style="padding:12px 18px;border-bottom:1px solid rgba(255,255,255,.08);">
        <div style="display:flex;align-items:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:0 10px;gap:6px;">
            <i class="fas fa-search" style="font-size:11px;color:rgba(255,255,255,.3);"></i>
            <input type="text" id="chat-emp-search" placeholder="Search employees..."
                   oninput="chatFilterEmps(this.value)"
                   style="background:none;border:none;outline:none;color:#fff;font-size:13px;padding:8px 0;width:100%;font-family:inherit;">
        </div>
    </div>
    <div id="chat-emp-list" style="flex:1;overflow-y:auto;padding:8px 0;">
        <div style="padding:30px;text-align:center;color:rgba(255,255,255,.25);font-size:12px;"><i class="fas fa-spinner fa-spin"></i></div>
    </div>
</div>

{{-- New Group Chat overlay --}}
<div id="chat-new-group-modal" style="display:none;position:fixed;top:0;right:52px;bottom:0;width:calc(90% - 52px);z-index:251;background:rgba(8,12,45,.97);backdrop-filter:blur(28px);flex-direction:column;">
    <div style="padding:16px 18px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;">
        <button onclick="chatCloseNewGroup()" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:14px;"><i class="fas fa-arrow-left"></i></button>
        <span style="color:#fff;font-size:14px;font-weight:700;">New Group Chat</span>
    </div>
    <div style="padding:14px 18px;flex:1;overflow-y:auto;">
        <div style="margin-bottom:14px;">
            <label style="display:block;color:rgba(255,255,255,.45);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Group Name</label>
            <input type="text" id="chat-group-name" placeholder="e.g. Marketing Team"
                   style="width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;color:#fff;font-size:13px;padding:9px 12px;outline:none;font-family:inherit;box-sizing:border-box;">
        </div>
        <div>
            <label style="display:block;color:rgba(255,255,255,.45);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Members</label>
            <div id="chat-group-members" style="display:flex;flex-direction:column;gap:4px;max-height:360px;overflow-y:auto;"></div>
        </div>
    </div>
    <div style="padding:12px 18px;border-top:1px solid rgba(255,255,255,.08);">
        <button onclick="chatCreateGroup()" style="width:100%;background:linear-gradient(135deg,#00C4D8,#1B72E8);border:none;color:#fff;border-radius:9px;padding:10px;font-size:13px;font-weight:700;cursor:pointer;">
            <i class="fas fa-users" style="margin-right:6px;"></i>Create Group
        </button>
    </div>
</div>

<style>
#chat-panel { display:none; }
#chat-panel.chat-open { display:flex !important; }
#chat-new-direct-modal.chat-open { display:flex !important; }
#chat-new-group-modal.chat-open  { display:flex !important; }
@keyframes chatSlideIn { from{transform:translateX(100%);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes chatPopupIn  { from{transform:translateX(24px);opacity:0} to{transform:translateX(0);opacity:1} }
@keyframes chatPopupOut { from{opacity:1;transform:translateX(0)} to{opacity:0;transform:translateX(24px)} }
@keyframes vnPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
.chat-conv-item { display:flex;align-items:center;gap:10px;padding:10px 12px;cursor:pointer;border-bottom:1px solid #e2e8f0;transition:background .12s; }
.chat-conv-item:hover { background:#e8f4f8; }
.chat-conv-item.active { background:#e0f7fa;border-left:2px solid #0891b2; }
.chat-conv-unread { background:#f0fdf4;border-left:2px solid #22c55e !important; }
.chat-conv-unread:hover { background:#dcfce7 !important; }
@keyframes chatFlash { 0%{background:rgba(34,197,94,.2)} 100%{background:#f0fdf4} }
.chat-msg-actions { display:none;align-items:center;gap:3px;flex-shrink:0; }
.chat-msg-outer:hover .chat-msg-actions { display:flex; }
.chat-action-btn { width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,.13);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:11px;color:#374151;line-height:1;transition:background .12s;padding:0; }
.chat-action-btn:hover { background:rgba(0,0,0,.22); }
#chat-msg-ctx { display:none;position:fixed;z-index:9999;background:#fff;border:1px solid #e2e8f0;border-radius:9px;padding:4px 0;min-width:130px;box-shadow:0 8px 28px rgba(0,0,0,.12); }
.chat-ctx-item { display:flex;align-items:center;gap:8px;padding:8px 14px;color:#374151;font-size:13px;cursor:pointer;transition:background .1s; }
.chat-ctx-item:hover { background:#f1f5f9; }
.chat-ctx-del { color:#ef4444; }
#chat-conv-list::-webkit-scrollbar { width:3px; }
#chat-conv-list::-webkit-scrollbar-thumb { background:#cbd5e1;border-radius:3px; }
#chat-msg-area::-webkit-scrollbar { width:3px; }
#chat-msg-area::-webkit-scrollbar-thumb { background:rgba(255,255,255,.12);border-radius:3px; }
#chat-textarea::placeholder { color:rgba(255,255,255,.28); }
#chat-emp-search::placeholder { color:rgba(255,255,255,.28); }
#chat-search-input { }
</style>

<script>
(function(){
let _chatOpen  = false;
let _activeConvId = null;
let _allConvs  = [];
let _allEmps   = [];
let _pollTimer = null;
let _lastMsgId = 0;
const ME_ID = {{ auth()->id() }};

/* ── Open / Close ── */
window.chatToggle = function() {
    // On full-page Messenger, don't open popup — navigate there if needed
    if (document.getElementById('cp-wrap')) return;
    _chatOpen ? chatClose() : chatOpen();
};

window.chatOpenDirect = async function(userId) {
    // On the full-page Messenger, use it directly instead of popup
    if (document.getElementById('cp-wrap') && window.cpStartDirect) {
        window.cpStartDirect(userId);
        return;
    }
    if (!_chatOpen) chatOpen();
    for (let i = 0; i < 30 && !_allConvs.length; i++) {
        await new Promise(r => setTimeout(r, 100));
    }
    chatStartDirect(userId);
};

window.chatOpen = function() {
    if (_chatOpen) return;
    _chatOpen = true;
    const p       = document.getElementById('chat-panel');
    const btn     = document.getElementById('chat-close-btn');
    const overlay = document.getElementById('chat-overlay');
    p.classList.add('chat-open');
    p.style.animation = 'chatSlideIn .25s cubic-bezier(.22,1,.36,1)';
    // Backdrop fade-in
    if (overlay) { overlay.style.display = 'block'; void overlay.offsetWidth; overlay.style.opacity = '1'; }
    // Position close button just outside the left edge of the chat panel
    if (btn) {
        const panelLeft = window.innerWidth - (window.innerWidth * 0.9);
        btn.style.left  = (panelLeft - 58) + 'px';
        btn.style.display = 'flex';
    }
    chatLoadConvs();
    _pollTimer = setInterval(chatPoll, 3000);
};

window.chatClose = function() {
    if (!_chatOpen) return;
    _chatOpen = false;
    document.getElementById('chat-panel').classList.remove('chat-open');
    document.getElementById('chat-new-direct-modal').classList.remove('chat-open');
    document.getElementById('chat-new-group-modal').classList.remove('chat-open');
    const btn     = document.getElementById('chat-close-btn');
    const overlay = document.getElementById('chat-overlay');
    if (btn) btn.style.display = 'none';
    if (overlay) { overlay.style.opacity = '0'; setTimeout(() => { overlay.style.display = 'none'; }, 220); }
    if (_pollTimer) { clearInterval(_pollTimer); _pollTimer = null; }
};

/* ── Conversations ── */
async function chatLoadConvs() {
    try {
        const r = await fetch(API_BASE + '/api/chat/convs');
        const d = await r.json();
        const prevUnread = Object.fromEntries(_allConvs.map(c => [c.id, c.unread || 0]));
        _allConvs = d.convs || [];
        const totalUnread = _allConvs.reduce((s,c) => s+(c.unread||0), 0);
        chatUpdateBadge(totalUnread);
        chatUpdateUserBadges();
        if (_chatOpen) chatRenderConvs(_allConvs);
        _allConvs.forEach(c => {
            if ((c.unread || 0) > (prevUnread[c.id] || 0)) {
                chatPlaySound();
                chatShowMsgPopup(c);
                if (_chatOpen) {
                    const row = document.querySelector(`.chat-conv-item[data-id="${c.id}"]`);
                    if (row) { row.style.animation='none'; void row.offsetWidth; row.style.animation='chatFlash .6s ease-out forwards'; }
                }
            }
        });
    } catch(e) {
        const el = document.getElementById('chat-conv-list');
        if (el) el.innerHTML =
            '<div style="padding:20px;text-align:center;color:rgba(255,82,82,.7);font-size:12px;">Failed to load</div>';
    }
}

/* ── Per-user message badges on right panel ── */
function chatUpdateUserBadges() {
    document.querySelectorAll('.user-msg-badge').forEach(b => {
        b.style.display = 'none';
        b.textContent = '';
    });
    _allConvs.forEach(c => {
        if (c.type === 'direct' && (c.unread || 0) > 0 && c.other_user_id) {
            const badge = document.querySelector('.user-msg-badge[data-uid="' + c.other_user_id + '"]');
            if (badge) {
                badge.textContent = c.unread > 99 ? '99+' : c.unread;
                badge.style.display = 'flex';
            }
        }
    });
}

/* ── Floating popup notification for new messages ── */
function chatShowMsgPopup(conv) {
    if (_chatOpen && _activeConvId === conv.id) return; // already viewing this conv
    const pid = 'cmsgpop-' + conv.id;
    const old = document.getElementById(pid);
    if (old) old.remove();
    const stackCount = document.querySelectorAll('.cmsg-popup').length;
    const popup = document.createElement('div');
    popup.id = pid;
    popup.className = 'cmsg-popup';
    const initials = (conv.name || '?').split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
    const eName = (conv.name || '').replace(/&/g,'&amp;').replace(/</g,'&lt;');
    const rawText = conv.lastMsg ? conv.lastMsg.text || '' : 'New message';
    const ePreview = previewText(rawText).substring(0, 48).replace(/&/g,'&amp;').replace(/</g,'&lt;');
    popup.style.cssText = 'position:fixed;top:' + (58 + stackCount * 78) + 'px;right:62px;'
        + 'background:rgba(8,12,52,.97);backdrop-filter:blur(20px);'
        + 'border:1px solid rgba(0,212,232,.3);border-radius:14px;'
        + 'padding:11px 13px;z-index:9997;width:250px;cursor:pointer;'
        + 'box-shadow:0 8px 40px rgba(0,0,0,.65);'
        + 'animation:chatPopupIn .28s cubic-bezier(.22,1,.36,1) forwards;';
    popup.innerHTML = '<div style="display:flex;align-items:center;gap:10px;">'
        + '<div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,rgba(0,212,232,.3),rgba(27,114,232,.3));'
        +   'flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#00D4E8;">' + initials + '</div>'
        + '<div style="flex:1;min-width:0;">'
        +   '<div style="color:#fff;font-size:12.5px;font-weight:600;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + eName + '</div>'
        +   '<div style="color:rgba(255,255,255,.5);font-size:11.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + ePreview + '</div>'
        + '</div>'
        + '<button class="cmsgpop-x" style="background:none;border:none;color:rgba(255,255,255,.35);cursor:pointer;font-size:18px;padding:0 0 0 6px;line-height:1;flex-shrink:0;">×</button>'
        + '</div>';
    popup.querySelector('.cmsgpop-x').addEventListener('click', function(e) {
        e.stopPropagation();
        popup.remove();
    });
    popup.addEventListener('click', function() {
        popup.remove();
        if (conv.type === 'direct' && conv.other_user_id) {
            chatOpenDirect(conv.other_user_id);
        } else {
            if (!_chatOpen) chatOpen();
        }
    });
    document.body.appendChild(popup);
    setTimeout(function() {
        if (popup.parentNode) {
            popup.style.animation = 'chatPopupOut .22s ease-in forwards';
            setTimeout(() => popup.remove(), 220);
        }
    }, 5000);
}

/* ── Send sound (short soft swoosh) ── */
function chatSendSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(900, ctx.currentTime);
        osc.frequency.exponentialRampToValueAtTime(600, ctx.currentTime + 0.12);
        gain.gain.setValueAtTime(0, ctx.currentTime);
        gain.gain.linearRampToValueAtTime(0.07, ctx.currentTime + 0.01);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.2);
    } catch(e) {}
}

function previewText(t) {
    return (t||'')
        .replace(/\[img\].*?\[\/img\]/gs,    '📷 Photo')
        .replace(/\[file name="[^"]*"\].*?\[\/file\]/gs, '📎 File')
        .replace(/\[voice(?:\s+dur="[^"]*")?\].*?\[\/voice\]/gs, '🎤 Voice')
        .replace(/\[URL=([^\]]*)\]([\s\S]*?)\[\/URL\]/gi, '$2')
        .replace(/\[URL\]([\s\S]*?)\[\/URL\]/gi, '$1')
        .replace(/\[\/?(B|I|S|U|CODE)\]/gi, '');
}

function chatRenderConvs(list) {
    const el = document.getElementById('chat-conv-list');
    if (!list.length) {
        el.innerHTML = '<div style="padding:30px 16px;text-align:center;color:#94a3b8;font-size:12px;">No conversations yet</div>';
        return;
    }
    el.innerHTML = list.map(c => {
        const avatar  = convAvatar(c, 32);
        const unread  = (c.unread && _activeConvId !== c.id) ? c.unread : 0;
        const lastLine = c.lastMsg
            ? `<span style="color:${unread?'#0f172a':'#64748b'};font-size:11px;font-weight:${unread?'600':'400'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                 ${c.lastMsg.byMe ? 'You: ' : (c.type!=='direct'?escH(c.lastMsg.senderName||'')+': ':'')}${escH(previewText(c.lastMsg.text).substring(0,40))}
               </span>`
            : `<span style="color:#94a3b8;font-size:11px;">No messages yet</span>`;
        const timeStr = c.lastMsg ? `<span style="color:${unread?'#0891b2':'#94a3b8'};font-size:10.5px;flex-shrink:0;">${c.lastMsg.time}</span>` : '';
        const badge   = unread ? `<div style="min-width:18px;height:18px;border-radius:9px;background:#0891b2;color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 4px;flex-shrink:0;">${unread>99?'99+':unread}</div>` : '';
        const isActive = _activeConvId === c.id;
        return `<div class="chat-conv-item${isActive?' active':''}${unread?' chat-conv-unread':''}" onclick="chatSelectConv(${c.id})" data-id="${c.id}">
            ${avatar}
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
                    <span style="color:#1e293b;font-size:12.5px;font-weight:${unread?'700':'600'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:110px;">${escH(c.name)}</span>
                    ${timeStr}
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:4px;">
                    <div style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${lastLine}</div>
                    ${badge}
                </div>
            </div>
        </div>`;
    }).join('');
}

function convAvatar(c, size) {
    const s = size + 'px';
    if (c.type === 'general') {
        return `<div style="width:${s};height:${s};border-radius:50%;background:rgba(0,212,232,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-globe" style="font-size:${size*0.45}px;color:#00D4E8;"></i></div>`;
    }
    if (c.type === 'group') {
        return `<div style="width:${s};height:${s};border-radius:50%;background:rgba(139,92,246,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-users" style="font-size:${size*0.4}px;color:#a78bfa;"></i></div>`;
    }
    if (c.avatar) {
        return `<img src="${c.avatar}" style="width:${s};height:${s};border-radius:50%;object-fit:cover;flex-shrink:0;">`;
    }
    const initials = c.name.split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
    return `<div style="width:${s};height:${s};border-radius:50%;background:rgba(27,114,232,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#60a5fa;font-size:${size*0.38}px;font-weight:700;">${initials}</div>`;
}

window.chatFilterConvs = function(q) {
    if (!q) { chatRenderConvs(_allConvs); return; }
    chatRenderConvs(_allConvs.filter(c => c.name.toLowerCase().includes(q.toLowerCase())));
};

/* ── Select conversation ── */
window.chatSelectConv = async function(id) {
    _activeConvId = id;
    _lastMsgId = 0;
    // Mark active in list
    document.querySelectorAll('.chat-conv-item').forEach(el => {
        const match = +el.dataset.id === id;
        el.classList.toggle('active', match);
        if (match) el.classList.remove('chat-conv-unread');
    });
    // Show loader in messages area
    const msgArea = document.getElementById('chat-msg-area');
    msgArea.innerHTML = '<div style="padding:30px;text-align:center;color:rgba(255,255,255,.25);font-size:12px;"><i class="fas fa-spinner fa-spin"></i></div>';
    msgArea.style.display = 'flex';
    document.getElementById('chat-right-empty').style.display = 'none';
    document.getElementById('chat-right-head').style.display  = 'flex';
    document.getElementById('chat-input-area').style.display  = 'block';

    try {
        const r = await fetch(API_BASE + '/api/chat/convs/' + id + '/msgs');
        const d = await r.json();
        // Update header
        const conv = d.conv;
        chatUpdateHeader(conv);
        const msgs = d.messages || [];
        chatRenderMsgs(msgs);
        if (msgs.length) _lastMsgId = Math.max(...msgs.map(m => m.id));
    } catch(e) {
        msgArea.innerHTML = '<div style="padding:20px;text-align:center;color:rgba(255,82,82,.7);font-size:12px;">Failed to load messages</div>';
    }
};

function chatUpdateHeader(conv) {
    const avatarEl = document.getElementById('chat-rh-avatar');
    const nameEl   = document.getElementById('chat-rh-name');
    const subEl    = document.getElementById('chat-rh-sub');

    nameEl.textContent = conv.name || 'Chat';

    if (conv.type === 'general') {
        avatarEl.innerHTML = '<i class="fas fa-globe" style="font-size:16px;color:#00D4E8;"></i>';
        avatarEl.style.background = 'rgba(0,212,232,.15)';
        subEl.textContent = 'General channel';
    } else if (conv.type === 'group') {
        avatarEl.innerHTML = '<i class="fas fa-users" style="font-size:14px;color:#a78bfa;"></i>';
        avatarEl.style.background = 'rgba(139,92,246,.15)';
        subEl.textContent = conv.members + ' members';
    } else {
        // Direct — find conv in list for avatar
        const c = _allConvs.find(x => x.id === conv.id);
        if (c && c.avatar) {
            avatarEl.innerHTML = `<img src="${c.avatar}" style="width:100%;height:100%;object-fit:cover;">`;
            avatarEl.style.background = 'transparent';
        } else {
            const initials = (conv.name||'?').split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
            avatarEl.innerHTML = `<span style="font-size:13px;font-weight:700;color:#60a5fa;">${initials}</span>`;
            avatarEl.style.background = 'rgba(27,114,232,.2)';
        }
        subEl.textContent = 'Direct message';
    }
}

/* ── Messages ── */
function chatRenderMsgs(msgs) {
    const el = document.getElementById('chat-msg-area');
    if (!msgs.length) {
        el.innerHTML = '<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:rgba(255,255,255,.7);font-size:12px;font-weight:500;"><i class="fas fa-comment-dots" style="font-size:28px;color:rgba(255,255,255,.4);"></i>No messages yet — say hello!</div>';
        return;
    }

    let html = '';
    let prevDate  = null;
    let prevAuthorId = null;

    msgs.forEach((m, i) => {
        // Date divider
        if (m.date !== prevDate) {
            const label = chatDayLabel(m.date);
            html += `<div style="display:flex;align-items:center;justify-content:center;margin:12px 0 8px;">
                <span style="background:rgba(0,0,0,.18);color:#fff;font-size:11px;font-weight:600;padding:3px 14px;border-radius:20px;letter-spacing:.3px;white-space:nowrap;">${label}</span>
            </div>`;
            prevDate = m.date;
            prevAuthorId = null;
        }

        const showName = !m.isMine && prevAuthorId !== m.author.id;
        html += chatBubble({isMine: m.isMine, name: m.author.name, avatar: m.author.avatar, text: m.text, time: m.time, showName, msgId: m.id, createdTs: m.createdTs||0});
        prevAuthorId = m.author.id;
    });

    el.innerHTML = `<div style="margin-top:auto;display:flex;flex-direction:column;gap:2px;">${html}</div>`;
    const goBottomChat = () => { el.scrollTop = el.scrollHeight + 9999; };
    goBottomChat();
    el.querySelectorAll('img').forEach(function(img) {
        if (!img.complete) img.addEventListener('load', goBottomChat, { once: true });
    });
    // Unify all images in this conversation into one gallery
    const _cImgs = [...el.querySelectorAll('img[style*="cursor:zoom-in"]')];
    if (_cImgs.length > 1 && window.registerGallery) {
        const _cGk = window.registerGallery(_cImgs.map(i => i.src));
        _cImgs.forEach((img, idx) => img.setAttribute('onclick', `imgLightbox(${_cGk},${idx})`));
    }
}

function chatDayLabel(dateStr) {
    const _pad = n => String(n).padStart(2,'0');
    const _n = new Date(); const today = _n.getFullYear()+'-'+_pad(_n.getMonth()+1)+'-'+_pad(_n.getDate());
    const _y = new Date(Date.now()-86400000); const yest = _y.getFullYear()+'-'+_pad(_y.getMonth()+1)+'-'+_pad(_y.getDate());
    if (dateStr === today)  return 'Today';
    if (dateStr === yest)   return 'Yesterday';
    return new Date(dateStr).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'});
}

function renderMsgContent(text, isMine) {
    const parts = text.split(/(\[img\].*?\[\/img\]|\[file name="[^"]*"\].*?\[\/file\]|\[voice(?:\s+dur="[^"]*")?\].*?\[\/voice\]|\[URL=[^\]]*\].*?\[\/URL\]|\[URL\].*?\[\/URL\])/gi);
    const allImgs = [];
    parts.forEach(p => { const m = p.match(/^\[img\](.*?)\[\/img\]$/i); if (m) allImgs.push(m[1]); });
    const galKey = allImgs.length > 1 && window.registerGallery ? window.registerGallery(allImgs) : null;
    let out = '', imgIdx = 0;
    parts.forEach(part => {
        const imgM   = part.match(/^\[img\](.*?)\[\/img\]$/i);
        const fileM  = part.match(/^\[file name="([^"]*)"\](.*?)\[\/file\]$/i);
        const voiceM = part.match(/^\[voice(?:\s+dur="([^"]*)")?\](.*?)\[\/voice\]$/i);
        const urlAtM = part.match(/^\[URL=([^\]]*)\](.*?)\[\/URL\]$/i);
        const urlM   = part.match(/^\[URL\](.*?)\[\/URL\]$/i);
        if (imgM) {
            const ci = imgIdx++;
            const fn = galKey !== null ? `imgLightbox(${galKey},${ci})` : `imgLightbox('${escH(imgM[1])}',0)`;
            out += `<img src="${escH(imgM[1])}" style="max-width:280px;max-height:220px;object-fit:cover;border-radius:8px;display:block;margin:4px 0;cursor:zoom-in;transition:opacity .15s;" loading="lazy" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'" onclick="${fn}">`;
        } else if (fileM) {
            out += `<a href="${escH(fileM[2])}" target="_blank" rel="noopener"
                style="display:inline-flex;align-items:center;gap:6px;background:rgba(0,0,0,.12);border-radius:7px;padding:5px 10px;color:inherit;text-decoration:none;font-size:11.5px;margin:3px 0;">
                <i class="fas fa-file" style="opacity:.7;"></i>${escH(fileM[1])}
            </a>`;
        } else if (voiceM) {
            out += window.voiceBubbleHtml ? window.voiceBubbleHtml(voiceM[2]||'', voiceM[1]||'0:00', !!isMine) : '';
        } else if (urlAtM) {
            const _su1 = /^https?:\/\//i.test(urlAtM[1]) ? urlAtM[1] : '#';
            out += `<a href="${escH(_su1)}" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;">${escH(urlAtM[2]||urlAtM[1])}</a>`;
        } else if (urlM) {
            const _su2 = /^https?:\/\//i.test(urlM[1]) ? urlM[1] : '#';
            out += `<a href="${escH(_su2)}" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;">${escH(urlM[1])}</a>`;
        } else if (part) {
            out += `<span style="white-space:pre-wrap;word-break:break-word;">${linkify(part)}</span>`;
        }
    });
    return out;
}

function chatBubble({isMine, name, avatar, text, time, showName=true, msgId=null, createdTs=0}) {
    const content = renderMsgContent(text, isMine);
    if (isMine) {
        const rawEsc = text.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
        const actions = msgId ? `<div class="chat-msg-actions">
            <button class="chat-action-btn" onclick="chatLikeClick(event,this,${msgId})" title="Like">👍</button>
            <button class="chat-action-btn" onclick="chatDotsClick(event,this,${msgId},true,${createdTs})" title="More">⋯</button>
        </div>` : '';
        return `<div data-msg-id="${msgId||''}" data-mine="1" data-created-ts="${createdTs}" class="chat-msg-outer" style="display:flex;justify-content:flex-end;align-items:center;gap:4px;margin-bottom:2px;">
            ${actions}
            <div style="max-width:45%;">
                <div class="chat-bubble-bg" style="background:rgba(200,240,210,.92);border-radius:14px 4px 14px 14px;padding:8px 12px;">
                    <div data-raw="${rawEsc}" style="font-size:13px;color:#1a3025;line-height:1.5;">${content}</div>
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:4px;margin-top:3px;">
                        <span style="font-size:10px;color:rgba(0,0,0,.35);">${time}</span>
                        <i class="fas fa-check-double" style="font-size:9px;color:rgba(0,120,80,.5);"></i>
                    </div>
                </div>
            </div>
        </div>`;
    } else {
        const avatarHtml = avatar
            ? `<img src="${avatar}" style="width:26px;height:26px;border-radius:50%;object-fit:cover;flex-shrink:0;margin-top:2px;">`
            : `<div style="width:26px;height:26px;border-radius:50%;background:rgba(100,116,139,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;font-size:10px;color:#94a3b8;">${name[0]||'?'}</div>`;
        const nameHtml = showName
            ? `<span style="font-size:11px;color:#64748b;font-weight:600;display:block;margin-bottom:2px;">${escH(name)}</span>`
            : '';
        const actionsOther = msgId ? `<div class="chat-msg-actions">
            <button class="chat-action-btn" onclick="chatLikeClick(event,this,${msgId})" title="Like">👍</button>
            <button class="chat-action-btn" onclick="chatDotsClick(event,this,${msgId},false,${createdTs})" title="More">⋯</button>
        </div>` : '';
        return `<div data-msg-id="${msgId||''}" data-mine="0" data-created-ts="${createdTs}" class="chat-msg-outer" style="display:flex;align-items:center;gap:4px;margin-bottom:2px;${showName?'margin-top:6px':''}">
            ${avatarHtml}
            <div style="max-width:45%;">
                ${nameHtml}
                <div class="chat-bubble-bg" style="background:rgba(255,255,255,.92);border-radius:4px 14px 14px 14px;padding:8px 12px;">
                    <div style="font-size:13px;color:#1e293b;line-height:1.5;">${content}</div>
                    <span style="font-size:10px;color:rgba(0,0,0,.35);display:block;margin-top:3px;text-align:right;">${time}</span>
                </div>
            </div>
            ${actionsOther}
        </div>`;
    }
}

let _chatCtxMsgId = null, _chatCtxIsMine = false;
window.chatDotsClick = function(e, btn, msgId, isMine, createdTs) {
    e.stopPropagation();
    _chatCtxMsgId = msgId;
    _chatCtxIsMine = isMine;
    const canEdit = isMine && (Date.now()/1000 - createdTs) < 86400;
    const menu = document.getElementById('chat-msg-ctx');
    menu.innerHTML =
        `<div class="chat-ctx-item" onclick="chatCtxReply()"><i class="fas fa-reply" style="font-size:11px;opacity:.7;width:14px;"></i>Reply</div>` +
        `<div class="chat-ctx-item" onclick="chatCtxCopy()"><i class="fas fa-copy" style="font-size:11px;opacity:.7;width:14px;"></i>Copy</div>` +
        (canEdit ? `<div class="chat-ctx-item" onclick="chatCtxEdit()"><i class="fas fa-pen" style="font-size:11px;opacity:.7;width:14px;"></i>Edit</div>` : '') +
        (isMine ? `<div class="chat-ctx-item chat-ctx-del" onclick="chatCtxDelete()"><i class="fas fa-trash-alt" style="font-size:11px;opacity:.7;width:14px;"></i>Delete</div>` : '');
    menu.style.display = 'block';
    const rect = btn.getBoundingClientRect();
    const mW = menu.offsetWidth || 130;
    const mH = menu.offsetHeight || 100;
    let x = isMine ? (rect.right - mW) : rect.left;
    let y = rect.bottom + 4;
    x = Math.max(8, Math.min(x, window.innerWidth - mW - 8));
    y = Math.max(8, Math.min(y, window.innerHeight - mH - 8));
    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
};
window.chatLikeClick = async function(e, btn, msgId) {
    e.stopPropagation();
    if (!_activeConvId) return;
    btn.style.opacity = '0.5';
    await fetch(`${API_BASE}/api/chat/convs/${_activeConvId}/send`, {
        method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({content:'👍',type:'text'})
    });
    btn.style.opacity = '';
};
window.chatCtxReply = function() {
    document.getElementById('chat-msg-ctx').style.display = 'none';
    if (!_chatCtxMsgId) return;
    const row = document.querySelector(`[data-msg-id="${_chatCtxMsgId}"]`);
    const raw = row ? (row.querySelector('[data-raw]')?.dataset.raw || '') : '';
    const ta = document.getElementById('chat-textarea');
    ta.value = (raw ? `> ${raw.substring(0,80)}\n\n` : '') + ta.value;
    ta.focus();
};
window.chatCtxCopy = function() {
    document.getElementById('chat-msg-ctx').style.display = 'none';
    if (!_chatCtxMsgId) return;
    const row = document.querySelector(`[data-msg-id="${_chatCtxMsgId}"]`);
    const raw = row ? (row.querySelector('[data-raw]')?.dataset.raw || '') : '';
    if (navigator.clipboard) navigator.clipboard.writeText(raw).catch(()=>{});
};
window.chatCtxEdit = function() {
    document.getElementById('chat-msg-ctx').style.display = 'none';
    if (!_chatCtxMsgId || !_chatCtxIsMine) return;
    const row = document.querySelector(`[data-msg-id="${_chatCtxMsgId}"]`);
    if (!row) return;
    const bubble = row.querySelector('.chat-bubble-bg');
    const textDiv = row.querySelector('[data-raw]');
    const raw = textDiv ? textDiv.dataset.raw : '';
    bubble.innerHTML = `
        <div style="display:flex;flex-direction:column;gap:6px;">
            <textarea id="chat-edit-ta" style="width:100%;min-width:180px;padding:6px 8px;border:1px solid #0891b2;border-radius:8px;font-size:13px;color:#1e293b;background:#fff;resize:none;outline:none;line-height:1.5;" rows="3">${raw.replace(/</g,'&lt;')}</textarea>
            <div style="display:flex;gap:6px;justify-content:flex-end;">
                <button onclick="chatCancelEdit()" style="padding:4px 12px;border-radius:6px;border:1px solid #cbd5e1;background:#fff;color:#64748b;font-size:12px;cursor:pointer;">Cancel</button>
                <button onclick="chatSaveEdit()" style="padding:4px 12px;border-radius:6px;border:none;background:#0891b2;color:#fff;font-size:12px;cursor:pointer;">Save</button>
            </div>
        </div>`;
    const ta = document.getElementById('chat-edit-ta');
    ta.focus();
    ta.selectionStart = ta.selectionEnd = ta.value.length;
};
window.chatCancelEdit = function() { chatLoadConvs(); };
window.chatSaveEdit = async function() {
    const ta = document.getElementById('chat-edit-ta');
    const newText = ta ? ta.value.trim() : '';
    if (!newText) return;
    await fetch(API_BASE + '/api/chat/msgs/' + _chatCtxMsgId, {
        method:'PATCH', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body: JSON.stringify({content: newText})
    });
    chatLoadConvs();
};
window.chatCtxDelete = async function() {
    document.getElementById('chat-msg-ctx').style.display = 'none';
    if (!_chatCtxMsgId) return;
    await fetch(API_BASE + '/api/chat/msgs/' + _chatCtxMsgId, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF}
    });
    chatLoadConvs();
};
document.addEventListener('click', function(e) {
    if (!e.target.closest('#chat-msg-ctx') && !e.target.closest('.chat-action-btn')) document.getElementById('chat-msg-ctx').style.display = 'none';
});

/* ── Send message ── */
window.chatSend = async function() {
    if (!_activeConvId) return;
    const ta = document.getElementById('chat-textarea');
    const text = ta.value.trim();
    const attachTags = window.getAttachmentTags ? window.getAttachmentTags('chat-textarea') : '';
    if (!text && !attachTags) return;

    ta.value = '';
    ta.style.height = 'auto';
    if (window.clearAttachments) window.clearAttachments('chat-textarea', 'chat-attach-preview');
    chatSendSound();

    const fullText = text + (attachTags ? (text ? '\n' : '') + attachTags : '');

    // Optimistic render
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
    const el = document.getElementById('chat-msg-area');
    el.insertAdjacentHTML('beforeend', chatBubble({isMine:true, name:'Me', avatar:'', text: fullText, time:timeStr, showName:false}));
    el.scrollTop = el.scrollHeight;

    try {
        const r = await fetch(API_BASE + '/api/chat/convs/' + _activeConvId + '/send', {
            method: 'POST',
            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({content: fullText}),
        });
        const d = await r.json();
        // Advance _lastMsgId so the next poll skips this just-sent message
        if (d.message?.id) _lastMsgId = Math.max(_lastMsgId, d.message.id);
        chatLoadConvs(); // refresh conv list for last message
    } catch(e) {}
};

/* ── New Direct ── */
window.chatNewDirect = async function() {
    document.getElementById('chat-new-direct-modal').classList.add('chat-open');
    document.getElementById('chat-emp-search').value = '';
    if (!_allEmps.length) {
        const r = await fetch(API_BASE + '/api/employees-list');
        _allEmps = await r.json();
    }
    chatFilterEmps('');
};
window.chatCloseNewDirect = function() {
    document.getElementById('chat-new-direct-modal').classList.remove('chat-open');
};
window.chatFilterEmps = function(q) {
    const list = q ? _allEmps.filter(e => e.name.toLowerCase().includes(q.toLowerCase())) : _allEmps;
    const el = document.getElementById('chat-emp-list');
    el.innerHTML = list.map(e => `
        <div onclick="chatStartDirect(${e.id})"
             style="display:flex;align-items:center;gap:10px;padding:10px 18px;cursor:pointer;transition:background .12s;"
             onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background='none'">
            <img src="${e.avatar}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;">
            <span style="color:#fff;font-size:13px;font-weight:500;">${escH(e.name)}</span>
        </div>`).join('');
};
window.chatStartDirect = async function(userId) {
    const r = await fetch(API_BASE + '/api/chat/direct', {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({user_id:userId})});
    const d = await r.json();
    chatCloseNewDirect();
    await chatLoadConvs();
    if (d.conv_id) chatSelectConv(d.conv_id);
};

/* ── New Group ── */
window.chatNewGroup = async function() {
    document.getElementById('chat-new-group-modal').classList.add('chat-open');
    document.getElementById('chat-group-name').value = '';
    if (!_allEmps.length) {
        const r = await fetch(API_BASE + '/api/employees-list');
        _allEmps = await r.json();
    }
    const el = document.getElementById('chat-group-members');
    el.innerHTML = _allEmps.map(e => `
        <label style="display:flex;align-items:center;gap:10px;padding:8px 4px;cursor:pointer;">
            <input type="checkbox" value="${e.id}" style="width:16px;height:16px;accent-color:#00D4E8;flex-shrink:0;">
            <img src="${e.avatar}" style="width:30px;height:30px;border-radius:50%;object-fit:cover;">
            <span style="color:rgba(255,255,255,.85);font-size:13px;">${escH(e.name)}</span>
        </label>`).join('');
};
window.chatCloseNewGroup = function() {
    document.getElementById('chat-new-group-modal').classList.remove('chat-open');
};
window.chatCreateGroup = async function() {
    const name = document.getElementById('chat-group-name').value.trim();
    if (!name) { alert('Enter a group name'); return; }
    const members = [...document.querySelectorAll('#chat-group-members input:checked')].map(i => +i.value);
    if (!members.length) { alert('Select at least one member'); return; }
    const r = await fetch(API_BASE + '/api/chat/group',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({name,members})});
    const d = await r.json();
    chatCloseNewGroup();
    await chatLoadConvs();
    if (d.conv_id) chatSelectConv(d.conv_id);
};

/* ── Polling ── */
async function chatPoll() {
    // Always update conversation list + unread counts (even when panel closed)
    chatLoadConvs();
    // Message polling only when panel is open with an active conversation
    if (!_chatOpen || !_activeConvId) return;
    try {
        const url = API_BASE + '/api/chat/convs/' + _activeConvId + '/msgs'
                  + (_lastMsgId ? '?after=' + _lastMsgId : '');
        const r = await fetch(url);
        const d = await r.json();
        const msgs = d.messages || [];
        if (!msgs.length) return;
        if (_lastMsgId === 0) {
            chatRenderMsgs(msgs);
        } else {
            const fromOthers = msgs.filter(m => !m.isMine);
            if (fromOthers.length) chatPlaySound();
            chatAppendMsgs(msgs);
        }
        _lastMsgId = Math.max(...msgs.map(m => m.id));
    } catch(e) {}
}
function chatAppendMsgs(msgs) {
    const el = document.getElementById('chat-msg-area');
    const inner = el.querySelector('[style*="margin-top:auto"]');
    if (!inner) { chatRenderMsgs(msgs); return; }
    msgs.forEach(m => {
        inner.insertAdjacentHTML('beforeend', chatBubble({
            isMine: m.isMine, name: m.author.name, avatar: m.author.avatar,
            text: m.text, time: m.time, showName: !m.isMine, msgId: m.id, createdTs: m.createdTs||0,
        }));
    });
    el.scrollTop = el.scrollHeight + 9999;
}
function chatUpdateBadge(count) {
    const b = document.getElementById('chat-unread-badge');
    if (!b) return;
    if (count > 0) { b.textContent = count > 99 ? '99+' : count; b.style.display = 'flex'; }
    else b.style.display = 'none';
}

/* ── Right-panel online status polling ── */
(function() {
    async function pollOnlineStatus() {
        try {
            const r = await fetch(API_BASE + '/api/online-status');
            if (!r.ok) return;
            const list = await r.json();
            const map = {};
            list.forEach(u => { map[u.id] = u.online; });
            document.querySelectorAll('.user-avatar-btn[data-uid]').forEach(function(btn) {
                const dot = btn.querySelector('.online-dot');
                if (!dot) return;
                const uid = +btn.dataset.uid;
                if (uid in map) dot.style.background = map[uid] ? '#22c55e' : '#6b7280';
            });
        } catch(e) {}
    }
    setInterval(pollOnlineStatus, 30000);
    // Expose for background polling outside this IIFE
    window.chatPoll = chatPoll;
})();

// Expose for task panel optimistic comment render
window.tpChatBubble = chatBubble;

/* ── Utility ── */
function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function linkify(text) {
    const urlRe = /(https?:\/\/[^\s<>"'[\]]+)/g;
    return String(text||'').split(urlRe).map(function(chunk, i) {
        if (i % 2 === 1) {
            const safe = chunk.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
            return '<a href="'+safe+'" target="_blank" rel="noopener noreferrer" style="color:#0ea5e9;text-decoration:underline;word-break:break-all;">'+safe+'</a>';
        }
        return chunk.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }).join('');
}

// Close on outside click
document.addEventListener('click', function(e) {
    if (!_chatOpen) return;
    const panel = document.getElementById('chat-panel');
    const dmModal = document.getElementById('chat-new-direct-modal');
    const grpModal = document.getElementById('chat-new-group-modal');
    const btn1 = document.getElementById('chat-topbar-btn');
    const btn2 = document.querySelector('button[onclick="chatToggle()"]');
    if (panel.contains(e.target) || dmModal.contains(e.target) || grpModal.contains(e.target)) return;
    if (btn1 && btn1.contains(e.target)) return;
    // chatClose();  // Uncomment if you want outside click to close
});
})();
</script>

{{-- ═══ EMOJI PICKER (shared) ═══ --}}
<div id="emoji-picker" style="display:none;position:fixed;z-index:99998;background:#1e2340;border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:10px;box-shadow:0 12px 40px rgba(0,0,0,.55);width:288px;">
    <div style="display:flex;gap:4px;margin-bottom:8px;flex-wrap:wrap;">
        <button class="ep-cat active" data-cat="smileys" onclick="epShowCat('smileys',this)" style="background:rgba(255,255,255,.1);border:none;border-radius:7px;padding:4px 9px;color:#fff;font-size:11px;cursor:pointer;">😀 Smileys</button>
        <button class="ep-cat" data-cat="hands" onclick="epShowCat('hands',this)" style="background:none;border:none;border-radius:7px;padding:4px 9px;color:rgba(255,255,255,.5);font-size:11px;cursor:pointer;">👍 Hands</button>
        <button class="ep-cat" data-cat="symbols" onclick="epShowCat('symbols',this)" style="background:none;border:none;border-radius:7px;padding:4px 9px;color:rgba(255,255,255,.5);font-size:11px;cursor:pointer;">❤️ Symbols</button>
        <button class="ep-cat" data-cat="work" onclick="epShowCat('work',this)" style="background:none;border:none;border-radius:7px;padding:4px 9px;color:rgba(255,255,255,.5);font-size:11px;cursor:pointer;">💼 Work</button>
        <button class="ep-cat" data-cat="party" onclick="epShowCat('party',this)" style="background:none;border:none;border-radius:7px;padding:4px 9px;color:rgba(255,255,255,.5);font-size:11px;cursor:pointer;">🎉 Fun</button>
    </div>
    <div id="ep-grid" style="display:grid;grid-template-columns:repeat(8,1fr);gap:2px;"></div>
</div>

<style>
.ep-btn { background:none;border:none;cursor:pointer;font-size:19px;padding:4px;border-radius:6px;transition:background .1s;line-height:1; }
.ep-btn:hover { background:rgba(255,255,255,.12); }
.ep-cat.active { background:rgba(0,212,232,.2) !important; color:#00D4E8 !important; }
</style>

<script>
(function(){
const EP_CATS = {
    smileys: ['😀','😁','😂','🤣','😃','😄','😅','😆','😇','😉','😊','🙂','🙃','😋','😌','😍','🥰','😎','🤓','😏','😒','🙄','😔','🤔','😐','😶','😬','😢','😭','😱','😤','😠','🤬','🥺','😇','🤗','😴','🤧','😷','🤒'],
    hands:   ['👍','👎','👏','🙌','👐','🤝','✊','👊','🤜','🤛','🤞','🤟','🤘','🤙','💪','👈','👉','👆','👇','☝️','👋','✋','🖐️','🖖','🫶','💅','🤳'],
    symbols: ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','✅','❌','⚠️','❗','❓','⭐','🌟','✨','💯','🔥','💡','🎯','📌','🔔','🚀','💬','📢','🔴','🟡','🟢'],
    work:    ['📝','📋','📊','📈','📉','🗂️','📁','📂','💼','🖥️','⌨️','🖱️','📱','📧','📞','🔍','🔑','🔒','⏰','📅','🏷️','🗒️','📤','📥','🔗','📎','🖇️','✂️'],
    party:   ['🎉','🎊','🎁','🥳','🏆','🥇','🎖️','🌈','🎈','🎂','🥂','🍾','🎵','🎶','🎸','🎮','⚽','🏀','🎯','🎲','🃏','🌸','🌺','🌻'],
};

let _epTarget = null;
let _epOpen   = false;

window.emojiToggle = function(targetId, btn) {
    const picker = document.getElementById('emoji-picker');
    if (_epOpen && _epTarget === targetId) { epClose(); return; }
    _epTarget = targetId;
    _epOpen   = true;

    // Populate current category
    const activeCat = picker.querySelector('.ep-cat.active')?.dataset.cat || 'smileys';
    epRenderCat(activeCat);

    // Position near button
    picker.style.display = 'block';
    const btnRect = btn.getBoundingClientRect();
    const pw = 288, ph = 220;
    let top  = btnRect.top - ph - 8;
    let left = btnRect.left;
    if (top < 8) top = btnRect.bottom + 8;
    if (left + pw > window.innerWidth - 8) left = window.innerWidth - pw - 8;
    picker.style.top  = top + 'px';
    picker.style.left = left + 'px';
};

function epClose() {
    _epOpen = false;
    document.getElementById('emoji-picker').style.display = 'none';
}

window.epShowCat = function(cat, btn) {
    document.querySelectorAll('.ep-cat').forEach(b => { b.classList.remove('active'); b.style.background='none'; b.style.color='rgba(255,255,255,.5)'; });
    btn.classList.add('active');
    epRenderCat(cat);
};

function epRenderCat(cat) {
    const grid = document.getElementById('ep-grid');
    grid.innerHTML = (EP_CATS[cat] || []).map(e =>
        `<button class="ep-btn" onclick="epInsert('${e}')" title="${e}">${e}</button>`
    ).join('');
}

window.epInsert = function(emoji) {
    const ta = document.getElementById(_epTarget);
    if (!ta) return;
    const start = ta.selectionStart ?? ta.value.length;
    const end   = ta.selectionEnd   ?? ta.value.length;
    ta.value = ta.value.slice(0, start) + emoji + ta.value.slice(end);
    ta.selectionStart = ta.selectionEnd = start + emoji.length;
    ta.focus();
    ta.dispatchEvent(new Event('input'));
    // Don't close so user can add multiple
};

// Close on outside click
document.addEventListener('click', function(e) {
    if (!_epOpen) return;
    const picker = document.getElementById('emoji-picker');
    if (picker.contains(e.target)) return;
    if (e.target.closest('[onclick*="emojiToggle"]')) return;
    epClose();
}, true);

/* ── File Upload (shared) ── */
const _pendingAtts = {};

window.uploadAndInsert = async function(textareaId, fileInputId, previewId) {
    const input = document.getElementById(fileInputId);
    const file  = input.files[0];
    if (!file) return;

    const ta = document.getElementById(textareaId);
    const origPH = ta.placeholder;
    ta.placeholder = 'Uploading...';
    ta.disabled    = true;

    try {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_token', CSRF);
        const r = await fetch(API_BASE + '/api/upload', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        if (d.url) {
            const imgExts = ['jpg','jpeg','png','gif','webp','bmp','svg'];
            const isImg   = imgExts.includes((d.ext||'').toLowerCase());
            const tag     = isImg ? `[img]${d.url}[/img]` : `[file name="${d.name}"]${d.url}[/file]`;
            const attId   = 'att-' + Date.now();
            if (!_pendingAtts[textareaId]) _pendingAtts[textareaId] = [];
            _pendingAtts[textareaId].push({ id: attId, tag });

            const pEl = previewId ? document.getElementById(previewId) : null;
            if (pEl) {
                pEl.style.display = 'flex';
                const chip = document.createElement('div');
                chip.id = attId;
                chip.style.cssText = 'position:relative;flex-shrink:0;';
                const _rmBtn = `<button type="button" onclick="removeAttachment('${textareaId}','${attId}','${previewId}')" style="position:absolute;top:-6px;right:-6px;width:18px;height:18px;border-radius:50%;background:#ef4444;border:none;color:#fff;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">&times;</button>`;
                if (isImg) {
                    chip.innerHTML = `<img src="${d.url}" style="width:64px;height:64px;object-fit:cover;border-radius:10px;border:2px solid rgba(14,165,233,.35);display:block;">${_rmBtn}`;
                } else {
                    const _ext = (d.name||'').split('.').pop().toLowerCase();
                    const _im = {pdf:['fa-file-pdf','#ef4444'],doc:['fa-file-word','#2563eb'],docx:['fa-file-word','#2563eb'],xls:['fa-file-excel','#16a34a'],xlsx:['fa-file-excel','#16a34a'],ppt:['fa-file-powerpoint','#ea580c'],pptx:['fa-file-powerpoint','#ea580c'],zip:['fa-file-archive','#ca8a04'],rar:['fa-file-archive','#ca8a04'],txt:['fa-file-alt','#64748b']};
                    const [_ico,_bg] = _im[_ext]||['fa-file','#0ea5e9'];
                    const _sn = (d.name||'').length>11?(d.name||'').slice(0,9)+'…':(d.name||'');
                    const _safe = String(d.name||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    chip.innerHTML = `<div style="width:64px;height:64px;border-radius:10px;background:${_bg};display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,.2);"><i class="fas ${_ico}" style="color:#fff;font-size:24px;"></i></div>
                        <p style="font-size:9.5px;color:rgba(255,255,255,.7);text-align:center;margin:3px 0 0;width:64px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${_safe}">${_sn}</p>${_rmBtn}`;
                }
                pEl.appendChild(chip);
            }
        }
    } catch(e) {
        alert('Upload failed. Please try again.');
    } finally {
        ta.placeholder = origPH;
        ta.disabled    = false;
        ta.focus();
        input.value    = '';
    }
};

window.removeAttachment = function(textareaId, attId, previewId) {
    if (_pendingAtts[textareaId]) {
        _pendingAtts[textareaId] = _pendingAtts[textareaId].filter(a => a.id !== attId);
    }
    const el = document.getElementById(attId);
    if (el) el.remove();
    const pEl = previewId ? document.getElementById(previewId) : null;
    if (pEl && !pEl.children.length) pEl.style.display = 'none';
};

window.uploadFileDirect = async function(file, textareaId, previewId) {
    const ta = document.getElementById(textareaId);
    if (!ta || !file) return;
    const origPH = ta.placeholder;
    ta.placeholder = 'Uploading...';
    ta.disabled = true;
    try {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_token', CSRF);
        const r = await fetch(API_BASE + '/api/upload', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        if (d.url) {
            const imgExts = ['jpg','jpeg','png','gif','webp','bmp','svg'];
            const isImg = imgExts.includes((d.ext||'').toLowerCase());
            const tag = isImg ? `[img]${d.url}[/img]` : `[file name="${d.name}"]${d.url}[/file]`;
            const attId = 'att-' + Date.now() + '-' + Math.random().toString(36).slice(2,7);
            if (!_pendingAtts[textareaId]) _pendingAtts[textareaId] = [];
            _pendingAtts[textareaId].push({ id: attId, tag });
            const pEl = previewId ? document.getElementById(previewId) : null;
            if (pEl) {
                pEl.style.display = 'flex';
                const chip = document.createElement('div');
                chip.id = attId;
                chip.style.cssText = 'position:relative;flex-shrink:0;';
                const _rmBtn2 = `<button type="button" onclick="removeAttachment('${textareaId}','${attId}','${previewId}')" style="position:absolute;top:-6px;right:-6px;width:18px;height:18px;border-radius:50%;background:#ef4444;border:none;color:#fff;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">&times;</button>`;
                if (isImg) {
                    chip.innerHTML = `<img src="${d.url}" style="width:64px;height:64px;object-fit:cover;border-radius:10px;border:2px solid rgba(14,165,233,.35);display:block;">${_rmBtn2}`;
                } else {
                    const _ext2 = (d.name||'').split('.').pop().toLowerCase();
                    const _im2 = {pdf:['fa-file-pdf','#ef4444'],doc:['fa-file-word','#2563eb'],docx:['fa-file-word','#2563eb'],xls:['fa-file-excel','#16a34a'],xlsx:['fa-file-excel','#16a34a'],ppt:['fa-file-powerpoint','#ea580c'],pptx:['fa-file-powerpoint','#ea580c'],zip:['fa-file-archive','#ca8a04'],rar:['fa-file-archive','#ca8a04'],txt:['fa-file-alt','#64748b']};
                    const [_ico2,_bg2] = _im2[_ext2]||['fa-file','#0ea5e9'];
                    const _sn2 = (d.name||'').length>11?(d.name||'').slice(0,9)+'…':(d.name||'');
                    const _safe2 = String(d.name||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    chip.innerHTML = `<div style="width:64px;height:64px;border-radius:10px;background:${_bg2};display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,.2);"><i class="fas ${_ico2}" style="color:#fff;font-size:24px;"></i></div>
                        <p style="font-size:9.5px;color:rgba(255,255,255,.7);text-align:center;margin:3px 0 0;width:64px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${_safe2}">${_sn2}</p>${_rmBtn2}`;
                }
                pEl.appendChild(chip);
            }
        }
    } catch(e) { console.error('Upload failed', e); }
    finally {
        ta.placeholder = origPH;
        ta.disabled = false;
        ta.focus();
    }
};

window.getAttachmentTags = function(textareaId) {
    return (_pendingAtts[textareaId] || []).map(a => a.tag).join('\n');
};

window.clearAttachments = function(textareaId, previewId) {
    _pendingAtts[textareaId] = [];
    const pEl = previewId ? document.getElementById(previewId) : null;
    if (pEl) { pEl.innerHTML = ''; pEl.style.display = 'none'; }
};
})();
</script>

{{-- ═══ IMAGE LIGHTBOX / GALLERY ═══ --}}
<div id="img-lightbox"
     onclick="if(event.target===this)lbClose()"
     style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.92);align-items:center;justify-content:center;">

    {{-- Prev --}}
    <button id="lb-prev" onclick="lbNav(-1)"
            style="display:none;position:absolute;left:20px;top:50%;transform:translateY(-50%);width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.14);border:none;color:#fff;font-size:18px;cursor:pointer;align-items:center;justify-content:center;transition:background .15s;z-index:2;"
            onmouseover="this.style.background='rgba(255,255,255,.28)'" onmouseout="this.style.background='rgba(255,255,255,.14)'">
        <i class="fas fa-chevron-left"></i>
    </button>

    <img id="img-lightbox-img" src="" alt=""
         style="max-width:88vw;max-height:86vh;object-fit:contain;border-radius:10px;box-shadow:0 24px 64px rgba(0,0,0,.7);pointer-events:none;z-index:1;transition:opacity .12s ease;">

    {{-- Next --}}
    <button id="lb-next" onclick="lbNav(1)"
            style="display:none;position:absolute;right:20px;top:50%;transform:translateY(-50%);width:46px;height:46px;border-radius:50%;background:rgba(255,255,255,.14);border:none;color:#fff;font-size:18px;cursor:pointer;align-items:center;justify-content:center;transition:background .15s;z-index:2;"
            onmouseover="this.style.background='rgba(255,255,255,.28)'" onmouseout="this.style.background='rgba(255,255,255,.14)'">
        <i class="fas fa-chevron-right"></i>
    </button>

    {{-- Counter --}}
    <div id="lb-counter" style="display:none;position:absolute;bottom:22px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.55);color:rgba(255,255,255,.8);font-size:12px;font-weight:600;padding:5px 16px;border-radius:20px;letter-spacing:.5px;white-space:nowrap;"></div>

    {{-- Close --}}
    <button onclick="lbClose()"
            style="position:absolute;top:18px;right:22px;background:rgba(255,255,255,.12);border:none;color:#fff;width:38px;height:38px;border-radius:50%;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(8px);z-index:3;"
            title="Close"><i class="fas fa-times"></i></button>
</div>
<style>
@keyframes lbFade { from{opacity:0} to{opacity:1} }
</style>
<script>
const _igReg = {};
let _igSeq = 0, _igCurKey = null, _igCurIdx = 0;

window.registerGallery = function(urls) {
    const k = ++_igSeq;
    _igReg[k] = urls;
    return k;
};

function _lbUrls() {
    if (typeof _igCurKey === 'string') return [_igCurKey];
    return _igReg[_igCurKey] || [];
}

function _lbRefresh(animate) {
    const urls  = _lbUrls();
    const multi = urls.length > 1;
    const img   = document.getElementById('img-lightbox-img');
    if (animate) {
        img.style.opacity = '0';
        setTimeout(() => { img.src = urls[_igCurIdx]||''; img.style.opacity = '1'; }, 110);
    } else {
        img.src = urls[_igCurIdx] || '';
    }
    const prev = document.getElementById('lb-prev');
    const next = document.getElementById('lb-next');
    const ctr  = document.getElementById('lb-counter');
    prev.style.display = multi && _igCurIdx > 0 ? 'flex' : 'none';
    next.style.display = multi && _igCurIdx < urls.length - 1 ? 'flex' : 'none';
    ctr.style.display  = multi ? 'block' : 'none';
    ctr.textContent    = (_igCurIdx + 1) + ' / ' + urls.length;
}

window.imgLightbox = function(key, idx) {
    _igCurKey = key;
    _igCurIdx = idx || 0;
    _lbRefresh(false);
    const lb = document.getElementById('img-lightbox');
    lb.style.display = 'flex';
    lb.style.animation = 'none';
    void lb.offsetWidth;
    lb.style.animation = 'lbFade .18s ease';
};

window.lbNav = function(dir) {
    const urls = _lbUrls();
    _igCurIdx = Math.max(0, Math.min(urls.length - 1, _igCurIdx + dir));
    _lbRefresh(true);
};

window.lbClose = function() {
    document.getElementById('img-lightbox').style.display = 'none';
};

document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('img-lightbox');
    if (!lb || lb.style.display === 'none') return;
    if (e.key === 'Escape')     lbClose();
    if (e.key === 'ArrowLeft')  lbNav(-1);
    if (e.key === 'ArrowRight') lbNav(1);
});

/* ═══════════════════════════════════════════
   VOICE NOTE PLAYER
═══════════════════════════════════════════ */
const _vpAudios = {};

window.voiceBubbleHtml = function(url, dur, isMine) {
    // Seeded waveform bars (consistent per URL)
    let seed = 0;
    for (let i = 0; i < url.length; i++) seed = (seed * 31 + url.charCodeAt(i)) | 0;
    let bars = '';
    for (let i = 0; i < 32; i++) {
        seed = (seed * 1664525 + 1013904223) | 0;
        const h = 4 + ((seed >>> 1) % 22);
        bars += `<div style="width:2px;height:${h}px;background:currentColor;opacity:.45;border-radius:1px;flex-shrink:0;transition:opacity .2s;"></div>`;
    }
    const eid = 'vp_' + Math.random().toString(36).slice(2, 9);
    const playColor = isMine ? '#15803d' : '#0ea5e9';
    const barColor  = isMine ? '#1a3025' : '#374151';
    const timerColor= isMine ? 'rgba(0,0,0,.38)' : '#94a3b8';
    const safeUrl = url.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
    const safeDur = String(dur).replace(/&/g,'&amp;').replace(/"/g,'&quot;');
    return `<div class="vn-wrap" style="display:flex;align-items:center;gap:8px;min-width:190px;max-width:260px;padding:2px 0;">
        <button id="${eid}" data-url="${safeUrl}" data-dur="${safeDur}" onclick="voiceToggle(this.id,this.dataset.url,this.dataset.dur)"
                style="width:36px;height:36px;border-radius:50%;background:${playColor};border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .15s;"
                onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
            <i class="fas fa-play" style="font-size:11px;margin-left:2px;"></i>
        </button>
        <div style="flex:1;display:flex;align-items:center;gap:2px;height:32px;overflow:hidden;color:${barColor};">${bars}</div>
        <span class="vn-timer" style="font-size:11px;color:${timerColor};white-space:nowrap;min-width:28px;text-align:right;">${dur}</span>
    </div>`;
};

window.voiceToggle = function(btnId, url, dur) {
    // Pause all other players
    Object.values(_vpAudios).forEach(a => { if (a && !a.paused) a.pause(); });
    document.querySelectorAll('.vn-wrap .fa-pause').forEach(i => {
        i.className = 'fas fa-play';
        i.style.marginLeft = '2px';
    });
    if (!_vpAudios[url]) _vpAudios[url] = new Audio(url);
    const audio = _vpAudios[url];
    const btn   = document.getElementById(btnId);
    if (!btn) return;
    const icon  = btn.querySelector('i');
    const timer = btn.closest('.vn-wrap').querySelector('.vn-timer');
    if (audio.paused) {
        audio.play().catch(() => {});
        icon.className = 'fas fa-pause';
        icon.style.marginLeft = '0';
        audio.ontimeupdate = function() {
            const s = Math.floor(audio.currentTime);
            if (timer) timer.textContent = Math.floor(s/60) + ':' + String(s%60).padStart(2,'0');
        };
        audio.onended = function() {
            icon.className = 'fas fa-play';
            icon.style.marginLeft = '2px';
            if (timer) timer.textContent = dur;
            audio.currentTime = 0;
        };
    } else {
        audio.pause();
        icon.className = 'fas fa-play';
        icon.style.marginLeft = '2px';
    }
};

/* ═══════════════════════════════════════════
   VOICE NOTE RECORDER
═══════════════════════════════════════════ */
let _vnRec = null, _vnChunks = [], _vnSecs = 0, _vnTick = null, _vnStream = null;

window.vnStart = async function(panel) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        alert('Microphone is not supported in this browser.');
        return;
    }
    try {
        _vnStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    } catch(e) {
        alert('Microphone access denied. Please allow microphone access and try again.');
        return;
    }
    _vnChunks = [];
    _vnSecs   = 0;
    const mime = MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm'
               : MediaRecorder.isTypeSupported('audio/ogg')  ? 'audio/ogg'
               : '';
    _vnRec = mime ? new MediaRecorder(_vnStream, { mimeType: mime }) : new MediaRecorder(_vnStream);
    _vnRec.ondataavailable = e => { if (e.data.size > 0) _vnChunks.push(e.data); };
    _vnRec.start(100);
    document.getElementById(panel + '-normal-input').style.display = 'none';
    const rec = document.getElementById(panel + '-vn-rec');
    rec.style.display = 'flex';
    const timerEl = document.getElementById(panel + '-vn-timer');
    if (timerEl) timerEl.textContent = '0:00';
    _vnTick = setInterval(() => {
        _vnSecs++;
        const m = Math.floor(_vnSecs / 60), s = _vnSecs % 60;
        if (timerEl) timerEl.textContent = m + ':' + String(s).padStart(2, '0');
        // Auto-stop at 5 minutes
        if (_vnSecs >= 300) vnSend(panel);
    }, 1000);
};

window.vnCancel = function(panel) {
    clearInterval(_vnTick);
    if (_vnRec && _vnRec.state !== 'inactive') { _vnRec.ondataavailable = null; _vnRec.stop(); }
    if (_vnStream) _vnStream.getTracks().forEach(t => t.stop());
    _vnRec = null; _vnChunks = []; _vnStream = null;
    document.getElementById(panel + '-vn-rec').style.display = 'none';
    document.getElementById(panel + '-normal-input').style.display = 'flex';
};

window.vnSend = function(panel) {
    if (!_vnRec || _vnRec.state === 'inactive') return;
    clearInterval(_vnTick);
    const dur = Math.floor(_vnSecs/60) + ':' + String(_vnSecs%60).padStart(2,'0');
    _vnRec.onstop = async () => {
        if (_vnStream) _vnStream.getTracks().forEach(t => t.stop());
        document.getElementById(panel + '-vn-rec').style.display = 'none';
        document.getElementById(panel + '-normal-input').style.display = 'flex';
        const mime = (_vnChunks[0] && _vnChunks[0].type) || 'audio/webm';
        const ext  = mime.includes('ogg') ? 'ogg' : 'webm';
        const blob = new Blob(_vnChunks, { type: mime });
        _vnRec = null; _vnChunks = []; _vnStream = null;
        try {
            const fd = new FormData();
            fd.append('file', blob, 'voice_' + Date.now() + '.' + ext);
            fd.append('_token', CSRF);
            const r = await fetch(API_BASE + '/api/upload', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
            const d = await r.json();
            if (!d.url) throw new Error('No URL');
            const tag = `[voice dur="${dur}"]${d.url}[/voice]`;
            if (panel === 'chat') {
                if (!_activeConvId) return;
                const el  = document.getElementById('chat-msg-area');
                const now = new Date();
                const ts  = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
                el.insertAdjacentHTML('beforeend', chatBubble({isMine:true, name:'Me', avatar:'', text:tag, time:ts, showName:false}));
                el.scrollTop = el.scrollHeight;
                await fetch(API_BASE + '/api/chat/convs/' + _activeConvId + '/send', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
                    body: JSON.stringify({content: tag}),
                });
                chatLoadConvs();
            } else if (panel === 'cp' && window.cpSendRaw) {
                await window.cpSendRaw(tag);
            }
        } catch(e) { console.error('Voice upload failed', e); }
    };
    _vnRec.stop();
};
</script>

<script src="//unpkg.com/alpinejs" defer></script>
<script>
function appShell() {
    return {
        sidebarOpen: localStorage.getItem('sb') !== 'false',
        init() { this.$watch('sidebarOpen', v => localStorage.setItem('sb', v)); }
    }
}
function openTaskModal(projectId) {
    _modalCloseToken++; // cancel any pending closeTaskModal reset
    const ov = document.getElementById('nt-overlay');
    if (!ov) return;
    ov.style.display = 'flex';
    requestAnimationFrame(() => { ov.style.opacity = '1'; });
    localStorage.setItem('nt_open', '1');
    if (projectId) {
        const sel = ov.querySelector('select[name="project_id"]');
        if (sel) { sel.value = projectId; localStorage.setItem('nt_project', projectId); }
    }
    setTimeout(() => {
        if (window.ntRestoreDraft) ntRestoreDraft();
        const t = document.getElementById('nt-title-input'); if(t) t.focus();
    }, 60);
}
let _modalCloseToken = 0;
function closeTaskModal() {
    if (window.ntExitEditMode) ntExitEditMode();
    const ov = document.getElementById('nt-overlay');
    if (!ov) return;
    ov.style.opacity = '0';
    // Clear persisted draft
    ['nt_open','nt_title','nt_desc','nt_status','nt_priority','nt_deadline','nt_project',
     'nt_assigned_id','nt_assigned_name','nt_assigned_avatar','nt_assigned_bg','nt_assigned_initial',
     'nt_members','nt_observers','nt_attachments','nt_ss_open','nt_ss_ta_show','nt_ss_val'
    ].forEach(k => localStorage.removeItem(k));
    const token = ++_modalCloseToken;
    setTimeout(() => {
        if (token !== _modalCloseToken) return; // modal was reopened, skip reset
        ov.style.display = 'none';
        const form = document.getElementById('nt-form');
        if (form) form.reset();
        document.querySelectorAll('.nt-pill').forEach(p => { p.classList.remove('active'); p.style.borderColor = 'transparent'; });
        const newPill = document.querySelector('.nt-pill[data-val="new"]');
        const medPill = document.querySelector('.nt-pill[data-val="medium"]');
        if (newPill) { newPill.classList.add('active'); newPill.style.borderColor = getComputedStyle(newPill).color; }
        if (medPill) { medPill.classList.add('active'); medPill.style.borderColor = getComputedStyle(medPill).color; }
        const sv = document.getElementById('nt-status-val');   if(sv) sv.value = 'new';
        const pv = document.getElementById('nt-priority-val'); if(pv) pv.value = 'medium';
        if (window.ntPickAssignee) ntPickAssignee(0,'','');
        if (window.ntResetAttachments) ntResetAttachments();
        // Reset participants trigger
        if (window.ntUpdateParticipantsTrigger) ntUpdateParticipantsTrigger();
        // Reset observers trigger
        if (window.ntUpdateObserversTrigger) ntUpdateObserversTrigger();
        // Reset status summary section
        const ssSec = document.getElementById('nt-ss-section');
        const ssTa  = document.getElementById('nt-ss-ta');
        const ssBdg = document.getElementById('nt-ss-badge');
        if (ssSec) ssSec.style.display = 'none';
        if (ssTa)  { ssTa.style.display = 'none'; ssTa.value = ''; }
        if (ssBdg) ssBdg.style.display  = 'flex';
    }, 220);
}
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('nt_open') === '1') {
        setTimeout(() => openTaskModal(), 150);
    }

    // ── Ripple effect on all .ikia-btn buttons ──
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.ikia-btn');
        if (!btn) return;
        const r = document.createElement('span');
        r.className = 'ripple';
        const rect = btn.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        r.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px;`;
        btn.appendChild(r);
        setTimeout(() => r.remove(), 520);
    });

    // ── Staggered fade-in for page cards ──
    document.querySelectorAll('.stat-card, .ikia-card').forEach((el, i) => {
        el.style.animation = `ikiaFadeUp .35s ${i * 0.06}s ease both`;
    });

    // ── Smooth hover on right-panel avatars ──
    document.querySelectorAll('#right-panel .user-avatar-wrap').forEach(el => {
        el.style.transition = 'transform .18s cubic-bezier(.34,1.56,.64,1)';
        el.addEventListener('mouseenter', () => el.style.transform = 'scale(1.12)');
        el.addEventListener('mouseleave', () => el.style.transform = 'scale(1)');
    });
});
document.addEventListener('keydown', function(e){ if(e.key==='Escape') { closeTaskModal(); notifClose(); } });

// ─── Notification system ───
let notifIsOpen    = false;
let notifPollTimer = null;
let _notifPrevCount = -1; // -1 = first load, no sound yet
const CSRF     = document.querySelector('meta[name="csrf-token"]')?.content || '';
const API_BASE = '{{ url("") }}';

function notifToggle() {
    notifIsOpen ? notifClose() : notifOpen();
}
function notifOpen() {
    notifIsOpen = true;
    const p = document.getElementById('notif-panel');
    p.style.display = 'flex';
    // re-trigger animation
    p.style.animation = 'none';
    void p.offsetWidth;
    p.style.animation = 'notifSlide .18s ease';
    notifLoad();
}
function notifClose() {
    notifIsOpen = false;
    document.getElementById('notif-panel').style.display = 'none';
}

async function notifLoad() {
    try {
        const res  = await fetch(API_BASE + '/api/notifications');
        const data = await res.json();
        notifRender(data.notifications || []);
        notifUpdateBadge(data.unread_count || 0);
    } catch(e) {
        document.getElementById('notif-list').innerHTML =
            '<div style="padding:30px;text-align:center;color:#ef4444;font-size:12px;">Failed to load</div>';
    }
}

function notifRender(list) {
    const el = document.getElementById('notif-list');
    if (!list.length) {
        el.innerHTML = '<div style="padding:48px 20px;text-align:center;"><i class="fas fa-bell-slash" style="font-size:28px;color:#e2e8f0;display:block;margin-bottom:10px;"></i><span style="font-size:13px;color:#9ca3af;">No notifications yet</span></div>';
        return;
    }
    el.innerHTML = list.map(n => {
        const unread = !n.read_at;
        const avatar = n.actor
            ? `<img src="${n.actor.avatar}" style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;">`
            : `<div style="width:38px;height:38px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-bell" style="font-size:14px;color:#9ca3af;"></i></div>`;
        const typeIcon = notifTypeIcon(n.type);
        const timeStr  = notifTimeAgo(n.created_at);
        return `
        <div class="notif-item${unread ? ' unread' : ''}" onclick="notifClick(${n.id}, ${n.task_id || 'null'})">
            <div style="position:relative;flex-shrink:0;">
                ${avatar}
                <div style="position:absolute;bottom:-1px;right:-3px;">${typeIcon}</div>
            </div>
            <div style="flex:1;min-width:0;">
                <p style="margin:0 0 3px;font-size:13px;color:#111827;line-height:1.45;${unread ? 'font-weight:600;' : 'font-weight:400;'}">${escHtml(n.message)}</p>
                <p style="margin:0;font-size:11px;color:#9ca3af;">${timeStr}</p>
            </div>
            ${unread ? '<div style="width:8px;height:8px;border-radius:50%;background:#0ea5e9;flex-shrink:0;margin-top:4px;"></div>' : ''}
        </div>`;
    }).join('');
}

function notifTypeIcon(type) {
    const map = {
        task_assigned:    {icon:'fa-user-check',    bg:'#dbeafe',col:'#2563eb'},
        task_comment:     {icon:'fa-comment-dots',  bg:'#dcfce7',col:'#16a34a'},
        task_status:      {icon:'fa-arrows-rotate', bg:'#fef9c3',col:'#ca8a04'},
        task_participant: {icon:'fa-users',          bg:'#ede9fe',col:'#7c3aed'},
        task_observer:    {icon:'fa-eye',            bg:'#f0fdf4',col:'#15803d'},
        task_deadline:    {icon:'fa-calendar-days', bg:'#fff1f2',col:'#be123c'},
    };
    const t = map[type] || {icon:'fa-bell',bg:'#f1f5f9',col:'#475569'};
    return `<div style="width:18px;height:18px;border-radius:50%;background:${t.bg};display:flex;align-items:center;justify-content:center;border:2px solid #fff;"><i class="fas ${t.icon}" style="font-size:7px;color:${t.col};"></i></div>`;
}

async function notifClick(id, taskId) {
    // Mark as read
    await fetch(`/api/notifications/${id}/read`, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF} });
    // Navigate to task
    if (taskId) {
        const currentUrl = new URL(location.href);
        currentUrl.searchParams.set('task', taskId);
        // If tpOpen exists on this page, use it; otherwise navigate
        if (typeof tpOpen === 'function') {
            notifClose();
            tpOpen('local', taskId);
        } else {
            location.href = '/tasks?' + currentUrl.searchParams.toString();
        }
    }
    notifLoad();
}

async function notifMarkAllRead() {
    await fetch(API_BASE + '/api/notifications/read-all', { method:'POST', headers:{'X-CSRF-TOKEN':CSRF} });
    notifLoad();
}

function notifUpdateBadge(count) {
    const badge = document.getElementById('notif-badge');
    if (count > 0) {
        badge.style.display = 'block';
        badge.textContent = count > 9 ? '9+' : count;
    } else {
        badge.style.display = 'none';
    }
}

function notifTimeAgo(isoStr) {
    const diff = Math.floor((Date.now() - new Date(isoStr)) / 1000);
    if (diff < 60)   return 'just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400)return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function notifPlaySound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const t = ctx.currentTime;
        [[659,0,0.2],[784,0.13,0.18],[1047,0.26,0.16]].forEach(([freq,delay,vol]) => {
            const osc = ctx.createOscillator(), gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = 'sine'; osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, t+delay);
            gain.gain.linearRampToValueAtTime(vol, t+delay+0.012);
            gain.gain.exponentialRampToValueAtTime(0.001, t+delay+0.55);
            osc.start(t+delay); osc.stop(t+delay+0.6);
        });
    } catch(e) {}
}
function chatPlaySound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const t = ctx.currentTime;
        [[440,0,0.15],[587,0.12,0.12]].forEach(([freq,delay,vol]) => {
            const osc = ctx.createOscillator(), gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = 'sine'; osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, t+delay);
            gain.gain.linearRampToValueAtTime(vol, t+delay+0.01);
            gain.gain.exponentialRampToValueAtTime(0.001, t+delay+0.5);
            osc.start(t+delay); osc.stop(t+delay+0.55);
        });
    } catch(e) {}
}

// Poll every 30s for badge count
async function notifPoll() {
    try {
        const res   = await fetch(API_BASE + '/api/notifications');
        const data  = await res.json();
        const count = data.unread_count || 0;
        // Play sound + animate badge when new notifications arrive
        if (_notifPrevCount !== -1 && count > _notifPrevCount) {
            notifPlaySound();
            const badge = document.getElementById('notif-badge');
            if (badge) {
                badge.style.transform = 'scale(1.5)';
                setTimeout(() => { badge.style.transform = 'scale(1)'; }, 300);
            }
            // Refresh kanban cards so new unseen badges appear without page reload
            if (typeof kbAjaxFilter === 'function') setTimeout(kbAjaxFilter, 500);
        }
        _notifPrevCount = count;
        notifUpdateBadge(count);
        if (notifIsOpen) notifRender(data.notifications || []);
    } catch(e) {}
}
function notifSchedulePoll() {
    clearTimeout(notifPollTimer);
    notifPollTimer = setTimeout(() => {
        notifSchedulePoll(); // schedule next immediately (don't wait for response)
        notifPoll();
    }, document.hidden ? 60000 : 5000);
}
document.addEventListener('DOMContentLoaded', () => {
    notifPoll();
    notifSchedulePoll();
    // Background conv-list refresh — keeps unread counts fresh when panel is closed.
    // Uses a separate lightweight call instead of full chatPoll to avoid racing the
    // 5-second message-poll timer that chatOpen() starts.
    setInterval(chatLoadConvs, 15000);
});
document.addEventListener('visibilitychange', () => {
    clearTimeout(notifPollTimer);
    if (!document.hidden) notifPoll(); // immediate poll when tab becomes active
    notifSchedulePoll();
});

// Close panel when clicking outside
document.addEventListener('click', function(e) {
    if (!notifIsOpen) return;
    const btn   = document.getElementById('notif-btn');
    const panel = document.getElementById('notif-panel');
    if (!btn.contains(e.target) && !panel.contains(e.target)) notifClose();
});
</script>

{{-- ─── Global Delete Confirmation Popup ─── --}}
<div id="app-del-overlay"
     onclick="if(event.target===this)appDelCancel()"
     style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.52);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);align-items:center;justify-content:center;">
    <div id="app-del-card"
         style="background:#fff;border-radius:20px;width:360px;max-width:calc(100vw - 40px);box-shadow:0 28px 70px rgba(0,0,0,.22),0 0 0 1px rgba(0,0,0,.04);transform:scale(.93) translateY(12px);opacity:0;transition:transform .22s cubic-bezier(.34,1.38,.64,1),opacity .18s ease;overflow:hidden;">

        {{-- Body --}}
        <div style="padding:32px 28px 24px;text-align:center;">
            <div style="width:60px;height:60px;border-radius:16px;background:#fee2e2;display:flex;align-items:center;justify-content:center;margin:0 auto 18px;box-shadow:0 4px 14px rgba(239,68,68,.18);">
                <i class="fas fa-trash-alt" style="font-size:22px;color:#ef4444;"></i>
            </div>
            <div style="font-size:16px;font-weight:700;color:#111827;margin-bottom:8px;letter-spacing:-.01em;">Delete Task</div>
            <div style="font-size:13.5px;color:#6b7280;line-height:1.65;">
                Are you sure you want to delete this task?
                <span style="display:block;margin-top:3px;font-weight:600;color:#374151;">This action cannot be undone.</span>
            </div>
        </div>

        {{-- Divider --}}
        <div style="height:1px;background:#f1f5f9;margin:0 28px;"></div>

        {{-- Buttons --}}
        <div style="display:flex;gap:10px;padding:20px 28px 24px;">
            <button onclick="appDelCancel()"
                    style="flex:1;padding:10px 0;border:1.5px solid #e2e8f0;background:#f8fafc;color:#374151;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;font-family:inherit;"
                    onmouseover="this.style.borderColor='#94a3b8';this.style.background='#f1f5f9'"
                    onmouseout="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc'">
                Cancel
            </button>
            <button id="app-del-btn" onclick="appDelExecute(this)"
                    style="flex:1;padding:10px 0;background:linear-gradient(135deg,#ef4444,#dc2626);border:none;color:#fff;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .15s;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:7px;box-shadow:0 4px 14px rgba(239,68,68,.3);"
                    onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                <i class="fas fa-trash-alt" style="font-size:10px;"></i>Delete
            </button>
        </div>
    </div>
</div>

<script>
var _appDelUrl = null, _appDelCb = null;

window.appDeleteConfirm = function(url, cb) {
    _appDelUrl = url; _appDelCb = cb || null;
    var ov   = document.getElementById('app-del-overlay');
    var card = document.getElementById('app-del-card');
    var btn  = document.getElementById('app-del-btn');
    btn.innerHTML = '<i class="fas fa-trash-alt" style="font-size:10px;"></i>Delete';
    btn.disabled  = false; btn.style.opacity = '1';
    ov.style.display = 'flex';
    requestAnimationFrame(function(){ requestAnimationFrame(function(){
        card.style.transform = 'scale(1) translateY(0)';
        card.style.opacity   = '1';
    }); });
};

window.appDelCancel = function() {
    var card = document.getElementById('app-del-card');
    card.style.transform = 'scale(.93) translateY(12px)';
    card.style.opacity   = '0';
    setTimeout(function(){ document.getElementById('app-del-overlay').style.display = 'none'; }, 200);
};

window.appDelExecute = function(btn) {
    if (!_appDelUrl) return;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:11px;"></i>&nbsp;Deleting…';
    btn.disabled  = true;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    fetch(_appDelUrl, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded'},
        body: '_method=DELETE'
    }).then(function() {
        appDelCancel();
        if (_appDelCb) { _appDelCb(); }
        else { setTimeout(function(){ location.reload(); }, 200); }
    }).catch(function() {
        btn.innerHTML = '<i class="fas fa-trash-alt" style="font-size:10px;"></i>Delete';
        btn.disabled  = false;
    });
};

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var ov = document.getElementById('app-del-overlay');
        if (ov && ov.style.display !== 'none') appDelCancel();
    }
});
</script>
</body>
</html>
