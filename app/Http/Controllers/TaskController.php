<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // If user explicitly clicked List, save preference
        if ($request->has('view') && $request->view === 'list') {
            session(['task_view' => 'list']);
        }

        // If last view was kanban and user didn't explicitly request list, redirect
        if (session('task_view') === 'kanban' && !$request->has('view')) {
            return redirect()->route('tasks.kanban');
        }

        $view = 'list';

        $query = Task::with(['project', 'assignee', 'creator']);
        if (!$user->isSuperAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id)
                  ->orWhereHas('members', fn($m) => $m->where('user_id', $user->id));
            });
        }

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where('title', 'like', $q);
        }
        if ($request->project_id) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('assignee_id')) {
            $query->where('assigned_to', $request->assignee_id);
        }

        $tasks    = $query->latest()->paginate(20)->withQueryString();
        $projects  = Cache::remember('all_projects_list', 120, fn() => Project::orderBy('name')->get(['id', 'name']));
        $employees = Cache::remember('active_employees_list', 120, fn() => User::where('is_active', true)->get(['id', 'name', 'avatar']));

        if ($request->ajax()) {
            $html = $tasks->map(fn($t) => view('tasks._task_row', ['task' => $t])->render())->implode('');
            return response()->json([
                'html'     => $html,
                'hasMore'  => $tasks->hasMorePages(),
                'nextPage' => $tasks->currentPage() + 1,
                'loaded'   => $tasks->count(),
                'total'    => $tasks->total(),
            ]);
        }

        return view('tasks.index', compact('tasks', 'projects', 'employees', 'view'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $hasPermission = $user->isAdmin() || ($user->permissions && !empty($user->permissions['create_tasks']));
        if (!$hasPermission) {
            abort(403, 'You do not have permission to create tasks.');
        }

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id'  => 'nullable|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority'    => 'required|in:low,medium,high,urgent',
            'status'      => 'nullable|in:new,in_progress,paused,completed',
            'deadline'    => 'nullable|date',
            'members'     => 'nullable|array',
            'members.*'   => 'exists:users,id',
            'observers'   => 'nullable|array',
            'observers.*' => 'exists:users,id',
        ]);

        $task = Task::create([
            'title'       => $request->title,
            'description' => $request->description,
            'project_id'  => $request->project_id,
            'assigned_to' => $request->assigned_to,
            'priority'    => $request->priority,
            'deadline'    => $request->deadline,
            'status'      => $request->status ?? 'new',
            'created_by'  => Auth::id(),
        ]);

        if ($request->members) {
            $task->members()->attach($request->members);
        }

        if ($request->observers) {
            $task->observers()->attach($request->observers);
        }

        if ($request->assigned_to && !in_array($request->assigned_to, (array)$request->members)) {
            $task->members()->syncWithoutDetaching([$request->assigned_to]);
        }

        $task->logActivity(Auth::user(), 'created');

        // Notify assignee
        if ($task->assigned_to) {
            Notification::notify(
                [$task->assigned_to], Auth::user(), 'task_assigned', $task,
                Auth::user()->name . ' assigned you to "' . $task->title . '"'
            );
        }

        if ($request->ajax()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Task created successfully.');
    }

    public function show(Task $task)
    {
        $task->load(['project', 'creator', 'assignee', 'members', 'observers', 'comments.user', 'activities.user']);
        $employees = User::where('is_active', true)->get();
        $projects = Project::all();
        return view('tasks.show', compact('task', 'employees', 'projects'));
    }

    public function update(Request $request, Task $task)
    {
        $user = Auth::user();

        if ($request->has('status') && !$request->has('title')) {
            if (!$task->isMember($user)) {
                abort(403, 'You are not a member of this task.');
            }
            $oldStatus = $task->status;
            $task->update(['status' => $request->status]);
            $task->logActivity($user, 'updated', 'status', $oldStatus, $request->status);
            return back()->with('success', 'Task status updated.');
        }

        if (!$user->isSuperAdmin() && $task->created_by !== $user->id) {
            abort(403, 'Only Super Admin or task creator can edit tasks.');
        }

        $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id'  => 'nullable|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priority'    => 'required|in:low,medium,high,urgent',
            'deadline'    => 'nullable|date',
            'status'      => 'required|in:new,in_progress,paused,completed',
            'members'     => 'nullable|array',
        ]);

        $changes = [];
        foreach (['title', 'assigned_to', 'deadline', 'status', 'priority'] as $field) {
            if ($request->has($field) && $task->$field != $request->$field) {
                $changes[$field] = ['old' => $task->$field, 'new' => $request->$field];
            }
        }

        $task->update($request->only('title', 'description', 'project_id', 'assigned_to', 'priority', 'deadline', 'status'));

        if ($request->has('members')) {
            $members = $request->members ?? [];
            if ($task->assigned_to) $members[] = $task->assigned_to;
            $task->members()->sync($members);
        }

        if ($request->has('observers')) {
            $task->observers()->sync($request->observers ?? []);
        }

        foreach ($changes as $field => $vals) {
            $old = $vals['old'];
            $new = $vals['new'];
            if ($field === 'assigned_to') {
                $old = $old ? (User::find($old)?->name ?? $old) : null;
                $new = $new ? (User::find($new)?->name ?? $new) : null;
            }
            $task->logActivity($user, 'updated', $field, $old, $new);
        }

        $task->logActivity($user, 'edited_task', null, null, null);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Task updated.');
    }

    public function destroy(Task $task)
    {
        if (!Auth::user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can delete tasks.');
        }

        $task->delete();
        return redirect()->route('tasks.index')->with('success', 'Task deleted.');
    }

    public function updateField(Request $request, Task $task)
    {
        $user  = Auth::user();
        $field = $request->field;
        $value = $request->value;

        $allowed = ['assigned_to', 'deadline', 'status', 'priority', 'project_id'];
        if (!in_array($field, $allowed)) {
            return response()->json(['success' => false, 'message' => 'Invalid field'], 422);
        }

        if ($field === 'status') {
            if (!$task->isMember($user)) return response()->json(['success' => false], 403);
        } else {
            if (!$user->isSuperAdmin() && $task->created_by !== $user->id) {
                return response()->json(['success' => false], 403);
            }
        }

        $oldValue = $task->$field;
        $task->update([$field => $value ?: null]);
        $logOld = $oldValue;
        $logNew = $value ?: null;
        if ($field === 'assigned_to') {
            $logOld = $logOld ? (User::find($logOld)?->name ?? $logOld) : null;
            $logNew = $logNew ? (User::find($logNew)?->name ?? $logNew) : null;
        }
        $task->logActivity($user, 'updated', $field, $logOld, $logNew);
        $task->load(['assignee', 'project', 'members', 'observers']);

        // Notifications
        if ($field === 'assigned_to' && $value) {
            Notification::notify(
                [(int)$value], $user, 'task_assigned', $task,
                $user->name . ' assigned you to "' . $task->title . '"'
            );
        }
        if ($field === 'status') {
            $allIds = $task->members->pluck('id')->merge($task->observers->pluck('id'))->unique()->toArray();
            Notification::notify(
                $allIds, $user, 'task_status', $task,
                $user->name . ' changed status to "' . $value . '" in "' . $task->title . '"'
            );
        }

        return response()->json([
            'success'      => true,
            'assignee'     => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name, 'avatar' => $task->assignee->avatar_url] : null,
            'deadline'     => $task->deadline ? $task->deadline->format('M d, Y H:i') : null,
            'deadline_raw' => $task->deadline ? $task->deadline->format('Y-m-d\TH:i') : null,
            'status'       => $task->status,
            'priority'     => $task->priority,
            'project'      => $task->project ? ['id' => $task->project->id, 'name' => $task->project->name] : null,
        ]);
    }

    public function toggleParticipant(Request $request, Task $task)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && $task->created_by !== $user->id) {
            return response()->json(['success' => false], 403);
        }
        $uid = (int) $request->user_id;
        $targetUser = User::find($uid);
        $targetName = $targetUser?->name ?? $uid;
        if ($task->members()->where('user_id', $uid)->exists()) {
            $task->members()->detach($uid);
            $action = 'removed';
            $task->logActivity($user, 'removed participant', null, null, $targetName);
        } else {
            $task->members()->attach($uid);
            $action = 'added';
            $task->logActivity($user, 'added participant', null, null, $targetName);
            Notification::notify([$uid], $user, 'task_participant', $task,
                $user->name . ' added you as participant in "' . $task->title . '"');
        }
        return response()->json(['success' => true, 'action' => $action]);
    }

    public function toggleObserver(Request $request, Task $task)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && $task->created_by !== $user->id) {
            return response()->json(['success' => false], 403);
        }
        $uid = (int) $request->user_id;
        $targetUser = User::find($uid);
        $targetName = $targetUser?->name ?? $uid;
        if ($task->observers()->where('user_id', $uid)->exists()) {
            $task->observers()->detach($uid);
            $action = 'removed';
            $task->logActivity($user, 'removed observer', null, null, $targetName);
        } else {
            $task->observers()->attach($uid);
            $action = 'added';
            $task->logActivity($user, 'added observer', null, null, $targetName);
            Notification::notify([$uid], $user, 'task_observer', $task,
                $user->name . ' added you as observer in "' . $task->title . '"');
        }
        return response()->json(['success' => true, 'action' => $action]);
    }

    public function move(Request $request, Task $task)
    {
        $all     = $request->all();
        $updates = ['updated_at' => now()];

        if (array_key_exists('status', $all) && $all['status']) {
            $updates['status'] = $all['status'];
        }
        if (array_key_exists('deadline', $all)) {
            $updates['deadline'] = $all['deadline'] ?: null;
        }

        // Direct DB update — bypass model events that might interfere
        \DB::table('tasks')->where('id', $task->id)->update($updates);

        // Notify assignee about the move
        $task->refresh();
        $mover = Auth::user();
        if ($task->assigned_to) {
            if (isset($updates['status']) && $updates['status'] === 'completed') {
                Notification::notify(
                    [$task->assigned_to], $mover, 'task_status', $task,
                    $mover->name . ' marked "' . $task->title . '" as completed'
                );
            } elseif (isset($updates['deadline'])) {
                $dlStr = $task->deadline ? $task->deadline->format('M d, Y') : 'no deadline';
                Notification::notify(
                    [$task->assigned_to], $mover, 'task_deadline', $task,
                    $mover->name . ' moved "' . $task->title . '" — new deadline: ' . $dlStr
                );
            }
        }

        return response()->json(['success' => true]);
    }

    public function kanban(Request $request)
    {
        session(['task_view' => 'kanban']);

        $user = Auth::user();

        // Task IDs with unseen notifications for this user (AJAX only — not stored in cache)
        $unseenTaskIds = $request->ajax()
            ? Notification::where('user_id', $user->id)
                ->whereNull('read_at')
                ->whereNotNull('task_id')
                ->pluck('task_id')
                ->unique()
                ->values()
                ->toArray()
            : [];

        $buildColumns = function () use ($request, $user, $unseenTaskIds) {
            $today       = now()->startOfDay();
            $todayEnd    = now()->endOfDay();
            $weekEnd     = now()->endOfWeek();
            $nextWeekEnd = now()->addWeek()->endOfWeek();

            $query = Task::with(['project', 'assignee', 'creator']);

            if (!$user->isSuperAdmin()) {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhere('assigned_to', $user->id)
                      ->orWhereExists(function ($sub) use ($user) {
                          $sub->from('task_members')
                              ->whereColumn('task_members.task_id', 'tasks.id')
                              ->where('task_members.user_id', $user->id);
                      });
                });
            }

            if ($request->project_id) {
                $query->where('project_id', $request->project_id);
            }
            if ($request->filled('search')) {
                $query->where('title', 'like', '%' . $request->search . '%');
            }
            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }
            if ($request->filled('assignee_id')) {
                $query->where('assigned_to', $request->assignee_id);
            }

            // Capture base query before status filter so clones stay clean
            $baseQuery = clone $query;

            if ($request->status) {
                $all = (clone $baseQuery)->where('status', $request->status)->latest()->limit(200)->get();
                $completedTotal = $request->status === 'completed'
                    ? $all->count()
                    : (clone $baseQuery)->where('status', 'completed')->count();
            } else {
                $all = (clone $baseQuery)->where('status', '!=', 'completed')->latest()->limit(200)->get();
                $completedTotal = (clone $baseQuery)->where('status', 'completed')->count();
                $all = $all->merge((clone $baseQuery)->where('status', 'completed')->latest()->limit(50)->get());
            }

            // Always include tasks with unseen notifications regardless of status/filter
            if (!empty($unseenTaskIds)) {
                $alreadyLoaded = $all->pluck('id')->flip()->toArray();
                $missing = array_diff($unseenTaskIds, array_keys($alreadyLoaded));
                if (!empty($missing)) {
                    $extra = (clone $baseQuery)->whereIn('id', $missing)->get();
                    $all   = $all->merge($extra);
                }
            }

            $columns = [
                'overdue'            => collect(),
                'due_today'          => collect(),
                'due_this_week'      => collect(),
                'due_next_week'      => collect(),
                'no_deadline'        => collect(),
                'due_over_two_weeks' => collect(),
                'completed'          => collect(),
            ];

            foreach ($all as $task) {
                if ($task->status === 'completed') {
                    $columns['completed']->push($task);
                } elseif (is_null($task->deadline)) {
                    $columns['no_deadline']->push($task);
                } elseif ($task->deadline->lt($today)) {
                    $columns['overdue']->push($task);
                } elseif ($task->deadline->lte($todayEnd)) {
                    $columns['due_today']->push($task);
                } elseif ($task->deadline->lte($weekEnd)) {
                    $columns['due_this_week']->push($task);
                } elseif ($task->deadline->lte($nextWeekEnd)) {
                    $columns['due_next_week']->push($task);
                } else {
                    $columns['due_over_two_weeks']->push($task);
                }
            }

            return [$columns, $completedTotal];
        };

        // AJAX filter/drag calls always query fresh — no cache so moves reflect immediately
        if ($request->ajax()) {
            [$columns, $completedTotal] = $buildColumns();
        } else {
            $cacheKey = 'kanban_' . $user->id . '_' . md5(json_encode($request->only(
                ['project_id', 'search', 'priority', 'assignee_id', 'status']
            )));
            [$columns, $completedTotal] = Cache::remember($cacheKey, 30, $buildColumns);
        }

        $projects  = Cache::remember('all_projects_list', 120, fn() => Project::orderBy('name')->get(['id', 'name']));
        $employees = Cache::remember('active_employees_list', 120, fn() => User::where('is_active', true)->get(['id', 'name', 'avatar']));

        if ($request->ajax()) {
            $result = [];
            foreach ($columns as $key => $colTasks) {
                $result[$key] = $colTasks->map(fn($t) => [
                    'id'       => $t->id,
                    'title'    => $t->title,
                    'desc'     => $t->description,
                    'priority' => $t->priority,
                    'status'   => $t->status,
                    'deadline' => $t->deadline ? $t->deadline->format('M d, Y') : null,
                    'dl_past'  => $t->deadline && $t->deadline->isPast() && $t->status !== 'completed',
                    'project'  => $t->project  ? $t->project->name  : null,
                    'assignee' => $t->assignee ? ['name' => $t->assignee->name, 'avatar' => $t->assignee->avatar_url] : null,
                ])->values();
            }
            return response()->json(['columns' => $result, 'completedTotal' => $completedTotal, 'unseenTaskIds' => $unseenTaskIds]);
        }

        return view('tasks.kanban', compact('columns', 'projects', 'employees', 'completedTotal'));
    }
}
