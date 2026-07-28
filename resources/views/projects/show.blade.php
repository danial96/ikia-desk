@extends('layouts.app')

@section('title', $project->name)
@section('page-title', $project->name)

@section('content')
<div x-data="{ showTaskModal: false, showEditModal: false }">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-6">
        <a href="{{ route('projects.index') }}" class="hover:text-indigo-600">Projects</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-800 font-medium">{{ $project->name }}</span>
    </div>

    {{-- Project Header --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: {{ $project->color }}20;">
                    <div class="w-5 h-5 rounded-full" style="background: {{ $project->color }};"></div>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800">{{ $project->name }}</h1>
                    <p class="text-sm text-gray-500">Created by {{ $project->creator->name }} &bull; {{ $project->created_at->format('M d, Y') }}</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if(auth()->user()->isAdmin())
                <button @click="showEditModal = true" class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition">Edit</button>
                @endif
                @if(auth()->user()->isSuperAdmin())
                <form action="{{ route('projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Delete this project?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-3 py-2 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition">Delete</button>
                </form>
                @endif
            </div>
        </div>

        @if($project->description)
        <p class="text-gray-600 mt-4">{{ $project->description }}</p>
        @endif

        @php $total = $tasks->count(); $done = $tasks->where('status', 'completed')->count(); $pct = $total > 0 ? round(($done/$total)*100) : 0; @endphp
        <div class="mt-4 flex items-center gap-4">
            <div class="flex-1">
                <div class="flex justify-between text-xs text-gray-500 mb-1">
                    <span>Progress</span><span>{{ $done }}/{{ $total }} tasks ({{ $pct }}%)</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all" style="width: {{ $pct }}%; background: {{ $project->color }};"></div>
                </div>
            </div>
            <span class="text-xs px-2 py-1 rounded-full capitalize font-medium
                {{ $project->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                {{ str_replace('_', ' ', $project->status) }}
            </span>
        </div>
    </div>

    {{-- Tasks Header --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-semibold text-gray-800">Tasks ({{ $tasks->count() }})</h2>
        @if(auth()->user()->isAdmin() || !empty(auth()->user()->permissions['create_tasks']))
        <button @click="showTaskModal = true"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Task
        </button>
        @endif
    </div>

    {{-- Task List --}}
    <div class="space-y-2">
        @forelse($tasks as $task)
        <a href="{{ route('tasks.show', $task) }}"
           class="flex items-center gap-4 bg-white rounded-xl border border-gray-200 px-5 py-4 hover:shadow-sm transition group">
            <span class="inline-flex px-2.5 py-1 text-xs font-medium rounded-full {{ $task->status_color }}">
                {{ str_replace('_', ' ', $task->status) }}
            </span>
            <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-800 group-hover:text-indigo-600 transition truncate">{{ $task->title }}</p>
                @if($task->deadline)
                <p class="text-xs text-gray-400 mt-0.5">Due {{ $task->deadline->format('M d, Y') }}
                    @if($task->deadline->isPast() && $task->status !== 'completed')
                    <span class="text-red-500">(Overdue)</span>
                    @endif
                </p>
                @endif
            </div>
            @if($task->assignee)
            <div class="flex items-center gap-2">
                <img src="{{ $task->assignee->avatar_url }}" class="w-6 h-6 rounded-full" alt="">
                <span class="text-xs text-gray-500 hidden sm:block">{{ $task->assignee->name }}</span>
            </div>
            @endif
            <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full {{ $task->priority_color }}">{{ $task->priority }}</span>
        </a>
        @empty
        <div class="py-12 text-center bg-white rounded-xl border border-gray-200">
            <p class="text-gray-400 text-sm">No tasks in this project yet</p>
        </div>
        @endforelse
    </div>

    {{-- Add Task Modal --}}
    @if(auth()->user()->isAdmin() || !empty(auth()->user()->permissions['create_tasks']))
    @include('tasks._form_modal', ['showVar' => 'showTaskModal', 'defaultProject' => $project->id])
    @endif

    {{-- Edit Project Modal --}}
    @if(auth()->user()->isAdmin())
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showEditModal = false">
        <div class="fixed inset-0 bg-black/50" @click="showEditModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
            <h2 class="text-lg font-semibold mb-5">Edit Project</h2>
            <form action="{{ route('projects.update', $project) }}" method="POST" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" value="{{ $project->name }}" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm resize-none">{{ $project->description }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                        @foreach(['active','completed','on_hold','cancelled'] as $s)
                        <option value="{{ $s }}" {{ $project->status === $s ? 'selected' : '' }}>{{ str_replace('_',' ',ucfirst($s)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showEditModal = false" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
