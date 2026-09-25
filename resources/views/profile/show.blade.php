@extends('layouts.app')
@section('title', $user->name . ' — Profile')
@section('page-title', 'Profile')

@section('content')
@php
    $isOwn        = auth()->id() === $user->id;
    $canEditOrg   = auth()->user()->isAdmin();

    $roStyle = 'width:100%;border:1.5px solid #f1f5f9;border-radius:8px;padding:8px 12px;font-size:13.5px;color:#374151;background:#f8fafc;box-sizing:border-box;min-height:38px;display:flex;align-items:center;';
    $inStyle = 'width:100%;border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:13.5px;color:#111827;outline:none;box-sizing:border-box;transition:border-color .15s;font-family:inherit;';
@endphp

@php
    $online   = $user->last_seen_at && $user->last_seen_at->diffInMinutes(now()) < 5;
    $none     = '<span style="color:#b9c0c6;">field is empty</span>';
    $genderT  = ['M' => 'Male', 'F' => 'Female'][$user->gender] ?? null;
    $fmtDate  = fn($d) => $d ? \Carbon\Carbon::parse($d)->format('F j, Y') : null;
    $rows = [
        ['Name',            $user->name],
        ['Email',           $user->email],
        ['Position',        $user->position],
        ['Department',      $user->department],
        ['Sex',             $genderT],
        ['Phone',           $user->phone],
        ['Work phone',      $user->work_phone],
        ['Personal phone',  $user->personal_phone],
        ['Skype',           $user->skype],
        ['Birthday',        $fmtDate($user->birthday)],
        ['Hired date',      $fmtDate($user->hired_date)],
        ['Time zone',       $user->time_zone],
    ];
