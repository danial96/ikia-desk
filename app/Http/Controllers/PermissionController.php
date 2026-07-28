<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if (!Auth::user()->isAdmin()) abort(403);
        $query = User::where('role', 'employee');

        $status = $request->input('status', 'active');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where(function($b) use ($q) {
                $b->where('name', 'like', $q)
                  ->orWhere('email', 'like', $q)
                  ->orWhere('department', 'like', $q);
            });
        }
        if ($request->filled('perm')) {
            $query->whereJsonContains('permissions->' . $request->perm, true);
        }
        $employees = $query->orderBy('name')->get();
        return view('permissions.index', compact('employees'));
    }

    public function update(Request $request, User $employee)
    {
        if (!Auth::user()->isAdmin()) abort(403);

        $allowedPermissions = [
            'create_tasks',
            'view_all_tasks',
            'create_projects',
            'manage_employees',
            'view_reports',
            'export_data',
        ];

        $permissions = [];
        foreach ($allowedPermissions as $perm) {
            $permissions[$perm] = $request->boolean($perm);
        }

        $employee->update(['permissions' => $permissions]);
        return back()->with('success', 'Permissions updated for ' . $employee->name);
    }
}
