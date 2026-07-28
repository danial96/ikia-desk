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

<div style="max-width:960px;margin:0 auto;padding-bottom:40px;">

    {{-- Success / Error --}}
    @if(session('success'))
    <div style="background:#dcfce7;border:1px solid #86efac;color:#15803d;border-radius:10px;padding:11px 16px;margin-bottom:18px;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#b91c1c;border-radius:10px;padding:11px 16px;margin-bottom:18px;font-size:13px;">
        <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
    </div>
    @endif

    <form action="{{ $isOwn ? route('profile.update') : route('profile.update.user', $user->id) }}"
          method="POST" enctype="multipart/form-data">
        @csrf

        {{-- ── Top card: avatar + name + position ── --}}
        <div style="background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.08);overflow:hidden;margin-bottom:18px;">

            {{-- Cover gradient --}}
            <div style="height:96px;background:linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%);position:relative;"></div>

            {{-- Avatar + headline --}}
            <div style="padding:0 28px 22px;display:flex;align-items:flex-end;gap:20px;margin-top:-48px;flex-wrap:wrap;">

                {{-- Avatar with upload --}}
                <div style="position:relative;flex-shrink:0;">
                    <div style="width:96px;height:96px;border-radius:50%;border:4px solid #fff;overflow:hidden;background:#e2e8f0;box-shadow:0 4px 16px rgba(0,0,0,.15);">
                        <img id="avatar-preview"
                             src="{{ $user->bitrix_photo_url ?: $user->avatar_url }}"
                             style="width:100%;height:100%;object-fit:cover;" alt="">
                    </div>
                    <label for="avatar-input" style="position:absolute;bottom:2px;right:2px;width:26px;height:26px;background:#0ea5e9;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.2);">
                        <i class="fas fa-camera" style="font-size:10px;color:#fff;"></i>
                    </label>
                    <input type="file" id="avatar-input" name="avatar" accept="image/*" style="display:none;"
                           onchange="var r=new FileReader();r.onload=function(e){document.getElementById('avatar-preview').src=e.target.result;};r.readAsDataURL(this.files[0]);">
                </div>

                {{-- Name + role + dept --}}
                <div style="flex:1;min-width:0;padding-top:54px;">
                    <div style="font-size:20px;font-weight:800;color:#111827;line-height:1.2;">{{ $user->name }}</div>
                    <div style="display:flex;align-items:center;gap:10px;margin-top:4px;flex-wrap:wrap;">
                        @if($user->position)
                        <span style="font-size:13px;color:#6b7280;">{{ $user->position }}</span>
                        @endif
                        @if($user->department)
                        <span style="width:4px;height:4px;border-radius:50%;background:#d1d5db;flex-shrink:0;"></span>
                        <span style="font-size:13px;color:#6b7280;">{{ $user->department }}</span>
                        @endif
                        <span style="padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.4px;
                            background:{{ $user->role === 'super_admin' ? '#fef3c7' : ($user->role === 'admin' ? '#dbeafe' : '#f1f5f9') }};
                            color:{{ $user->role === 'super_admin' ? '#b45309' : ($user->role === 'admin' ? '#1d4ed8' : '#475569') }};">
                            {{ strtoupper(str_replace('_',' ',$user->role)) }}
                        </span>
                        <span style="padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;background:{{ $user->is_active ? '#dcfce7' : '#fee2e2' }};color:{{ $user->is_active ? '#15803d' : '#b91c1c' }};">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>

                {{-- Save button top-right --}}
                <div style="padding-top:54px;">
                    <button type="submit"
                            style="padding:9px 24px;background:#0ea5e9;border:none;color:#fff;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:opacity .15s;"
                            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                        <i class="fas fa-save" style="font-size:11px;"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Two-column body ── --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">

            {{-- LEFT: General Info --}}
            <div style="display:flex;flex-direction:column;gap:18px;">

                <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:22px 24px;">
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
                <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:22px 24px;">
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
                <div style="background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.07);padding:22px 24px;">
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

        {{-- Bottom Save --}}
        <div style="margin-top:18px;display:flex;justify-content:flex-end;">
            <button type="submit"
                    style="padding:10px 32px;background:#0ea5e9;border:none;color:#fff;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:opacity .15s;box-shadow:0 4px 14px rgba(14,165,233,.35);"
                    onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </div>

    </form>
</div>

<script>
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
