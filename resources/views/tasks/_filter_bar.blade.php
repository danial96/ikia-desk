{{--
    Shared task filter bar — include in both list and kanban views.
    Expects: $formAction (string URL), $activeView ('list'|'kanban'), $projects, $employees
--}}
<div style="background:rgba(255,255,255,.12);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.18);border-radius:14px;padding:10px 16px;margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:10px;">

        {{-- View toggle --}}
        <div style="display:flex;background:rgba(0,0,0,.28);border-radius:9px;padding:3px;gap:2px;flex-shrink:0;">
            <a href="{{ route('tasks.index', ['view'=>'list']) }}"
               style="padding:6px 16px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;
                      {{ $activeView==='list' ? 'background:#00D4E8;color:#040820;' : 'color:rgba(255,255,255,.75);' }}">
                <i class="fas fa-list" style="font-size:11px;margin-right:5px;"></i>List
            </a>
            <a href="{{ route('tasks.kanban') }}"
               style="padding:6px 16px;border-radius:7px;font-size:13px;font-weight:600;text-decoration:none;white-space:nowrap;
                      {{ $activeView==='kanban' ? 'background:#00D4E8;color:#040820;' : 'color:rgba(255,255,255,.75);' }}">
                <i class="fas fa-th" style="font-size:11px;margin-right:5px;"></i>Kanban
            </a>
        </div>

        {{-- Search + filter form --}}
        <form id="task-search-form" action="{{ $formAction }}" method="GET" style="flex:1;">

            {{-- Search bar row --}}
            <div style="display:flex;align-items:center;background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.25);border-radius:10px;"
                 id="tsf-bar">

                {{-- Active filter chips --}}
                <div id="tsf-chips" style="display:flex;align-items:center;gap:5px;padding:0 8px;flex-wrap:nowrap;overflow:hidden;max-width:460px;">

                    @if(request('status'))
                    <span class="tsf-chip" data-field="status" style="display:inline-flex;align-items:center;gap:4px;background:rgba(14,165,233,.25);border:1px solid rgba(14,165,233,.5);border-radius:6px;padding:3px 8px;font-size:11.5px;color:#7dd3fc;white-space:nowrap;">
                        {{ str_replace('_',' ',ucfirst(request('status'))) }}
                        <input type="hidden" name="status" value="{{ request('status') }}">
                        <span onclick="tsfRemoveChip(this,'status')" style="cursor:pointer;opacity:.7;font-size:12px;line-height:1;">&times;</span>
                    </span>
                    @endif

                    @if(request('priority'))
                    <span class="tsf-chip" data-field="priority" style="display:inline-flex;align-items:center;gap:4px;background:rgba(99,102,241,.25);border:1px solid rgba(99,102,241,.5);border-radius:6px;padding:3px 8px;font-size:11.5px;color:#a5b4fc;white-space:nowrap;">
                        Priority: {{ ucfirst(request('priority')) }}
                        <input type="hidden" name="priority" value="{{ request('priority') }}">
                        <span onclick="tsfRemoveChip(this,'priority')" style="cursor:pointer;opacity:.7;font-size:12px;line-height:1;">&times;</span>
                    </span>
                    @endif

                    @if(request('project_id'))
                    @php $activeProj = $projects->firstWhere('id', request('project_id')); @endphp
                    <span class="tsf-chip" data-field="project_id" style="display:inline-flex;align-items:center;gap:4px;background:rgba(16,185,129,.2);border:1px solid rgba(16,185,129,.4);border-radius:6px;padding:3px 8px;font-size:11.5px;color:#6ee7b7;white-space:nowrap;">
                        {{ $activeProj?->name ?? 'Project' }}
                        <input type="hidden" name="project_id" value="{{ request('project_id') }}">
                        <span onclick="tsfRemoveChip(this,'project_id')" style="cursor:pointer;opacity:.7;font-size:12px;line-height:1;">&times;</span>
                    </span>
                    @endif

                    @if(request('assignee_id'))
                    @php $activeAssignee = $employees->firstWhere('id', request('assignee_id')); @endphp
                    <span class="tsf-chip" data-field="assignee_id" style="display:inline-flex;align-items:center;gap:4px;background:rgba(245,158,11,.2);border:1px solid rgba(245,158,11,.4);border-radius:6px;padding:3px 8px;font-size:11.5px;color:#fcd34d;white-space:nowrap;">
                        {{ $activeAssignee?->name ?? 'Assignee' }}
                        <input type="hidden" name="assignee_id" value="{{ request('assignee_id') }}">
                        <span onclick="tsfRemoveChip(this,'assignee_id')" style="cursor:pointer;opacity:.7;font-size:12px;line-height:1;">&times;</span>
                    </span>
                    @endif

                </div>

                {{-- Text input --}}
                <input type="text" name="search" id="tsf-input" value="{{ request('search') }}"
                       placeholder="Search tasks..."
                       autocomplete="off"
                       style="flex:1;min-width:100px;background:transparent;border:none;outline:none;color:#fff;font-size:13.5px;padding:9px 8px;font-family:inherit;"
                       onfocus="tsfOpen()" />

                @if(request()->hasAny(['status','priority','project_id','assignee_id']) || request('search'))
                <button type="button" onclick="tsfClearAll()" style="background:none;border:none;color:rgba(255,255,255,.45);cursor:pointer;padding:0 6px;font-size:13px;" title="Clear all">
                    <i class="fas fa-times"></i>
                </button>
                @endif

                <button type="submit" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;padding:0 10px;font-size:14px;">
                    <i class="fas fa-search"></i>
                </button>
            </div>

        </form>

        {{-- New Task button --}}
        @if(auth()->user()->isAdmin() || !empty(auth()->user()->permissions['create_tasks']))
        <button onclick="openTaskModal()" class="ikia-btn" style="flex-shrink:0;">
            <i class="fas fa-plus" style="font-size:12px;margin-right:4px;"></i>New Task
        </button>
        @endif

    </div>
