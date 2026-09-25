@forelse($tasks as $task)
@php
    $coverFile = $task->coverFile->first();
    $thumbUrl  = $coverFile ? asset($coverFile->disk_path) : null;
    // Overdue as soon as the deadline time passes (matches the task panel / board columns).
    $dlPast = $task->deadline && $task->deadline->lt(now()) && $task->status !== 'completed';
    // Only Super Admin / the creator / the assignee may drag this card (matches TaskController::move).
    // Plain participants/members can view and open it, just not drag it to change status/deadline.
    $canMove = auth()->user()->isSuperAdmin() || $task->created_by === auth()->id() || $task->assigned_to === auth()->id();
@endphp
<div id="kb-task-{{ $task->id }}" data-task-id="{{ $task->id }}" data-can-move="{{ $canMove ? '1' : '0' }}" onclick="tpOpen('local', {{ $task->id }})"
   style="position:relative;background:#fff;border-radius:10px;padding:10px 12px 12px;cursor:pointer;transition:box-shadow .15s;box-shadow:0 1px 2px rgba(0,0,0,.12);"
   onmouseover="this.style.boxShadow='0 3px 10px rgba(0,0,0,.22)'"
   onmouseout="this.style.boxShadow='0 1px 2px rgba(0,0,0,.12)'">

    <p style="font-size:13px;font-weight:500;color:#333;margin:0 0 8px;line-height:1.35;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">{{ $task->title }}@if(in_array($task->priority, ['high','urgent'], true)) <i class="fas fa-fire" style="color:#f5a623;font-size:11px;margin-left:3px;" title="High priority"></i>@endif</p>

    @if($task->members->count())
    <div style="font-size:10.5px;color:#9aa0a6;line-height:1.3;margin-bottom:2px;">Participants</div>
    <div style="font-size:11.5px;font-weight:600;color:#2067b0;line-height:1.35;margin-bottom:8px;">{{ $task->members->pluck('name')->implode(', ') }}</div>
    @endif

    @if($thumbUrl)
    <div style="margin:2px 0 8px;overflow:hidden;border-radius:4px;background:#111;text-align:center;">
        <img src="{{ $thumbUrl }}" alt="" loading="lazy" style="max-width:100%;max-height:120px;object-fit:contain;display:inline-block;" onerror="this.parentElement.style.display='none'">
    </div>
    @endif

    @if($task->task_files_count)
    <div style="margin-bottom:8px;"><span style="display:inline-flex;align-items:center;gap:4px;font-size:10.5px;color:#9aa0a6;border:1px solid #e3e6e9;border-radius:9px;padding:0 6px;line-height:16px;"><i class="fas fa-paperclip" style="font-size:9px;"></i>{{ $task->task_files_count }}</span></div>
    @endif

    @php
        $dlInfo = $task->kanbanDeadline();
        $pill = ['overdue' => ['#e0413a', '#e0413a', '#fdecea'], 'today' => ['#e08a00', '#fde8c4', '#fde8c4'], 'normal' => ['#2067b0', '#2067b0', '#fff'], 'done' => ['#7d858c', '#d5d9dd', '#fff']];
    @endphp
    <div style="margin-bottom:8px;">
        @if($dlInfo)
        @php [$pc, $pb, $pg] = $pill[$dlInfo['kind']]; @endphp
        <span style="display:inline-block;font-size:11.5px;color:{{ $pc }};border:1px solid {{ $pb }};background:{{ $pg }};border-radius:12px;padding:0 10px;line-height:22px;">{{ $dlInfo['label'] }}</span>
        @else
        <span style="display:inline-block;font-size:11.5px;color:#7d858c;border:1px solid #d5d9dd;border-radius:12px;padding:0 10px;line-height:22px;">No deadline</span>
        @endif
    </div>

    <div style="display:flex;align-items:center;gap:4px;">
        @if($task->creator)<img src="{{ $task->creator->avatar_url }}" title="{{ $task->creator->name }}" alt="" style="width:22px;height:22px;border-radius:50%;object-fit:cover;">@endif
        <i class="fas fa-chevron-right" style="font-size:8px;color:#c0c6cc;"></i>
        @if($task->assignee)<img src="{{ $task->assignee->avatar_url }}" title="{{ $task->assignee->name }}" alt="" style="width:22px;height:22px;border-radius:50%;object-fit:cover;">@endif
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
