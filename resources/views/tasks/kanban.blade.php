@extends('layouts.app')
@section('title', 'Kanban Board')
@section('page-title', 'Tasks')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<div id="nt-tasks-content" style="min-width:0;">

    @include('tasks._filter_bar', [
        'formAction' => route('tasks.kanban'),
        'activeView' => 'kanban',
        'projects'   => $projects,
        'employees'  => $employees,
    ])

    {{-- Kanban Board — horizontal scroll with arrows --}}
    @php
    $colConfig = [
        'overdue'            => ['label' => 'Overdue',            'bg' => '#ff5752'],
        'due_today'          => ['label' => 'Due today',          'bg' => '#9dcf00'],
        'due_this_week'      => ['label' => 'Due this week',      'bg' => '#2fc6f6'],
        'due_next_week'      => ['label' => 'Due next week',      'bg' => '#55d0e0'],
        'no_deadline'        => ['label' => 'No deadline',        'bg' => '#a8adb4'],
        'due_over_two_weeks' => ['label' => 'Due over two weeks', 'bg' => '#468ee5'],
        'completed'          => ['label' => 'Completed',          'bg' => '#6f768f'],
    ];
    @endphp

    <div style="position:relative;min-width:0;">

        {{-- Left arrow --}}
        <button id="kb-left"
                style="position:fixed;left:230px;top:50%;transform:translateY(-50%);z-index:99;width:44px;height:44px;border-radius:50%;background:rgba(30,30,80,.55);backdrop-filter:blur(12px);border:1.5px solid rgba(255,255,255,.25);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s,opacity .2s;opacity:0;pointer-events:none;box-shadow:0 4px 20px rgba(0,0,0,.4);"
                onclick="document.getElementById('kb-scroll').scrollBy({left:-280,behavior:'smooth'})"
                onmouseover="this.style.background='rgba(255,255,255,.25)'"
                onmouseout="this.style.background='rgba(30,30,80,.55)'">
            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>

        {{-- Right arrow --}}
        <button id="kb-right"
                style="position:fixed;right:62px;top:50%;transform:translateY(-50%);z-index:99;width:44px;height:44px;border-radius:50%;background:rgba(30,30,80,.55);backdrop-filter:blur(12px);border:1.5px solid rgba(255,255,255,.25);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s,opacity .2s;box-shadow:0 4px 20px rgba(0,0,0,.4);"
                onclick="document.getElementById('kb-scroll').scrollBy({left:280,behavior:'smooth'})"
                onmouseover="this.style.background='rgba(255,255,255,.25)'"
                onmouseout="this.style.background='rgba(30,30,80,.55)'">
            <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

    <div id="kb-scroll" style="display:flex;gap:12px;overflow-x:auto;padding:0 8px 8px;align-items:flex-start;scrollbar-width:none;" onscroll="kbUpdateArrows()">
        <style>
        #kb-scroll::-webkit-scrollbar{display:none}
        .kb-drag-ghost { opacity:.4; background:#e0f7ff !important; border:2px dashed #00D4E8 !important; border-radius:10px; }
        .kb-drag-chosen { box-shadow:0 8px 24px rgba(0,212,232,.35) !important; transform:rotate(1.5deg) scale(1.02) !important; }
        .kb-drag-active { opacity:.85; cursor:grabbing !important; }
        [data-task-id] { cursor:grab; }
        [data-task-id]:active { cursor:grabbing; }
        </style>
        @foreach($colConfig as $key => $col)
        @php $tasks = $columns[$key] ?? collect(); @endphp
        <div style="flex-shrink:0;width:260px;display:flex;flex-direction:column;">

            {{-- Column header --}}
            <div style="background:{{ $col['bg'] }};border-radius:10px 10px 0 0;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:13px;font-weight:700;color:#000;">{{ $col['label'] }}</span>
                @if($key === 'completed')
                <span id="kb-count-{{ $key }}" style="font-size:12px;font-weight:700;color:#000;background:rgba(0,0,0,.15);padding:2px 9px;border-radius:20px;">{{ $completedTotal }}</span>
                @else
                <span id="kb-count-{{ $key }}" style="font-size:12px;font-weight:700;color:#000;background:rgba(0,0,0,.15);padding:2px 9px;border-radius:20px;">{{ $tasks->count() }}</span>
                @endif
            </div>

            {{-- Column body --}}
            <div id="kb-col-{{ $key }}" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-top:none;border-radius:0 0 10px 10px;padding:8px;display:flex;flex-direction:column;gap:7px;height:calc(100vh - 210px);overflow-y:auto;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.15) transparent;">

                @include('tasks._kanban_col_body', ['tasks' => $tasks, 'colKey' => $key, 'completedTotal' => $completedTotal])

            </div>
        </div>
        @endforeach
    </div>

    </div>{{-- /kb-scroll --}}

    {{-- Custom scrollbar — fixed at bottom --}}
    <div id="kb-track"
         style="position:fixed;bottom:16px;left:230px;right:62px;height:6px;background:rgba(255,255,255,.1);border-radius:99px;cursor:pointer;z-index:90;"
         onclick="kbTrackClick(event)">
        <div id="kb-thumb"
             style="height:100%;background:rgba(255,255,255,.4);border-radius:99px;position:absolute;top:0;left:0;transition:background .15s;cursor:grab;min-width:40px;"
             onmouseover="this.style.background='rgba(255,255,255,.7)'"
             onmouseout="this.style.background='rgba(255,255,255,.4)'">
        </div>
    </div>

    </div>{{-- /relative wrapper --}}

    @include('tasks._task_panel')

    <script>
    /* Legacy b24RenderPanel — kept for potential direct calls */
    function b24RenderPanel(data) {
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

        const sLbl = B24K_STATUS_LABEL[t.status] || '—';
        const pLbl = B24K_PRIO_LABEL[t.priority] || 'LOW';
        const sc = {'1':['#f1f5f9','#334155'],'2':['#fef3c7','#92400e'],'3':['#dbeafe','#1e40af'],'4':['#ede9fe','#5b21b6'],'5':['#dcfce7','#166534'],'6':['#f1f5f9','#475569']}[t.status]||['#f1f5f9','#334155'];
        const pc = {'0':['#e2e8f0','#475569'],'1':['#ffedd5','#c2410c'],'2':['#fee2e2','#b91c1c']}[t.priority]||['#e2e8f0','#475569'];

        const userAvatar = (u, size=36) => u
            ? (u.icon
                ? `<img src="${u.icon}" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;border:2px solid rgba(0,212,232,.3);" title="${u.name}" alt="">`
                : `<div style="width:${size}px;height:${size}px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:${Math.round(size*0.38)}px;font-weight:700;color:#fff;border:2px solid rgba(0,212,232,.3);" title="${u.name}">${u.name.charAt(0)}</div>`)
            : '';

        // BBCode parser for chat messages
        const parseMsg = txt => (txt||'')
            .replace(/\[USER=\d+\]([^\[]*)\[\/USER\]/g, '<span style="color:#00D4E8;font-weight:600;">$1</span>')
            .replace(/\[TIMESTAMP=(\d+)\s+FORMAT=[^\]]*\]/g, (_, ts) => {
                const d = new Date(parseInt(ts)*1000);
                return '<span style="color:#fbbf24;">' + d.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) + '</span>';
            })
            .replace(/\[\/?\w[^\]]*\]/g, '')
            .replace(/\n/g,'<br>');

        // Description: strip BBCode tags for display
        const desc = (t.description || '').replace(/\[url\](.*?)\[\/url\]/gi, '<a href="$1" target="_blank" style="color:#00D4E8;word-break:break-all;">$1</a>').replace(/\[.*?\]/g,'');

        // Files HTML
        const fileIcons = {'pdf':'fa-file-pdf','jpg':'fa-file-image','jpeg':'fa-file-image','png':'fa-file-image','doc':'fa-file-word','docx':'fa-file-word','xls':'fa-file-excel','xlsx':'fa-file-excel','zip':'fa-file-archive'};
        const filesHtml = files.length ? `
            <div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 12px;">
                    <i class="fas fa-paperclip" style="margin-right:6px;color:#00D4E8;"></i>Files (${files.length})
                </p>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    ${files.map(f => {
                        const ext = (f.name.split('.').pop()||'').toLowerCase();
                        const icon = fileIcons[ext] || 'fa-file';
                        const sizeKb = f.size ? Math.round(f.size/1024) + ' KB' : '';
                        return `<a href="${f.downloadUrl||'#'}" target="_blank" style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:9px;text-decoration:none;transition:background .15s;" onmouseover="this.style.background='rgba(0,212,232,.08)'" onmouseout="this.style.background='rgba(255,255,255,.05)'">
                            <i class="fas ${icon}" style="font-size:18px;color:#00D4E8;width:22px;flex-shrink:0;"></i>
                            <div style="flex:1;min-width:0;">
                                <p style="color:rgba(255,255,255,.85);font-size:12.5px;font-weight:500;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${f.name}</p>
                                ${sizeKb ? `<p style="color:rgba(255,255,255,.35);font-size:11px;margin:2px 0 0;">${sizeKb}</p>` : ''}
                            </div>
                            <i class="fas fa-download" style="font-size:12px;color:rgba(255,255,255,.3);flex-shrink:0;"></i>
                        </a>`;
                    }).join('')}
                </div>
            </div>` : '';

        // Participants + Observers
        const peopleRow = (label, list) => !list.length ? '' : `
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="color:rgba(255,255,255,.3);font-size:11px;min-width:80px;">${label}</span>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    ${list.map(u => `<div style="display:flex;align-items:center;gap:6px;background:rgba(255,255,255,.06);border-radius:20px;padding:4px 10px 4px 4px;">${userAvatar(u,26)}<span style="color:rgba(255,255,255,.8);font-size:12px;">${u.name}</span></div>`).join('')}
                </div>
            </div>`;

        // Task Chat (from Bitrix24 IM)
        const userMsgs = chat.filter(m => !m.isSystem);
        const chatHtml = `
            <div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 12px;">
                    <i class="fas fa-comment-dots" style="margin-right:6px;color:#00D4E8;"></i>Task Chat${userMsgs.length ? ' ('+userMsgs.length+')' : ''}
                </p>
                ${chat.length ? `
                <div style="display:flex;flex-direction:column;gap:7px;max-height:340px;overflow-y:auto;padding-right:3px;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.1) transparent;">
                    ${chat.map(m => {
                        const mDate = new Date(m.date).toLocaleString('en-GB',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
                        const mText = parseMsg(m.text);
                        if (m.isSystem) {
                            return `<div style="display:flex;align-items:flex-start;gap:7px;padding:5px 9px;background:rgba(255,255,255,.025);border-radius:6px;border-left:2px solid rgba(255,255,255,.08);">
                                <i class="fas fa-info-circle" style="font-size:9px;color:rgba(255,255,255,.2);margin-top:3px;flex-shrink:0;"></i>
                                <p style="color:rgba(255,255,255,.3);font-size:11px;margin:0;font-style:italic;flex:1;line-height:1.45;">${mText}</p>
                                <span style="color:rgba(255,255,255,.15);font-size:9.5px;flex-shrink:0;white-space:nowrap;margin-left:6px;">${mDate}</span>
                            </div>`;
                        }
                        const a = m.author;
                        return `<div style="display:flex;gap:8px;align-items:flex-start;">
                            <div style="flex-shrink:0;">${userAvatar(a, 28)}</div>
                            <div style="flex:1;background:rgba(255,255,255,.05);border-radius:0 9px 9px 9px;padding:8px 11px;">
                                <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                                    <span style="color:#00D4E8;font-size:12px;font-weight:600;">${a ? a.name : '—'}</span>
                                    <span style="color:rgba(255,255,255,.25);font-size:10px;">${mDate}</span>
                                </div>
                                <p style="color:rgba(255,255,255,.82);font-size:12.5px;margin:0;line-height:1.5;">${mText}</p>
                            </div>
                        </div>`;
                    }).join('')}
                </div>` : `<p style="color:rgba(255,255,255,.2);font-size:12px;margin:0;text-align:center;padding:18px 0;"><i class="fas fa-comment-slash" style="margin-right:6px;"></i>No messages yet</p>`}
                <div style="margin-top:12px;display:flex;gap:8px;">
                    <div style="flex:1;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:9px;padding:10px 13px;color:rgba(255,255,255,.2);font-size:12px;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-comment" style="font-size:11px;color:rgba(255,255,255,.15);"></i>
                        <span>Reply in Bitrix24...</span>
                    </div>
                    <a href="https://thesoftcube.bitrix24.com/tasks/task/view/${t.id}/" target="_blank"
                       style="background:rgba(0,212,232,.12);border:1px solid rgba(0,212,232,.25);border-radius:9px;padding:10px 14px;color:#00D4E8;font-size:12px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:5px;white-space:nowrap;transition:background .15s;"
                       onmouseover="this.style.background='rgba(0,212,232,.22)'" onmouseout="this.style.background='rgba(0,212,232,.12)'">
                        <i class="fas fa-external-link-alt" style="font-size:10px;"></i> Open
                    </a>
                </div>
            </div>`;

        document.getElementById('b24-panel-body').innerHTML = `
            <div style="padding:22px 24px;background:linear-gradient(135deg,rgba(0,212,232,.08),rgba(99,102,241,.05));border-bottom:1px solid rgba(255,255,255,.07);">
                <h2 style="color:#fff;font-size:16px;font-weight:700;margin:0 0 12px;line-height:1.5;">${t.title}</h2>
                <div style="display:flex;gap:7px;flex-wrap:wrap;">
                    <span style="font-size:11.5px;font-weight:600;padding:4px 13px;border-radius:20px;background:${sc[0]};color:${sc[1]};"><i class="fas fa-circle" style="font-size:7px;margin-right:4px;"></i>${sLbl}</span>
                    <span style="font-size:11.5px;font-weight:600;padding:4px 13px;border-radius:20px;background:${pc[0]};color:${pc[1]};"><i class="fas fa-flag" style="font-size:9px;margin-right:4px;"></i>${pLbl}</span>
                    <span style="font-size:11.5px;font-weight:600;padding:4px 13px;border-radius:20px;background:rgba(0,212,232,.12);color:#00D4E8;">#${t.id}</span>
                </div>
            </div>

            <div style="padding:0 24px;">

                ${desc ? `<div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                    <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 8px;"><i class="fas fa-align-left" style="margin-right:6px;color:#00D4E8;"></i>Description</p>
                    <p style="color:rgba(255,255,255,.75);font-size:13px;line-height:1.6;margin:0;">${desc}</p>
                </div>` : ''}

                <div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);">
                    <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 10px;"><i class="fas fa-calendar-alt" style="margin-right:6px;color:#00D4E8;"></i>Deadline</p>
                    ${dl ? `<div style="display:flex;align-items:center;justify-content:space-between;">
                        <div>
                            <p style="color:${isOvr?'#f87171':'rgba(255,255,255,.9)'};font-size:14px;font-weight:600;margin:0;">${dlFull}</p>
                            <p style="color:rgba(255,255,255,.35);font-size:11.5px;margin:3px 0 0;"><i class="fas fa-clock" style="margin-right:4px;font-size:9px;"></i>${dlTime}</p>
                        </div>
                        ${isOvr
                            ? `<span style="background:rgba(239,68,68,.15);color:#f87171;font-size:11px;font-weight:700;padding:5px 11px;border-radius:20px;border:1px solid rgba(239,68,68,.3);"><i class="fas fa-exclamation-triangle" style="margin-right:3px;"></i>OVERDUE</span>`
                            : daysLeft === 0
                                ? `<span style="background:rgba(245,158,11,.15);color:#fbbf24;font-size:11px;font-weight:700;padding:5px 11px;border-radius:20px;border:1px solid rgba(245,158,11,.3);">Due Today</span>`
                                : `<span style="background:rgba(34,197,94,.1);color:#4ade80;font-size:11px;font-weight:600;padding:5px 11px;border-radius:20px;">${daysLeft}d left</span>`}
                    </div>` : `<p style="color:rgba(255,255,255,.35);font-size:13px;margin:0;">No deadline set</p>`}
                </div>

                <div style="padding:16px 0;border-bottom:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;gap:12px;">
                    <p style="color:rgba(255,255,255,.3);font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0;"><i class="fas fa-users" style="margin-right:6px;color:#00D4E8;"></i>People</p>
                    ${responsible ? `<div style="display:flex;align-items:center;gap:12px;">${userAvatar(responsible,42)}<div><p style="color:rgba(255,255,255,.35);font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 2px;">Assignee</p><p style="color:#fff;font-size:13.5px;font-weight:600;margin:0;">${responsible.name}</p>${responsible.workPosition?`<p style="color:#00D4E8;font-size:11px;margin:2px 0 0;">${responsible.workPosition}</p>`:''}</div></div>` : ''}
                    ${creator ? `<div style="display:flex;align-items:center;gap:12px;">${userAvatar(creator,42)}<div><p style="color:rgba(255,255,255,.35);font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 2px;">Task Owner</p><p style="color:#fff;font-size:13.5px;font-weight:600;margin:0;">${creator.name}</p>${creator.workPosition?`<p style="color:#00D4E8;font-size:11px;margin:2px 0 0;">${creator.workPosition}</p>`:''}</div></div>` : ''}
                    ${peopleRow('Participants', participants)}
                    ${peopleRow('Observers', observers)}
                </div>

                ${filesHtml}
                ${chatHtml}

                <div style="padding:16px 0;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:9px;padding:11px;">
                            <p style="color:rgba(255,255,255,.3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 4px;">Created</p>
                            <p style="color:rgba(255,255,255,.8);font-size:12px;font-weight:500;margin:0;">${t.createdDate ? new Date(t.createdDate).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) : '—'}</p>
                        </div>
                        <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);border-radius:9px;padding:11px;">
                            <p style="color:rgba(255,255,255,.3);font-size:10px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 4px;">Task ID</p>
                            <p style="color:#00D4E8;font-size:12px;font-weight:600;margin:0;">#${t.id}</p>
                        </div>
                    </div>
                </div>

            </div>`;
    }

    </script>

    <script>
    // Bitrix24 status: 1=New,2=Pending,3=In Progress,4=Reviewing,5=Completed,6=Deferred
    const B24K_STATUS_LABEL = {'1':'Pending','2':'Pending','3':'In Progress','4':'Reviewing','5':'Completed','6':'Deferred'};
    const B24K_STATUS_BG    = {'1':'#f1f5f9','2':'#fef3c7','3':'#dbeafe','4':'#ede9fe','5':'#dcfce7','6':'#f1f5f9'};
    const B24K_STATUS_COL   = {'1':'#475569','2':'#92400e','3':'#1d4ed8','4':'#5b21b6','5':'#166534','6':'#475569'};
    const B24K_PRIO_BG      = {'0':'#e2e8f0','1':'#ffedd5','2':'#fee2e2'};
    const B24K_PRIO_COL     = {'0':'#475569','1':'#c2410c','2':'#b91c1c'};
    const B24K_PRIO_LABEL   = {'0':'LOW','1':'HIGH','2':'URGENT'};

    // Map Bitrix24 task to kanban column key
    function b24ColKey(t) {
        if (t.status === '5') return 'completed';
        const now = new Date();
        const today     = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const todayEnd  = new Date(today.getTime() + 86399999);
        const weekEnd   = new Date(today); weekEnd.setDate(today.getDate() + (7 - today.getDay()));
        const nextWeekEnd = new Date(weekEnd); nextWeekEnd.setDate(weekEnd.getDate() + 7);
        if (!t.deadline) return 'no_deadline';
        const dl = new Date(t.deadline);
        if (dl < today)      return 'overdue';
        if (dl <= todayEnd)  return 'due_today';
        if (dl <= weekEnd)   return 'due_this_week';
        if (dl <= nextWeekEnd) return 'due_next_week';
        return 'due_over_two_weeks';
    }

    function b24BuildCard(t, idx) {
        const dl = t.deadline ? new Date(t.deadline) : null;
        const dlStr = dl ? dl.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) : 'No deadline';
        const isOvr = dl && dl < new Date() && t.status !== '5';
        const pBg  = B24K_PRIO_BG[t.priority]  || '#e2e8f0';
        const pCol = B24K_PRIO_COL[t.priority] || '#475569';
        const pLbl = B24K_PRIO_LABEL[t.priority] || 'LOW';
        const sBg  = B24K_STATUS_BG[t.status]   || '#f1f5f9';
        const sCol = B24K_STATUS_COL[t.status]  || '#475569';
        const sLbl = B24K_STATUS_LABEL[t.status] || '—';
        const resp = t.responsible ? t.responsible.name : '—';
        const avatar = t.responsible && t.responsible.icon
            ? `<img src="${t.responsible.icon}" style="width:24px;height:24px;border-radius:50%;border:2px solid #e2e8f0;object-fit:cover;" title="${resp}" alt="">`
            : `<div style="width:24px;height:24px;border-radius:50%;background:#6366f1;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;">${resp.charAt(0)}</div>`;
        return `<div style="display:block;background:#fff;border-radius:10px;padding:12px;box-shadow:0 1px 4px rgba(0,0,0,.12);transition:box-shadow .15s,transform .15s;animation:b24fadeIn .25s ease ${idx*0.07}s both;border-left:3px solid #00D4E8;cursor:pointer;"
            onclick="tpOpen('b24', ${t.id})"
            onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.18)';this.style.transform='translateY(-1px)'"
            onmouseout="this.style.boxShadow='0 1px 4px rgba(0,0,0,.12)';this.style.transform=''">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;margin-bottom:6px;">
                <p style="font-size:12.5px;font-weight:600;color:#1a1a2e;margin:0;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${t.title}</p>
                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;flex-shrink:0;text-transform:uppercase;background:${pBg};color:${pCol};">${pLbl}</span>
            </div>
            <p style="font-size:10px;color:#00b8cc;margin:0 0 6px;font-weight:600;"><i class="fas fa-bolt" style="font-size:9px;margin-right:3px;"></i>Bitrix24</p>
            <div style="margin-bottom:8px;">
                <span style="font-size:10.5px;font-weight:500;padding:2px 9px;border-radius:6px;background:${sBg};color:${sCol};">${sLbl}</span>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:11px;color:${isOvr?'#ef4444':'#94a3b8'};">${dlStr}</span>
                ${avatar}
            </div>
        </div>`;
    }

    </script>

    @if(auth()->user()->isAdmin() || !empty(auth()->user()->permissions['create_tasks']))
    @include('tasks._form_modal', ['showVar' => 'showModal'])
    @endif