</div>

{{-- ── Filter panel portal (rendered in <body> to escape stacking contexts) ── --}}
<div id="tsf-panel" style="display:none;position:fixed;background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:20px;z-index:99999;box-shadow:0 8px 32px rgba(0,0,0,.18);">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px 24px;">

        {{-- Status --}}
        <div>
            <p style="color:#94a3b8;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin:0 0 8px;">Status</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach(['new'=>'New','in_progress'=>'In Progress','paused'=>'Paused','completed'=>'Completed'] as $val=>$lbl)
                <button type="button" data-field="status" data-value="{{ $val }}"
                        onclick="tsfToggleFilter('status','{{ $val }}','{{ $lbl }}')"
                        class="{{ request('status')===$val ? 'tsf-active' : '' }}"
                        style="padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #cbd5e1;background:#f1f5f9;color:#475569;transition:all .15s;">
                    {{ $lbl }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Priority --}}
        <div>
            <p style="color:#94a3b8;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin:0 0 8px;">Priority</p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                @foreach(['low'=>'Low','medium'=>'Medium','high'=>'High','urgent'=>'Urgent'] as $val=>$lbl)
                <button type="button" data-field="priority" data-value="{{ $val }}"
                        onclick="tsfToggleFilter('priority','{{ $val }}','Priority: {{ $lbl }}')"
                        class="{{ request('priority')===$val ? 'tsf-active' : '' }}"
                        style="padding:4px 14px;border-radius:20px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid #cbd5e1;background:#f1f5f9;color:#475569;transition:all .15s;">
                    {{ $lbl }}
                </button>
                @endforeach
            </div>
        </div>

        {{-- Project --}}
        <div>
            <p style="color:#94a3b8;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin:0 0 8px;">Project</p>
            <select id="tsf-project-sel"
                    onchange="tsfToggleFilter('project_id', this.value, this.options[this.selectedIndex].text)"
                    style="width:100%;background:#f8fafc;border:1.5px solid #cbd5e1;color:#334155;border-radius:8px;padding:7px 10px;font-size:13px;outline:none;cursor:pointer;">
                <option value="">All Projects</option>
                @foreach($projects as $proj)
                <option value="{{ $proj->id }}" {{ request('project_id')==$proj->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Assignee --}}
        <div>
            <p style="color:#94a3b8;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;margin:0 0 8px;">Assignee</p>
            <select id="tsf-assignee-sel"
                    onchange="tsfToggleFilter('assignee_id', this.value, this.options[this.selectedIndex].text)"
                    style="width:100%;background:#f8fafc;border:1.5px solid #cbd5e1;color:#334155;border-radius:8px;padding:7px 10px;font-size:13px;outline:none;cursor:pointer;">
                <option value="">All Assignees</option>
                @foreach($employees as $emp)
                <option value="{{ $emp->id }}" {{ request('assignee_id')==$emp->id ? 'selected' : '' }}>{{ $emp->name }}</option>
                @endforeach
            </select>
        </div>

    </div>

    {{-- Panel footer --}}
    <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;margin-top:18px;padding-top:14px;border-top:1px solid #e2e8f0;">
        <button type="button" onclick="tsfClearAll()"
                style="background:#fff;border:1.5px solid #cbd5e1;color:#64748b;border-radius:8px;padding:7px 18px;font-size:13px;cursor:pointer;font-weight:500;">
            Reset
        </button>
        <button type="button" onclick="document.getElementById('task-search-form').submit()"
                style="background:linear-gradient(135deg,#00C4D8,#1B72E8);border:none;color:#fff;border-radius:8px;padding:7px 22px;font-size:13px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-search" style="font-size:11px;"></i> Search
        </button>
    </div>
</div>

