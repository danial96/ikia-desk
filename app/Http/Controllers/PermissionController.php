<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermissionController extends Controller
{
    // Only permissions the app actually enforces
    public const PERMISSIONS = ['create_tasks', 'view_all_tasks', 'create_projects', 'access_payment_terminal'];

    public function index(Request $request)
    {
        if (!Auth::user()->isAdmin()) abort(403);
        $query = User::whereIn('role', ['employee', 'admin']);   // admins are listed too: the Payment Terminal tab can be switched on for them

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
        if ($request->filled('perm') && in_array($request->perm, self::PERMISSIONS, true)) {
            $query->whereJsonContains('permissions->' . $request->perm, true);
        }
        $employees = $query->orderBy('name')->get();
        return view('permissions.index', compact('employees'));
    }

    public function update(Request $request, User $employee)
    {
        if (!Auth::user()->isAdmin()) abort(403);

        $permissions = [];
        foreach (self::PERMISSIONS as $perm) {
            $permissions[$perm] = $request->boolean($perm);
        }

        $employee->update(['permissions' => $permissions]);
        return back()->with('success', 'Permissions updated for ' . $employee->name);
    }
}
