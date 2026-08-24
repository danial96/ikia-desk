<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        if (!Auth::user()->isAdmin()) abort(403);
        $query = User::latest();

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where(function($builder) use ($q) {
                $builder->where('name', 'like', $q)
                        ->orWhere('email', 'like', $q)
                        ->orWhere('department', 'like', $q)
                        ->orWhere('position', 'like', $q);
            });
        }
        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $employees = $query->paginate(15)->withQueryString();

        if ($request->ajax()) {
            $html = $employees->map(fn($e) => view('employees._emp_card', ['emp' => $e])->render())->implode('');
            return response()->json([
                'html'     => $html,
                'hasMore'  => $employees->hasMorePages(),
                'nextPage' => $employees->currentPage() + 1,
                'loaded'   => $employees->count(),
                'total'    => $employees->total(),
            ]);
        }

        return view('employees.index', compact('employees'));
    }

    public function store(Request $request)
    {
        if (!Auth::user()->isAdmin()) abort(403);

        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users',
            'password'   => 'required|string|min:8',
            'role'       => 'required|in:super_admin,admin,employee',
            'phone'      => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position'   => 'nullable|string|max:100',
        ]);

        if ($request->role === 'super_admin' && !Auth::user()->isSuperAdmin()) {
            abort(403, 'Only Super Admin can create Super Admin accounts.');
        }

        User::create([
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'role'       => $request->role,
            'phone'      => $request->phone,
            'department' => $request->department,
            'position'   => $request->position,
            'is_active'  => true,
        ]);

        return back()->with('success', 'Employee added successfully.');
    }

    public function update(Request $request, User $employee)
    {
        if (!Auth::user()->isAdmin()) abort(403);

        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $employee->id,
            'role'       => 'required|in:super_admin,admin,employee',
            'phone'      => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position'   => 'nullable|string|max:100',
            'is_active'  => 'boolean',
        ]);

        $data = $request->only('name', 'email', 'role', 'phone', 'department', 'position', 'is_active');
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $employee->update($data);
        return back()->with('success', 'Employee updated.');
    }

    public function destroy(User $employee)
    {
        if (!Auth::user()->isSuperAdmin()) abort(403);
        if ($employee->id === Auth::id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }
        $employee->delete();
        return back()->with('success', 'Employee removed.');
    }

    public function toggleActive(User $employee)
    {
        if (!Auth::user()->isAdmin()) abort(403);
        $employee->update(['is_active' => !$employee->is_active]);
        return back()->with('success', 'Status updated.');
    }
}
