@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<style>
.glass { background:rgba(255,255,255,.07); backdrop-filter:blur(10px); border:1px solid rgba(255,255,255,.1); border-radius:14px; }
.txt-main { color:rgba(255,255,255,.88); }
.txt-sub  { color:rgba(255,255,255,.4); }
.txt-dim  { color:rgba(255,255,255,.25); }
.divider  { border-color:rgba(255,255,255,.07); }
</style>

<div style="display:flex;flex-direction:column;gap:20px;">

    {{-- Welcome banner --}}
    <div class="glass" style="padding:22px 26px;display:flex;align-items:center;justify-content:space-between;overflow:hidden;position:relative;">
        <div style="position:absolute;right:-40px;top:-40px;width:220px;height:220px;background:radial-gradient(circle,rgba(0,212,232,.12) 0%,transparent 70%);pointer-events:none;"></div>
        <div>
            <h2 class="txt-main" style="font-size:18px;font-weight:700;margin:0 0 5px;">
                Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }}, {{ explode(' ', auth()->user()->name)[0] }}!
            </h2>
            <p class="txt-sub" style="font-size:13px;margin:0;">Here's what's happening today.</p>
        </div>
        <img src="{{ asset('logo-white.png') }}" alt="" style="height:32px;opacity:.2;object-fit:contain;">
    </div>

    {{-- Stats --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;">
        @php
        $statsConfig = [
            ['label'=>'Total Tasks',    'value'=>$stats['total_tasks'],    'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color'=>'#1B72E8','glow'=>'rgba(27,114,232,.2)'],
            ['label'=>'My Active',      'value'=>$stats['my_tasks'],       'icon'=>'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', 'color'=>'#00D4E8','glow'=>'rgba(0,212,232,.2)'],
            ['label'=>'Overdue',        'value'=>$stats['overdue_tasks'],  'icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'color'=>'#ef4444','glow'=>'rgba(239,68,68,.2)'],
            ['label'=>'Projects',       'value'=>$stats['total_projects'], 'icon'=>'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z', 'color'=>'#7B2FBE','glow'=>'rgba(123,47,190,.2)'],
        ];
        @endphp
        @foreach($statsConfig as $s)
        <div class="glass" style="padding:20px;">
            <div style="width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:{{ $s['glow'] }};margin-bottom:14px;">
                <svg style="width:20px;height:20px;" fill="none" stroke="{{ $s['color'] }}" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $s['icon'] }}"/>
                </svg>
            </div>
            <p class="txt-main" style="font-size:26px;font-weight:700;margin:0 0 3px;">{{ $s['value'] }}</p>
            <p class="txt-sub" style="font-size:12px;margin:0;">{{ $s['label'] }}</p>
        </div>
        @endforeach
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

        {{-- My Tasks --}}
        <div class="glass" style="overflow:hidden;">
            <div style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07);">
                <h2 class="txt-main" style="font-size:13.5px;font-weight:600;margin:0;">In Progress Tasks</h2>
                <a href="{{ route('tasks.index') }}"
                   style="font-size:12px;font-weight:500;color:#00D4E8;background:rgba(0,212,232,.1);padding:5px 12px;border-radius:8px;text-decoration:none;">View all</a>
            </div>
            @forelse($myTasks as $task)
            <a href="{{ route('tasks.show', $task) }}"
               style="display:flex;align-items:center;gap:12px;padding:13px 20px;border-bottom:1px solid rgba(255,255,255,.04);text-decoration:none;transition:background .15s;"
               onmouseover="this.style.background='rgba(255,255,255,.05)'" onmouseout="this.style.background=''">
                <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;
                     background:{{ ['low'=>'#64748b','medium'=>'#1B72E8','high'=>'#f97316','urgent'=>'#ef4444'][$task->priority] ?? '#64748b' }};"></div>
                <div style="flex:1;min-width:0;">
                    <p class="txt-main" style="font-size:13px;font-weight:500;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $task->title }}</p>
                    @if($task->deadline)
                    <p style="font-size:11px;margin:2px 0 0;color:{{ $task->deadline->isPast() ? '#ef4444' : 'rgba(255,255,255,.3)' }};">
                        Due {{ $task->deadline->format('M d') }}
                    </p>
                    @endif
                </div>
                <span style="font-size:11px;padding:3px 8px;border-radius:6px;font-weight:500;flex-shrink:0;
                     background:{{ ['new'=>'rgba(148,163,184,.15)','in_progress'=>'rgba(27,114,232,.2)','paused'=>'rgba(251,191,36,.2)','completed'=>'rgba(34,197,94,.15)'][$task->status] ?? 'rgba(148,163,184,.15)' }};
                     color:{{ ['new'=>'#94a3b8','in_progress'=>'#60a5fa','paused'=>'#fde68a','completed'=>'#4ade80'][$task->status] ?? '#94a3b8' }};">
                    {{ str_replace('_',' ',ucfirst($task->status)) }}
                </span>
            </a>
            @empty
            <div style="padding:40px 20px;text-align:center;">
                <div style="width:44px;height:44px;border-radius:12px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;background:rgba(27,114,232,.1);">
                    <svg style="width:22px;height:22px;" fill="none" stroke="#1B72E8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
                <p class="txt-sub" style="font-size:13px;margin:0;">No tasks assigned to you</p>
            </div>
            @endforelse
        </div>

        {{-- Recent Projects --}}
        <div class="glass" style="overflow:hidden;">
            <div style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07);">
                <h2 class="txt-main" style="font-size:13.5px;font-weight:600;margin:0;">Recent Projects</h2>
                <a href="{{ route('projects.index') }}"
                   style="font-size:12px;font-weight:500;color:#00D4E8;background:rgba(0,212,232,.1);padding:5px 12px;border-radius:8px;text-decoration:none;">View all</a>
            </div>
            @forelse($recentProjects as $project)
            <a href="{{ route('projects.show', $project) }}"
               style="display:flex;align-items:center;gap:14px;padding:13px 20px;border-bottom:1px solid rgba(255,255,255,.04);text-decoration:none;transition:background .15s;"
               onmouseover="this.style.background='rgba(255,255,255,.05)'" onmouseout="this.style.background=''">
                <div style="width:36px;height:36px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:{{ $project->color }}25;">
                    <div style="width:12px;height:12px;border-radius:50%;background:{{ $project->color }};"></div>
                </div>
                <div style="flex:1;min-width:0;">
                    <p class="txt-main" style="font-size:13px;font-weight:500;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $project->name }}</p>
                    <p class="txt-sub" style="font-size:11px;margin:2px 0 0;">{{ $project->tasks->count() }} tasks</p>
                </div>
                <span style="font-size:11px;padding:3px 8px;border-radius:6px;font-weight:500;flex-shrink:0;
                     background:{{ $project->status==='active' ? 'rgba(34,197,94,.15)' : 'rgba(148,163,184,.12)' }};
                     color:{{ $project->status==='active' ? '#4ade80' : '#94a3b8' }};">
                    {{ $project->status }}
                </span>
            </a>
            @empty
            <div style="padding:40px 20px;text-align:center;">
                <p class="txt-sub" style="font-size:13px;margin:0;">No projects yet</p>
            </div>
            @endforelse
        </div>

    </div>

    {{-- Bitrix24 Live Tasks --}}
    <div class="glass" style="overflow:hidden;">
        <div style="padding:15px 20px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(255,255,255,.07);">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:28px;height:28px;border-radius:8px;background:rgba(0,212,232,.15);display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-bolt" style="font-size:12px;color:#00D4E8;"></i>
                </div>
                <div>
                    <h2 class="txt-main" style="font-size:13.5px;font-weight:600;margin:0;line-height:1;">Bitrix24 Tasks</h2>
                    <span class="txt-sub" style="font-size:10.5px;">Upcoming &amp; active</span>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <span id="b24-live-dot" style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:rgba(255,255,255,.35);">
                    <span style="width:7px;height:7px;border-radius:50%;background:#22c55e;box-shadow:0 0 6px #22c55e;display:inline-block;"></span>Live
                </span>
                <button onclick="b24Refresh()" style="width:28px;height:28px;border-radius:8px;background:rgba(255,255,255,.06);border:none;cursor:pointer;color:rgba(255,255,255,.5);display:flex;align-items:center;justify-content:center;transition:all .15s;" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='rgba(255,255,255,.06)'" title="Refresh">
                    <i class="fas fa-sync-alt" style="font-size:11px;" id="b24-refresh-icon"></i>
                </button>
            </div>
        </div>

        {{-- Loading skeleton --}}
        <div id="b24-loading" style="padding:14px 20px;display:flex;flex-direction:column;gap:10px;">
            @for($i=0;$i<4;$i++)
            <div style="display:flex;align-items:center;gap:12px;animation:b24pulse 1.4s ease-in-out infinite;animation-delay:{{ $i*0.15 }}s;">
                <div style="width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.1);flex-shrink:0;"></div>
                <div style="flex:1;height:13px;border-radius:6px;background:rgba(255,255,255,.07);"></div>
                <div style="width:60px;height:13px;border-radius:6px;background:rgba(255,255,255,.05);"></div>
                <div style="width:80px;height:13px;border-radius:6px;background:rgba(255,255,255,.05);"></div>
            </div>
            @endfor
        </div>

        {{-- Tasks list --}}
        <div id="b24-tasks" style="display:none;"></div>

        {{-- Error --}}
        <div id="b24-error" style="display:none;padding:24px 20px;text-align:center;">
            <i class="fas fa-exclamation-circle" style="font-size:20px;color:rgba(239,68,68,.6);margin-bottom:8px;display:block;"></i>
            <p class="txt-sub" style="font-size:12.5px;margin:0;">Could not load Bitrix24 tasks</p>
        </div>
    </div>

