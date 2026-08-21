<div style="background:rgba(255,255,255,.12);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.16);border-radius:12px;padding:12px 18px;display:flex;align-items:center;gap:14px;transition:background .15s;"
     onmouseover="this.style.background='rgba(255,255,255,.18)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">

    <form action="{{ route('tasks.update', $task) }}" method="POST">
        @csrf @method('PUT')
        <input type="hidden" name="status" value="{{ $task->status === 'completed' ? 'new' : 'completed' }}">
        <button type="submit"
                style="width:20px;height:20px;border-radius:50%;border:2px solid {{ $task->status === 'completed' ? '#22c55e' : 'rgba(255,255,255,.35)' }};
                       background:{{ $task->status === 'completed' ? '#22c55e' : 'transparent' }};
                       display:flex;align-items:center;justify-content:center;flex-shrink:0;cursor:pointer;transition:border-color .2s;"
                onmouseover="this.style.borderColor='#00D4E8'" onmouseout="this.style.borderColor='{{ $task->status === 'completed' ? '#22c55e' : 'rgba(255,255,255,.35)' }}'">
            @if($task->status === 'completed')
            <svg style="width:11px;height:11px;" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            @endif
        </button>
    </form>

    <div onclick="tpOpen('local', {{ $task->id }})" style="flex:1;min-width:0;cursor:pointer;">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <p style="font-size:14px;font-weight:500;color:rgba(255,255,255,.9);margin:0;{{ $task->status === 'completed' ? 'text-decoration:line-through;opacity:.5;' : '' }}">{{ $task->title }}</p>
            <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:6px;
                 background:{{ ['low'=>'rgba(100,116,139,.3)','medium'=>'rgba(27,114,232,.3)','high'=>'rgba(249,115,22,.3)','urgent'=>'rgba(239,68,68,.3)'][$task->priority] ?? 'rgba(100,116,139,.3)' }};
                 color:{{ ['low'=>'#94a3b8','medium'=>'#60a5fa','high'=>'#fb923c','urgent'=>'#f87171'][$task->priority] ?? '#94a3b8' }};">
                {{ $task->priority }}
            </span>
            @if($task->project)
            <span style="font-size:12px;color:rgba(255,255,255,.35);">• {{ $task->project->name }}</span>
            @endif
        </div>
        @if($task->deadline)
        <p style="font-size:11.5px;margin:3px 0 0;color:{{ $task->deadline->copy()->setTimezone('Asia/Karachi')->toDateString() < now('Asia/Karachi')->toDateString() && $task->status !== 'completed' ? '#f87171' : 'rgba(255,255,255,.35)' }};">
            Due {{ $task->deadline->copy()->setTimezone('Asia/Karachi')->format('M d, Y') }}
        </p>
        @endif
    </div>

    <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
        <span style="font-size:11.5px;font-weight:500;padding:4px 10px;border-radius:7px;
             background:{{ ['new'=>'rgba(148,163,184,.2)','in_progress'=>'rgba(27,114,232,.25)','paused'=>'rgba(251,191,36,.2)','completed'=>'rgba(34,197,94,.2)'][$task->status] ?? 'rgba(148,163,184,.2)' }};
             color:{{ ['new'=>'#cbd5e1','in_progress'=>'#93c5fd','paused'=>'#fde68a','completed'=>'#86efac'][$task->status] ?? '#cbd5e1' }};">
            {{ str_replace('_',' ',ucfirst($task->status)) }}
        </span>
        @if($task->assignee)
        <img src="{{ $task->assignee->avatar_url }}" style="width:28px;height:28px;border-radius:50%;border:2px solid rgba(255,255,255,.2);" title="{{ $task->assignee->name }}" alt="">
        @endif
    </div>
</div>
