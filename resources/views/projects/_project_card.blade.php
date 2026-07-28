@php
    $total = $project->tasks->count();
    $done  = $project->tasks->where('status','completed')->count();
    $pct   = $total > 0 ? round(($done/$total)*100) : 0;
@endphp
<div class="proj-card">
    <div style="height:3px;background:{{ $project->color }};"></div>
    <div style="padding:20px;">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:{{ $project->color }}25;">
                    <div style="width:13px;height:13px;border-radius:50%;background:{{ $project->color }};"></div>
                </div>
                <div>
                    <h3 class="txt-main" style="font-size:13.5px;font-weight:600;margin:0;">{{ $project->name }}</h3>
                    <p class="txt-sub" style="font-size:11.5px;margin:2px 0 0;">{{ $project->creator->name }}</p>
                </div>
            </div>
            <span style="font-size:11px;padding:3px 9px;border-radius:6px;font-weight:500;flex-shrink:0;
                 background:{{ $project->status==='active'?'rgba(34,197,94,.15)':($project->status==='completed'?'rgba(27,114,232,.15)':'rgba(148,163,184,.1)') }};
                 color:{{ $project->status==='active'?'#4ade80':($project->status==='completed'?'#60a5fa':'#94a3b8') }};">
                {{ str_replace('_',' ',$project->status) }}
            </span>
        </div>

        @if($project->description)
        <p class="txt-sub" style="font-size:12px;line-height:1.6;margin:0 0 14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $project->description }}</p>
        @else
        <div style="margin-bottom:14px;"></div>
        @endif

        <div style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;font-size:11px;color:rgba(255,255,255,.35);margin-bottom:6px;">
                <span>{{ $done }}/{{ $total }} tasks</span>
                <span>{{ $pct }}%</span>
            </div>
            <div style="width:100%;background:rgba(255,255,255,.1);border-radius:99px;height:4px;overflow:hidden;">
                <div style="height:100%;border-radius:99px;transition:width .5s;width:{{ $pct }}%;background:linear-gradient(90deg,#00D4E8,{{ $project->color }});"></div>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;">
            <a href="{{ route('projects.show', $project) }}"
               style="font-size:12.5px;font-weight:600;color:#00D4E8;text-decoration:none;">
                Open Project →
            </a>
            <span style="font-size:11px;color:rgba(255,255,255,.25);">{{ $project->created_at->diffForHumans() }}</span>
        </div>
    </div>
</div>