@endphp
<style>
.pf-shell { background:#eef2f3; border-radius:11px; padding:22px 24px 28px; box-shadow:0 4px 30px rgba(0,0,0,.25); }
.pf-tab { display:inline-block; padding:6px 13px; border-radius:16px; font-size:14px; color:#6b7680; text-decoration:none; }
.pf-tab.on { background:#dfe6e9; color:#333; }
.pf-card { background:#fff; border-radius:11px; padding:20px 24px; }
.pf-card h3 { margin:0 0 14px; padding-bottom:12px; border-bottom:1px solid #e7ecee; font-size:17px; font-weight:400; color:#535c69; display:flex; align-items:center; justify-content:space-between; }
.pf-lbl { font-size:11.5px; color:#a3acb3; margin:0 0 2px; }
.pf-val { font-size:14.5px; color:#333; margin:0 0 14px; word-break:break-word; }
.pf-editing #pf-view, .pf-editing #pf-org { display:none; } .pf-editing #pf-edit { display:block !important; } .pf-editing .pf-cam { display:flex !important; }
@media (max-width:900px){ .pf-grid { grid-template-columns:1fr !important; } }
</style>

<div style="max-width:1180px;margin:0 auto;padding-bottom:40px;">

    @if(session('success'))
    <div style="background:#dcfce7;border:1px solid #86efac;color:#15803d;border-radius:10px;padding:11px 16px;margin-bottom:14px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;border-radius:10px;padding:11px 16px;margin-bottom:14px;font-size:13px;">
        <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
    </div>
    @endif

    <form action="{{ $isOwn ? route('profile.update') : route('profile.update.user', $user->id) }}"
          method="POST" enctype="multipart/form-data" id="pf-form">
        @csrf

        <div class="pf-shell" id="pf-shell">

            {{-- Header: name + tabs --}}
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <h1 style="margin:0;font-size:26px;font-weight:500;color:#000;line-height:1.2;">{{ $user->name }}</h1>
                <div style="display:flex;gap:8px;">
                    <button type="button" onclick="pfEdit(true,'pf-pass')" style="background:#dfe6e9;border:none;border-radius:6px;padding:5px 12px;font-size:13px;color:#525c69;cursor:pointer;">Security</button>
                </div>
            </div>
            <div style="margin:14px 0 16px;display:flex;gap:6px;flex-wrap:wrap;">
                <span class="pf-tab on">General</span>
                <a class="pf-tab" href="{{ route('tasks.index', ['assignee_id' => $user->id]) }}">Tasks</a>
            </div>

            <div class="pf-grid" style="display:grid;grid-template-columns:320px 1fr;gap:12px;align-items:start;">

                {{-- Photo card --}}
                <div class="pf-card" style="padding:18px 18px 26px;text-align:center;position:relative;">
                    <div style="text-align:right;font-size:11px;font-weight:700;letter-spacing:.4px;color:#8a949c;">
                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $online ? '#9dcf00' : '#c4cacf' }};margin-right:4px;"></span>{{ $online ? 'ONLINE' : 'OFFLINE' }}
                    </div>
                    <div style="position:relative;width:160px;height:160px;margin:14px auto 6px;">
                        <div style="width:160px;height:160px;border-radius:50%;overflow:hidden;background:#e2e8f0;">
                            <img id="avatar-preview" src="{{ $user->bitrix_photo_url ?: $user->avatar_url }}" style="width:100%;height:100%;object-fit:cover;" alt="">
                        </div>
                        <label for="avatar-input" class="pf-cam" style="display:none;position:absolute;bottom:6px;right:6px;width:34px;height:34px;background:#0075fd;border-radius:50%;align-items:center;justify-content:center;cursor:pointer;border:3px solid #fff;">
                            <i class="fas fa-camera" style="font-size:12px;color:#fff;"></i>
                        </label>
                        <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none;"
                           onchange="var r=new FileReader();r.onload=function(e){document.getElementById('avatar-preview').src=e.target.result;};r.readAsDataURL(this.files[0]);">
                    </div>
                    <div style="margin-top:10px;font-size:13px;color:#8a949c;">
                        {{ strtoupper(str_replace('_',' ',$user->role)) }} · {{ $user->is_active ? 'Active' : 'Inactive' }}
                    </div>
                </div>

                <div>
                    {{-- Read-only view (Bitrix "Contact information") --}}
                    <div id="pf-view" class="pf-card">
                        <h3><span>Contact information</span><a href="#" onclick="pfEdit(true);return false;" style="font-size:12.5px;color:#8a949c;text-decoration:none;">edit</a></h3>
                        @foreach($rows as [$label, $value])
                        <p class="pf-lbl">{{ $label }}</p>
                        <p class="pf-val">
                            @if($value)
                                @if($label === 'Email')<a href="mailto:{{ $value }}" style="color:#2067b0;">{{ $value }}</a>@else{{ $value }}@endif
                            @else{!! $none !!}@endif
                        </p>
                        @endforeach
                        @if($user->bitrix_id)
                        <p class="pf-lbl">Bitrix ID</p><p class="pf-val" style="margin-bottom:0;">#{{ $user->bitrix_id }}@if($user->last_login_at) &nbsp;·&nbsp; last login {{ \Carbon\Carbon::parse($user->last_login_at)->format('d M Y') }}@endif</p>
                        @endif
                    </div>

                    {{-- Additional information (Bitrix): supervisor + subordinates --}}
                    @if($supervisor || $subordinates->count())
                    <div id="pf-org" class="pf-card" style="margin-top:12px;">
                        <h3><span>Additional information</span></h3>
                        @if($subordinates->count())
                        <p class="pf-lbl">Subordinates</p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px 24px;margin:6px 0 18px;">
                            @foreach($subordinates as $sub)
                            <a href="{{ route('profile.show.user', $sub->id) }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;min-width:0;">
                                <img src="{{ $sub->avatar_url }}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0;" alt="">
                                <span style="min-width:0;"><span style="display:block;font-size:14px;color:#333;">{{ $sub->name }}</span><span style="display:block;font-size:11.5px;color:#a3acb3;">{{ $sub->position }}</span></span>
                            </a>
                            @endforeach
                        </div>
                        @endif
                        @if($supervisor)
                        <p class="pf-lbl">Supervisor</p>
                        <a href="{{ route('profile.show.user', $supervisor->id) }}" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit;margin-top:6px;">
                            <img src="{{ $supervisor->avatar_url }}" style="width:34px;height:34px;border-radius:50%;object-fit:cover;" alt="">
                            <span><span style="display:block;font-size:14px;color:#333;">{{ $supervisor->name }}</span><span style="display:block;font-size:11.5px;color:#a3acb3;">{{ $supervisor->position }}</span></span>
                        </a>
                        @endif
                    </div>
                    @endif

                    {{-- Edit mode: the real form --}}
                    <div id="pf-edit" style="display:none;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                            <button type="submit" style="padding:11px 24px;background:#0075fd;border:none;color:#fff;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;">Save</button>
                            <button type="button" onclick="pfEdit(false)" style="padding:11px 18px;background:none;border:none;color:#333;font-size:15px;cursor:pointer;">Cancel</button>
                        </div>
        {{-- ── Two-column body ── --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

            {{-- LEFT: General Info --}}
            <div style="display:flex;flex-direction:column;gap:18px;">

                <div style="background:#fff;border-radius:11px;padding:22px 24px;">
                    <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-user" style="color:#0ea5e9;font-size:10px;"></i> General
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px;">

                        {{-- Full Name — always editable --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Full Name</label>
                            <input type="text" name="name" value="{{ $user->name }}" placeholder="Full name"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>

                        {{-- Job Title / Position --}}
                        @php
                            $posInList   = $positions->contains($user->position);
                            $deptInList  = $departments->contains($user->department);
                            $posIsCustom = $user->position && !$posInList;
                            $deptIsCustom= $user->department && !$deptInList;
                        @endphp
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">
                                Job Title / Position
                                @if(!$canEditOrg)<i class="fas fa-lock" style="font-size:8px;color:#cbd5e1;margin-left:4px;" title="Set by admin"></i>@endif
                            </label>
                            @if($canEditOrg)
                                {{-- select has name="position"; when Custom is chosen JS removes name and activates input --}}
                                <select id="pos-select" name="position"
                                        style="{{ $inStyle }}cursor:pointer;background:#fff;"
                                        onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'"
                                        onchange="profFieldSwitch('pos-select','pos-custom','position')">
                                    <option value="">— Select Position —</option>
                                    @foreach($positions as $p)
                                    <option value="{{ $p }}" @selected($user->position === $p && !$posIsCustom)>{{ $p }}</option>
                                    @endforeach
                                    <option value="__other__" @selected($posIsCustom)>+ Custom...</option>
                                </select>
                                <input type="text" id="pos-custom"
                                       value="{{ $posIsCustom ? $user->position : '' }}"
                                       placeholder="Type custom position..."
                                       style="{{ $inStyle }}margin-top:6px;{{ $posIsCustom ? '' : 'display:none;' }}"
                                       onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                            @else
                                <div style="{{ $roStyle }}">{{ $user->position ?: '—' }}</div>
                            @endif
                        </div>

                        {{-- Department --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">
                                Department
                                @if(!$canEditOrg)<i class="fas fa-lock" style="font-size:8px;color:#cbd5e1;margin-left:4px;" title="Set by admin"></i>@endif
                            </label>
                            @if($canEditOrg)
                                <select id="dept-select" name="department"
                                        style="{{ $inStyle }}cursor:pointer;background:#fff;"
                                        onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'"
                                        onchange="profFieldSwitch('dept-select','dept-custom','department')">
                                    <option value="">— Select Department —</option>
                                    @foreach($departments as $d)
                                    <option value="{{ $d }}" @selected($user->department === $d && !$deptIsCustom)>{{ $d }}</option>
                                    @endforeach
                                    <option value="__other__" @selected($deptIsCustom)>+ Custom...</option>
                                </select>
                                <input type="text" id="dept-custom"
                                       value="{{ $deptIsCustom ? $user->department : '' }}"
                                       placeholder="Type custom department..."
                                       style="{{ $inStyle }}margin-top:6px;{{ $deptIsCustom ? '' : 'display:none;' }}"
                                       onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                            @else
                                <div style="{{ $roStyle }}">{{ $user->department ?: '—' }}</div>
                            @endif
                        </div>

                        {{-- Gender --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Gender</label>
                            <select name="gender" style="width:100%;border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:13.5px;color:#111827;outline:none;box-sizing:border-box;background:#fff;font-family:inherit;cursor:pointer;" onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                                <option value="">— Not specified —</option>
                                <option value="M" {{ $user->gender === 'M' ? 'selected' : '' }}>Male</option>
                                <option value="F" {{ $user->gender === 'F' ? 'selected' : '' }}>Female</option>
                            </select>
                        </div>

                        {{-- Birthday --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Date of Birth</label>
                            <input type="date" name="birthday" value="{{ $user->birthday ? \Carbon\Carbon::parse($user->birthday)->format('Y-m-d') : '' }}"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>

                        {{-- Hired Date --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Hired Date</label>
                            <input type="date" name="hired_date" value="{{ $user->hired_date ? \Carbon\Carbon::parse($user->hired_date)->format('Y-m-d') : '' }}"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>

                        {{-- Timezone --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Timezone</label>
                            <select name="time_zone" style="width:100%;border:1.5px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:13.5px;color:#111827;outline:none;box-sizing:border-box;background:#fff;font-family:inherit;cursor:pointer;" onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                                <option value="">— Select —</option>
                                @foreach(['Asia/Karachi','Asia/Dubai','Asia/Kolkata','Europe/London','America/New_York','America/Los_Angeles','UTC'] as $tz)
                                <option value="{{ $tz }}" {{ $user->time_zone === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            {{-- RIGHT: Contact + Security --}}
            <div style="display:flex;flex-direction:column;gap:18px;">

                {{-- Contact --}}
                <div style="background:#fff;border-radius:11px;padding:22px 24px;">
                    <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-address-card" style="color:#0ea5e9;font-size:10px;"></i> Contact
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px;">

                        {{-- Work Email — read-only for employees --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">
                                Work Email
                                @if(!$canEditOrg)<i class="fas fa-lock" style="font-size:8px;color:#cbd5e1;margin-left:4px;" title="Set by admin"></i>@endif
                            </label>
                            @if($canEditOrg)
                                <input type="email" name="email" value="{{ $user->email }}"
                                       style="{{ $inStyle }}"
                                       onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                            @else
                                <div style="{{ $roStyle }}">{{ $user->email ?: '—' }}</div>
                            @endif
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Phone</label>
                            <input type="tel" name="phone" value="{{ $user->phone }}"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>

                        {{-- Work Phone --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Work Phone</label>
                            <input type="tel" name="work_phone" value="{{ $user->work_phone }}"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>

                        {{-- Personal Phone --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Personal Phone</label>
                            <input type="tel" name="personal_phone" value="{{ $user->personal_phone }}"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>

                        {{-- Skype --}}
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Skype</label>
                            <input type="text" name="skype" value="{{ $user->skype }}"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>

                    </div>
                </div>

                {{-- Security / Password --}}
                <div id="pf-pass" style="background:#fff;border-radius:11px;padding:22px 24px;">
                    <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:1px;text-transform:uppercase;margin-bottom:16px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-lock" style="color:#0ea5e9;font-size:10px;"></i> Change Password
                    </div>
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">New Password</label>
                            <input type="password" name="password" placeholder="Leave blank to keep current"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>
                        <div>
                            <label style="display:block;font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">Confirm Password</label>
                            <input type="password" name="password_confirmation" placeholder="Repeat new password"
                                   style="{{ $inStyle }}"
                                   onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        </div>
                        <p style="font-size:11.5px;color:#9ca3af;margin:0;">Minimum 8 characters. Leave blank to keep current password.</p>
                    </div>
                </div>

                {{-- Bitrix info (read-only) --}}
                @if($user->bitrix_id)
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px 20px;">
                    <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:1px;text-transform:uppercase;margin-bottom:12px;display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-link" style="color:#6366f1;font-size:10px;"></i> Bitrix24 Info
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                        <div>
                            <p style="font-size:10px;color:#9ca3af;margin:0 0 2px;text-transform:uppercase;letter-spacing:.4px;">Bitrix ID</p>
                            <p style="font-size:13px;color:#374151;font-weight:600;margin:0;">#{{ $user->bitrix_id }}</p>
                        </div>
                        @if($user->last_login_at)
                        <div>
                            <p style="font-size:10px;color:#9ca3af;margin:0 0 2px;text-transform:uppercase;letter-spacing:.4px;">Last Login</p>
                            <p style="font-size:13px;color:#374151;font-weight:600;margin:0;">{{ \Carbon\Carbon::parse($user->last_login_at)->format('d M Y') }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

            </div>
        </div>

                    </div>{{-- /pf-edit --}}
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function pfEdit(on, scrollId) {
    document.getElementById('pf-shell').classList.toggle('pf-editing', !!on);
    if (on && scrollId) { var el = document.getElementById(scrollId); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
    if (!on) { document.getElementById('pf-form').reset(); }
}
if (location.hash === '#security') document.addEventListener('DOMContentLoaded', function(){ pfEdit(true,'pf-pass'); });
// Only one field (select OR input) should carry the name at a time.
function profFieldSwitch(selId, inputId, fieldName) {
    const sel = document.getElementById(selId);
    const inp = document.getElementById(inputId);
    if (!sel || !inp) return;
    if (sel.value === '__other__') {
        sel.removeAttribute('name');   // select does NOT submit
        inp.name = fieldName;          // input submits
        inp.style.display = 'block';
        inp.focus();
    } else {
        sel.name = fieldName;          // select submits
        inp.removeAttribute('name');   // input does NOT submit
        inp.style.display = 'none';
        inp.value = '';
    }
}

// On page load: if custom input is active, remove name from select
(function(){
    [['pos-select','pos-custom','position'],['dept-select','dept-custom','department']].forEach(function(t){
        const sel = document.getElementById(t[0]);
        const inp = document.getElementById(t[1]);
        if (!sel || !inp) return;
        if (sel.value === '__other__' && inp.value) {
            sel.removeAttribute('name');
            inp.name = t[2];
        }
    });
})();
</script>
@endsection
