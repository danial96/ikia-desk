@extends('layouts.app')
@section('title', 'Trash')
@section('page-title', 'Trash')

@section('content')
<div style="max-width:1100px;margin:0 auto;padding-bottom:40px;">

    <div style="background:#eef2f3;border-radius:11px;padding:22px 24px 26px;box-shadow:0 4px 30px rgba(0,0,0,.25);">

        <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:6px;">
            <h1 style="margin:0;font-size:24px;font-weight:500;color:#000;">Trash</h1>
            <form method="GET" action="{{ route('tasks.trash') }}" style="display:flex;align-items:center;gap:8px;background:#fff;border-radius:20px;padding:0 14px;height:38px;min-width:260px;">
                <i class="fas fa-search" style="font-size:13px;color:#8b98a3;"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Search deleted tasks"
                       style="border:none;outline:none;box-shadow:none;background:none;font-size:14px;color:#333;width:100%;">
            </form>
        </div>
        <p style="margin:0 0 16px;font-size:13px;color:#7d8790;">
            @if(auth()->user()->isAdmin())
                Deleted tasks from everyone. Restoring one puts it back exactly as it was.
            @else
                Tasks you created and deleted. Restoring one puts it back exactly as it was.
            @endif
        </p>

        @if(session('success'))
        <div style="background:#dcfce7;border:1px solid #86efac;color:#15803d;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px;font-weight:600;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
        @endif

        <div style="background:#fff;border-radius:11px;overflow:hidden;">
            @forelse($tasks as $task)
            <div style="display:flex;align-items:center;gap:16px;padding:14px 20px;{{ !$loop->last ? 'border-bottom:1px solid #eef1f3;' : '' }}">
                <div style="width:38px;height:38px;border-radius:50%;background:#f3e9e9;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-trash" style="color:#c0605f;font-size:14px;"></i>
                </div>
                <div style="min-width:0;flex:1;">
                    <div style="font-size:15px;font-weight:500;color:#333;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $task->title }}</div>
                    <div style="font-size:12.5px;color:#8a949c;margin-top:3px;display:flex;flex-wrap:wrap;gap:4px 14px;">
                        @if($task->project)<span><i class="far fa-folder" style="margin-right:4px;"></i>{{ $task->project->name }}</span>@endif
                        @if($task->creator)<span>Owner: {{ $task->creator->name }}</span>@endif
                        @if($task->assignee)<span>Assignee: {{ $task->assignee->name }}</span>@endif
                        <span title="{{ $task->deleted_at->copy()->setTimezone(config('app.timezone'))->format('d M Y, g:i a') }}">
                            Deleted {{ $task->deleted_at->diffForHumans() }}@if(!empty($deleters[$task->deleted_by])) by {{ $deleters[$task->deleted_by] }}@endif
                        </span>
                    </div>
                </div>
                <form method="POST" action="{{ route('tasks.restore', $task->id) }}">
                    @csrf
                    <button type="submit" style="background:#0075fd;border:none;color:#fff;border-radius:8px;padding:9px 18px;font-size:14px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;">
                        <i class="fas fa-rotate-left" style="font-size:12px;"></i>Restore
                    </button>
                </form>
            </div>
            @empty
            <div style="padding:60px 20px;text-align:center;color:#9aa5ad;">
                <i class="far fa-trash-can" style="font-size:34px;margin-bottom:12px;display:block;color:#c5ccd1;"></i>
                <div style="font-size:16px;color:#7d8790;">Trash is empty</div>
                <div style="font-size:13px;margin-top:4px;">Deleted tasks will appear here.</div>
            </div>
            @endforelse
        </div>

        @if($tasks->hasPages())
        <div style="margin-top:14px;display:flex;justify-content:center;gap:10px;">
            @if(!$tasks->onFirstPage())<a href="{{ $tasks->previousPageUrl() }}" style="background:#fff;border-radius:8px;padding:8px 16px;font-size:13px;color:#333;text-decoration:none;">&larr; Newer</a>@endif
            @if($tasks->hasMorePages())<a href="{{ $tasks->nextPageUrl() }}" style="background:#fff;border-radius:8px;padding:8px 16px;font-size:13px;color:#333;text-decoration:none;">Older &rarr;</a>@endif
        </div>
        @endif
    </div>
</div>
@endsection
