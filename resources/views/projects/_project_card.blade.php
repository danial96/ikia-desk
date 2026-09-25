@php
    $total  = $project->tasks->count();
    $done   = $project->tasks->where('status', 'completed')->count();
    $pct    = $total > 0 ? round(($done / $total) * 100) : 0;
    $words  = preg_split('/\s+/u', trim($project->name)) ?: [];
    $ini    = mb_strtoupper(mb_substr($words[0] ?? '?', 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
    $created = $project->created_at?->copy()->setTimezone(config('app.timezone'));
    $stCol  = $project->status === 'active' ? ['#dcfce7', '#15803d'] : ($project->status === 'completed' ? ['#dbeafe', '#1d4ed8'] : ['#eef1f3', '#5b6670']);
@endphp
<tr class="bx-row" onclick="location.href='{{ route('projects.show', $project) }}'">
    <td class="c-id">{{ $project->id }}</td>
    <td class="c-name">
        <span style="display:inline-flex;align-items:center;gap:12px;">
            <span style="width:26px;height:26px;border-radius:50%;background:{{ $project->color }};color:#fff;font-size:10px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">{{ $ini }}</span>
            <a href="{{ route('projects.show', $project) }}" class="bx-title" style="text-decoration:none;" onclick="event.stopPropagation()">{{ $project->name }}</a>
        </span>
    </td>
    <td class="c-active">{{ $created ? strtolower($created->format('F j, Y, g:i a')) : '' }}</td>
    <td class="c-perf">
        <span style="display:inline-flex;align-items:center;gap:10px;min-width:150px;">
            <span style="flex:1;height:6px;border-radius:99px;background:#e6ebee;overflow:hidden;min-width:80px;"><span style="display:block;height:100%;width:{{ $pct }}%;background:#7ec01f;border-radius:99px;"></span></span>
            <span style="font-size:13px;color:#4a5560;">{{ $pct }}%</span>
        </span>
    </td>
    <td class="c-proj">{{ $done }} / {{ $total }}</td>
    <td>@if($project->creator)<span class="bx-user"><img src="{{ $project->creator->avatar_url }}" alt=""><span>{{ $project->creator->name }}</span></span>@endif</td>
    <td><span class="bx-status" style="background:{{ $stCol[0] }};color:{{ $stCol[1] }};">{{ ucfirst(str_replace('_', ' ', $project->status)) }}</span></td>
</tr>
