<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\TaskController;
use App\Models\Task;
use App\Support\Tz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The parts of the Tasks area the website renders as HTML (list, trash) or answers with a redirect (delete, restore),
 * as JSON. Everything else — create, edit, move, comments, checklists, files — is served by the same handlers as the
 * website (see routes/api.php), so the permission rules exist in exactly one place.
 */
class TaskApiController extends Controller
{
    /** The Tasks list: same visibility and filters as the website, newest activity first. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->validate([
            'status'      => 'nullable',
            'search'      => 'nullable|string|max:200',
            'project_id'  => 'nullable|integer',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'assignee_id' => 'nullable|integer',
            'per_page'    => 'nullable|integer|min:1|max:50',
            'page'        => 'nullable|integer|min:1',
        ]);

        $query = Task::visibleTo($user)
            ->with(['project:id,name', 'assignee', 'creator'])
            ->withCount([
                'files as files_count' => fn ($q) => $q->where('is_task_attachment', true),
                'comments as comments_count',
            ]);

        $query->search($request->search);
        if ($request->filled('project_id'))  $query->where('project_id', $request->project_id);
        if ($request->filled('priority'))    $query->where('priority', $request->priority);
        if ($request->filled('assignee_id')) $query->where('assigned_to', $request->assignee_id);
        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', TaskController::expandStatuses($statuses));
        }

        $page = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Task $t) => $this->card($t, $user))->values(),
            'meta' => [
                'page'     => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total'    => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
        ]);
    }

    /** Trash: admins see every deleted task, everybody else only the ones they created. */
    public function trash(Request $request): JsonResponse
    {
        $user  = $request->user();
        $query = Task::onlyTrashed()->with(['creator', 'assignee', 'project:id,name'])->withCount('comments as comments_count');
        if (!$user->isAdmin()) $query->where('created_by', $user->id);
        if ($q = trim((string) $request->query('search', ''))) $query->where('title', 'like', '%' . $q . '%');

        $page     = $query->orderByDesc('deleted_at')->paginate(50);
        $deleters = \App\Models\User::whereIn('id', $page->getCollection()->pluck('deleted_by')->filter()->unique())->pluck('name', 'id');

        return response()->json([
            'data' => $page->getCollection()->map(fn (Task $t) => $this->card($t, $user) + [
                'deleted_at' => $t->deleted_at?->toIso8601String(),
                'deleted_by' => $t->deleted_by ? ($deleters[$t->deleted_by] ?? null) : null,
            ])->values(),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'has_more' => $page->hasMorePages()],
        ]);
    }

    // Delete / restore / delete-forever run the website's own handler (who may do it is decided there, once)
    // and only the answer differs: JSON instead of a redirect with a flash message.
    public function destroy(Task $task): JsonResponse
    {
        app(TaskController::class)->destroy($task);
        return response()->json(['success' => true]);
    }

    public function restore(int $id): JsonResponse
    {
        app(TaskController::class)->restore($id);
        return response()->json(['success' => true]);
    }

    public function forceDestroy(int $id): JsonResponse
    {
        app(TaskController::class)->forceDestroy($id);
        return response()->json(['success' => true]);
    }

    private function card(Task $t, $user): array
    {
        $person = fn ($u) => $u ? ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar_url] : null;

        return [
            'id'             => $t->id,
            'title'          => $t->title,
            'status'         => $t->status,
            'priority'       => $t->priority,
            'deadline'       => $t->deadline ? Tz::forViewer($t->deadline, $user)->toIso8601String() : null,
            'is_overdue'     => (bool) ($t->deadline && $t->status !== 'completed' && $t->deadline->lt(now())),
            'created_at'     => $t->created_at?->toIso8601String(),
            'updated_at'     => $t->updated_at?->toIso8601String(),
            'project'        => $t->project ? ['id' => $t->project->id, 'name' => $t->project->name] : null,
            'assignee'       => $person($t->assignee),
            'creator'        => $person($t->creator),
            'files_count'    => (int) ($t->files_count ?? 0),
            'comments_count' => (int) ($t->comments_count ?? 0),
        ];
    }
}
