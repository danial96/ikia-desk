<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show($id = null)
    {
        $user = $id ? \App\Models\User::findOrFail($id) : Auth::user();

        // Admins and super_admins can view any profile
        if ($id && Auth::id() != $id && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $positions   = \App\Models\User::whereNotNull('position')->where('position','!=','')
                            ->pluck('position')->unique()->sort()->values();
        $departments = \App\Models\Department::orderBy('name')->pluck('name');

        return view('profile.show', compact('user', 'positions', 'departments'));
    }

    public function update(Request $request, $id = null)
    {
        $user = $id ? \App\Models\User::findOrFail($id) : Auth::user();

        if ($id && Auth::id() != $id && !Auth::user()->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email,'.$user->id,
            'phone'          => 'nullable|string|max:30',
            'work_phone'     => 'nullable|string|max:30',
            'personal_phone' => 'nullable|string|max:30',
            'work_email'     => 'nullable|email|max:255',
            'position'       => 'nullable|string|max:255',
            'department'     => 'nullable|string|max:255',
            'skype'          => 'nullable|string|max:100',
            'gender'         => 'nullable|in:M,F',
            'birthday'       => 'nullable|date',
            'hired_date'     => 'nullable|date',
            'time_zone'      => 'nullable|string|max:60',
            'password'       => 'nullable|string|min:8|confirmed',
            'avatar'         => 'nullable|image|max:3072',
        ]);

        $data = $request->only([
            'name','email','phone','work_phone','personal_phone',
            'work_email','position','department','skype',
            'gender','birthday','hired_date','time_zone',
        ]);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar) Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }
}
