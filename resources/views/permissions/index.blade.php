@extends('layouts.app')

@section('title', 'Permissions')
@section('page-title', 'Employee Permissions')

@section('content')
<style>
#psf-input::placeholder { color:rgba(255,255,255,.32); }
.psf-pill { padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);color:rgba(255,255,255,.65);transition:all .15s; }
.psf-pill:hover { background:rgba(255,255,255,.12); }
.psf-pill.psf-on { background:rgba(0,212,232,.18);border-color:rgba(0,212,232,.55);color:#00D4E8; }
.psf-status-pill { padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);color:rgba(255,255,255,.65);transition:all .15s; }
.psf-status-pill:hover { background:rgba(255,255,255,.12); }
.psf-status-pill.psf-on { background:rgba(0,212,232,.18);border-color:rgba(0,212,232,.55);color:#00D4E8; }
</style>

@php
$permLabels = [
    'create_tasks'     => ['label' => 'Create Tasks',     'desc' => 'Can create and assign tasks to others'],
    'view_all_tasks'   => ['label' => 'View All Tasks',   'desc' => 'Can see all tasks, not just assigned ones'],
    'create_projects'  => ['label' => 'Create Projects',  'desc' => 'Can create new projects'],
    'manage_employees' => ['label' => 'Manage Employees', 'desc' => 'Can add and edit employee accounts'],
    'view_reports'     => ['label' => 'View Reports',     'desc' => 'Can access reports and analytics'],
    'export_data'      => ['label' => 'Export Data',      'desc' => 'Can export data to CSV/Excel'],
];
@endphp

{{-- ── Filter bar ── --}}
<div style="background:rgba(255,255,255,.12);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.18);border-radius:14px;padding:10px 16px;margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:10px;">

        <form id="psf-form" method="GET" action="{{ route('permissions.index') }}" style="flex:1;">
            <div id="psf-bar" style="display:flex;align-items:center;background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.25);border-radius:10px;">

                {{-- Active chips --}}
                <div id="psf-chips" style="display:flex;align-items:center;gap:5px;padding:0 8px;flex-wrap:nowrap;overflow:hidden;max-width:420px;">
                    @if(request('perm'))
                    <span class="psf-chip" data-field="perm" style="display:inline-flex;align-items:center;gap:4px;background:rgba(99,102,241,.25);border:1px solid rgba(99,102,241,.5);color:#a5b4fc;border-radius:6px;padding:3px 8px;font-size:11.5px;white-space:nowrap;">
                        {{ $permLabels[request('perm')]['label'] ?? request('perm') }}
                        <input type="hidden" name="perm" value="{{ request('perm') }}">
                        <span onclick="psfRemoveChip(this,'perm')" style="cursor:pointer;opacity:.7;font-size:13px;line-height:1;">&times;</span>
                    </span>
                    @endif
                    @php $curStatus = request('status','active'); @endphp
                    @if($curStatus !== 'all')
                    <span class="psf-chip" data-field="status" style="display:inline-flex;align-items:center;gap:4px;background:{{ $curStatus==='active' ? 'rgba(16,185,129,.2)' : 'rgba(239,68,68,.2)' }};border:1px solid {{ $curStatus==='active' ? 'rgba(16,185,129,.5)' : 'rgba(239,68,68,.5)' }};color:{{ $curStatus==='active' ? '#6ee7b7' : '#fca5a5' }};border-radius:6px;padding:3px 8px;font-size:11.5px;white-space:nowrap;">
                        <i class="fas fa-circle" style="font-size:6px;"></i>
                        {{ ucfirst($curStatus) }}
                        <input type="hidden" name="status" value="{{ $curStatus }}">
                        <span onclick="psfSetStatus('all')" style="cursor:pointer;opacity:.7;font-size:13px;line-height:1;">&times;</span>
                    </span>
                    @else
                    <input type="hidden" name="status" value="all">
                    @endif
                </div>

                <input type="text" name="search" id="psf-input" value="{{ request('search') }}"
                       placeholder="Search employees by name, email, department..."
                       autocomplete="off"
                       style="flex:1;min-width:120px;background:transparent;border:none;outline:none;color:#fff;font-size:13.5px;padding:9px 8px;font-family:inherit;"
                       onfocus="psfOpen()" />

                @if(request('search') || request('perm'))
                <button type="button" onclick="psfClearAll()" title="Clear"
                        style="background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;padding:0 6px;font-size:13px;">
                    <i class="fas fa-times"></i>
                </button>
                @endif
                <button type="submit" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;padding:0 10px;font-size:14px;">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>

        <p style="color:rgba(255,255,255,.4);font-size:13px;white-space:nowrap;margin:0;">{{ $employees->count() }} employees</p>
    </div>
</div>

{{-- Filter panel --}}
<div id="psf-panel" style="display:none;position:fixed;background:rgba(12,17,42,.97);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:20px;z-index:99999;box-shadow:0 8px 32px rgba(0,0,0,.6);">
    <p style="color:rgba(255,255,255,.35);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin:0 0 8px;">Status</p>
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:16px;">
        @foreach(['active'=>'Active','inactive'=>'Inactive','all'=>'All'] as $val => $lbl)
        <button type="button" class="psf-status-pill {{ request('status','active')===$val ? 'psf-on' : '' }}"
                data-value="{{ $val }}" onclick="psfSetStatus('{{ $val }}')">
            @if($val==='active')<i class="fas fa-circle" style="font-size:7px;color:#10b981;margin-right:4px;"></i>@elseif($val==='inactive')<i class="fas fa-circle" style="font-size:7px;color:#ef4444;margin-right:4px;"></i>@endif
            {{ $lbl }}
        </button>
        @endforeach
    </div>

    <p style="color:rgba(255,255,255,.35);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin:0 0 10px;">Filter by Permission</p>
    <div style="display:flex;flex-wrap:wrap;gap:7px;">
        @foreach($permLabels as $key => $meta)
        <button type="button" class="psf-pill {{ request('perm')===$key ? 'psf-on' : '' }}"
                data-field="perm" data-value="{{ $key }}"
                onclick="psfTogglePerm('{{ $key }}','{{ addslashes($meta['label']) }}')">
            {{ $meta['label'] }}
        </button>
        @endforeach
    </div>
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid rgba(255,255,255,.1);">
        <button type="button" onclick="psfClearAll()"
                style="background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.15);color:rgba(255,255,255,.6);border-radius:8px;padding:7px 18px;font-size:13px;cursor:pointer;font-weight:500;">
            Reset
        </button>
        <button type="button" onclick="document.getElementById('psf-form').submit()"
                style="background:linear-gradient(135deg,#00C4D8,#1B72E8);border:none;color:#fff;border-radius:8px;padding:7px 22px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-search" style="font-size:11px;"></i> Apply
        </button>
    </div>
</div>

<script>
(function(){
    var _open = false;
    var _activePerm = '{{ request('perm') }}';

    document.addEventListener('DOMContentLoaded', function(){
        var panel = document.getElementById('psf-panel');
        if (panel && panel.parentElement !== document.body) document.body.appendChild(panel);
        var inp = document.getElementById('psf-input');
        if (inp) inp.addEventListener('keydown', function(e){ if(e.key==='Enter'){ e.preventDefault(); document.getElementById('psf-form').submit(); } });
    });

    function reposition(){
        var bar   = document.getElementById('psf-bar');
        var panel = document.getElementById('psf-panel');
        if (!bar||!panel||!_open) return;
        var r = bar.getBoundingClientRect();
        panel.style.top   = (r.bottom+8)+'px';
        panel.style.left  = r.left+'px';
        panel.style.width = r.width+'px';
    }

    window.psfOpen = function(){
        if (_open) return; _open = true;
        document.getElementById('psf-panel').style.display = 'block';
        document.getElementById('psf-bar').style.borderColor = 'rgba(0,212,232,.6)';
        reposition();
    };
    window.psfClose = function(){
        if (!_open) return; _open = false;
        document.getElementById('psf-panel').style.display = 'none';
        document.getElementById('psf-bar').style.borderColor = 'rgba(255,255,255,.25)';
    };

    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);

    document.addEventListener('click', function(e){
        if (!_open) return;
        var bar  = document.getElementById('psf-bar');
        var panel= document.getElementById('psf-panel');
        var form = document.getElementById('psf-form');
        if (!panel.contains(e.target) && !form.contains(e.target)) psfClose();
    });

    window.psfTogglePerm = function(key, label){
        if (_activePerm === key){ psfRemoveChip(null, 'perm'); return; }
        _activePerm = key;
        document.querySelectorAll('.psf-chip[data-field="perm"]').forEach(function(c){ c.remove(); });
        var chip = document.createElement('span');
        chip.className = 'psf-chip';
        chip.dataset.field = 'perm';
        chip.style.cssText = 'display:inline-flex;align-items:center;gap:4px;background:rgba(99,102,241,.25);border:1px solid rgba(99,102,241,.5);color:#a5b4fc;border-radius:6px;padding:3px 8px;font-size:11.5px;white-space:nowrap;';
        chip.innerHTML = label.replace(/</g,'&lt;') +
            '<input type="hidden" name="perm" value="'+key+'">' +
            '<span onclick="psfRemoveChip(this,\'perm\')" style="cursor:pointer;opacity:.7;font-size:13px;line-height:1;">&times;</span>';
        document.getElementById('psf-chips').appendChild(chip);
        document.querySelectorAll('.psf-pill[data-field="perm"]').forEach(function(b){
            b.classList.toggle('psf-on', b.dataset.value===key);
        });
    };

    window.psfRemoveChip = function(el, field){
        if (el) el.closest('.psf-chip').remove();
        else document.querySelectorAll('.psf-chip[data-field="'+field+'"]').forEach(function(c){ c.remove(); });
        _activePerm = '';
        document.querySelectorAll('.psf-pill').forEach(function(b){ b.classList.remove('psf-on'); });
    };

    window.psfSetStatus = function(val){
        // remove existing status chip
        document.querySelectorAll('.psf-chip[data-field="status"]').forEach(function(c){ c.remove(); });
        // remove hidden status input if any
        document.querySelectorAll('input[type="hidden"][name="status"]').forEach(function(i){ i.remove(); });
        // add new hidden input to form
        var hid = document.createElement('input');
        hid.type = 'hidden'; hid.name = 'status'; hid.value = val;
        document.getElementById('psf-chips').appendChild(hid);
        // highlight pill
        document.querySelectorAll('.psf-status-pill').forEach(function(b){
            b.classList.toggle('psf-on', b.dataset.value === val);
        });
        document.getElementById('psf-form').submit();
    };

    window.psfClearAll = function(){
        psfRemoveChip(null, 'perm');
        var inp = document.getElementById('psf-input');
        if (inp) inp.value = '';
        document.getElementById('psf-form').submit();
    };
})();
</script>

