<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = Project::with(['creator', 'tasks'])->latest()->paginate(9);

        if (request()->ajax()) {
            $html = $projects->map(fn($p) => view('projects._project_card', ['project' => $p])->render())->implode('');
            return response()->json([
                'html'     => $html,
                'hasMore'  => $projects->hasMorePages(),
                'nextPage' => $projects->currentPage() + 1,
                'loaded'   => $projects->count(),
                'total'    => $projects->total(),
            ]);
        }

        return view('projects.index', compact('projects'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'color'       => 'nullable|string|max:7',
        ]);

        Project::create([
            'name'        => $request->name,
            'description' => $request->description,
            'color'       => $request->color ?? '#6366f1',
            'created_by'  => Auth::id(),
        ]);

        return back()->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        $project->load(['creator', 'tasks.assignee', 'tasks.creator']);
        $tasks = $project->tasks()->with(['assignee', 'creator'])->latest()->get();
        return view('projects.show', compact('project', 'tasks'));
    }

    public function update(Request $request, Project $project)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:active,completed,on_hold,cancelled',
            'color'       => 'nullable|string|max:7',
        ]);

        $project->update($request->only('name', 'description', 'status', 'color'));

        return back()->with('success', 'Project updated.');
    }

    public function destroy(Project $project)
    {
        if (!Auth::user()->isSuperAdmin()) {
            abort(403);
        }

        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Project deleted.');
    }
}