</div>

<script>
function kbUpdateArrows() {
    const el    = document.getElementById('kb-scroll');
    const left  = document.getElementById('kb-left');
    const right = document.getElementById('kb-right');
    const thumb = document.getElementById('kb-thumb');
    const track = document.getElementById('kb-track');
    if (!el) return;

    const atStart = el.scrollLeft <= 2;
    const atEnd   = el.scrollLeft + el.clientWidth >= el.scrollWidth - 2;

    left.style.opacity       = atStart ? '0' : '1';
    left.style.pointerEvents = atStart ? 'none' : 'auto';
    right.style.opacity      = atEnd   ? '0' : '1';
    right.style.pointerEvents = atEnd  ? 'none' : 'auto';

    // Sidebar-aware positions
    const sidebar = document.getElementById('sidebar');
    const sbWidth = (sidebar && !sidebar.classList.contains('hidden-sidebar')) ? 220 : 0;
    left.style.left        = (sbWidth + 10) + 'px';
    if (track) track.style.left = (sbWidth + 10) + 'px';

    // Thumb width & position
    if (thumb && track) {
        const trackW     = track.getBoundingClientRect().width;
        const ratio      = el.clientWidth / el.scrollWidth;
        const thumbW     = Math.max(40, trackW * ratio);
        const maxScroll  = el.scrollWidth - el.clientWidth;
        const thumbLeft  = maxScroll > 0 ? (el.scrollLeft / maxScroll) * (trackW - thumbW) : 0;
        thumb.style.width = thumbW + 'px';
        thumb.style.left  = thumbLeft + 'px';
    }
}

