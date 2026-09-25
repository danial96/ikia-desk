@php
    $dl     = $task->kanbanDeadline();
    $pill   = ['overdue' => ['#e0413a', '#e0413a', '#fdecea'], 'today' => ['#e08a00', '#fde8c4', '#fde8c4'], 'normal' => ['#1a6fd4', '#1a6fd4', '#fff'], 'done' => ['#7d858c', '#d5d9dd', '#fff']];
    $active = $task->updated_at?->copy()->setTimezone(config('app.timezone'));
    $stCol  = ['new' => ['#eef1f3', '#5b6670'], 'pending' => ['#e0f2fe', '#0369a1'], 'in_progress' => ['#dbeafe', '#1d4ed8'], 'reviewing' => ['#ede9fe', '#6d28d9'], 'paused' => ['#fef3c7', '#b45309'], 'completed' => ['#dcfce7', '#15803d']][$task->status] ?? ['#eef1f3', '#5b6670'];
    $person = fn($u) => $u ? '<span class="bx-user"><img src="' . e($u->avatar_url) . '" alt=""><span>' . e($u->name) . '</span></span>' : '';
@endphp
<tr class="bx-row" onclick="tpOpen('local', {{ $task->id }})">
    <td class="c-chk" onclick="event.stopPropagation()"><input type="checkbox" class="bx-chk" onchange="bxCount()"></td>
    <td class="c-name">
        <span class="bx-title" style="{{ $task->status === 'completed' ? 'text-decoration:line-through;opacity:.55;' : '' }}">{{ $task->title }}</span>
        @if(in_array($task->priority, ['high', 'urgent'], true))<i class="fas fa-fire" style="color:#f5a623;font-size:11px;margin-left:5px;" title="High priority"></i>@endif
        @if(($task->task_files_count ?? 0) > 0)<span class="bx-chip"><i class="fas fa-paperclip"></i>{{ $task->task_files_count }}</span>@endif
    </td>
    <td class="c-active">{{ $active ? strtolower($active->format('F j, g:i a')) : '' }}</td>
    <td class="c-dl">
        @if($dl)
        @php [$pc, $pb, $pg] = $pill[$dl['kind']]; @endphp
        <span class="bx-pill" style="color:{{ $pc }};border-color:{{ $pb }};background:{{ $pg }};">{{ $dl['label'] }}</span>
        @endif
    </td>
    <td>{!! $person($task->creator) !!}</td>
    <td>{!! $person($task->assignee) !!}</td>
    <td class="c-proj">{{ $task->project?->name }}</td>
    <td><span class="bx-status" style="background:{{ $stCol[0] }};color:{{ $stCol[1] }};">{{ str_replace('_', ' ', ucfirst($task->status)) }}</span></td>
</tr>