</div>

<style>
@keyframes b24pulse { 0%,100%{opacity:.6} 50%{opacity:1} }
@keyframes b24fadeIn { from{opacity:0;transform:translateY(6px)} to{opacity:1;transform:translateY(0)} }
.b24-row { display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid rgba(255,255,255,.04);text-decoration:none;transition:background .15s;animation:b24fadeIn .25s ease both; }
.b24-row:hover { background:rgba(255,255,255,.05); }
.b24-row:last-child { border-bottom:none; }
</style>

<script>
const B24_URL = '{{ route("api.bitrix.tasks") }}';
const b24Status      = { '1':'New','2':'Pending','3':'In Progress','4':'Reviewing','5':'Completed','6':'Deferred' };
const b24StatusColor = { '1':'rgba(148,163,184,.15)','2':'rgba(245,158,11,.15)','3':'rgba(27,114,232,.2)','4':'rgba(139,92,246,.15)','5':'rgba(34,197,94,.15)','6':'rgba(148,163,184,.1)' };
const b24StatusText  = { '1':'#94a3b8','2':'#fbbf24','3':'#60a5fa','4':'#a78bfa','5':'#4ade80','6':'#64748b' };
const b24PriorityDot = { '0':'#64748b','1':'#f97316','2':'#ef4444' };