<style>
.tsf-active { background:#e0f7fa !important; border-color:#00C4D8 !important; color:#0e7490 !important; }
#tsf-input::placeholder { color:rgba(255,255,255,.32); }
</style>
<script>
(function() {
    let _open = false;

    // Move panel to <body> so it escapes all stacking contexts
    document.addEventListener('DOMContentLoaded', function() {
        const panel = document.getElementById('tsf-panel');
        if (panel && panel.parentElement !== document.body) {
            document.body.appendChild(panel);
        }

        const inp = document.getElementById('tsf-input');
        if (inp) {
            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') { e.preventDefault(); document.getElementById('task-search-form').submit(); }
            });
        }
    });

    function tsfReposition() {
        const bar  = document.getElementById('tsf-bar');
        const panel = document.getElementById('tsf-panel');
        if (!bar || !panel || !_open) return;
        const r = bar.getBoundingClientRect();
        panel.style.top   = (r.bottom + 8) + 'px';
        panel.style.left  = r.left + 'px';
        panel.style.width = r.width + 'px';
    }

    window.tsfOpen = function() {
        if (_open) return;
        _open = true;
        const panel = document.getElementById('tsf-panel');
        panel.style.display = 'block';
        document.getElementById('tsf-bar').style.borderColor = 'rgba(0,212,232,.6)';
        tsfReposition();
    };

    window.tsfClose = function() {
        if (!_open) return;
        _open = false;
        document.getElementById('tsf-panel').style.display = 'none';
        document.getElementById('tsf-bar').style.borderColor = 'rgba(255,255,255,.25)';
    };

    window.addEventListener('scroll', tsfReposition, true);
    window.addEventListener('resize', tsfReposition);

    document.addEventListener('click', function(e) {
        if (!_open) return;
        const bar   = document.getElementById('tsf-bar');
        const panel = document.getElementById('tsf-panel');
        const form  = document.getElementById('task-search-form');
        if (!panel.contains(e.target) && !form.contains(e.target)) tsfClose();
    });

    const _filters = {
        status:      '{{ request('status') }}',
        priority:    '{{ request('priority') }}',
        project_id:  '{{ request('project_id') }}',
        assignee_id: '{{ request('assignee_id') }}',
    };

    const _chipColors = {
        status:      'background:rgba(14,165,233,.25);border:1px solid rgba(14,165,233,.5);color:#7dd3fc;',
        priority:    'background:rgba(99,102,241,.25);border:1px solid rgba(99,102,241,.5);color:#a5b4fc;',
        project_id:  'background:rgba(16,185,129,.2);border:1px solid rgba(16,185,129,.4);color:#6ee7b7;',
        assignee_id: 'background:rgba(245,158,11,.2);border:1px solid rgba(245,158,11,.4);color:#fcd34d;',
    };

    window.tsfToggleFilter = function(field, value, label) {
        if (!value) { tsfClearField(field); return; }
        if (_filters[field] === value) { tsfClearField(field); return; }
        _filters[field] = value;

        document.querySelectorAll('.tsf-chip[data-field="'+field+'"]').forEach(c => c.remove());
        const chip = document.createElement('span');
        chip.className = 'tsf-chip';
        chip.dataset.field = field;
        chip.style.cssText = 'display:inline-flex;align-items:center;gap:4px;border-radius:6px;padding:3px 8px;font-size:11.5px;white-space:nowrap;' + (_chipColors[field]||'');
        chip.innerHTML = escH(label) +
            '<input type="hidden" name="'+field+'" value="'+escH(value)+'">'+
            '<span onclick="tsfRemoveChip(this,\''+field+'\')" style="cursor:pointer;opacity:.7;font-size:12px;line-height:1;">&times;</span>';
        document.getElementById('tsf-chips').appendChild(chip);

        document.querySelectorAll('[data-field="'+field+'"]').forEach(b => {
            b.classList.toggle('tsf-active', b.dataset && b.dataset.value === value);
        });
    };

    window.tsfRemoveChip = function(el, field) {
        el.closest('.tsf-chip').remove();
        tsfClearField(field);
    };

    window.tsfClearField = function(field) {
        _filters[field] = '';
        document.querySelectorAll('[data-field="'+field+'"]').forEach(b => b.classList && b.classList.remove('tsf-active'));
        const sel = document.getElementById('tsf-' + (field === 'project_id' ? 'project' : field === 'assignee_id' ? 'assignee' : field) + '-sel');
        if (sel) sel.value = '';
    };

    window.tsfClearAll = function() {
        ['status','priority','project_id','assignee_id'].forEach(f => tsfClearField(f));
        document.querySelectorAll('.tsf-chip').forEach(c => c.remove());
        const inp = document.getElementById('tsf-input');
        if (inp) inp.value = '';
        document.getElementById('task-search-form').submit();
    };

    function escH(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
})();
</script>
