<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskCommentController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $request->validate(['content' => 'required|string|max:5000']);

        $content = $request->content;
        $mentions = [];
        preg_match_all('/@(\w+)/', $content, $matches);
        if (!empty($matches[1])) {
            $mentions = \App\Models\User::whereIn('name', $matches[1])->pluck('id')->toArray();
        }

        $comment = $task->comments()->create([
            'user_id'  => Auth::id(),
            'content'  => $content,
            'mentions' => $mentions,
        ]);

        $task->logActivity(Auth::user(), 'commented', null, null, $content);

        $task->load(['members', 'observers']);
        $recipientIds = collect()
            ->push($task->created_by)
            ->push($task->assigned_to)
            ->merge($task->members->pluck('id'))
            ->merge($task->observers->pluck('id'))
            ->filter()->unique()->values()->toArray();
        Notification::notify($recipientIds, Auth::user(), 'task_comment', $task,
            Auth::user()->name . ' commented on "' . $task->title . '"');

        return back()->with('success', 'Comment added.');
    }

    public function destroy(Task $task, TaskComment $comment)
    {
        if ($comment->user_id !== Auth::id() && !Auth::user()->isSuperAdmin()) {
            abort(403);
        }
        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }
}