{{-- Employee list --}}
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;">
    @forelse($employees as $emp)
    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:20px 22px;box-shadow:0 1px 4px rgba(0,0,0,.06);">
        {{-- Header --}}
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
            <img src="{{ $emp->avatar_url }}" style="width:42px;height:42px;border-radius:50%;border:2px solid #e2e8f0;object-fit:cover;" alt="">
            <div>
                <p style="font-size:14px;font-weight:700;color:#1e293b;margin:0;">{{ $emp->name }}</p>
                <p style="font-size:11.5px;color:#94a3b8;margin:2px 0 0;">{{ $emp->email }} &bull; {{ $emp->department ?? 'No department' }}</p>
            </div>
        </div>

        <form action="{{ route('permissions.update', $emp) }}" method="POST">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:16px;">
                @foreach($permLabels as $key => $meta)
                @php $checked = isset($emp->permissions[$key]) && $emp->permissions[$key]; @endphp
                <label style="display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1.5px solid {{ $checked ? '#00D4E8' : '#e2e8f0' }};border-radius:10px;cursor:pointer;transition:background .15s,border-color .15s;background:{{ $checked ? 'rgba(0,212,232,.05)' : 'transparent' }};"
                       onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='{{ $checked ? 'rgba(0,212,232,.05)' : 'transparent' }}'">
                    <div style="position:relative;margin-top:2px;flex-shrink:0;">
                        <input type="checkbox" name="{{ $key }}" value="1" {{ $checked ? 'checked' : '' }}
                               class="sr-only peer" id="{{ $key }}-{{ $emp->id }}">
                        <div style="width:36px;height:20px;border-radius:99px;transition:background .2s;background:{{ $checked ? '#00D4E8' : '#cbd5e1' }};" class="perm-track-{{ $emp->id }}-{{ $key }}"></div>
                        <div style="position:absolute;top:2px;left:2px;width:16px;height:16px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.3);transform:{{ $checked ? 'translateX(16px)' : 'translateX(0)' }};" class="perm-thumb-{{ $emp->id }}-{{ $key }}"></div>
                    </div>
                    <div>
                        <p style="font-size:13px;font-weight:600;color:#1e293b;margin:0;">{{ $meta['label'] }}</p>
                        <p style="font-size:11.5px;color:#64748b;margin:3px 0 0;line-height:1.4;">{{ $meta['desc'] }}</p>
                    </div>
                    <script>
                    (function(){
                        var inp = document.getElementById('{{ $key }}-{{ $emp->id }}');
                        if (!inp) return;
                        inp.addEventListener('change', function(){
                            var on = this.checked;
                            document.querySelector('.perm-track-{{ $emp->id }}-{{ $key }}').style.background = on ? '#00D4E8' : '#cbd5e1';
                            document.querySelector('.perm-thumb-{{ $emp->id }}-{{ $key }}').style.transform = on ? 'translateX(16px)' : 'translateX(0)';
                        });
                    })();
                    </script>
                </label>
                @endforeach
            </div>
            <button type="submit" class="ikia-btn" style="font-size:13px;padding:8px 20px;">
                <i class="fas fa-save" style="font-size:11px;margin-right:5px;"></i>Save Permissions
            </button>
        </form>
    </div>
    @empty
    <div style="padding:60px 20px;text-align:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;">
        <i class="fas fa-lock" style="font-size:32px;color:rgba(255,255,255,.15);display:block;margin-bottom:12px;"></i>
        <p style="color:rgba(255,255,255,.35);font-size:13px;margin:0 0 10px;">No employees found</p>
        <a href="{{ route('permissions.index') }}" style="font-size:13px;color:#00D4E8;text-decoration:none;">Clear filters</a>
    </div>
    @endforelse
</div>
@endsection
