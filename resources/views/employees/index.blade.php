@extends('layouts.app')

@section('title', 'Employees')
@section('page-title', 'Employees')

@section('content')
<style>
#esf-input::placeholder { color:rgba(255,255,255,.32); }
.esf-chip { display:inline-flex;align-items:center;gap:4px;border-radius:6px;padding:3px 8px;font-size:11.5px;white-space:nowrap; }
.esf-pill { padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid rgba(255,255,255,.18);background:rgba(255,255,255,.07);color:rgba(255,255,255,.65);transition:all .15s; }
.esf-pill:hover { background:rgba(255,255,255,.12); }
.esf-pill.esf-on { background:rgba(0,212,232,.18);border-color:rgba(0,212,232,.55);color:#00D4E8; }
</style>

<div x-data="{ showModal: false }">

    {{-- ── Filter bar ── --}}
    <div style="background:rgba(255,255,255,.12);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.18);border-radius:14px;padding:10px 16px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:10px;">

            {{-- Search + chips form --}}
            <form id="emp-filter-form" method="GET" action="{{ route('employees.index') }}" style="flex:1;">
                <div id="esf-bar" style="display:flex;align-items:center;background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.25);border-radius:10px;">

                    {{-- Active chips --}}
                    <div id="esf-chips" style="display:flex;align-items:center;gap:5px;padding:0 8px;flex-wrap:nowrap;overflow:hidden;max-width:360px;">
                        @if(request('role'))
                        <span class="esf-chip" data-field="role" style="background:rgba(99,102,241,.25);border:1px solid rgba(99,102,241,.5);color:#a5b4fc;">
                            {{ str_replace('_',' ',ucfirst(request('role'))) }}
                            <input type="hidden" name="role" value="{{ request('role') }}">
                            <span onclick="esfRemoveChip(this,'role')" style="cursor:pointer;opacity:.7;font-size:13px;line-height:1;">&times;</span>
                        </span>
                        @endif
                        @if(request('status'))
                        <span class="esf-chip" data-field="status" style="background:{{ request('status')==='active'?'rgba(34,197,94,.2)':'rgba(239,68,68,.2)' }};border:1px solid {{ request('status')==='active'?'rgba(34,197,94,.4)':'rgba(239,68,68,.4)' }};color:{{ request('status')==='active'?'#86efac':'#fca5a5' }};">
                            {{ ucfirst(request('status')) }}
                            <input type="hidden" name="status" value="{{ request('status') }}">
                            <span onclick="esfRemoveChip(this,'status')" style="cursor:pointer;opacity:.7;font-size:13px;line-height:1;">&times;</span>
                        </span>
                        @endif
                    </div>

                    {{-- Text input --}}
                    <input type="text" name="search" id="esf-input" value="{{ request('search') }}"
                           placeholder="Search by name, email, department..."
                           autocomplete="off"
                           style="flex:1;min-width:120px;background:transparent;border:none;outline:none;color:#fff;font-size:13.5px;padding:9px 8px;font-family:inherit;"
                           onfocus="esfOpen()" />

                    @if(request()->hasAny(['role','status']) || request('search'))
                    <button type="button" onclick="esfClearAll()" title="Clear filters"
                            style="background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;padding:0 6px;font-size:13px;">
                        <i class="fas fa-times"></i>
                    </button>
                    @endif
                    <button type="submit" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;padding:0 10px;font-size:14px;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>

            {{-- Count --}}
            <p id="emp-count" style="color:rgba(255,255,255,.4);font-size:13px;white-space:nowrap;margin:0;">{{ $employees->total() }} employees</p>

            {{-- Add button --}}
            @if(auth()->user()->isAdmin())
            <button @click="showModal = true" class="ikia-btn" style="flex-shrink:0;">
                <i class="fas fa-plus" style="font-size:12px;margin-right:4px;"></i>Add Employee
            </button>
            @endif
        </div>
    </div>

    {{-- Filter panel --}}
    <div id="esf-panel" style="display:none;position:fixed;background:rgba(12,17,42,.97);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:20px;z-index:99999;box-shadow:0 8px 32px rgba(0,0,0,.6);">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px 28px;">

            {{-- Role --}}
            <div>
                <p style="color:rgba(255,255,255,.35);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin:0 0 9px;">Role</p>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    @foreach(['employee'=>'Employee','admin'=>'Admin','super_admin'=>'Super Admin'] as $val=>$lbl)
                    <button type="button" class="esf-pill {{ request('role')===$val ? 'esf-on' : '' }}"
                            data-field="role" data-value="{{ $val }}"
                            onclick="esfToggle('role','{{ $val }}','{{ $lbl }}','rgba(99,102,241,.25)','rgba(99,102,241,.5)','#a5b4fc')">{{ $lbl }}</button>
                    @endforeach
                </div>
            </div>

            {{-- Status --}}
            <div>
                <p style="color:rgba(255,255,255,.35);font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin:0 0 9px;">Status</p>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    <button type="button" class="esf-pill {{ request('status')==='active' ? 'esf-on' : '' }}"
                            data-field="status" data-value="active"
                            onclick="esfToggle('status','active','Active','rgba(34,197,94,.2)','rgba(34,197,94,.4)','#86efac')">Active</button>
                    <button type="button" class="esf-pill {{ request('status')==='inactive' ? 'esf-on' : '' }}"
                            data-field="status" data-value="inactive"
                            onclick="esfToggle('status','inactive','Inactive','rgba(239,68,68,.2)','rgba(239,68,68,.4)','#fca5a5')">Inactive</button>
                </div>
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid rgba(255,255,255,.1);">
            <button type="button" onclick="esfClearAll()"
                    style="background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.15);color:rgba(255,255,255,.6);border-radius:8px;padding:7px 18px;font-size:13px;cursor:pointer;font-weight:500;">
                Reset
            </button>
            <button type="button" onclick="document.getElementById('emp-filter-form').submit()"
                    style="background:linear-gradient(135deg,#00C4D8,#1B72E8);border:none;color:#fff;border-radius:8px;padding:7px 22px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-search" style="font-size:11px;"></i> Apply
            </button>
        </div>
    </div>

    <script>
    (function(){
        var _open = false;
        var _filters = {
            role:   '{{ request('role') }}',
            status: '{{ request('status') }}',
        };
        var _chipStyle = {
            role:   'background:rgba(99,102,241,.25);border:1px solid rgba(99,102,241,.5);color:#a5b4fc;',
            status_active:   'background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.4);color:#86efac;',
            status_inactive: 'background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);color:#fca5a5;',
        };

        document.addEventListener('DOMContentLoaded', function(){
            var panel = document.getElementById('esf-panel');
            if (panel && panel.parentElement !== document.body) document.body.appendChild(panel);
            var inp = document.getElementById('esf-input');
            if (inp) inp.addEventListener('keydown', function(e){ if (e.key==='Enter'){ e.preventDefault(); document.getElementById('emp-filter-form').submit(); } });
        });

        function reposition(){
            var bar   = document.getElementById('esf-bar');
            var panel = document.getElementById('esf-panel');
            if (!bar||!panel||!_open) return;
            var r = bar.getBoundingClientRect();
            panel.style.top   = (r.bottom+8)+'px';
            panel.style.left  = r.left+'px';
            panel.style.width = r.width+'px';
        }

        window.esfOpen = function(){
            if (_open) return;
            _open = true;
            var panel = document.getElementById('esf-panel');
            panel.style.display = 'block';
            document.getElementById('esf-bar').style.borderColor = 'rgba(0,212,232,.6)';
            reposition();
        };
        window.esfClose = function(){
            if (!_open) return;
            _open = false;
            document.getElementById('esf-panel').style.display = 'none';
            document.getElementById('esf-bar').style.borderColor = 'rgba(255,255,255,.25)';
        };

        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);

        document.addEventListener('click', function(e){
            if (!_open) return;
            var bar  = document.getElementById('esf-bar');
            var panel= document.getElementById('esf-panel');
            var form = document.getElementById('emp-filter-form');
            if (!panel.contains(e.target) && !form.contains(e.target)) esfClose();
        });

        window.esfToggle = function(field, value, label, bg, border, color){
            if (_filters[field] === value){ esfClearField(field); return; }
            _filters[field] = value;
            document.querySelectorAll('.esf-chip[data-field="'+field+'"]').forEach(function(c){ c.remove(); });
            var styleKey = field === 'status' ? field+'_'+value : field;
            var s = _chipStyle[styleKey] || ('background:rgba(99,102,241,.25);border:1px solid rgba(99,102,241,.5);color:#a5b4fc;');
            var chip = document.createElement('span');
            chip.className = 'esf-chip';
            chip.dataset.field = field;
            chip.style.cssText = s;
            chip.innerHTML = label.replace(/</g,'&lt;') +
                '<input type="hidden" name="'+field+'" value="'+value.replace(/"/g,'&quot;')+'">' +
                '<span onclick="esfRemoveChip(this,\''+field+'\')" style="cursor:pointer;opacity:.7;font-size:13px;line-height:1;">&times;</span>';
            document.getElementById('esf-chips').appendChild(chip);
            document.querySelectorAll('.esf-pill[data-field="'+field+'"]').forEach(function(b){
                b.classList.toggle('esf-on', b.dataset.value===value);
            });
        };

        window.esfRemoveChip = function(el, field){
            el.closest('.esf-chip').remove();
            esfClearField(field);
        };

        window.esfClearField = function(field){
            _filters[field] = '';
            document.querySelectorAll('.esf-pill[data-field="'+field+'"]').forEach(function(b){ b.classList.remove('esf-on'); });
        };

        window.esfClearAll = function(){
            ['role','status'].forEach(esfClearField);
            document.querySelectorAll('.esf-chip').forEach(function(c){ c.remove(); });
            var inp = document.getElementById('esf-input');
            if (inp) inp.value = '';
            document.getElementById('emp-filter-form').submit();
        };
    })();
    </script>

    @if($employees->isEmpty())
    <div style="padding:60px 20px;text-align:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:14px;">
        <i class="fas fa-search" style="font-size:32px;color:rgba(255,255,255,.15);display:block;margin-bottom:12px;"></i>
        <p style="color:rgba(255,255,255,.35);font-size:13px;margin:0 0 10px;">No employees found</p>
        <a href="{{ route('employees.index') }}" style="font-size:13px;color:#00D4E8;text-decoration:none;">Clear filters</a>
    </div>
    @endif

    {{-- Employees Grid --}}
    <div id="emp-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @foreach($employees as $emp)
        @include('employees._emp_card')
        @endforeach
    </div>

    <div id="emp-sentinel" style="height:1px;margin-top:8px;"></div>
    <div id="emp-scroll-status" class="text-center py-4 text-sm text-gray-400" style="display:none;">All employees loaded</div>

    <script>
    (function(){
        var _page = {{ $employees->currentPage() }};
        var _hasMore = {{ $employees->hasMorePages() ? 'true' : 'false' }};
        var _loading = false;
        var _total = {{ $employees->total() }};

        var grid     = document.getElementById('emp-grid');
        var sentinel = document.getElementById('emp-sentinel');
        var statusEl = document.getElementById('emp-scroll-status');

        function getParams() {
            var p = new URLSearchParams(window.location.search);
            p.delete('page');
            return p;
        }

        async function loadMore() {
            if (!_hasMore || _loading) return;
            _loading = true;
            sentinel.innerHTML = '<div style="text-align:center;padding:16px;"><div style="display:inline-block;width:20px;height:20px;border:2px solid #e5e7eb;border-top-color:#6366f1;border-radius:50%;animation:spin .7s linear infinite;"></div></div>';

            var params = getParams();
            params.set('page', _page + 1);

            try {
                var r = await fetch(location.pathname + '?' + params.toString(), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                var d = await r.json();
                grid.insertAdjacentHTML('beforeend', d.html);
                _page++;
                _hasMore = d.hasMore;
                sentinel.innerHTML = '';
                if (!_hasMore) {
                    sentinel.style.display = 'none';
                    statusEl.style.display = 'block';
                    statusEl.textContent = 'All ' + _total + ' employees loaded';
                }
            } catch(e) {
                sentinel.innerHTML = '<div style="text-align:center;padding:16px;color:#ef4444;font-size:12px;">Load failed. <button onclick="empLoadMore()" style="color:#6366f1;background:none;border:none;cursor:pointer;font-size:12px;">Retry</button></div>';
            }
            _loading = false;
        }

        window.empLoadMore = loadMore;

        new IntersectionObserver(function(entries){
            if (entries[0].isIntersecting) loadMore();
        }, { rootMargin: '300px' }).observe(sentinel);
    })();

    window.empEditOpen = function(emp) {
        var modal = document.getElementById('emp-edit-modal');
        var form  = document.getElementById('emp-edit-form');
        form.action = '{{ url('/employees') }}/' + emp.id;
        form.querySelector('[name=name]').value       = emp.name  || '';
        form.querySelector('[name=email]').value      = emp.email || '';
        form.querySelector('[name=phone]').value      = emp.phone || '';
        form.querySelector('[name=department]').value = emp.department || '';
        form.querySelector('[name=position]').value   = emp.position || '';
        var roleSelect = form.querySelector('[name=role]');
        if (roleSelect) roleSelect.value = emp.role || 'employee';
        modal.style.display = 'flex';
    };

    window.empEditClose = function() {
        document.getElementById('emp-edit-modal').style.display = 'none';
    };
    </script>

    {{-- Add Employee Modal --}}
    <div x-show="showModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="showModal = false">
        <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
            <h2 class="text-lg font-semibold mb-5">Add New Employee</h2>
            <form action="{{ route('employees.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="name" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                        <input type="password" name="password" required minlength="8" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select name="role" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                            <option value="employee">Employee</option>
                            <option value="admin">Admin</option>
                            @if(auth()->user()->isSuperAdmin())
                            <option value="super_admin">Super Admin</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="phone" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" @click="showModal = false" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Add Employee</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Employee Modal (plain JS, works for dynamically loaded cards) --}}
    <div id="emp-edit-modal" class="fixed inset-0 z-50 items-center justify-center p-4" style="display:none;background:rgba(0,0,0,.5);" onclick="if(event.target===this)empEditClose()">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" onclick="event.stopPropagation()">
            <h2 class="text-lg font-semibold mb-5">Edit Employee</h2>
            <form id="emp-edit-form" method="POST" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="name" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" required class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="password" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm" placeholder="Leave blank to keep">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select name="role" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                            <option value="employee">Employee</option>
                            <option value="admin">Admin</option>
                            @if(auth()->user()->isSuperAdmin())
                            <option value="super_admin">Super Admin</option>
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="phone" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                        <input type="text" name="position" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none text-sm">
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="empEditClose()" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" class="flex-1 px-4 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
