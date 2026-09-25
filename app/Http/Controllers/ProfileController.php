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

        // Org chart (Bitrix "Additional information"): the head of my department is my supervisor;
        // people in departments I head (and their sub-departments) are my subordinates.
        $allDepts   = \App\Models\Department::all();
        $byId       = $allDepts->keyBy('id');
        $mine       = $allDepts->first(fn($d) => $user->department && mb_strtolower($d->name) === mb_strtolower($user->department));
        $supervisor = null;
        for ($d = $mine, $guard = 0; $d && $guard < 10; $d = $d->parent_id ? $byId->get($d->parent_id) : null, $guard++) {
            if ($d->head_id && $d->head_id != $user->id) { $supervisor = \App\Models\User::find($d->head_id); break; }
        }
        $deptIds = collect();
        $queue   = $allDepts->where('head_id', $user->id)->pluck('id')->all();
        while ($queue) {
            $id = array_shift($queue);
            if ($deptIds->contains($id)) continue;
            $deptIds->push($id);
            foreach ($allDepts->where('parent_id', $id) as $child) $queue[] = $child->id;
        }
        $deptNames    = $allDepts->whereIn('id', $deptIds->all())->pluck('name')->map(fn($n) => mb_strtolower($n))->all();
        $subordinates = $deptNames
            ? \App\Models\User::where('is_active', true)->where('id', '!=', $user->id)->whereNotNull('department')->get()
                ->filter(fn($u) => in_array(mb_strtolower($u->department), $deptNames, true))->sortBy('name')->values()
            : collect();

        return view('profile.show', compact('user', 'positions', 'departments', 'supervisor', 'subordinates'));
    }

    public function update(Request $request, $id = null)
    {
        $user = $id ? \App\Models\User::findOrFail($id) : Auth::user();

        if ($id && Auth::id() != $id && !Auth::user()->isAdmin()) {
            abort(403);
        }

        // Admins can edit other profiles, but only a Super Admin may change a Super Admin's email/password
        if (Auth::id() != $user->id && $user->isSuperAdmin() && !Auth::user()->isSuperAdmin()) {
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
            $file = $request->file('avatar');
            $webp = \App\Support\AvatarImage::toWebp($file->get());
            if ($webp) {
                $data['avatar'] = 'avatars/' . $user->id . '-' . substr(md5($webp), 0, 8) . '.webp';
                Storage::disk('public')->put($data['avatar'], $webp);
            } else {
                $data['avatar'] = $file->store('avatars', 'public');
            }
        }

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }
}