function kbTrackClick(e) {
    const track = document.getElementById('kb-track');
    const el    = document.getElementById('kb-scroll');
    const rect  = track.getBoundingClientRect();
    const ratio = (e.clientX - rect.left) / rect.width;
    el.scrollLeft = ratio * (el.scrollWidth - el.clientWidth);
}

document.addEventListener('DOMContentLoaded', () => {
    kbUpdateArrows();

    // Drag thumb
    const thumb = document.getElementById('kb-thumb');
    const el    = document.getElementById('kb-scroll');
    let dragging = false, startX = 0, startScroll = 0;
    thumb.addEventListener('mousedown', e => {
        dragging = true; startX = e.clientX; startScroll = el.scrollLeft;
        thumb.style.cursor = 'grabbing';
        e.preventDefault();
    });
    document.addEventListener('mousemove', e => {
        if (!dragging) return;
        const track   = document.getElementById('kb-track');
        const thumb   = document.getElementById('kb-thumb');
        const dx      = e.clientX - startX;
        const ratio   = dx / (track.clientWidth - thumb.offsetWidth);
        el.scrollLeft = startScroll + ratio * (el.scrollWidth - el.clientWidth);
    });
    document.addEventListener('mouseup', () => {
        dragging = false;
        thumb.style.cursor = 'grab';
    });
});