function b24Render(tasks) {
    const wrap = document.getElementById('b24-tasks');
    wrap.innerHTML = tasks.map((t, i) => {
        const dl = t.deadline ? new Date(t.deadline) : null;
        const dlStr = dl ? dl.toLocaleDateString('en-GB',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) : '—';
        const isOverdue = dl && dl < new Date() && t.status !== '4';
        const dot = b24PriorityDot[t.priority] || '#64748b';
        const resp = t.responsible ? t.responsible.name : '—';
        return `<a class="b24-row" href="#" style="animation-delay:${i*0.07}s;">
            <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${dot};box-shadow:0 0 5px ${dot}55;"></div>
            <div style="flex:1;min-width:0;">
                <p class="txt-main" style="font-size:13px;font-weight:500;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${t.title}</p>
                <p style="font-size:11px;margin:2px 0 0;color:${isOverdue?'#ef4444':'rgba(255,255,255,.3)'};">
                    <i class="fas fa-calendar-alt" style="font-size:9px;margin-right:3px;"></i>${dlStr}
                    ${isOverdue?'<span style="color:#ef4444;margin-left:4px;">Overdue</span>':''}
                </p>
            </div>
            <span style="font-size:11px;color:rgba(255,255,255,.4);white-space:nowrap;margin-right:6px;">
                <i class="fas fa-user" style="font-size:9px;margin-right:3px;"></i>${resp.split(' ')[0]}
            </span>
            <span style="font-size:11px;padding:3px 9px;border-radius:6px;font-weight:500;flex-shrink:0;background:${b24StatusColor[t.status]||'rgba(148,163,184,.15)'};color:${b24StatusText[t.status]||'#94a3b8'};">
                ${b24Status[t.status]||'—'}
            </span>
        </a>`;
    }).join('');
    wrap.style.display = 'block';
}

function b24Refresh() {
    document.getElementById('b24-tasks').style.display = 'none';
    document.getElementById('b24-error').style.display = 'none';
    document.getElementById('b24-loading').style.display = 'flex';
    const icon = document.getElementById('b24-refresh-icon');
    icon.style.animation = 'spin 0.8s linear infinite';
    icon.style.animationName = 'b24spin';

    fetch(B24_URL, { headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } })
        .then(r => r.json())
        .then(data => {
            document.getElementById('b24-loading').style.display = 'none';
            icon.style.animation = '';
            const tasks = data.result && data.result.tasks;
            if (tasks && tasks.length) b24Render(tasks.slice(0, 4));
            else document.getElementById('b24-error').style.display = 'block';
        })
        .catch(() => {
            document.getElementById('b24-loading').style.display = 'none';
            document.getElementById('b24-error').style.display = 'block';
            icon.style.animation = '';
        });
}

document.addEventListener('DOMContentLoaded', b24Refresh);
</script>

@endsection
