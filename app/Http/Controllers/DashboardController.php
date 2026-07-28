<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            $stats = [
                'total_tasks'    => Task::count(),
                'my_tasks'       => Task::whereNotIn('status', ['completed'])->count(),
                'overdue_tasks'  => Task::where('deadline', '<', now())->whereNotIn('status', ['completed'])->count(),
                'total_projects' => Project::count(),
            ];
            $myTasks = Task::with(['project', 'creator'])
                ->whereNotIn('status', ['completed'])
                ->orderBy('deadline')
                ->limit(10)
                ->get();
        } else {
            $stats = [
                'total_tasks'    => Task::whereHas('members', fn($q) => $q->where('user_id', $user->id))
                    ->orWhere('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id)
                    ->count(),
                'my_tasks'       => Task::where('assigned_to', $user->id)->whereNotIn('status', ['completed'])->count(),
                'overdue_tasks'  => Task::where('assigned_to', $user->id)
                    ->where('deadline', '<', now())
                    ->whereNotIn('status', ['completed'])
                    ->count(),
                'total_projects' => Project::count(),
            ];
            $myTasks = Task::with(['project', 'creator'])
                ->where('assigned_to', $user->id)
                ->whereNotIn('status', ['completed'])
                ->orderBy('deadline')
                ->limit(10)
                ->get();
        }

        $recentProjects = Project::with('creator')->latest()->limit(5)->get();

        return view('dashboard', compact('stats', 'myTasks', 'recentProjects'));
    }
}