// Hover-hold arrows
['kb-left','kb-right'].forEach(id => {
    const btn = document.getElementById(id);
    let iv;
    const dir = id === 'kb-left' ? -1 : 1;
    btn.addEventListener('mouseenter', () => {
        iv = setInterval(() => {
            document.getElementById('kb-scroll').scrollBy({left: dir * 7});
            kbUpdateArrows();
        }, 16);
    });
    btn.addEventListener('mouseleave', () => clearInterval(iv));
});
</script>

<script>
// ── Kanban AJAX filter (client-side rendering) ───────────────────────────────
(function () {
    let _kbInitializing = false;

    const _origToggle = window.tsfToggleFilter;
    window.tsfToggleFilter = function (field, value, label) {
        _origToggle(field, value, label);
        if (!_kbInitializing) kbAjaxFilter();
    };

    window.tsfRemoveChip = function (el, field) {
        el.closest('.tsf-chip').remove();
        tsfClearField(field);
        kbAjaxFilter();
    };

    window.tsfClearAll = function () {
        ['status', 'priority', 'project_id', 'assignee_id'].forEach(f => tsfClearField(f));
        document.querySelectorAll('.tsf-chip').forEach(c => c.remove());
        const inp = document.getElementById('tsf-input');
        if (inp) inp.value = '';
        typeof tsfClose === 'function' && tsfClose();
        kbAjaxFilter();
    };

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('task-search-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                typeof tsfClose === 'function' && tsfClose();
                kbAjaxFilter();
            });
        }

        const panel = document.getElementById('tsf-panel');
        if (panel) {
            panel.querySelectorAll('button').forEach(btn => {
                if (btn.textContent.trim().includes('Search')) {
                    btn.onclick = function () {
                        typeof tsfClose === 'function' && tsfClose();
                        kbAjaxFilter();
                    };
                }
            });
        }

        @if(!request()->hasAny(['status', 'priority', 'project_id', 'assignee_id', 'search']))
        _kbInitializing = true;
        tsfToggleFilter('status', 'in_progress', 'In Progress');
        _kbInitializing = false;
        kbAjaxFilter();
        @endif
    });
})();

