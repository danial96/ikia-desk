<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            $counts = Task::selectRaw("
                count(*) as total,
                sum(status != 'completed') as active,
                sum(status != 'completed' and deadline < now()) as overdue
            ")->first();

            $stats = [
                'total_tasks'    => $counts->total ?? 0,
                'my_tasks'       => $counts->active ?? 0,
                'overdue_tasks'  => $counts->overdue ?? 0,
                'total_projects' => Project::count(),
            ];

            $myTasks = Task::with(['project', 'creator'])
                ->where('status', 'in_progress')
                ->orderBy('deadline')
                ->limit(10)
                ->get();
        } else {
            $uid = $user->id;

            $memberTaskIds = \DB::table('task_members')
                ->where('user_id', $uid)
                ->pluck('task_id');

            $counts = Task::selectRaw("
                count(*) as total,
                sum(assigned_to = ? and status != 'completed') as active,
                sum(assigned_to = ? and status != 'completed' and deadline < now()) as overdue
            ", [$uid, $uid])
                ->where(function ($q) use ($uid, $memberTaskIds) {
                    $q->where('created_by', $uid)
                      ->orWhere('assigned_to', $uid)
                      ->orWhereIn('id', $memberTaskIds);
                })
                ->first();

            $stats = [
                'total_tasks'    => $counts->total ?? 0,
                'my_tasks'       => $counts->active ?? 0,
                'overdue_tasks'  => $counts->overdue ?? 0,
                'total_projects' => Project::count(),
            ];

            $myTasks = Task::with(['project', 'creator'])
                ->where('assigned_to', $uid)
                ->where('status', 'in_progress')
                ->orderBy('deadline')
                ->limit(10)
                ->get();
        }

        $recentProjects = Project::with('creator')->latest()->limit(5)->get();

        return view('dashboard', compact('stats', 'myTasks', 'recentProjects'));
    }
}
