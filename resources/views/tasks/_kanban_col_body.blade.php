@forelse($tasks as $task)
@php
    $dotColor = ['low'=>'#cbd5e1','medium'=>'#3b82f6','high'=>'#f59e0b','urgent'=>'#ef4444'][$task->priority] ?? '#cbd5e1';
    $coverFile = $task->coverFile->first();
    $thumbUrl  = $coverFile ? asset($coverFile->disk_path) : null;
    $dlPast = $task->deadline
        && $task->deadline->copy()->setTimezone('Asia/Karachi')->toDateString() < now('Asia/Karachi')->toDateString()
        && $task->status !== 'completed';
    // Only Super Admin / the creator / the assignee may drag this card (matches TaskController::move).
    // Plain participants/members can view and open it, just not drag it to change status/deadline.
    $canMove = auth()->user()->isSuperAdmin() || $task->created_by === auth()->id() || $task->assigned_to === auth()->id();
@endphp
<div id="kb-task-{{ $task->id }}" data-task-id="{{ $task->id }}" data-can-move="{{ $canMove ? '1' : '0' }}" onclick="tpOpen('local', {{ $task->id }})"
   style="position:relative;background:#fff;border:1px solid #eef0f2;border-radius:10px;padding:12px;cursor:pointer;transition:box-shadow .15s,transform .15s;box-shadow:0 1px 3px rgba(0,0,0,.07);"
   onmouseover="this.style.boxShadow='0 4px 14px rgba(0,0,0,.13)';this.style.transform='translateY(-1px)'"
   onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,.07)';this.style.transform=''">

    @if($thumbUrl)
    <div style="margin:-12px -12px 10px;overflow:hidden;border-radius:10px 10px 0 0;">
        <img src="{{ $thumbUrl }}" alt="" loading="lazy"
             style="width:100%;max-height:170px;object-fit:cover;display:block;"
             onerror="this.parentElement.style.display='none'">
    </div>
    @endif

    <p style="font-size:14px;font-weight:600;color:#333;margin:0 0 8px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $dotColor }};margin-right:7px;vertical-align:middle;" title="{{ $task->priority }}"></span>{{ $task->title }}
    </p>

    @if($task->project)
    <p style="font-size:11px;color:#8a94a6;margin:0 0 8px;display:flex;align-items:center;gap:5px;">
        <i class="fas fa-folder" style="font-size:9px;color:#b4bcc8;"></i>{{ $task->project->name }}
    </p>
    @endif

    @if($task->assignee)
    <div style="display:flex;align-items:center;gap:7px;margin-bottom:9px;">
        <img src="{{ $task->assignee->avatar_url }}" style="width:24px;height:24px;border-radius:50%;object-fit:cover;flex-shrink:0;" title="{{ $task->assignee->name }}" alt="">
        <span style="font-size:12px;color:#555e6d;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $task->assignee->name }}</span>
    </div>
    @endif

    <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid #f1f3f5;padding-top:8px;">
        @if($task->deadline)
        <span style="font-size:11.5px;color:{{ $dlPast ? '#ef4444' : '#8a94a6' }};display:flex;align-items:center;gap:4px;">
            <i class="far fa-clock" style="font-size:10px;"></i>{{ $task->deadline->copy()->setTimezone('Asia/Karachi')->format('M d, Y') }}
        </span>
        @else
        <span style="font-size:11.5px;color:#c4ccd6;">No deadline</span>
        @endif
    </div>
</div>
@empty
<div class="kb-empty-msg" style="padding:20px 0;text-align:center;">
    <span style="font-size:11.5px;color:rgba(255,255,255,.2);">No tasks</span>
</div>
@endforelse

@if($colKey === 'completed' && $completedTotal > count($tasks) && count($tasks) > 0)
<button type="button" id="kb-load-more-completed"
        data-offset="{{ count($tasks) }}" data-total="{{ $completedTotal }}"
        onclick="kbLoadMoreCompleted(this)"
        style="margin:8px 4px 4px;padding:9px;border-radius:8px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.08);color:#fff;font-size:11.5px;font-weight:600;cursor:pointer;transition:background .15s;"
        onmouseover="this.style.background='rgba(255,255,255,.16)'"
        onmouseout="this.style.background='rgba(255,255,255,.08)'">
    Load more ({{ number_format($completedTotal - count($tasks)) }} remaining)
</button>
@endif
