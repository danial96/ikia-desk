<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/** The pick-lists the app needs: people (assignee, @mention, new chat) and projects. */
class DirectoryController extends Controller
{
    public function users(): JsonResponse
    {
        $users = Cache::remember('api_directory_users', 120, fn () => User::where('is_active', true)->orderBy('name')
            ->get(['id', 'name', 'position', 'avatar'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'position' => $u->position, 'avatar' => $u->avatar_url])
            ->values());

        return response()->json(['data' => $users]);
    }

    public function projects(): JsonResponse
    {
        return response()->json(['data' => Cache::remember('all_projects_list', 120, fn () => Project::orderBy('name')->get(['id', 'name']))]);
    }
}
