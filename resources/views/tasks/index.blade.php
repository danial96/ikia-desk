@extends('layouts.app')

@section('title', 'Tasks')
@section('page-title', 'Tasks')

@section('content')
<div id="nt-tasks-content">

    @include('tasks._filter_bar', [
        'formAction' => route('tasks.index'),
        'activeView' => 'list',
        'projects'   => $projects,
        'employees'  => $employees,
    ])
    {{-- Task List (Bitrix-style table) --}}
    <style>
    .bx-card { background:#fff; border-radius:11px; overflow:hidden; box-shadow:0 2px 14px rgba(0,0,0,.25); }
    .bx-table { width:100%; border-collapse:collapse; font-size:14px; color:#333; }
    .bx-table th { text-align:left; font-weight:400; color:#535c69; padding:16px 12px; border-bottom:1px solid #e7ecee; white-space:nowrap; font-size:14.5px; }
    .bx-table td { padding:12px; border-bottom:1px solid #eef1f3; vertical-align:middle; }
    .bx-table th.c-active, .bx-table td.c-active { background:#f0f9fd; }
    .bx-row { cursor:pointer; transition:background .1s; }
    .bx-row:hover td { background:#f5f9fb; } .bx-row:hover td.c-active { background:#e9f5fb; }
    .c-chk { width:44px; text-align:center; } .c-name { min-width:280px; width:34%; }
    .c-active { color:#4a5560; white-space:nowrap; } .c-dl { white-space:nowrap; } .c-proj { color:#5b6670; }
    .bx-title { font-size:14.5px; color:#333; }
    .bx-chip { display:inline-flex; align-items:center; gap:4px; margin-left:8px; font-size:11px; color:#9aa0a6; border:1px solid #e3e6e9; border-radius:9px; padding:0 6px; line-height:17px; }
    .bx-chip i { font-size:9px; }
    .bx-pill { display:inline-block; border:1px solid; border-radius:12px; padding:0 11px; line-height:24px; font-size:13px; }
    .bx-user { display:inline-flex; align-items:center; gap:9px; white-space:nowrap; }
    .bx-user img { width:26px; height:26px; border-radius:50%; object-fit:cover; }
    .bx-status { display:inline-block; border-radius:6px; padding:3px 10px; font-size:12.5px; font-weight:500; white-space:nowrap; }
    .bx-foot { display:flex; gap:44px; align-items:center; padding:16px 22px; font-size:11.5px; letter-spacing:.3px; color:#535c69; text-transform:uppercase; border-top:1px solid #e7ecee; }
    .bx-foot b { color:#333; }
    .bx-chk, #bx-all { width:17px; height:17px; cursor:pointer; }
    @media (max-width: 1100px) { .bx-table th:nth-child(5), .bx-table td:nth-child(5), .bx-table th:nth-child(7), .bx-table td:nth-child(7) { display:none; } }
    </style>
    <div class="bx-card">
        <div style="overflow-x:auto;">
        <table class="bx-table">
            <thead><tr>
                <th class="c-chk"><input type="checkbox" id="bx-all" onchange="bxAll(this)"></th>
                <th>Name</th><th class="c-active">Active <i class="fas fa-chevron-down" style="font-size:9px;margin-left:3px;"></i></th><th>Deadline</th><th>Created by</th><th>Assignee</th><th>Project</th><th>Status</th>
            </tr></thead>
            <tbody id="task-list">
                @forelse($tasks as $task)
                @include('tasks._task_row')
                @empty
                <tr><td colspan="8" style="padding:60px 20px;text-align:center;color:#9aa5ad;">
                    <div style="font-size:15px;color:#7d8790;">No tasks found</div>
                    @if(auth()->user()->isAdmin())
                    <button onclick="openTaskModal()" style="color:#0075fd;background:none;border:none;font-size:13.5px;font-weight:600;cursor:pointer;margin-top:8px;">Create your first task</button>
                    @endif
                </td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="bx-foot">
            <span>Selected: <b id="bx-sel">0</b> / {{ $tasks->total() }}</span>
            <span>Total: <b>{{ number_format($tasks->total()) }}</b></span>
        </div>
    </div>
    <script>
    function bxCount(){ var n=document.querySelectorAll('.bx-chk:checked').length; document.getElementById('bx-sel').textContent=n; var a=document.getElementById('bx-all'); if(a) a.checked = n>0 && n===document.querySelectorAll('.bx-chk').length; }
    function bxAll(cb){ document.querySelectorAll('.bx-chk').forEach(function(c){ c.checked=cb.checked; }); bxCount(); }
    </script>

    {{-- Infinite scroll sentinel --}}
    <div id="task-sentinel" style="height:1px;margin-top:8px;"></div>
    <div id="task-scroll-status" style="text-align:center;padding:16px 0;color:rgba(255,255,255,.3);font-size:12px;display:none;">All tasks loaded</div>

    <script>
    (function(){
        var _page = {{ $tasks->currentPage() }};
        var _hasMore = {{ $tasks->hasMorePages() ? 'true' : 'false' }};
        var _loading = false;
        var _itemCount = {{ $tasks->count() + ($tasks->currentPage() - 1) * $tasks->perPage() }};
        var _total = {{ $tasks->total() }};

        var list = document.getElementById('task-list');
        var sentinel = document.getElementById('task-sentinel');
        var statusEl = document.getElementById('task-scroll-status');

        function getParams() {
            var p = new URLSearchParams(window.location.search);
            p.delete('page');
            return p;
        }

        async function loadMore() {
            if (!_hasMore || _loading) return;
            _loading = true;
            sentinel.innerHTML = '<div style="text-align:center;padding:16px;"><div style="display:inline-block;width:20px;height:20px;border:2px solid rgba(255,255,255,.2);border-top-color:#00D4E8;border-radius:50%;animation:spin .7s linear infinite;"></div></div>';

            var params = getParams();
            params.set('page', _page + 1);

            try {
                var r = await fetch(location.pathname + '?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                var d = await r.json();
                list.insertAdjacentHTML('beforeend', d.html);
                _page++;
                _hasMore = d.hasMore;
                _itemCount += d.loaded;
                sentinel.innerHTML = '';
                if (!_hasMore) {
                    sentinel.style.display = 'none';
                    statusEl.style.display = 'block';
                    statusEl.textContent = 'All ' + _total + ' tasks loaded';
                }
            } catch(e) {
                sentinel.innerHTML = '<div style="text-align:center;padding:16px;color:rgba(255,82,82,.6);font-size:12px;">Load failed. <button onclick="loadMore()" style="color:#00D4E8;background:none;border:none;cursor:pointer;font-size:12px;">Retry</button></div>';
            }
            _loading = false;
        }

        window.loadMore = loadMore;

        var obs = new IntersectionObserver(function(entries){
            if (entries[0].isIntersecting) loadMore();
        }, { rootMargin: '300px' });

        obs.observe(sentinel);
    })();
    </script>

    @if(auth()->user()->isAdmin() || !empty(auth()->user()->permissions['create_tasks']))
    @include('tasks._form_modal', ['showVar' => 'showModal'])
    @endif
</div>

@include('tasks._task_panel')

<script>
/* Legacy dead code kept for reference only — remove once panel is stable */
function b24lRenderPanel_REMOVED(data) {
    const t     = data.task;
    const users = data.users || {};
    const files = data.files || [];
    const chat  = data.chat  || [];

    const responsible  = users[t.responsibleId] || null;
    const creator      = users[t.creatorId]     || null;
    const participants = (t.accomplices || []).map(uid => users[uid]).filter(Boolean);
    const observers    = (t.auditors    || []).map(uid => users[uid]).filter(Boolean);

    const dl = t.deadline ? new Date(t.deadline) : null;
    const dlFull   = dl ? dl.toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long',year:'numeric'}) : null;
    const dlTime   = dl ? dl.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'}) : null;
    const isOvr    = dl && dl < new Date() && t.status !== '5';
    const daysLeft = dl ? Math.ceil((dl - new Date()) / 86400000) : null;

    const sLbl = B24L_STATUS_LABEL[t.status] || '—';
    const pLbl = {'0':'LOW','1':'HIGH','2':'URGENT'}[t.priority] || 'LOW';
    const sc = {'1':['#f1f5f9','#334155'],'2':['#fef3c7','#92400e'],'3':['#dbeafe','#1e40af'],'4':['#ede9fe','#5b21b6'],'5':['#dcfce7','#166534'],'6':['#f1f5f9','#475569']}[t.status]||['#f1f5f9','#334155'];
    const pc = {'0':['#e2e8f0','#475569'],'1':['#ffedd5','#c2410c'],'2':['#fee2e2','#b91c1c']}[t.priority]||['#e2e8f0','#475569'];

    const uAvatar = (u, size=36) => !u ? '' : u.icon
        ? `<img src="${u.icon}" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,212,232,.3);" title="${u.name}" alt="">`
        : `<div style="width:${size}px;height:${size}px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:${Math.round(size*.38)}px;font-weight:700;color:#fff;border:2px solid rgba(0,212,232,.3);" title="${u.name}">${u.name.charAt(0)}</div>`;

    // BBCode parser for chat messages
    const parseMsg = txt => (txt||'')
        .replace(/\[USER=\d+\]([^\[]*)\[\/USER\]/g, '<span style="color:#00D4E8;font-weight:600;">$1</span>')
        .replace(/\[TIMESTAMP=(\d+)\s+FORMAT=[^\]]*\]/g, (_, ts) => {
            const d = new Date(parseInt(ts)*1000);
            return '<span style="color:#fbbf24;">' + d.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) + '</span>';
        })
        .replace(/\[\/?\w[^\]]*\]/g, '')
        .replace(/\n/g,'<br>');

    const desc = (t.description||'').replace(/\[url\](.*?)\[\/url\]/gi,'<a href="$1" target="_blank" style="color:#00D4E8;word-break:break-all;">$1</a>').replace(/\[.*?\]/g,'');

    const fileIcons = {'pdf':'fa-file-pdf','jpg':'fa-file-image','jpeg':'fa-file-image','png':'fa-file-image','doc':'fa-file-word','docx':'fa-file-word','xls':'fa-file-excel','xlsx':'fa-file-excel','zip':'fa-file-archive'};
    const filesHtml = files.length ? `
        <div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);">
            <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 12px;"><i class="fas fa-paperclip" style="margin-right:6px;color:#00D4E8;"></i>Files (${files.length})</p>
            <div style="display:flex;flex-direction:column;gap:8px;">${files.map(f=>{
                const ext=(f.name.split('.').pop()||'').toLowerCase();
                const icon=fileIcons[ext]||'fa-file';
                const sz=f.size?Math.round(f.size/1024)+' KB':'';
                return `<a href="${f.downloadUrl||'#'}" target="_blank" style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:9px;text-decoration:none;transition:background .15s;" onmouseover="this.style.background='rgba(0,212,232,.08)'" onmouseout="this.style.background='rgba(255,255,255,.05)'">
                    <i class="fas ${icon}" style="font-size:18px;color:#00D4E8;width:22px;flex-shrink:0;"></i>
                    <div style="flex:1;min-width:0;"><p style="color:rgba(255,255,255,.85);font-size:12.5px;font-weight:500;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${f.name}</p>${sz?`<p style="color:rgba(255,255,255,.35);font-size:11px;margin:2px 0 0;">${sz}</p>`:''}</div>
                    <i class="fas fa-download" style="font-size:12px;color:rgba(255,255,255,.3);flex-shrink:0;"></i>
                </a>`;
            }).join('')}</div>
        </div>` : '';

    const peopleRow = (label, list) => !list.length ? '' : `
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span style="color:rgba(255,255,255,.3);font-size:11px;min-width:80px;">${label}</span>
            <div style="display:flex;gap:6px;flex-wrap:wrap;">${list.map(u=>`<div style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.06);border-radius:20px;padding:4px 10px 4px 4px;">${uAvatar(u,26)}<span style="color:rgba(255,255,255,.8);font-size:12px;">${u.name}</span></div>`).join('')}</div>
        </div>`;

    const userMsgs = chat.filter(m => !m.isSystem);
    const chatHtml = `
        <div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);">
            <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 12px;">
                <i class="fas fa-comment-dots" style="margin-right:6px;color:#00D4E8;"></i>Task Chat${userMsgs.length ? ' ('+userMsgs.length+')' : ''}
            </p>
            ${chat.length ? `
            <div style="display:flex;flex-direction:column;gap:7px;max-height:340px;overflow-y:auto;padding-right:3px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.1) transparent;">
                ${chat.map(m=>{
                    const mDate=new Date(m.date).toLocaleString('en-GB',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
                    const mText=parseMsg(m.text);
                    if(m.isSystem){return `<div style="display:flex;align-items:flex-start;gap:7px;padding:5px 9px;background:rgba(255,255,255,.025);border-radius:6px;border-left:2px solid rgba(255,255,255,.08);"><i class="fas fa-info-circle" style="font-size:9px;color:rgba(255,255,255,.2);margin-top:3px;flex-shrink:0;"></i><p style="color:rgba(255,255,255,.3);font-size:11px;margin:0;font-style:italic;flex:1;line-height:1.45;">${mText}</p><span style="color:rgba(255,255,255,.15);font-size:9.5px;flex-shrink:0;white-space:nowrap;margin-left:6px;">${mDate}</span></div>`;}
                    const a=m.author;
                    return `<div style="display:flex;gap:8px;align-items:flex-start;"><div style="flex-shrink:0;">${uAvatar(a,28)}</div><div style="flex:1;background:rgba(255,255,255,.05);border-radius:0 9px 9px 9px;padding:8px 11px;"><div style="display:flex;align-items:baseline;gap:8px;margin-bottom:4px;flex-wrap:wrap;"><span style="color:#00D4E8;font-size:12px;font-weight:600;">${a?a.name:'—'}</span><span style="color:rgba(255,255,255,.25);font-size:10px;">${mDate}</span></div><p style="color:rgba(255,255,255,.82);font-size:12.5px;margin:0;line-height:1.5;">${mText}</p></div></div>`;
                }).join('')}
            </div>` : `<p style="color:rgba(255,255,255,.2);font-size:12px;margin:0;text-align:center;padding:18px 0;"><i class="fas fa-comment-slash" style="margin-right:6px;"></i>No messages yet</p>`}
            <div style="margin-top:12px;display:flex;gap:8px;">
                <div style="flex:1;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:9px;padding:10px 13px;color:rgba(255,255,255,.2);font-size:12px;display:flex;align-items:center;gap:8px;">
                    <i class="fas fa-comment" style="font-size:11px;color:rgba(255,255,255,.15);"></i>
                    <span>Reply in Desk...</span>
                </div>
                <a href="https://thesoftcube.bitrix24.com/tasks/task/view/${t.id}/" target="_blank"
                   style="background:rgba(0,212,232,.12);border:1px solid rgba(0,212,232,.25);border-radius:9px;padding:10px 14px;color:#00D4E8;font-size:12px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:5px;white-space:nowrap;transition:background .15s;"
                   onmouseover="this.style.background='rgba(0,212,232,.22)'" onmouseout="this.style.background='rgba(0,212,232,.12)'">
                    <i class="fas fa-external-link-alt" style="font-size:10px;"></i> Open
                </a>
            </div>
        </div>`;

    document.getElementById('b24l-panel-body').innerHTML = `
        <div style="padding:22px 24px;background:linear-gradient(135deg,rgba(0,212,232,.08),rgba(99,102,241,.05));border-bottom:1px solid rgba(255,255,255,.07);">
            <h2 style="color:#fff;font-size:16px;font-weight:700;margin:0 0 12px;line-height:1.5;">${t.title}</h2>
            <div style="display:flex;gap:7px;flex-wrap:wrap;">
                <span style="font-size:11.5px;font-weight:600;padding:4px 13px;border-radius:20px;background:${sc[0]};color:${sc[1]};"><i class="fas fa-circle" style="font-size:7px;margin-right:4px;"></i>${sLbl}</span>
                <span style="font-size:11.5px;font-weight:600;padding:4px 13px;border-radius:20px;background:${pc[0]};color:${pc[1]};"><i class="fas fa-flag" style="font-size:9px;margin-right:4px;"></i>${pLbl}</span>
                <span style="font-size:11.5px;font-weight:600;padding:4px 13px;border-radius:20px;background:rgba(0,212,232,.12);color:#00D4E8;">#${t.id}</span>
            </div>
        </div>
        <div style="padding:0 24px;">
            ${desc?`<div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);"><p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 8px;"><i class="fas fa-align-left" style="margin-right:6px;color:#00D4E8;"></i>Description</p><p style="color:rgba(255,255,255,.75);font-size:13px;line-height:1.6;margin:0;">${desc}</p></div>`:''}
            <div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 10px;"><i class="fas fa-calendar-alt" style="margin-right:6px;color:#00D4E8;"></i>Deadline</p>
                ${dl?`<div style="display:flex;align-items:center;justify-content:space-between;"><div><p style="color:${isOvr?'#f87171':'rgba(255,255,255,.9)'};font-size:14px;font-weight:600;margin:0;">${dlFull}</p><p style="color:rgba(255,255,255,.35);font-size:11.5px;margin:3px 0 0;"><i class="fas fa-clock" style="margin-right:4px;font-size:9px;"></i>${dlTime}</p></div>${isOvr?`<span style="background:rgba(239,68,68,.15);color:#f87171;font-size:11px;font-weight:700;padding:5px 11px;border-radius:20px;border:1px solid rgba(239,68,68,.3);"><i class="fas fa-exclamation-triangle" style="margin-right:3px;"></i>OVERDUE</span>`:daysLeft===0?`<span style="background:rgba(245,158,11,.15);color:#fbbf24;font-size:11px;font-weight:700;padding:5px 11px;border-radius:20px;border:1px solid rgba(245,158,11,.3);">Due Today</span>`:`<span style="background:rgba(34,197,94,.1);color:#4ade80;font-size:11px;font-weight:600;padding:5px 11px;border-radius:20px;">${daysLeft}d left</span>`}</div>`:
                `<p style="color:rgba(255,255,255,.35);font-size:13px;margin:0;">No deadline set</p>`}
            </div>
            <div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;gap:12px;">
                <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0;"><i class="fas fa-users" style="margin-right:6px;color:#00D4E8;"></i>People</p>
                ${responsible?`<div style="display:flex;align-items:center;gap:12px;">${uAvatar(responsible,42)}<div><p style="color:rgba(255,255,255,.35);font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 2px;">Assignee</p><p style="color:#fff;font-size:13.5px;font-weight:600;margin:0;">${responsible.name}</p>${responsible.workPosition?`<p style="color:#00D4E8;font-size:11px;margin:2px 0 0;">${responsible.workPosition}</p>`:''}</div></div>`:''}
                ${creator?`<div style="display:flex;align-items:center;gap:12px;">${uAvatar(creator,42)}<div><p style="color:rgba(255,255,255,.35);font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 2px;">Task Owner</p><p style="color:#fff;font-size:13.5px;font-weight:600;margin:0;">${creator.name}</p>${creator.workPosition?`<p style="color:#00D4E8;font-size:11px;margin:2px 0 0;">${creator.workPosition}</p>`:''}</div></div>`:''}
                ${peopleRow('Participants', participants)}
                ${peopleRow('Observers', observers)}
            </div>
            ${filesHtml}
            ${chatHtml}
            <div style="padding:16px 0;"><div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:9px;padding:11px;"><p style="color:rgba(255,255,255,.3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 4px;">Created</p><p style="color:rgba(255,255,255,.8);font-size:12px;font-weight:500;margin:0;">${t.createdDate?new Date(t.createdDate).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}):'—'}</p></div>
                <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:9px;padding:11px;"><p style="color:rgba(255,255,255,.3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 4px;">Task ID</p><p style="color:#00D4E8;font-size:12px;font-weight:600;margin:0;">#${t.id}</p></div>
            </div></div>
        </div>`;
}

}
</script>
@endsection