// ── Client-side card renderer ─────────────────────────────────────────────────
function kbH(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function kbRenderCard(t) {
    const pc = { low:['#e2e8f0','#475569'], medium:['#dbeafe','#1d4ed8'], high:['#ffedd5','#c2410c'], urgent:['#fee2e2','#b91c1c'] }[t.priority] || ['#e2e8f0','#475569'];
    const sc = { new:['#f1f5f9','#334155','#cbd5e1'], in_progress:['#dbeafe','#1d4ed8','#bfdbfe'], paused:['#fef3c7','#b45309','#fde68a'], completed:['#dcfce7','#166534','#bbf7d0'] }[t.status] || ['#f1f5f9','#334155','#cbd5e1'];
    const statusLabel = (t.status||'').replace('_',' ').replace(/\b\w/g, c => c.toUpperCase());

    let thumb = '';
    if (t.desc) { const m = t.desc.match(/\[img\]([^\[\]\s]+)\[\/img\]/i); if (m) thumb = `<div style="margin:-4px -12px 8px;overflow:hidden;"><img src="${kbH(m[1])}" loading="lazy" style="width:100%;height:200px;object-fit:contain;display:block;border-radius:6px 6px 0 0;"></div>`; }

    const proj  = t.project  ? `<p style="font-size:10.5px;color:#64748b;margin:0 0 7px;display:flex;align-items:center;gap:4px;"><i class="fas fa-folder" style="font-size:9px;color:#94a3b8;"></i>${kbH(t.project)}</p>` : '';
    const dl    = t.deadline ? `<span style="font-size:10.5px;color:${t.dl_past?'#ef4444':'#94a3b8'};display:flex;align-items:center;gap:3px;"><i class="fas fa-calendar-alt" style="font-size:9px;"></i>${kbH(t.deadline)}</span>` : `<span style="font-size:10.5px;color:#cbd5e1;">—</span>`;
    const asgn  = t.assignee ? `<div style="display:flex;align-items:center;gap:5px;"><span style="font-size:10px;color:#64748b;">${kbH(t.assignee.name.split(' ')[0])}</span><img src="${kbH(t.assignee.avatar)}" style="width:22px;height:22px;border-radius:50%;border:2px solid #e2e8f0;object-fit:cover;" title="${kbH(t.assignee.name)}" alt=""></div>` : '';

    return `<div id="kb-task-${t.id}" data-task-id="${t.id}" onclick="tpOpen('local',${t.id})"
        style="background:#fff;border-radius:10px;padding:12px;cursor:pointer;transition:box-shadow .15s,transform .15s;box-shadow:0 1px 4px rgba(0,0,0,.12);"
        onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.18)';this.style.transform='translateY(-1px)'"
        onmouseout="this.style.boxShadow='0 1px 4px rgba(0,0,0,.12)';this.style.transform=''">
        <p style="font-size:12.5px;font-weight:600;color:#1a1a2e;margin:0 0 7px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">${kbH(t.title)}</p>
        ${thumb}
        <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:8px;">
            <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;text-transform:uppercase;background:${pc[0]};color:${pc[1]};">${kbH((t.priority||'').toUpperCase())}</span>
            <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:5px;background:${sc[0]};color:${sc[1]};border:1px solid ${sc[2]};">${kbH(statusLabel)}</span>
        </div>
        ${proj}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">${dl}${asgn}</div>
    </div>`;
}

function kbRenderCol(tasks, key, completedTotal) {
    if (!tasks || tasks.length === 0)
        return '<div class="kb-empty-msg" style="padding:20px 0;text-align:center;"><span style="font-size:11.5px;color:rgba(255,255,255,.2);">No tasks</span></div>';
    let html = tasks.map(t => kbRenderCard(t)).join('');
    if (key === 'completed' && completedTotal > 50)
        html += `<div style="padding:10px 6px 4px;text-align:center;"><span style="font-size:10.5px;color:rgba(255,255,255,.3);">Showing latest 50 of ${Number(completedTotal).toLocaleString()}</span></div>`;
    return html;
}

// ── AJAX fetch ────────────────────────────────────────────────────────────────
function kbAjaxFilter() {
    const params = new URLSearchParams();
    document.querySelectorAll('#tsf-chips input[type="hidden"]').forEach(inp => { if (inp.value) params.set(inp.name, inp.value); });
    const sv = (document.getElementById('tsf-input') || {}).value;
    if (sv && sv.trim()) params.set('search', sv.trim());

    document.querySelectorAll('[id^="kb-col-"]').forEach(c => { c.style.opacity = '0.35'; c.style.transition = 'opacity 0.15s'; });

    fetch('{{ route("tasks.kanban") }}?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(data => {
        ['overdue','due_today','due_this_week','due_next_week','no_deadline','due_over_two_weeks','completed'].forEach(key => {
            const col     = document.getElementById('kb-col-' + key);
            const countEl = document.getElementById('kb-count-' + key);
            if (col) {
                col.innerHTML = kbRenderCol(data.columns[key], key, data.completedTotal);
                col.style.opacity = '1';
                col.style.transition = 'opacity 0.22s';
            }
            if (countEl) countEl.textContent = key === 'completed' ? (data.completedTotal ?? 0) : (data.columns[key]?.length ?? 0);
        });
        kbDragInit(); // re-init after column refresh
    })
    .catch(() => { document.querySelectorAll('[id^="kb-col-"]').forEach(c => { c.style.opacity = '1'; }); });
}

