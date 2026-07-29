@forelse($tasks as $task)
<div id="kb-task-{{ $task->id }}" data-task-id="{{ $task->id }}" onclick="tpOpen('local', {{ $task->id }})"
   style="background:#fff;border-radius:10px;padding:12px;cursor:pointer;transition:box-shadow .15s,transform .15s;box-shadow:0 1px 4px rgba(0,0,0,.12);"
   onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.18)';this.style.transform='translateY(-1px)'"
   onmouseout="this.style.boxShadow='0 1px 4px rgba(0,0,0,.12)';this.style.transform=''">

    <p style="font-size:12.5px;font-weight:600;color:#1a1a2e;margin:0 0 7px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $task->title }}</p>

    @php
        $thumbUrl = null;
        if ($task->description && preg_match('/\[img\]([^\[\]\s]+)\[\/img\]/i', $task->description, $m)) {
            $thumbUrl = trim($m[1]);
        }
    @endphp
    @if($thumbUrl)
    <div style="margin:-4px -12px 8px;overflow:hidden;border-radius:0;">
        <img src="{{ $thumbUrl }}" alt="" loading="lazy"
             style="width:100%;height:200px;object-fit:contain;display:block;border-radius:6px 6px 0 0;">
    </div>
    @endif

    <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:8px;">
        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:5px;text-transform:uppercase;
             background:{{ ['low'=>'#e2e8f0','medium'=>'#dbeafe','high'=>'#ffedd5','urgent'=>'#fee2e2'][$task->priority] ?? '#e2e8f0' }};
             color:{{ ['low'=>'#475569','medium'=>'#1d4ed8','high'=>'#c2410c','urgent'=>'#b91c1c'][$task->priority] ?? '#475569' }};">
            {{ strtoupper($task->priority) }}
        </span>
        <span style="font-size:10px;font-weight:600;padding:2px 8px;border-radius:5px;
             background:{{ ['new'=>'#f1f5f9','in_progress'=>'#dbeafe','paused'=>'#fef3c7','completed'=>'#dcfce7'][$task->status] ?? '#f1f5f9' }};
             color:{{ ['new'=>'#334155','in_progress'=>'#1d4ed8','paused'=>'#b45309','completed'=>'#166534'][$task->status] ?? '#334155' }};
             border:1px solid {{ ['new'=>'#cbd5e1','in_progress'=>'#bfdbfe','paused'=>'#fde68a','completed'=>'#bbf7d0'][$task->status] ?? '#cbd5e1' }};">
            {{ str_replace('_',' ',ucfirst($task->status)) }}
        </span>
    </div>

    @if($task->project)
    <p style="font-size:10.5px;color:#64748b;margin:0 0 7px;display:flex;align-items:center;gap:4px;">
        <i class="fas fa-folder" style="font-size:9px;color:#94a3b8;"></i>{{ $task->project->name }}
    </p>
    @endif

    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:4px;">
        @if($task->deadline)
        <span style="font-size:10.5px;color:{{ $task->deadline->isPast() && $task->status !== 'completed' ? '#ef4444' : '#94a3b8' }};display:flex;align-items:center;gap:3px;">
            <i class="fas fa-calendar-alt" style="font-size:9px;"></i>
            {{ $task->deadline->format('M d, Y') }}
        </span>
        @else
        <span style="font-size:10.5px;color:#cbd5e1;">—</span>
        @endif
        @if($task->assignee)
        <div style="display:flex;align-items:center;gap:5px;">
            <span style="font-size:10px;color:#64748b;">{{ explode(' ',$task->assignee->name)[0] }}</span>
            <img src="{{ $task->assignee->avatar_url }}" style="width:22px;height:22px;border-radius:50%;border:2px solid #e2e8f0;object-fit:cover;" title="{{ $task->assignee->name }}" alt="">
        </div>
        @endif
    </div>
</div>
@empty
<div class="kb-empty-msg" style="padding:20px 0;text-align:center;">
    <span style="font-size:11.5px;color:rgba(255,255,255,.2);">No tasks</span>
</div>
@endforelse

@if($colKey === 'completed' && $completedTotal > 50)
<div style="padding:10px 6px 4px;text-align:center;">
    <span style="font-size:10.5px;color:rgba(255,255,255,.3);">Showing latest 50 of {{ number_format($completedTotal) }}</span>
</div>
@endif
