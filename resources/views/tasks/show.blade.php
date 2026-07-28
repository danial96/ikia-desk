@extends('layouts.app')
@section('title', $task->title)
@section('page-title', '')

@section('content')
@php
$palette = ['#E8703A','#0d9488','#8B5CF6','#EC4899','#059669','#F59E0B','#3B82F6'];
$feed    = collect();
foreach ($task->comments   as $c) { $feed->push(['type'=>'comment',  'at'=>$c->created_at,'obj'=>$c]); }
foreach ($task->activities as $a) { if($a->action!=='commented') $feed->push(['type'=>'activity','at'=>$a->created_at,'obj'=>$a]); }
$feed    = $feed->sortBy('at')->values();
$canEdit = auth()->user()->isSuperAdmin() || $task->created_by === auth()->id();
$csrf    = csrf_token();
@endphp

<style>

.tp-lscroll::-webkit-scrollbar { width:4px; }
.tp-lscroll::-webkit-scrollbar-thumb { background:#cbd5e1;border-radius:4px; }
.tp-lscroll::-webkit-scrollbar-track { background:#f8fafc; }
.tp-rscroll::-webkit-scrollbar { width:4px; }
.tp-rscroll::-webkit-scrollbar-thumb { background:rgba(255,255,255,.2);border-radius:4px; }
.tp-rscroll::-webkit-scrollbar-track { background:transparent; }

/* left rows */
.tp-prop       { display:flex;align-items:center;padding:6px 0; }
.tp-prop-label { font-size:11.5px;color:#94a3b8;width:110px;flex-shrink:0; }
.tp-prop-val   { flex:1;display:flex;align-items:center;gap:7px;font-size:13px;color:#1e293b; }
.tp-sec        { font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;padding:10px 0 3px; }

/* inline editor */
.ie-wrap    { position:relative;flex:1; }
.ie-display { display:flex;align-items:center;gap:7px;padding:3px 7px;border-radius:7px;cursor:pointer;border:1.5px solid transparent;transition:all .15s;min-height:28px; }
.ie-display:hover { background:#f0f9ff;border-color:#bae6fd; }
.ie-display:hover .ie-pen { opacity:1; }
.ie-pen     { opacity:0;font-size:9px;color:#94a3b8;margin-left:auto;transition:opacity .15s; }
@keyframes iePop     { from{opacity:0;transform:scale(.96) translateY(-4px)} to{opacity:1;transform:scale(1) translateY(0)} }
@keyframes editSlide { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
.task-panel { animation: editSlide .22s ease both; }
.ie-picker  { position:absolute;top:calc(100% + 3px);left:-7px;z-index:300;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.14);min-width:210px;max-width:270px;overflow:hidden;animation:iePop .16s ease both; }
.ie-search  { width:100%;padding:8px 12px;border:none;border-bottom:1px solid #f1f5f9;font-size:12.5px;outline:none;background:#f8fafc;box-sizing:border-box; }
.ie-opts    { max-height:190px;overflow-y:auto; }
.ie-opt     { display:flex;align-items:center;gap:8px;padding:7px 12px;cursor:pointer;font-size:12.5px;color:#374151;transition:background .1s; }
.ie-opt:hover { background:#f0f9ff; }
.ie-opt.active { background:#e0f7fa;color:#0891b2; }
.ie-pill-row { display:flex;flex-wrap:wrap;gap:6px;padding:10px 12px; }
.ie-pill     { padding:4px 12px;border-radius:6px;font-size:11.5px;font-weight:600;cursor:pointer;border:1.5px solid transparent;transition:all .15s; }
.ie-pill:hover { filter:brightness(.93); }
.ie-pill.active { box-shadow:0 0 0 2px currentColor; }
.pill-new{background:#f1f5f9;color:#475569}.pill-in_progress{background:#dbeafe;color:#1d4ed8}.pill-paused{background:#fef3c7;color:#b45309}.pill-completed{background:#dcfce7;color:#166534}
.pill-low{background:#f1f5f9;color:#64748b}.pill-medium{background:#dbeafe;color:#1d4ed8}
.pill-high{background:#fff7ed;color:#c2410c}.pill-urgent{background:#fee2e2;color:#b91c1c}
.sb { font-size:11.5px;font-weight:600;padding:3px 10px;border-radius:6px; }
.ie-dt-wrap { padding:10px 12px; }
.ie-dt-input { width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:12.5px;outline:none;background:#f8fafc;box-sizing:border-box;transition:border-color .2s; }
.ie-dt-input:focus { border-color:#0891b2;background:#fff; }
.ie-dt-acts { display:flex;gap:6px;margin-top:7px; }
.ie-dt-save   { flex:1;padding:6px;background:#0891b2;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer; }
.ie-dt-cancel { flex:1;padding:6px;background:#f1f5f9;color:#64748b;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer; }
.av-sm { width:24px;height:24px;border-radius:50%;border:1.5px solid #e2e8f0;flex-shrink:0; }
.av-add { width:24px;height:24px;border-radius:50%;background:#f1f5f9;border:1.5px dashed #cbd5e1;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#94a3b8;font-size:10px;transition:all .15s; }
.av-add:hover { background:#e0f7fa;border-color:#0891b2;color:#0891b2; }

/* chat */
.chat-bg { background-color:#1b3f52;background-image:radial-gradient(rgba(255,255,255,.045) 1px,transparent 1px);background-size:22px 22px; }
.cb      { background:#fff;border-radius:2px 12px 12px 12px;padding:9px 13px;max-width:78%;box-shadow:0 1px 3px rgba(0,0,0,.15);font-size:13px;line-height:1.55;color:#1a1a2e;word-break:break-word; }
.cb.mine { background:#d1f5eb;border-radius:12px 2px 12px 12px;margin-left:auto;color:#0a3540; }
.date-sep { display:flex;align-items:center;gap:10px;padding:4px 0;margin:2px 0; }
.date-sep::before,.date-sep::after { content:'';flex:1;height:1px;background:rgba(255,255,255,.1); }
.date-sep span { font-size:11px;color:rgba(255,255,255,.45);background:rgba(0,0,0,.22);padding:3px 12px;border-radius:12px;white-space:nowrap; }
.sys-msg { text-align:left;margin:1px 0;padding:2px 0; }
.sys-msg span { font-size:11.5px;color:rgba(255,255,255,.45);display:inline; }
.chat-input-wrap { background:#fff;border-top:1px solid #e2e8f0;padding:10px 14px;display:flex;align-items:flex-end;gap:8px;flex-shrink:0; }
.chat-ta   { flex:1;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:12px;padding:9px 14px;font-size:13px;color:#1e293b;outline:none;resize:none;min-height:38px;max-height:100px;transition:border-color .2s;font-family:inherit;line-height:1.5;box-sizing:border-box; }
.chat-ta:focus { border-color:#0891b2;background:#fff; }
.chat-send { width:36px;height:36px;background:#0891b2;border:none;border-radius:10px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0; }
.chat-send:hover { background:#0e7490; }
.chat-icon-btn { width:34px;height:34px;background:none;border:none;color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:8px;transition:all .15s;flex-shrink:0; }
.chat-icon-btn:hover { background:#f1f5f9;color:#475569; }
</style>

<div class="task-panel" style="display:flex;height:calc(100vh - 76px);border-radius:14px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 8px 32px rgba(0,0,0,.1);">

{{-- ════ LEFT PANEL ════ --}}
<div style="flex:0 0 40%;min-width:0;display:flex;flex-direction:column;background:#fff;border-right:1px solid #e8edf2;">

    {{-- Header --}}
    <div style="padding:14px 18px 10px;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:6px;">
            <button onclick="closeTaskPanel('{{ route('tasks.index') }}')"
                    style="width:27px;height:27px;flex-shrink:0;margin-top:1px;border-radius:7px;background:#f1f5f9;border:none;cursor:pointer;color:#64748b;display:flex;align-items:center;justify-content:center;transition:background .15s;"
                    onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                <i class="fas fa-times" style="font-size:12px;"></i>
            </button>
            <h1 style="flex:1;font-size:14.5px;font-weight:700;color:#0f172a;margin:0;line-height:1.35;">{{ $task->title }}</h1>
            <div style="display:flex;gap:4px;flex-shrink:0;">
                @if($canEdit)
                <button onclick="openEditModal()" style="font-size:11.5px;font-weight:600;color:#475569;background:none;border:none;cursor:pointer;padding:3px 8px;border-radius:6px;display:flex;align-items:center;gap:3px;transition:color .15s;" onmouseover="this.style.color='#0891b2'" onmouseout="this.style.color='#475569'">
                    <i class="fas fa-pen" style="font-size:9.5px;"></i>Edit
                </button>
                @endif
                @if(auth()->user()->isSuperAdmin())
                <button type="button"
                        onclick="appDeleteConfirm('{{ route('tasks.destroy',$task) }}',function(){window.location='{{ route('tasks.index') }}';})"
                        style="font-size:11.5px;font-weight:600;color:#ef4444;background:none;border:none;cursor:pointer;padding:3px 8px;border-radius:6px;display:flex;align-items:center;gap:3px;">
                    <i class="fas fa-trash" style="font-size:9.5px;"></i>Delete
                </button>
                @endif
            </div>
        </div>
        <span style="font-size:10.5px;color:#94a3b8;">ID: {{ $task->id }} &nbsp;·&nbsp; {{ $task->created_at->format('M d, Y') }}</span>
    </div>

    {{-- Description --}}
    @if($task->description)
    <div style="padding:30px 38px;border-bottom:1px solid #f1f5f9;flex-shrink:0;min-height:300px;">
        <p id="desc-text" style="font-size:12.5px;color:#475569;line-height:1.65;margin:0 0 4px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:6;-webkit-box-orient:vertical;">{{ $task->description }}</p>
        <button id="desc-btn" onclick="toggleDesc()" style="display:none;font-size:11.5px;color:#0891b2;background:none;border:none;cursor:pointer;padding:0;font-weight:600;transition:opacity .15s;" onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'">
            <i class="fas fa-chevron-down" style="font-size:9px;margin-right:3px;" id="desc-icon"></i><span id="desc-label">Expand</span>
        </button>
    </div>
    @endif

    {{-- Scrollable meta --}}
    <div class="tp-lscroll" style="flex:1;overflow-y:auto;padding:4px 18px 12px;" onclick="ieCloseAll(event)">

        <div class="tp-prop">
            <span class="tp-prop-label">Task owner</span>
            <div class="tp-prop-val">
                <img src="{{ $task->creator->avatar_url }}" class="av-sm" alt="">
                <span>{{ $task->creator->name }}</span>
            </div>
        </div>

        {{-- Assignee --}}
        <div class="tp-prop">
            <span class="tp-prop-label">Assignee</span>
            <div class="tp-prop-val">
                <div class="ie-wrap">
                    <div class="ie-display" onclick="event.stopPropagation();ieToggle('assigned_to')" id="disp-assigned_to">
                        @if($task->assignee)
                        <img id="av-assigned_to" src="{{ $task->assignee->avatar_url }}" class="av-sm" alt="">
                        <span id="txt-assigned_to">{{ $task->assignee->name }}</span>
                        @else
                        <span id="txt-assigned_to" style="color:#cbd5e1;">Unassigned</span>
                        @endif
                        <i class="fas fa-chevron-down ie-pen"></i>
                    </div>
                    <div class="ie-picker" id="pick-assigned_to" style="display:none;">
                        <input class="ie-search" placeholder="Search..." oninput="ieSearch(this,'opts-assigned_to')">
                        <div class="ie-opts" id="opts-assigned_to">
                            <div class="ie-opt" data-search="unassigned" onclick="ieUpdateUser('assigned_to',null,null,null)">
                                <i class="fas fa-user-slash" style="font-size:11px;color:#94a3b8;width:20px;text-align:center;"></i><span>Unassigned</span>
                            </div>
                            @foreach($employees as $emp)
                            <div class="ie-opt {{ $task->assigned_to==$emp->id?'active':'' }}" data-search="{{ strtolower($emp->name) }}" data-uid="{{ $emp->id }}"
                                 onclick="ieUpdateUser('assigned_to',{{ $emp->id }},'{{ $emp->name }}','{{ $emp->avatar_url }}')">
                                <img src="{{ $emp->avatar_url }}" class="av-sm" alt=""><span>{{ $emp->name }}</span>
                                @if($task->assigned_to==$emp->id)<i class="fas fa-check" style="margin-left:auto;color:#0891b2;font-size:10px;"></i>@endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Deadline --}}
        <div class="tp-prop">
            <span class="tp-prop-label">Deadline</span>
            <div class="tp-prop-val">
                <div class="ie-wrap">
                    <div class="ie-display" onclick="event.stopPropagation();ieToggle('deadline')" id="disp-deadline">
                        <i class="fas fa-calendar-alt" style="font-size:11px;color:#94a3b8;flex-shrink:0;"></i>
                        @if($task->deadline)
                        <span id="txt-deadline" style="{{ $task->deadline->isPast()&&$task->status!=='completed' ? 'color:#ef4444;font-weight:600;' : '' }}">{{ $task->deadline->format('M d, Y H:i') }}</span>
                        @else
                        <span id="txt-deadline" style="color:#cbd5e1;">No deadline</span>
                        @endif
                        <i class="fas fa-pen ie-pen"></i>
                    </div>
                    <div class="ie-picker" id="pick-deadline" style="display:none;">
                        <div class="ie-dt-wrap">
                            <input type="datetime-local" id="dl-input" class="ie-dt-input" value="{{ $task->deadline?$task->deadline->format('Y-m-d\TH:i'):'' }}">
                            <div class="ie-dt-acts">
                                <button class="ie-dt-save" onclick="ieSaveDeadline()"><i class="fas fa-check" style="margin-right:3px;"></i>Save</button>
                                <button class="ie-dt-cancel" onclick="ieClose('deadline')">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Status --}}
        <div class="tp-prop">
            <span class="tp-prop-label">Status</span>
            <div class="tp-prop-val">
                <div class="ie-wrap">
                    <div class="ie-display" onclick="event.stopPropagation();ieToggle('status')" id="disp-status">
                        <span id="badge-status" class="sb pill-{{ $task->status }}">{{ str_replace('_',' ',ucfirst($task->status)) }}</span>
                        <i class="fas fa-chevron-down ie-pen"></i>
                    </div>
                    <div class="ie-picker" id="pick-status" style="display:none;">
                        <div class="ie-pill-row">
                            @foreach(['new','in_progress','paused','completed'] as $s)
                            <span class="ie-pill pill-{{ $s }} {{ $task->status===$s?'active':'' }}" onclick="ieUpdateField('status','{{ $s }}')">{{ str_replace('_',' ',ucfirst($s)) }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Priority --}}
        <div class="tp-prop">
            <span class="tp-prop-label">Priority</span>
            <div class="tp-prop-val">
                <div class="ie-wrap">
                    <div class="ie-display" onclick="event.stopPropagation();ieToggle('priority')" id="disp-priority">
                        <span id="badge-priority" class="sb pill-{{ $task->priority }}">{{ ucfirst($task->priority) }}</span>
                        <i class="fas fa-chevron-down ie-pen"></i>
                    </div>
                    <div class="ie-picker" id="pick-priority" style="display:none;">
                        <div class="ie-pill-row">
                            @foreach(['low','medium','high','urgent'] as $p)
                            <span class="ie-pill pill-{{ $p }} {{ $task->priority===$p?'active':'' }}" onclick="ieUpdateField('priority','{{ $p }}')">{{ ucfirst($p) }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Project --}}
        <div class="tp-prop">
            <span class="tp-prop-label">Project</span>
            <div class="tp-prop-val">
                <div class="ie-wrap">
                    <div class="ie-display" onclick="event.stopPropagation();ieToggle('project_id')" id="disp-project_id">
                        <i class="fas fa-folder" style="font-size:11px;color:#94a3b8;flex-shrink:0;"></i>
                        @if($task->project)
                        <span id="txt-project_id" style="color:#0891b2;font-weight:500;">{{ $task->project->name }}</span>
                        @else
                        <span id="txt-project_id" style="color:#cbd5e1;">No project</span>
                        @endif
                        <i class="fas fa-chevron-down ie-pen"></i>
                    </div>
                    <div class="ie-picker" id="pick-project_id" style="display:none;">
                        <input class="ie-search" placeholder="Search project..." oninput="ieSearch(this,'opts-project_id')">
                        <div class="ie-opts" id="opts-project_id">
                            <div class="ie-opt" data-search="none" onclick="ieUpdateProject(null,'No project')">
                                <i class="fas fa-ban" style="font-size:11px;color:#94a3b8;width:18px;"></i><span>No project</span>
                            </div>
                            @foreach($projects as $proj)
                            <div class="ie-opt {{ $task->project_id==$proj->id?'active':'' }}" data-search="{{ strtolower($proj->name) }}"
                                 onclick="ieUpdateProject({{ $proj->id }},'{{ addslashes($proj->name) }}')">
                                <i class="fas fa-folder" style="font-size:11px;color:#0891b2;width:18px;"></i><span>{{ $proj->name }}</span>
                                @if($task->project_id==$proj->id)<i class="fas fa-check" style="margin-left:auto;color:#0891b2;font-size:10px;"></i>@endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Participants --}}
        <div class="tp-sec">Participants</div>
        <div class="tp-prop" style="align-items:flex-start;">
            <span class="tp-prop-label" style="padding-top:4px;"></span>
            <div class="tp-prop-val" style="flex-wrap:wrap;">
                <div class="ie-wrap" style="flex:unset;">
                    <div class="ie-display" onclick="event.stopPropagation();ieToggle('participants')" id="disp-participants" style="flex-wrap:wrap;gap:4px;padding:4px 7px;">
                        <div style="display:flex;flex-wrap:wrap;gap:3px;" id="av-participants">
                            @foreach($task->members as $m)
                            <img src="{{ $m->avatar_url }}" class="av-sm" title="{{ $m->name }}" data-uid="{{ $m->id }}" alt="">
                            @endforeach
                        </div>
                        <div class="av-add"><i class="fas fa-plus" style="font-size:9px;"></i></div>
                    </div>
                    <div class="ie-picker" id="pick-participants" style="display:none;">
                        <input class="ie-search" placeholder="Search..." oninput="ieSearch(this,'opts-participants')">
                        <div class="ie-opts" id="opts-participants">
                            @foreach($employees as $emp)
                            @php $isMem=$task->members->contains('id',$emp->id); @endphp
                            <div class="ie-opt {{ $isMem?'active':'' }}" id="part-{{ $emp->id }}" data-search="{{ strtolower($emp->name) }}"
                                 onclick="ieToggleUser('participants',{{ $emp->id }},'{{ $emp->avatar_url }}','{{ $emp->name }}',this)">
                                <img src="{{ $emp->avatar_url }}" class="av-sm" alt=""><span>{{ $emp->name }}</span>
                                <i class="fas fa-check" style="margin-left:auto;color:#0891b2;font-size:10px;{{ $isMem?'':'display:none;' }}"></i>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @if($task->members->isEmpty())<span style="font-size:12px;color:#cbd5e1;padding:4px 7px;">Add participants</span>@endif
            </div>
        </div>

        {{-- Observers --}}
        <div class="tp-sec">Observers</div>
        <div class="tp-prop" style="align-items:flex-start;margin-bottom:4px;">
            <span class="tp-prop-label" style="padding-top:4px;"></span>
            <div class="tp-prop-val" style="flex-wrap:wrap;">
                <div class="ie-wrap" style="flex:unset;">
                    <div class="ie-display" onclick="event.stopPropagation();ieToggle('observers')" id="disp-observers" style="flex-wrap:wrap;gap:4px;padding:4px 7px;">
                        <div style="display:flex;flex-wrap:wrap;gap:3px;" id="av-observers">
                            @foreach($task->observers as $o)
                            <img src="{{ $o->avatar_url }}" class="av-sm" title="{{ $o->name }}" data-uid="{{ $o->id }}" alt="">
                            @endforeach
                        </div>
                        <div class="av-add"><i class="fas fa-plus" style="font-size:9px;"></i></div>
                    </div>
                    <div class="ie-picker" id="pick-observers" style="display:none;">
                        <input class="ie-search" placeholder="Search..." oninput="ieSearch(this,'opts-observers')">
                        <div class="ie-opts" id="opts-observers">
                            @foreach($employees as $emp)
                            @php $isObs=$task->observers->contains('id',$emp->id); @endphp
                            <div class="ie-opt {{ $isObs?'active':'' }}" id="obs-{{ $emp->id }}" data-search="{{ strtolower($emp->name) }}"
                                 onclick="ieToggleUser('observers',{{ $emp->id }},'{{ $emp->avatar_url }}','{{ $emp->name }}',this)">
                                <img src="{{ $emp->avatar_url }}" class="av-sm" alt=""><span>{{ $emp->name }}</span>
                                <i class="fas fa-check" style="margin-left:auto;color:#0891b2;font-size:10px;{{ $isObs?'':'display:none;' }}"></i>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @if($task->observers->isEmpty())<span style="font-size:12px;color:#cbd5e1;padding:4px 7px;">Add observers</span>@endif
            </div>
        </div>

    </div>{{-- /lscroll --}}

    {{-- Action bar --}}
    <div style="padding:10px 16px;border-top:1px solid #f1f5f9;display:flex;align-items:center;gap:7px;flex-shrink:0;background:#fff;">
        @if($task->isMember(auth()->user()))
            @if($task->status==='completed')
            <form action="{{ route('tasks.update',$task) }}" method="POST">@csrf @method('PUT')
                <input type="hidden" name="status" value="new">
                <button type="submit" style="padding:7px 18px;font-size:12px;font-weight:600;border:1.5px solid #e2e8f0;background:#f8fafc;color:#64748b;border-radius:8px;cursor:pointer;">
                    <i class="fas fa-redo" style="font-size:9px;margin-right:4px;"></i>Resume
                </button>
            </form>
            @else
                @if($task->status==='new')
                <form action="{{ route('tasks.update',$task) }}" method="POST">@csrf @method('PUT')
                    <input type="hidden" name="status" value="in_progress">
                    <button type="submit" style="padding:7px 18px;font-size:12px;font-weight:700;border:none;background:linear-gradient(135deg,#00D4E8,#0891b2);color:#fff;border-radius:8px;cursor:pointer;">
                        <i class="fas fa-play" style="font-size:9px;margin-right:4px;"></i>Start
                    </button>
                </form>
                @endif
                <form action="{{ route('tasks.update',$task) }}" method="POST">@csrf @method('PUT')
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" style="padding:7px 18px;font-size:12px;font-weight:700;border:1.5px solid #bbf7d0;background:#f0fdf4;color:#16a34a;border-radius:8px;cursor:pointer;">
                        <i class="fas fa-check" style="font-size:9px;margin-right:4px;"></i>Complete
                    </button>
                </form>
            @endif
        @endif
        <div style="margin-left:auto;font-size:11.5px;color:#94a3b8;display:flex;align-items:center;gap:4px;">
            <i class="fas fa-eye"></i>{{ $task->members->count() }}
        </div>
    </div>

</div>{{-- /left --}}

{{-- ════ RIGHT PANEL ════ --}}
<div style="flex:1;min-width:0;display:flex;flex-direction:column;background:#fff;">

    {{-- Chat header --}}
    <div style="padding:12px 18px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;flex-shrink:0;">
        <div>
            <div style="font-size:14px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:7px;">
                <i class="fas fa-comments" style="font-size:13px;color:#0891b2;"></i>Task chat
            </div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ $task->members->count() }} members</div>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:8px;">
            <button style="padding:6px 14px;font-size:12px;font-weight:600;background:linear-gradient(135deg,#7c3aed,#6d28d9);color:#fff;border:none;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:6px;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                <i class="fas fa-video" style="font-size:11px;"></i>Video call
            </button>
            <div style="display:flex;">
                @foreach($task->members->take(4) as $m)
                <img src="{{ $m->avatar_url }}" style="width:28px;height:28px;border-radius:50%;border:2px solid #fff;box-shadow:0 0 0 1.5px #e2e8f0;margin-left:-6px;" title="{{ $m->name }}" alt="">
                @endforeach
            </div>
            <button class="chat-icon-btn" title="Search"><i class="fas fa-search" style="font-size:12px;"></i></button>
            <button class="chat-icon-btn"><i class="fas fa-ellipsis-v" style="font-size:12px;"></i></button>
        </div>
    </div>

    {{-- Unified feed --}}
    <div id="chat-scroll" class="tp-rscroll chat-bg" style="flex:1;overflow-y:auto;padding:16px 18px;display:flex;flex-direction:column;gap:8px;">

        @php $prevDate = null; @endphp

        @forelse($feed as $item)

        @php $curDate = $item['at']->format('F j, Y'); @endphp
        @if($curDate !== $prevDate)
        <div class="date-sep"><span>{{ $curDate }}</span></div>
        @php $prevDate = $curDate; @endphp
        @endif

        @if($item['type'] === 'comment')
        @php
            $c    = $item['obj'];
            $isMe = $c->user_id === auth()->id();
            $col  = $palette[$c->user_id % count($palette)];
        @endphp
        <div style="display:flex;gap:9px;{{ $isMe?'flex-direction:row-reverse;':'' }}align-items:flex-end;">
            <img src="{{ $c->user->avatar_url }}" style="width:32px;height:32px;border-radius:50%;flex-shrink:0;border:2px solid rgba(255,255,255,.15);" alt="">
            <div style="display:flex;flex-direction:column;gap:3px;max-width:78%;{{ $isMe?'align-items:flex-end;':'' }}">
                <div style="display:flex;align-items:center;gap:8px;{{ $isMe?'flex-direction:row-reverse;':'' }}">
                    <span style="font-size:11.5px;font-weight:700;color:{{ $col }};">{{ $c->user->name }}</span>
                    <span style="font-size:10.5px;color:rgba(255,255,255,.38);">{{ $c->created_at->format('H:i') }}</span>
                    @if($c->user_id===auth()->id()||auth()->user()->isSuperAdmin())
                    <form action="{{ route('tasks.comments.destroy',[$task,$c]) }}" method="POST" style="display:inline;">@csrf @method('DELETE')
                        <button type="submit" style="font-size:10px;color:rgba(255,255,255,.25);background:none;border:none;cursor:pointer;padding:0;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='rgba(255,255,255,.25)'"><i class="fas fa-times"></i></button>
                    </form>
                    @endif
                </div>
                <div class="cb {{ $isMe?'mine':'' }}">{!! preg_replace('/@(\w+)/','<span style="color:#0891b2;font-weight:600;">@$1</span>',e($c->content)) !!}</div>
            </div>
        </div>

        @else
        @php $a = $item['obj']; @endphp
        <div class="sys-msg"><span>
            @if($a->action==='created')
                <strong style="color:rgba(255,255,255,.8);">{{ $a->user->name }}</strong> created this task
            @elseif($a->field==='status')
                <strong style="color:rgba(255,255,255,.8);">{{ $a->user->name }}</strong>
                changed status
                @if($a->old_value) from <em>{{ str_replace('_',' ',$a->old_value) }}</em>@endif
                to <em>{{ str_replace('_',' ',$a->new_value) }}</em>
            @elseif($a->field==='deadline')
                <strong style="color:rgba(255,255,255,.8);">{{ $a->user->name }}</strong> updated the deadline
            @elseif($a->field==='assigned_to')
                <strong style="color:rgba(255,255,255,.8);">{{ $a->user->name }}</strong> changed the assignee
            @elseif($a->field)
                <strong style="color:rgba(255,255,255,.8);">{{ $a->user->name }}</strong>
                updated <em>{{ str_replace('_',' ',$a->field) }}</em>
            @endif
            <span style="color:rgba(255,255,255,.28);font-size:10px;margin-left:5px;">{{ $a->created_at->format('H:i') }}</span>
        </span></div>
        @endif

        @empty
        <div style="flex:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:10px;padding:60px 0;">
            <i class="fas fa-comments" style="font-size:36px;color:rgba(255,255,255,.12);"></i>
            <p style="font-size:13px;color:rgba(255,255,255,.35);margin:0;">No messages yet. Start the conversation!</p>
        </div>
        @endforelse
    </div>

    {{-- Input --}}
    <div class="chat-input-wrap">
        <form action="{{ route('tasks.comments.store',$task) }}" method="POST"
              style="display:flex;gap:8px;align-items:flex-end;width:100%;"
              x-data="mentionInput()"
              @submit="$el.querySelector('textarea').value = content">
            @csrf
            <button type="button" class="chat-icon-btn" title="Attach file" style="color:#94a3b8;">
                <i class="fas fa-paperclip" style="font-size:15px;"></i>
            </button>
            <div style="flex:1;position:relative;">
                <textarea name="content" class="chat-ta" rows="1" required
                          x-model="content" @input="handleInput"
                          @keydown.enter.prevent="if(!$event.shiftKey){ content.trim() && $el.closest('form').requestSubmit(); }"
                          placeholder="Type @ to mention a person..."></textarea>
                <div x-show="showMentions && mentionResults.length > 0"
                     style="position:absolute;bottom:calc(100% + 4px);left:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden;min-width:180px;box-shadow:0 8px 24px rgba(0,0,0,.1);z-index:10;">
                    <template x-for="u in mentionResults" :key="u.id">
                        <button type="button" @click="selectMention(u)"
                                style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 12px;background:none;border:none;cursor:pointer;font-size:12.5px;color:#374151;"
                                onmouseover="this.style.background='#f0fafb'" onmouseout="this.style.background='none'">
                            <span x-text="u.name"></span>
                        </button>
                    </template>
                </div>
            </div>
            <button type="button" class="chat-icon-btn" style="color:#94a3b8;"><i class="far fa-smile" style="font-size:16px;"></i></button>
            <button type="submit" class="chat-send"><i class="fas fa-paper-plane" style="font-size:12px;"></i></button>
        </form>
    </div>

</div>{{-- /right --}}
</div>{{-- /task-panel --}}

{{-- Edit Modal --}}
@if($canEdit)
<div id="editTaskModal" onclick="if(event.target===this)closeEditModal()"
     style="display:none;position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.35);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;">
    <div onclick="event.stopPropagation()"
         style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;width:100%;max-width:500px;padding:28px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.15);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h2 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;">Edit Task</h2>
            <button onclick="closeEditModal()" style="background:#f1f5f9;border:none;border-radius:8px;color:#64748b;cursor:pointer;width:30px;height:30px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-times" style="font-size:12px;"></i>
            </button>
        </div>
        <form action="{{ route('tasks.update',$task) }}" method="POST">
            @csrf @method('PUT')
            <style>
            .em-f { width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;color:#1e293b;border-radius:9px;padding:9px 12px;font-size:13px;outline:none;box-sizing:border-box;transition:border-color .2s; }
            .em-f:focus { border-color:#0891b2;background:#fff; }
            .em-l { display:block;font-size:10.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px; }
            </style>
            <div style="margin-bottom:14px;"><label class="em-l">Title *</label><input type="text" name="title" value="{{ $task->title }}" required class="em-f"></div>
            <div style="margin-bottom:14px;"><label class="em-l">Description</label><textarea name="description" rows="3" class="em-f" style="resize:none;">{{ $task->description }}</textarea></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div><label class="em-l">Status</label><select name="status" class="em-f">@foreach(['new','in_progress','paused','completed'] as $s)<option value="{{ $s }}" {{ $task->status===$s?'selected':'' }}>{{ str_replace('_',' ',ucfirst($s)) }}</option>@endforeach</select></div>
                <div><label class="em-l">Priority</label><select name="priority" class="em-f">@foreach(['low','medium','high','urgent'] as $p)<option value="{{ $p }}" {{ $task->priority===$p?'selected':'' }}>{{ ucfirst($p) }}</option>@endforeach</select></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div><label class="em-l">Assign To</label><select name="assigned_to" class="em-f"><option value="">Unassigned</option>@foreach($employees as $emp)<option value="{{ $emp->id }}" {{ $task->assigned_to==$emp->id?'selected':'' }}>{{ $emp->name }}</option>@endforeach</select></div>
                <div><label class="em-l">Deadline</label><input type="datetime-local" name="deadline" value="{{ $task->deadline?$task->deadline->format('Y-m-d\TH:i'):'' }}" class="em-f"></div>
            </div>
            <div style="margin-bottom:20px;">
                <label class="em-l">Members</label>
                <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:9px;padding:10px;max-height:110px;overflow-y:auto;display:flex;flex-direction:column;gap:5px;">
                    @foreach($employees as $emp)
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="members[]" value="{{ $emp->id }}" style="accent-color:#0891b2;" {{ $task->members->contains($emp->id)?'checked':'' }}>
                        <img src="{{ $emp->avatar_url }}" style="width:20px;height:20px;border-radius:50%;" alt="">
                        <span style="font-size:12.5px;color:#374151;">{{ $emp->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <div style="display:flex;gap:10px;">
                <button type="button" onclick="closeEditModal()" style="flex:1;padding:10px;border:1.5px solid #e2e8f0;background:#f8fafc;color:#64748b;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
                <button type="submit" style="flex:1;padding:10px;border:none;background:linear-gradient(135deg,#00D4E8,#0891b2);color:#fff;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
const FIELD_URL = '{{ route("tasks.field",$task) }}';
const PART_URL  = '{{ route("tasks.participants.toggle",$task) }}';
const OBS_URL   = '{{ route("tasks.observers.toggle",$task) }}';
const CSRF      = '{{ $csrf }}';

function closeTaskPanel(url){
    const p=document.querySelector('.task-panel');
    if(p){p.classList.add('closing');setTimeout(()=>window.location.href=url,200);}
    else window.location.href=url;
}
function openEditModal() { document.getElementById('editTaskModal').style.display='flex'; }
function closeEditModal(){ document.getElementById('editTaskModal').style.display='none'; }
document.addEventListener('keydown',e=>{ if(e.key==='Escape'){closeEditModal();ieCloseAll();} });

function ieCloseAll(event){
    if(event&&event.target.closest&&event.target.closest('.ie-picker')) return;
    document.querySelectorAll('.ie-picker').forEach(p=>p.style.display='none');
}
function ieToggle(field){
    const pk=document.getElementById('pick-'+field);
    const open=pk.style.display!=='none';
    document.querySelectorAll('.ie-picker').forEach(p=>p.style.display='none');
    if(!open) pk.style.display='block';
}
function ieClose(field){ const p=document.getElementById('pick-'+field); if(p)p.style.display='none'; }
function ieSearch(inp,optsId){
    const q=inp.value.toLowerCase();
    document.querySelectorAll('#'+optsId+' .ie-opt').forEach(o=>{
        o.style.display=(o.dataset.search||'').includes(q)?'flex':'none';
    });
}
function ieFlash(id){
    const d=document.getElementById(id); if(!d)return;
    d.style.background='#e0f7fa';setTimeout(()=>d.style.background='',600);
}

async function iePost(url,body){
    const r=await fetch(url,{method:'PATCH',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(body)});
    return r.json();
}
async function iePostPlain(url,body){
    const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(body)});
    return r.json();
}

async function ieUpdateField(field,value){
    const data=await iePost(FIELD_URL,{field,value}); if(!data.success)return;
    ieClose(field);
    if(field==='status'){
        const b=document.getElementById('badge-status');
        b.className='sb pill-'+data.status;
        b.textContent=data.status.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase());
        document.querySelectorAll('#pick-status .ie-pill').forEach(p=>{
            p.classList.toggle('active',p.textContent.trim().toLowerCase().replace(' ','_')===data.status);
        });
    }
    if(field==='priority'){
        const b=document.getElementById('badge-priority');
        b.className='sb pill-'+data.priority;
        b.textContent=data.priority.charAt(0).toUpperCase()+data.priority.slice(1);
        document.querySelectorAll('#pick-priority .ie-pill').forEach(p=>{
            p.classList.toggle('active',p.textContent.trim().toLowerCase()===data.priority);
        });
    }
    ieFlash('disp-'+field);
}

async function ieUpdateUser(field,userId,name,avatar){
    const data=await iePost(FIELD_URL,{field,value:userId}); if(!data.success)return;
    ieClose(field);
    const txt=document.getElementById('txt-'+field);
    const av=document.getElementById('av-'+field);
    if(userId&&data.assignee){
        txt.textContent=data.assignee.name; txt.style.color='#1e293b';
        if(av){av.src=data.assignee.avatar;}
        else{const img=document.createElement('img');img.id='av-'+field;img.src=data.assignee.avatar;img.className='av-sm';txt.before(img);}
    } else {
        txt.textContent='Unassigned'; txt.style.color='#cbd5e1';
        if(av)av.remove();
    }
    document.querySelectorAll('#opts-'+field+' .ie-opt').forEach(o=>{
        const uid=parseInt(o.dataset.uid||0);
        o.classList.toggle('active',uid===userId);
        const chk=o.querySelector('.fa-check'); if(chk)chk.style.display=uid===userId?'':'none';
    });
    ieFlash('disp-'+field);
}

async function ieUpdateProject(projId,projName){
    const data=await iePost(FIELD_URL,{field:'project_id',value:projId}); if(!data.success)return;
    ieClose('project_id');
    const txt=document.getElementById('txt-project_id');
    txt.textContent=projId&&data.project?data.project.name:'No project';
    txt.style.color=projId?'#0891b2':'#cbd5e1';
    ieFlash('disp-project_id');
}

async function ieSaveDeadline(){
    const val=document.getElementById('dl-input').value;
    const data=await iePost(FIELD_URL,{field:'deadline',value:val}); if(!data.success)return;
    ieClose('deadline');
    const txt=document.getElementById('txt-deadline');
    txt.textContent=data.deadline||'No deadline';
    txt.style.color=data.deadline?'#1e293b':'#cbd5e1';
    txt.style.fontWeight='';
    ieFlash('disp-deadline');
}

async function ieToggleUser(type,uid,avatar,name,optEl){
    const url=type==='participants'?PART_URL:OBS_URL;
    const data=await iePostPlain(url,{user_id:uid}); if(!data.success)return;
    const avRow=document.getElementById('av-'+type);
    const isAdded=data.action==='added';
    const chk=optEl.querySelector('.fa-check');
    if(chk)chk.style.display=isAdded?'':'none';
    optEl.classList.toggle('active',isAdded);
    const existing=avRow.querySelector('[data-uid="'+uid+'"]');
    if(isAdded&&!existing){
        const img=document.createElement('img');
        img.src=avatar;img.className='av-sm';img.title=name;img.dataset.uid=uid;img.alt='';
        avRow.appendChild(img);
    } else if(!isAdded&&existing) existing.remove();
    ieFlash('disp-'+type);
}

document.addEventListener('click',ieCloseAll);
document.addEventListener('DOMContentLoaded',()=>{
    const c=document.getElementById('chat-scroll'); if(c)c.scrollTop=c.scrollHeight;
    // Show expand button only when description is actually clamped
    const dt=document.getElementById('desc-text'),db=document.getElementById('desc-btn');
    if(dt&&db&&dt.scrollHeight>dt.clientHeight) db.style.display='inline-block';
});
let _descExp=false;
function toggleDesc(){
    const dt=document.getElementById('desc-text'),icon=document.getElementById('desc-icon'),lbl=document.getElementById('desc-label');
    _descExp=!_descExp;
    dt.style.webkitLineClamp=_descExp?'unset':'6';
    dt.style.display=_descExp?'block':'-webkit-box';
    icon.className=_descExp?'fas fa-chevron-up':'fas fa-chevron-down';
    icon.style.fontSize='9px';icon.style.marginRight='3px';
    lbl.textContent=_descExp?'Collapse':'Expand';
}

function mentionInput(){
    return {
        content:'',showMentions:false,mentionResults:[],mentionStart:-1,
        users:@json($employees->map(fn($u)=>['id'=>$u->id,'name'=>$u->name])),
        handleInput(e){
            const txt=this.content,pos=e.target.selectionStart,before=txt.substring(0,pos);
            const m=before.match(/@(\w*)$/);
            if(m){this.mentionQuery=m[1].toLowerCase();this.mentionStart=pos-m[0].length;this.mentionResults=this.users.filter(u=>u.name.toLowerCase().includes(this.mentionQuery));this.showMentions=this.mentionResults.length>0;}
            else this.showMentions=false;
        },
        selectMention(u){
            const before=this.content.substring(0,this.mentionStart),after=this.content.substring(this.mentionStart+(this.mentionQuery||'').length+1);
            this.content=before+'@'+u.name+' '+after;this.showMentions=false;
        }
    }
}
</script>
@endsection