// ── Drag & Drop (SortableJS) ─────────────────────────────────────────────────
const _kbSortables = {};
const _kbCsrf = document.querySelector('meta[name="csrf-token"]')?.content;

function kbDragInit() {
    const colKeys = ['overdue','due_today','due_this_week','due_next_week','no_deadline','due_over_two_weeks','completed'];
    colKeys.forEach(key => {
        const el = document.getElementById('kb-col-' + key);
        if (!el) return;
        if (_kbSortables[key]) { _kbSortables[key].destroy(); }

        _kbSortables[key] = new Sortable(el, {
            group:        'kanban',
            animation:    180,
            ghostClass:   'kb-drag-ghost',
            chosenClass:  'kb-drag-chosen',
            dragClass:    'kb-drag-active',
            filter:       '.kb-empty-msg',
            forceFallback: false,

            onMove: function (evt) {
                // Prevent dropping INTO overdue
                const toKey = evt.to.id.replace('kb-col-', '');
                return toKey !== 'overdue';
            },

            onEnd: function (evt) {
                const taskId  = evt.item.dataset.taskId;
                const fromKey = evt.from.id.replace('kb-col-', '');
                const toKey   = evt.to.id.replace('kb-col-', '');
                if (fromKey === toKey) return;

                // Build payload
                const payload = {};
                const dl = kbColDeadline(toKey);
                if (toKey === 'completed') {
                    payload.status = 'completed';
                } else {
                    if (fromKey === 'completed') payload.status = 'in_progress';
                    if (dl !== undefined) payload.deadline = dl;
                }

                // Optimistic UI: handle empty-state placeholders
                evt.to.querySelectorAll('.kb-empty-msg').forEach(e => e.remove());
                const remaining = evt.from.querySelectorAll('[data-task-id]');
                if (remaining.length === 0) {
                    evt.from.innerHTML = '<div class="kb-empty-msg" style="padding:20px 0;text-align:center;"><span style="font-size:11.5px;color:rgba(255,255,255,.2);">No tasks</span></div>';
                }
                kbSyncColCounts();

                // Server update — then refresh columns via AJAX
                const moveUrl = '{{ url("/tasks") }}/' + taskId + '/move';
                fetch(moveUrl, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _kbCsrf, 'Accept': 'application/json' },
                    body:    JSON.stringify(payload),
                })
                .then(() => kbAjaxFilter())   // always refresh: correct deadline text + column placement
                .catch(() => kbAjaxFilter()); // on network error also refresh (reverts card)
            },
        });
    });
}

function kbColDeadline(col) {
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const fmt   = d => d.toISOString().split('T')[0];
    const add   = n => { const d = new Date(today); d.setDate(today.getDate() + n); return fmt(d); };
    const dow   = today.getDay(); // 0=Sun … 6=Sat
    const toFri = dow <= 5 ? (5 - dow) || 7 : 6; // days until this/next Friday
    return {
        due_today:          fmt(today),
        due_this_week:      add(toFri),
        due_next_week:      add(toFri + 7),
        no_deadline:        null,
        due_over_two_weeks: add(21),
        completed:          undefined, // don't touch deadline
    }[col];
}

function kbSyncColCounts() {
    ['overdue','due_today','due_this_week','due_next_week','no_deadline','due_over_two_weeks','completed'].forEach(key => {
        const col     = document.getElementById('kb-col-' + key);
        const countEl = document.getElementById('kb-count-' + key);
        if (col && countEl) countEl.textContent = col.querySelectorAll('[data-task-id]').length;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // Wait a tick so AJAX default filter doesn't race with init
    setTimeout(kbDragInit, 600);
});

</script>
@endsection
