{{--
  Unified full-screen task detail panel — Light Theme
  Include once per page: @include('tasks._task_panel')
  Open with JS: tpOpen('local', id)  or  tpOpen('b24', id)
--}}

<style>
@keyframes tpSlideUp   { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
@keyframes tpSlideIn   { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
@keyframes tpPulse     { 0%,100%{opacity:.35} 50%{opacity:.65} }
#tp-overlay { transition:opacity .25s ease; }
#tp-panel   { transition:transform .28s cubic-bezier(.4,0,.2,1), opacity .25s ease; }
#tp-panel.tp-entering { animation:tpSlideIn .28s cubic-bezier(.4,0,.2,1) both; }
.tp-msg-bubble { animation:tpSlideUp .16s ease; }
.tp-skel { animation:tpPulse 1.4s ease-in-out infinite; background:rgba(0,0,0,.07); border-radius:6px; }
.tp-pill {
    display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:20px;
    font-size:11.5px;font-weight:600;cursor:pointer;border:1.5px solid transparent;
    transition:all .15s ease;user-select:none;
}
.tp-pill:hover { filter:brightness(.94); }
.tp-pill.active { box-shadow:0 0 0 2px rgba(0,0,0,.18); }
.tp-member-chip {
    display:inline-flex;align-items:center;gap:5px;background:#f1f5f9;
    border:1px solid #e2e8f0;border-radius:20px;padding:3px 8px 3px 3px;
    font-size:11.5px;color:#374151;
}
.tp-member-chip .rm {
    margin-left:3px;color:#9ca3af;cursor:pointer;font-size:10px;transition:color .12s;
}
.tp-member-chip .rm:hover { color:#ef4444; }
.tp-people-dropdown {
    position:absolute;z-index:10;background:#fff;border:1px solid #e2e8f0;
    border-radius:10px;padding:6px;min-width:220px;max-height:220px;overflow-y:auto;
    box-shadow:0 8px 30px rgba(0,0,0,.12);
}
.tp-people-dropdown input {
    width:100%;background:#f8fafc;border:1px solid #e2e8f0;
    border-radius:7px;padding:6px 10px;color:#111827;font-size:12px;outline:none;margin-bottom:5px;
    box-sizing:border-box;
}
.tp-people-dropdown .opt {
    display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:7px;cursor:pointer;
    transition:background .12s;font-size:12.5px;color:#374151;
}
.tp-people-dropdown .opt:hover { background:#f1f5f9; }
.tp-people-dropdown .opt.selected { color:#0ea5e9; }
</style>

{{-- Full-screen overlay --}}
<div id="tp-overlay"
     style="display:none;opacity:0;position:fixed;inset:0;z-index:3000;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);overflow:hidden;">

    {{-- Close button — floats in the left gap (outside panel) --}}
    <button onclick="tpClose()"
            style="position:absolute;left:8px;top:8px;width:42px;height:42px;border-radius:50%;background:#0ea5e9;border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(14,165,233,.4);transition:background .15s;z-index:10;"
            onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
        <i class="fas fa-times" style="font-size:14px;"></i>
    </button>

    {{-- Two-column panel --}}
    <div id="tp-panel"
         style="position:absolute;top:58px;left:58px;right:0;bottom:0;display:flex;background:#fff;border-radius:12px 0 0 0;overflow:hidden;box-shadow:0 -4px 40px rgba(0,0,0,.15);">

        {{-- ===== LEFT COLUMN ===== --}}
        <div id="tp-left"
             style="flex:0 0 660px;width:660px;display:flex;flex-direction:column;border-right:1px solid #e9ecef;overflow:hidden;background:#eef2f4;">

            {{-- Left header --}}
            <div id="tp-left-header"
                 style="flex-shrink:0;padding:16px 24px;border-bottom:1px solid #dde3e7;display:flex;align-items:flex-start;gap:12px;background:#eef2f4;">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                        <div id="tp-source-badge"></div>
                        <span id="tp-task-id" style="font-size:10px;color:#adb5bd;font-weight:600;"></span>
                    </div>
                    <h2 id="tp-title" style="color:#111827;font-size:16px;font-weight:700;margin:0;line-height:1.45;"></h2>
                </div>
                <div id="tp-header-actions" style="flex-shrink:0;display:flex;gap:6px;margin-top:2px;"></div>
            </div>

            {{-- Left scrollable body --}}
            <div id="tp-left-body"
                 style="flex:1;overflow-y:auto;padding:0 24px;scrollbar-width:thin;scrollbar-color:#dee2e6 transparent;background:#eef2f4;">
            </div>

            {{-- Bottom action bar --}}
            <div id="tp-left-footer"
                 style="flex-shrink:0;padding:10px 20px;border-top:1px solid #dde3e7;background:#eef2f4;display:flex;align-items:center;justify-content:space-between;gap:10px;">
            </div>
        </div>

        {{-- ===== RIGHT COLUMN — comments ===== --}}
        <div id="tp-right"
             style="flex:1;min-width:0;display:flex;flex-direction:column;overflow:hidden;background:#f8fafc;position:relative;">

            {{-- Drag-and-drop overlay (scoped to comments area) --}}
            <div id="tp-drop-overlay"
                 style="display:none;position:absolute;inset:0;z-index:999;background:rgba(248,250,252,.82);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:3px dashed #0ea5e9;pointer-events:none;flex-direction:column;align-items:center;justify-content:center;gap:20px;">
                <div style="width:90px;height:90px;border-radius:50%;background:rgba(14,165,233,.15);border:2px solid rgba(14,165,233,.4);display:flex;align-items:center;justify-content:center;">
                    <i class="fas fa-cloud-upload-alt" style="font-size:36px;color:#0ea5e9;"></i>
                </div>
                <div style="text-align:center;">
                    <p style="font-size:20px;font-weight:800;color:#0f172a;margin:0 0 8px;letter-spacing:.3px;">Drop files to attach</p>
                    <p style="font-size:13px;color:#475569;margin:0;">Files will be added to your comment</p>
                </div>
            </div>

            {{-- Right header --}}
            <div id="tp-right-header"
                 style="flex-shrink:0;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,0.4);display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,0.55);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);">
                <div style="display:flex;align-items:center;gap:7px;">
                    <i class="fas fa-comment-dots" style="color:#0ea5e9;font-size:12px;"></i>
                    <span id="tp-chat-title" style="color:#374151;font-size:13px;font-weight:700;">Comments</span>
                </div>
                <span id="tp-chat-count" style="font-size:11px;color:#9ca3af;font-weight:600;"></span>
            </div>

            {{-- Messages --}}
            <div id="tp-messages"
                 style="flex:1;overflow-y:auto;padding:14px 18px;display:flex;flex-direction:column;gap:8px;scrollbar-width:thin;scrollbar-color:#dee2e6 transparent;background-image:url('{{ asset('pattern-chat.svg') }}');background-size:cover;background-position:center;background-repeat:no-repeat;background-attachment:local;">
            </div>

            {{-- Comment footer --}}
            <div id="tp-comment-footer" style="flex-shrink:0;padding:12px 18px;border-top:1px solid rgba(255,255,255,0.4);background:rgba(255,255,255,0.55);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);">
            </div>
        </div>
    </div>
</div>

{{-- ── Edit task modal (inside overlay z-space) ── --}}
<div id="tp-edit-modal"
     onclick="if(event.target===this)tpEditClose()"
     style="display:none;position:fixed;inset:0;z-index:4500;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;">
    <div onclick="event.stopPropagation()"
         style="background:#fff;border:1.5px solid #e2e8f0;border-radius:18px;width:100%;max-width:520px;padding:28px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.18);">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
            <h2 style="font-size:15px;font-weight:700;color:#0f172a;margin:0;"><i class="fas fa-pen" style="color:#0ea5e9;margin-right:8px;font-size:13px;"></i>Edit Task</h2>
            <button onclick="tpEditClose()" style="background:#f1f5f9;border:none;border-radius:8px;color:#64748b;cursor:pointer;width:30px;height:30px;display:flex;align-items:center;justify-content:center;transition:background .12s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                <i class="fas fa-times" style="font-size:12px;"></i>
            </button>
        </div>
        <style>
        .tpe-f { width:100%;background:#f8fafc;border:1.5px solid #e2e8f0;color:#1e293b;border-radius:9px;padding:9px 12px;font-size:13px;outline:none;box-sizing:border-box;transition:border-color .2s;font-family:inherit; }
        .tpe-f:focus { border-color:#0ea5e9;background:#fff; }
        .tpe-l { display:block;font-size:10.5px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px; }
        </style>
        <div style="margin-bottom:14px;">
            <label class="tpe-l">Title *</label>
            <input id="tpe-title" type="text" class="tpe-f" required>
        </div>
        <div style="margin-bottom:14px;">
            <label class="tpe-l">Description</label>
            <textarea id="tpe-desc" rows="3" class="tpe-f" style="resize:vertical;"></textarea>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div>
                <label class="tpe-l">Status</label>
                <select id="tpe-status" class="tpe-f">
                    <option value="new">New</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div>
                <label class="tpe-l">Priority</label>
                <select id="tpe-priority" class="tpe-f">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
            <div>
                <label class="tpe-l">Assign To</label>
                <select id="tpe-assigned" class="tpe-f"></select>
            </div>
            <div>
                <label class="tpe-l">Deadline</label>
                <input id="tpe-deadline" type="datetime-local" class="tpe-f">
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:4px;">
            <button type="button" onclick="tpEditClose()" style="flex:1;padding:10px;border:1.5px solid #e2e8f0;background:#f8fafc;color:#64748b;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;transition:background .12s;" onmouseover="this.style.background='#e9ecef'" onmouseout="this.style.background='#f8fafc'">Cancel</button>
            <button type="button" id="tpe-save-btn" onclick="tpEditSubmit()" style="flex:1;padding:10px;border:none;background:#0ea5e9;color:#fff;border-radius:10px;font-size:13px;font-weight:700;cursor:pointer;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'"><i class="fas fa-save" style="margin-right:6px;font-size:11px;"></i>Save Changes</button>
        </div>
    </div>
</div>

{{-- ── Deadline calendar popup (shared, fixed-positioned) ── --}}
<div id="tp-cal-popup"
     onclick="event.stopPropagation()"
     style="display:none;position:fixed;z-index:5000;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.14);overflow:hidden;">
    <div id="tp-cal-date-view" style="display:flex;">
        <div style="padding:16px 14px;min-width:268px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 2px;">
                <button type="button" onclick="tpCalPrev()" style="width:28px;height:28px;border:none;background:none;cursor:pointer;color:#6b7280;border-radius:6px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background=''"><i class="fas fa-chevron-left" style="font-size:11px;"></i></button>
                <span id="tp-cal-title" style="font-size:14px;font-weight:700;color:#374151;"></span>
                <button type="button" onclick="tpCalNext()" style="width:28px;height:28px;border:none;background:none;cursor:pointer;color:#6b7280;border-radius:6px;display:flex;align-items:center;justify-content:center;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background=''"><i class="fas fa-chevron-right" style="font-size:11px;"></i></button>
            </div>
            <div style="display:grid;grid-template-columns:repeat(7,36px);text-align:center;margin-bottom:2px;">
                <div style="font-size:11px;font-weight:600;color:#ef4444;padding:3px 0;">Sun</div>
                <div style="font-size:11px;font-weight:600;color:#9ca3af;padding:3px 0;">Mon</div>
                <div style="font-size:11px;font-weight:600;color:#9ca3af;padding:3px 0;">Tue</div>
                <div style="font-size:11px;font-weight:600;color:#9ca3af;padding:3px 0;">Wed</div>
                <div style="font-size:11px;font-weight:600;color:#9ca3af;padding:3px 0;">Thu</div>
                <div style="font-size:11px;font-weight:600;color:#9ca3af;padding:3px 0;">Fri</div>
                <div style="font-size:11px;font-weight:600;color:#ef4444;padding:3px 0;">Sat</div>
            </div>
            <div id="tp-cal-grid" style="display:grid;grid-template-columns:repeat(7,36px);"></div>
            <div id="tp-cal-time-row" style="display:none;margin-top:10px;padding-top:8px;border-top:1px solid #f1f3f5;">
                <div onclick="tpCalShowTime()" style="display:inline-flex;align-items:center;gap:7px;padding:5px 10px;border:1.5px solid #e2e8f0;border-radius:7px;cursor:pointer;transition:all .12s;" onmouseover="this.style.borderColor='#0ea5e9';this.style.background='#f0f9ff'" onmouseout="this.style.borderColor='#e2e8f0';this.style.background=''">
                    <i class="fas fa-clock" style="font-size:12px;color:#0ea5e9;"></i>
                    <span id="tp-cal-time-label" style="font-size:13px;color:#374151;font-weight:500;"></span>
                </div>
            </div>
        </div>
        <div style="width:1px;background:#f1f3f5;align-self:stretch;flex-shrink:0;"></div>
        <div id="tp-cal-quick" style="padding:10px 8px;min-width:192px;display:flex;flex-direction:column;gap:5px;overflow-y:auto;"></div>
    </div>
    <div id="tp-cal-time-view" style="display:none;padding:16px 20px;">
        <div style="display:flex;gap:20px;">
            <div>
                <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.06em;margin-bottom:5px;">AM</div>
                <div id="tp-cal-hours-am" style="display:grid;grid-template-columns:repeat(4,38px);gap:3px;"></div>
                <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.06em;margin:8px 0 5px;">PM</div>
                <div id="tp-cal-hours-pm" style="display:grid;grid-template-columns:repeat(4,38px);gap:3px;"></div>
            </div>
            <div style="width:1px;background:#f1f3f5;align-self:stretch;flex-shrink:0;"></div>
            <div id="tp-cal-minutes" style="display:grid;grid-template-columns:repeat(2,46px);gap:3px;align-content:start;"></div>
        </div>
    </div>
</div>

<script>
(function(){
const TP_B24_URL        = '{{ url("/api/bitrix-task") }}';
const TP_LOCAL_URL      = '{{ url("/api/local-task") }}';
const TP_TASKS_URL      = '{{ url("/tasks") }}';
const TP_CSRF           = '{{ csrf_token() }}';
const ME_LOCAL_ID       = {{ auth()->id() }};
const ME_B24_ID         = {{ (int) env('BITRIX_USER_ID', 155) }};

/* ─── helpers ──────────────────────────────────────────── */
const $  = id => document.getElementById(id);
const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

const uAvatar = (u, size=34) => {
    if (!u) return '';
    const initials = (u.name||'?').charAt(0).toUpperCase();
    const src = u.icon || u.avatar_url || u.avatar || null;
    const colors = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
    const bg = colors[(u.name||'').charCodeAt(0) % colors.length];
    return src
        ? `<img src="${src}" style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid #e9ecef;" title="${esc(u.name)}" alt="">`
        : `<div style="width:${size}px;height:${size}px;border-radius:50%;background:${bg};display:flex;align-items:center;justify-content:center;font-size:${Math.round(size*.38)}px;font-weight:700;color:#fff;flex-shrink:0;border:2px solid #e9ecef;" title="${esc(u.name)}">${initials}</div>`;
};

const diskFileUrl = id => `/api/disk-file/${id}`;

/* ── description text only (strips [img]/[file] tags) ── */
const parseDescText = raw => {
    const stripped = (raw||'')
        .replace(/\[img\][\s\S]*?\[\/img\]/g,'')
        .replace(/\[file name="[^"]*"\][\s\S]*?\[\/file\]/g,'')
        .replace(/\[disk\s+file\s+id=n(\d+)[^\]]*\]/gi,'')
        .trim();
    if (!stripped) return '';
    return stripped
        // Decode HTML entities first (Bitrix imports store &amp; &lt; etc.)
        .replace(/&amp;/gi,'&').replace(/&lt;/gi,'<').replace(/&gt;/gi,'>').replace(/&nbsp;/gi,' ').replace(/&quot;/gi,'"')
        // Convert <br> HTML tags to newlines
        .replace(/<br\s*\/?>/gi,'\n')
        // Strip any remaining HTML tags
        .replace(/<[^>]+>/g,'')
        // Now safely escape for HTML output
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        // BBcode
        .replace(/\[USER=\d+\]([^\[]*)\[\/USER\]/g,'<span style="color:#0ea5e9;font-weight:600;">$1</span>')
        .replace(/\[TIMESTAMP=(\d+)\s+FORMAT=[^\]]*\]/g,(_,ts)=>{
            const d=new Date(parseInt(ts)*1000);
            return '<span style="color:#f59e0b;">'+d.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})+'</span>';
        })
        .replace(/\[url=([^\]]+)\]([^\[]*)\[\/url\]/gi,'<a href="$1" target="_blank" rel="noopener" style="color:#0ea5e9;text-decoration:underline;">$2</a>')
        .replace(/\[url\](.*?)\[\/url\]/gi,'<a href="$1" target="_blank" rel="noopener" style="color:#0ea5e9;text-decoration:underline;">$1</a>')
        .replace(/\[\/?\w[^\]]*\]/g,'')
        // Auto-link plain URLs
        .replace(/(?<!href=")(https?:\/\/[^\s<>"'[\]]+)/g,'<a href="$1" target="_blank" rel="noopener noreferrer" style="color:#0ea5e9;text-decoration:underline;word-break:break-all;">$1</a>')
        .replace(/\n/g,'<br>');
};

/* ── attachment cards from [img]/[file]/[disk file] tags ── */
const parseDescAttachments = raw => {
    const atts = [];
    const re = /\[disk\s+file\s+id=n(\d+)[^\]]*\]|\[img\]([\s\S]*?)\[\/img\]|\[file name="([^"]*)"\]([\s\S]*?)\[\/file\]/gi;
    let m;
    while ((m = re.exec(raw||'')) !== null) {
        if (m[1] !== undefined)      atts.push({ type:'img',  url: diskFileUrl(m[1]), isDisk:true });
        else if (m[2] !== undefined) atts.push({ type:'img',  url: m[2].trim() });
        else                         atts.push({ type:'file', name: m[3], url: m[4].trim() });
    }
    if (!atts.length) return '';

    const fileColor = ext => {
        const map={doc:'#2b579a',docx:'#2b579a',xls:'#217346',xlsx:'#217346',pdf:'#e53e3e',zip:'#d97706',rar:'#d97706',txt:'#6b7280',csv:'#6b7280',ppt:'#d24726',pptx:'#d24726'};
        return {color: map[ext]||'#6b7280', label:(ext||'file').toUpperCase().slice(0,5)};
    };

    const cards = atts.map(a => {
        if (a.type === 'img') {
            const urlEsc = a.url.replace(/"/g,'&quot;');
            const fn = `imgLightbox('${a.url.replace(/\\/g,'\\\\').replace(/'/g,"\\'")}',0)`;
            const imgName = decodeURIComponent(a.url.split('/').pop().split('?')[0]);
            const shortImg = imgName.length > 16 ? imgName.slice(0,13)+'…' : imgName;
            return `<div onclick="${fn}" style="width:110px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:6px 6px 8px;display:inline-flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0;cursor:zoom-in;transition:box-shadow .12s;" onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
                <img src="${urlEsc}" style="width:98px;height:74px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;" loading="lazy">
                <span title="${imgName.replace(/"/g,'&quot;')}" style="font-size:10.5px;color:#374151;text-align:center;line-height:1.35;width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${shortImg}</span>
            </div>`;
        }
        const ext = (a.name.split('.').pop()||'').toLowerCase();
        const fs = fileColor(ext);
        const short = a.name.length > 16 ? a.name.slice(0,13)+'…' : a.name;
        return `<a href="${a.url.replace(/"/g,'&quot;')}" target="_blank" rel="noopener"
            style="width:110px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:12px 8px 8px;display:inline-flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0;text-decoration:none;transition:box-shadow .12s;"
            onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow='none'">
            <div style="width:52px;height:62px;flex-shrink:0;">
                <svg viewBox="0 0 52 62" style="width:52px;height:62px;" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4,0 L36,0 L52,16 L52,58 Q52,62 48,62 L4,62 Q0,62 0,58 L0,4 Q0,0 4,0 Z" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1.5"/>
                    <path d="M36,0 L36,16 L52,16 Z" fill="#e2e8f0"/>
                    <rect x="6" y="32" width="40" height="20" rx="3" fill="${fs.color}"/>
                    <text x="26" y="46" text-anchor="middle" fill="#fff" font-size="9" font-weight="700" font-family="sans-serif">${fs.label}</text>
                </svg>
            </div>
            <span title="${esc(a.name)}" style="font-size:10.5px;color:#374151;text-align:center;line-height:1.35;width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(short)}</span>
        </a>`;
    }).join('');

    return sec(`${sLabel('fa-paperclip','Files')}<div style="display:flex;flex-wrap:wrap;gap:8px;">${cards}</div>`);
};

const parseMsg = txt => {
    const parts = (txt||'').split(/(\[disk\s+file\s+id=n\d+[^\]]*\]|\[img\].*?\[\/img\]|\[file name="[^"]*"\].*?\[\/file\])/gi);
    const allImgs = [];
    parts.forEach(p => {
        const dm = p.match(/^\[disk\s+file\s+id=n(\d+)[^\]]*\]$/i);
        const im = p.match(/^\[img\](.*?)\[\/img\]$/);
        if (dm) allImgs.push(diskFileUrl(dm[1]));
        else if (im) allImgs.push(im[1]);
    });
    const galKey = allImgs.length > 1 && window.registerGallery ? window.registerGallery(allImgs) : null;
    let imgIdx = 0;
    return parts.map(part => {
        const diskM = part.match(/^\[disk\s+file\s+id=n(\d+)[^\]]*\]$/i);
        const imgM  = part.match(/^\[img\](.*?)\[\/img\]$/);
        const fileM = part.match(/^\[file name="([^"]*)"\](.*?)\[\/file\]$/);
        if (diskM) {
            const url = diskFileUrl(diskM[1]);
            const fn = `imgLightbox('${url}',0)`;
            return `<img src="${url}" style="max-width:280px;max-height:220px;object-fit:cover;border-radius:8px;display:block;margin:4px 0;cursor:zoom-in;transition:opacity .15s;" loading="lazy" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'" onclick="${fn}">`;
        }
        if (imgM) {
            const ci = imgIdx++;
            const url = imgM[1].replace(/"/g,'&quot;');
            const fn = galKey !== null ? `imgLightbox(${galKey},${ci})` : `imgLightbox('${imgM[1].replace(/'/g,"\\'")}',0)`;
            return `<img src="${url}" style="max-width:280px;max-height:220px;object-fit:cover;border-radius:8px;display:block;margin:4px 0;cursor:zoom-in;transition:opacity .15s;" loading="lazy" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'" onclick="${fn}">`;
        }
        if (fileM) {
            const _fn = fileM[1], _url = fileM[2];
            const _ext = (_fn.split('.').pop()||'').toLowerCase();
            const _ic = {pdf:['fa-file-pdf','#ef4444'],doc:['fa-file-word','#2563eb'],docx:['fa-file-word','#2563eb'],xls:['fa-file-excel','#16a34a'],xlsx:['fa-file-excel','#16a34a'],ppt:['fa-file-powerpoint','#ea580c'],pptx:['fa-file-powerpoint','#ea580c'],zip:['fa-file-archive','#ca8a04'],rar:['fa-file-archive','#ca8a04'],txt:['fa-file-alt','#64748b']};
            const [_ico, _bg] = _ic[_ext] || ['fa-file','#0ea5e9'];
            const _safe = _fn.replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return `<a href="${_url.replace(/"/g,'&quot;')}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:10px 14px;text-decoration:none;margin:4px 0;min-width:190px;max-width:280px;">
                <div style="width:40px;height:40px;border-radius:8px;background:${_bg};display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas ${_ico}" style="color:#fff;font-size:18px;"></i></div>
                <div style="min-width:0;flex:1;overflow:hidden;"><p style="font-size:12.5px;font-weight:600;color:#1e293b;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${_safe}</p><p style="font-size:10.5px;color:#94a3b8;margin:2px 0 0;letter-spacing:.4px;">${_ext.toUpperCase()}</p></div>
                <i class="fas fa-download" style="color:#94a3b8;font-size:11px;flex-shrink:0;"></i>
            </a>`;
        }
        return part
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/\[USER=\d+\]([^\[]*)\[\/USER\]/g,'<span style="color:#0ea5e9;font-weight:600;">$1</span>')
            .replace(/\[TIMESTAMP=(\d+)\s+FORMAT=[^\]]*\]/g,(_,ts)=>{
                const d=new Date(parseInt(ts)*1000);
                return '<span style="color:#f59e0b;">'+d.toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})+'</span>';
            })
            .replace(/\[url=([^\]]+)\]([^\[]*)\[\/url\]/gi,'<a href="$1" target="_blank" rel="noopener" style="color:#0ea5e9;text-decoration:underline;">$2</a>')
            .replace(/\[url\](.*?)\[\/url\]/gi,'<a href="$1" target="_blank" rel="noopener" style="color:#0ea5e9;text-decoration:underline;">$1</a>')
            .replace(/\[\/?\w[^\]]*\]/g,'')
            .replace(/(?<!href=")(https?:\/\/[^\s<>"'[\]]+)/g,'<a href="$1" target="_blank" rel="noopener noreferrer" style="color:#0ea5e9;text-decoration:underline;word-break:break-all;">$1</a>')
            .replace(/\n/g,'<br>');
    }).join('');
};

const fmtDate  = iso => iso ? new Date(iso).toLocaleDateString('en-GB',{weekday:'short',day:'numeric',month:'short',year:'numeric'}) : '—';
const fmtTime  = iso => { if(!iso) return ''; const d=new Date(iso); const h=d.getHours(),m=d.getMinutes(); return (h%12||12)+':'+String(m).padStart(2,'0')+' '+(h>=12?'PM':'AM'); };
const fmtShort = iso => iso ? new Date(iso).toLocaleString('en-GB',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'}) : '';
const fmtTimeOnly = iso => { if(!iso) return ''; const d=new Date(iso); return (''+d.getHours()).padStart(2,'0')+':'+(''+d.getMinutes()).padStart(2,'0'); };
const chatDayKey  = iso => { if(!iso) return ''; const d=new Date(iso); return d.getFullYear()+'-'+d.getMonth()+'-'+d.getDate(); };
const chatDayLabel = iso => {
    const d=new Date(iso), now=new Date(), diff=Math.floor((now-d)/(86400000));
    if(diff===0) return 'today';
    if(diff===1) return 'yesterday';
    return d.toLocaleDateString('en-GB',{day:'numeric',month:'long',year:'numeric'});
};
const chatDivider = iso => `<div style="display:flex;align-items:center;justify-content:center;margin:12px 0 8px;"><span style="background:rgba(0,0,0,0.28);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:rgba(255,255,255,.9);font-size:11px;font-weight:600;padding:3px 14px;border-radius:12px;letter-spacing:.3px;">${chatDayLabel(iso)}</span></div>`;

/* build a chat bubble — isMine = right green, else left white */
const chatBubble = ({isMine, name, nameColor, text, time, showName=true, isSystem=false, files=[]}) => {
    if(isSystem) return `
        <div style="display:flex;justify-content:center;margin:3px 0 6px;">
            <div style="background:rgba(0,0,0,0.22);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);border-radius:12px;padding:4px 14px;max-width:88%;text-align:center;line-height:1.4;">
                <span style="color:rgba(255,255,255,.88);font-size:11.5px;">${text}</span>
                <span style="color:rgba(255,255,255,.45);font-size:9.5px;margin-left:8px;white-space:nowrap;">${time}</span>
            </div>
        </div>`;
    const bg   = isMine ? 'rgba(200,240,210,0.90)' : 'rgba(255,255,255,0.82)';
    const br   = isMine ? '14px 4px 14px 14px'     : '4px 14px 14px 14px';
    const tc   = isMine ? '#1a3a25' : '#1e293b';
    const timec= isMine ? '#5a8a6a' : '#94a3b8';
    const tick = isMine ? '<i class="fas fa-check-double" style="font-size:8px;color:#5a8a6a;margin-left:3px;"></i>' : '';
    const nameHtml = (!isMine && showName && name) ? `<div style="color:${nameColor||'#0ea5e9'};font-size:11.5px;font-weight:700;margin-bottom:3px;">${esc(name)}</div>` : '';
    const fileIcons = {pdf:'fa-file-pdf',doc:'fa-file-word',docx:'fa-file-word',xls:'fa-file-excel',xlsx:'fa-file-excel',ppt:'fa-file-powerpoint',pptx:'fa-file-powerpoint',zip:'fa-file-zipper',rar:'fa-file-zipper',mp4:'fa-file-video',mov:'fa-file-video',mp3:'fa-file-audio'};
    const imgExts = new Set(['jpg','jpeg','png','gif','webp','svg','bmp']);
    const filesHtml = (files||[]).length ? `<div style="display:flex;flex-direction:column;gap:6px;margin-top:6px;">${(files||[]).map(f=>{
        const ext=(f.name||'').split('.').pop().toLowerCase();
        const url=f.downloadUrl||'#';
        if(imgExts.has(ext)){
            return`<a href="${url}" target="_blank" rel="noopener" style="display:block;border-radius:10px;overflow:hidden;max-width:260px;line-height:0;">
                <img src="${url}" alt="${esc(f.name)}" loading="lazy" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;display:block;cursor:zoom-in;" onerror="this.parentElement.innerHTML='<span style=&quot;display:flex;align-items:center;gap:7px;padding:6px 9px;background:rgba(0,0,0,0.06);border-radius:7px;&quot;><i class=&quot;fas fa-file-image&quot; style=&quot;color:#0ea5e9;font-size:13px;&quot;></i><span style=&quot;color:${tc};font-size:11.5px;&quot;>${esc(f.name)}</span></span>';">
            </a>`;
        }
        const ic=fileIcons[ext]||'fa-file';
        const fclr={pdf:'#ef4444',doc:'#2563eb',docx:'#2563eb',xls:'#16a34a',xlsx:'#16a34a',ppt:'#ea580c',pptx:'#ea580c',zip:'#ca8a04',rar:'#ca8a04'}[ext]||'#6b7280';
        const fbg={pdf:'rgba(239,68,68,.12)',doc:'rgba(37,99,235,.1)',docx:'rgba(37,99,235,.1)',xls:'rgba(22,163,74,.1)',xlsx:'rgba(22,163,74,.1)',ppt:'rgba(234,88,12,.1)',pptx:'rgba(234,88,12,.1)',zip:'rgba(202,138,4,.1)',rar:'rgba(202,138,4,.1)'}[ext]||'rgba(107,114,128,.1)';
        const fsz=f.size?(f.size>=1048576?(f.size/1048576).toFixed(1)+' MB':Math.round(f.size/1024)+' KB'):'';
        return`<a href="${url}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:10px;padding:8px 11px;background:rgba(255,255,255,0.75);border:1px solid rgba(0,0,0,0.09);border-radius:10px;text-decoration:none;min-width:180px;max-width:280px;"><div style="width:38px;height:46px;background:${fbg};border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas ${ic}" style="color:${fclr};font-size:19px;"></i></div><div style="flex:1;overflow:hidden;"><div style="color:${tc};font-size:12px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(f.name)}</div>${fsz?`<div style="color:${timec};font-size:10px;margin-top:2px;">${fsz}</div>`:''}</div><i class="fas fa-download" style="color:#94a3b8;font-size:10px;flex-shrink:0;"></i></a>`;
    }).join('')}</div>` : '';
    return `
        <div style="display:flex;justify-content:${isMine?'flex-end':'flex-start'};margin-bottom:2px;">
            <div style="max-width:78%;background:${bg};backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.6);border-radius:${br};padding:8px 12px 6px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
                ${nameHtml}
                ${text ? `<div style="color:${tc};font-size:13px;line-height:1.5;word-break:break-word;">${text}</div>` : ''}
                ${filesHtml}
                <div style="text-align:right;margin-top:3px;">
                    <span style="color:${timec};font-size:10px;">${time}${tick}</span>
                </div>
            </div>
        </div>`;
};
const fmtLocal = iso => { if(!iso) return ''; const d=new Date(iso); return d.getFullYear()+'-'+(''+(d.getMonth()+1)).padStart(2,'0')+'-'+(''+(d.getDate())).padStart(2,'0')+'T'+(''+(d.getHours())).padStart(2,'0')+':'+(''+(d.getMinutes())).padStart(2,'0'); };

const sLabel = (icon,text) =>
    `<p style="color:#9ca3af;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 10px;display:flex;align-items:center;gap:6px;"><i class="fas ${icon}" style="color:#0ea5e9;font-size:9px;"></i>${text}</p>`;

const sec = html =>
    `<div style="padding:16px 0;border-bottom:1px solid #f1f3f5;">${html}</div>`;

/* ─── skeleton ─────────────────────────────────────────── */
function tpSkeleton() {
    $('tp-title').textContent=''; $('tp-task-id').textContent=''; $('tp-source-badge').innerHTML='';
    $('tp-left-body').innerHTML=[60,100,45,80,70,55].map(w=>
        `<div style="padding:16px 0;border-bottom:1px solid #f1f3f5;display:flex;flex-direction:column;gap:8px;">
            <div class="tp-skel" style="height:9px;width:${w}px;"></div>
            <div class="tp-skel" style="height:14px;width:90%;"></div>
        </div>`).join('');
    $('tp-messages').innerHTML=[1,2,3].map(()=>
        `<div style="display:flex;gap:9px;align-items:flex-start;">
            <div class="tp-skel" style="width:32px;height:32px;border-radius:50%;flex-shrink:0;"></div>
            <div style="flex:1;display:flex;flex-direction:column;gap:5px;">
                <div class="tp-skel" style="height:10px;width:70px;"></div>
                <div class="tp-skel" style="height:13px;width:100%;"></div>
                <div class="tp-skel" style="height:13px;width:65%;"></div>
            </div>
        </div>`).join('');
    $('tp-comment-footer').innerHTML='';
}

/* ─── open / close ─────────────────────────────────────── */
let _currentTaskId=null, _currentTaskType=null;

window.tpOpen = function(type, id) {
    _currentTaskId=id; _currentTaskType=type;
    const ov=$('tp-overlay');
    ov.style.display='flex'; void ov.offsetWidth; ov.style.opacity='1';
    const panel=$('tp-panel');
    panel.classList.add('tp-entering');
    setTimeout(()=>panel.classList.remove('tp-entering'),300);
    document.body.style.overflow='hidden';
    tpSkeleton();
    const sp=new URLSearchParams(location.search);
    sp.set('task',id);
    if(type==='b24') sp.set('src','b24'); else sp.delete('src');
    history.pushState({task:id,src:type},'',location.pathname+'?'+sp.toString());
    const url=(type==='b24') ? TP_B24_URL+'/'+id : TP_LOCAL_URL+'/'+id;
    fetch(url,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}})
        .then(r=>r.json())
        .then(data=>{
            if(type==='b24') tpRenderB24(data,id);
            else { tpRenderLocal(data); tpStartChatPoll(id); }
        })
        .catch(()=>{ $('tp-left-body').innerHTML='<p style="color:#ef4444;padding:24px 0;">Could not load task details.</p>'; });

    // Mark this task's notifications as read + remove card badge
    if (type === 'local') {
        const wasUnseen = window._kbUnseenIds && window._kbUnseenIds.has(String(id));
        fetch(`/api/notifications/task/${id}/read`, { method:'POST', headers:{'X-CSRF-TOKEN':TP_CSRF} });
        if (wasUnseen) {
            // Remove badge from card immediately (optimistic)
            const card = document.getElementById('kb-task-' + id);
            if (card) {
                card.querySelectorAll('.kb-unseen-dot').forEach(el => el.remove());
                card.style.borderLeft = '';
            }
            // Remove from unseen set then refresh kanban so completed task follows filter again
            if (window._kbUnseenIds) window._kbUnseenIds.delete(String(id));
            if (typeof kbAjaxFilter === 'function') setTimeout(kbAjaxFilter, 400);
        }
    }
};

window.tpClose = function(updateUrl=true) {
    tpStopChatPoll();
    const ov=$('tp-overlay');
    ov.style.opacity='0'; document.body.style.overflow=''; _currentTaskId=null;
    setTimeout(()=>{ ov.style.display='none'; },230);
    if(updateUrl){
        const sp=new URLSearchParams(location.search);
        sp.delete('task'); sp.delete('src');
        const qs=sp.toString();
        history.pushState(null,'',location.pathname+(qs?'?'+qs:''));
    }
};

window.addEventListener('popstate',function(){
    const sp=new URLSearchParams(location.search);
    const taskId=sp.get('task');
    if(taskId) tpOpen(sp.get('src')==='b24'?'b24':'local',parseInt(taskId));
    else tpClose(false);
});

document.addEventListener('keydown',e=>{
    if(e.key!=='Escape') return;
    const lb=document.getElementById('img-lightbox');
    if(lb&&lb.style.display!=='none'){ if(window.lbClose) lbClose(); return; }
    const em=document.getElementById('tp-edit-modal');
    if(em&&em.style.display!=='none'){ tpEditClose(); return; }
    tpClose();
});
$('tp-overlay').addEventListener('click',function(e){ if(e.target===this) tpClose(); });

// Move overlay to body so it's not constrained by parent stacking context
document.addEventListener('DOMContentLoaded', function() {
    const ov = document.getElementById('tp-overlay');
    if (ov && ov.parentElement !== document.body) {
        document.body.appendChild(ov);
    }
});

(function(){
    const sp=new URLSearchParams(location.search);
    const taskId=sp.get('task');
    if(taskId){
        if(document.readyState!=='loading') tpOpen(sp.get('src')==='b24'?'b24':'local',parseInt(taskId));
        else document.addEventListener('DOMContentLoaded',()=>tpOpen(sp.get('src')==='b24'?'b24':'local',parseInt(taskId)));
    }
})();

/* ─── AJAX helpers ─────────────────────────────────────── */
window.tpUpdateField = function(taskId, field, value, onDone) {
    fetch(TP_TASKS_URL+'/'+taskId+'/field',{
        method:'PATCH',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'},
        body:JSON.stringify({field,value}),
    }).then(r=>r.json()).then(resp=>{ if(resp.success&&onDone) onDone(resp); else if(!resp.success) alert(resp.message||'Update failed. You may not have permission.'); })
    .catch(()=>alert('Update failed.'));
};
window.tpToggleMember = function(taskId,userId,onDone){
    fetch(TP_TASKS_URL+'/'+taskId+'/participants/toggle',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'},body:JSON.stringify({user_id:userId})}).then(r=>r.json()).then(resp=>{if(onDone)onDone(resp);});
};
window.tpToggleObserver = function(taskId,userId,onDone){
    fetch(TP_TASKS_URL+'/'+taskId+'/observers/toggle',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'},body:JSON.stringify({user_id:userId})}).then(r=>r.json()).then(resp=>{if(onDone)onDone(resp);});
};

/* ─── People chips + dropdown ──────────────────────────── */
function renderChips(list, taskId, toggleFn, allEmployees) {
    const dropId='tp-drop-'+toggleFn+'-'+taskId;
    const chips=list.map(u=>
        `<span class="tp-member-chip" onclick="tpToggleMemberUI(${taskId},${u.id},'${toggleFn}')" title="Click to remove" style="cursor:pointer;" onmouseover="this.style.background='#fee2e2';this.style.borderColor='#fca5a5'" onmouseout="this.style.background='#f1f5f9';this.style.borderColor='#e2e8f0'">
            ${uAvatar(u,22)}
            <span>${esc(u.name)}</span>
            <i class="fas fa-times rm"></i>
        </span>`).join('');
    return `<div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;position:relative;">
        ${chips}
        <span onclick="tpToggleDropdown('${dropId}')" style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;border:1.5px dashed #cbd5e1;color:#94a3b8;font-size:11.5px;cursor:pointer;transition:all .15s;" onmouseover="this.style.borderColor='#0ea5e9';this.style.color='#0ea5e9'" onmouseout="this.style.borderColor='#cbd5e1';this.style.color='#94a3b8'">
            <i class="fas fa-plus" style="font-size:8px;"></i>Add
        </span>
        <div id="${dropId}" class="tp-people-dropdown" style="display:none;top:34px;left:0;">
            <input type="text" placeholder="Search..." oninput="tpFilterDrop('${dropId}',this.value)">
            <div class="opts">
                ${allEmployees.map(e=>{
                    const isIn=list.some(u=>u.id===e.id);
                    return `<div class="opt${isIn?' selected':''}" data-name="${esc(e.name.toLowerCase())}" onclick="tpToggleMemberUI(${taskId},${e.id},'${toggleFn}')">
                        ${uAvatar(e,24)}
                        <span>${esc(e.name)}</span>
                        ${isIn?'<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:#0ea5e9;"></i>':''}
                    </div>`;
                }).join('')}
            </div>
        </div>
    </div>`;
}

window.tpToggleDropdown = function(id) {
    document.querySelectorAll('.tp-people-dropdown').forEach(d=>{ if(d.id!==id) d.style.display='none'; });
    const el=$(id); if(!el) return;
    const opening = el.style.display==='none';
    el.style.display = opening ? 'block' : 'none';
    if(opening){
        const inp=el.querySelector('input[type="text"]');
        if(inp){ inp.value=''; tpFilterDrop(id,''); setTimeout(()=>inp.focus(),30); }
    }
};
window.tpFilterDrop = function(dropId,query) {
    const el=$(dropId); if(!el) return;
    el.querySelectorAll('.opt').forEach(opt=>{ opt.style.display=opt.dataset.name.includes(query.toLowerCase())?'flex':'none'; });
};
window.tpToggleMemberUI = function(taskId,userId,type) {
    const fn=type==='observer'?tpToggleObserver:tpToggleMember;
    fn(taskId,userId,()=>{
        fetch(TP_LOCAL_URL+'/'+taskId,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>tpRenderLocal(d));
    });
};
document.addEventListener('click',function(e){
    if(!e.target.closest('.tp-people-dropdown')&&!e.target.closest('[onclick*="tpToggleDropdown"]'))
        document.querySelectorAll('.tp-people-dropdown').forEach(d=>d.style.display='none');
});

/* ─── Deadline block ───────────────────────────────────── */
function renderDeadlineBlock(iso, status, taskId, createdAt) {
    const dl=iso?new Date(iso):null, now=new Date();
    const isOvr=dl&&dl<now&&status!=='completed';
    const daysLeft=dl?Math.ceil((dl-now)/86400000):null;
    const badge=isOvr
        ? `<span style="background:#fee2e2;color:#b91c1c;font-size:10px;font-weight:700;padding:3px 9px;border-radius:12px;border:1px solid #fecaca;"><i class="fas fa-exclamation-triangle" style="margin-right:3px;"></i>OVERDUE</span>`
        : daysLeft===0 ? `<span style="background:#fef3c7;color:#b45309;font-size:10px;font-weight:700;padding:3px 9px;border-radius:12px;">Due Today</span>`
        : daysLeft>0  ? `<span style="background:#dcfce7;color:#15803d;font-size:10px;font-weight:600;padding:3px 9px;border-radius:12px;">${daysLeft}d left</span>`
        : '';
    const current=dl
        ? `<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
               <div>
                   <p style="color:${isOvr?'#b91c1c':'#111827'};font-size:13.5px;font-weight:600;margin:0;">${fmtDate(iso)}</p>
                   <p style="color:#9ca3af;font-size:11px;margin:2px 0 0;">${fmtTime(iso)}</p>
               </div>${badge}
           </div>`
        : `<p style="color:#9ca3af;font-size:13px;margin:0;">No deadline set</p>`;
    const createdCol = createdAt
        ? `<div style="flex-shrink:0;padding-left:32px;border-left:1px solid #f1f3f5;">
               <p style="color:#9ca3af;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 10px;display:flex;align-items:center;gap:6px;"><i class="fas fa-clock" style="color:#0ea5e9;font-size:9px;"></i>Created</p>
               <p style="color:#374151;font-size:13.5px;font-weight:600;margin:0;">${fmtDate(createdAt)}</p>
               <p style="color:#9ca3af;font-size:11px;margin:2px 0 0;">${fmtTime(createdAt)}</p>
           </div>`
        : '';
    return sec(`
        <div style="display:flex;gap:0;align-items:flex-start;">
            <div style="flex:1;min-width:0;">
                ${sLabel('fa-calendar-alt','Deadline')}
                <div onclick="tpCalOpen(${taskId}, this, '${iso||''}')" style="display:inline-flex;align-items:center;gap:10px;cursor:pointer;padding:6px 8px;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">${current}</div>
            </div>
            ${createdCol}
        </div>`);
}
/* ─── Custom calendar (deadline picker) ────────────────── */
const _tpCal={taskId:null,year:null,month:null,selDate:null,selH:9,selM:0};

function _tpCalDateStr(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }

window.tpCalOpen=function(taskId,anchor,currentIso){
    _tpCal.taskId=taskId;
    const now=new Date();
    if(currentIso){
        const d=new Date(currentIso);
        _tpCal.year=d.getFullYear(); _tpCal.month=d.getMonth();
        _tpCal.selDate=_tpCalDateStr(d);
        _tpCal.selH=d.getHours(); _tpCal.selM=Math.round(d.getMinutes()/5)*5%60;
    } else {
        _tpCal.year=now.getFullYear(); _tpCal.month=now.getMonth();
        _tpCal.selDate=null; _tpCal.selH=9; _tpCal.selM=0;
    }
    $('tp-cal-date-view').style.display='flex';
    $('tp-cal-time-view').style.display='none';
    _tpCalRender(); _tpCalQuick(); _tpCalTimeLabel();
    const pop=$('tp-cal-popup');
    pop.style.display='block';
    const rect=anchor.getBoundingClientRect();
    const pw=pop.offsetWidth||480;
    const left=Math.max(8,Math.min(rect.left,window.innerWidth-pw-8));
    const top=Math.min(rect.bottom+6, window.innerHeight-400);
    pop.style.left=left+'px'; pop.style.top=top+'px';
};

window.tpCalClose=function(){ $('tp-cal-popup').style.display='none'; };
window.tpCalPrev=function(){ if(_tpCal.month===0){_tpCal.month=11;_tpCal.year--;}else _tpCal.month--; _tpCalRender(); };
window.tpCalNext=function(){ if(_tpCal.month===11){_tpCal.month=0;_tpCal.year++;}else _tpCal.month++; _tpCalRender(); };

function _tpCalRender(){
    const MON=['January','February','March','April','May','June','July','August','September','October','November','December'];
    $('tp-cal-title').textContent=MON[_tpCal.month]+' '+_tpCal.year;
    const first=new Date(_tpCal.year,_tpCal.month,1);
    const dim=new Date(_tpCal.year,_tpCal.month+1,0).getDate();
    const startDay=first.getDay();
    const todayStr=_tpCalDateStr(new Date());
    let html='';
    for(let i=0;i<startDay;i++) html+='<div></div>';
    for(let d=1;d<=dim;d++){
        const ds=_tpCal.year+'-'+String(_tpCal.month+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
        const isSel=ds===_tpCal.selDate, isToday=ds===todayStr;
        const isPast=ds<todayStr;
        const dow=(startDay+d-1)%7;
        const isWE=dow===0||dow===6;
        const bg=isSel?'#0ea5e9':isToday?'#e0f2fe':'transparent';
        const col=isSel?'#fff':isToday?'#0ea5e9':isPast?'#d1d5db':isWE?'#ef4444':'#374151';
        html+=`<div onclick="tpCalSelectDate('${ds}')" style="height:32px;width:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;cursor:pointer;font-size:12.5px;font-weight:${isSel||isToday?'700':'400'};color:${col};background:${bg};transition:background .1s;" onmouseover="this.style.background=this.style.background||'';if('${isSel}'!=='true')this.style.background='#f1f5f9'" onmouseout="this.style.background='${bg}'">${d}</div>`;
    }
    $('tp-cal-grid').innerHTML=html;
    const tr=$('tp-cal-time-row');
    tr.style.display=_tpCal.selDate?'block':'none';
    if(_tpCal.selDate) _tpCalTimeLabel();
}

function _tpCalTimeLabel(){
    const el=$('tp-cal-time-label'); if(!el) return;
    const h=_tpCal.selH%12||12, ampm=_tpCal.selH<12?'AM':'PM', m=String(_tpCal.selM).padStart(2,'0');
    el.textContent=h+':'+m+' '+ampm;
}

function _tpCalQuick(){
    const now=new Date(), today=new Date(now.getFullYear(),now.getMonth(),now.getDate());
    const MON=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const fq=d=>d.getDate()+' '+MON[d.getMonth()];
    const opts=[
        {l:'Today',     d:new Date(today)},
        {l:'Tomorrow',  d:new Date(today.getFullYear(),today.getMonth(),today.getDate()+1)},
        {l:'In 3 days', d:new Date(today.getFullYear(),today.getMonth(),today.getDate()+3)},
        {l:'Next week', d:new Date(today.getFullYear(),today.getMonth(),today.getDate()+7)},
        {l:'In 2 weeks',d:new Date(today.getFullYear(),today.getMonth(),today.getDate()+14)},
        {l:'Next month',d:new Date(today.getFullYear(),today.getMonth()+1,today.getDate())},
    ];
    $('tp-cal-quick').innerHTML=opts.map(o=>{
        const ds=_tpCalDateStr(o.d), isSel=ds===_tpCal.selDate;
        return `<button type="button" onclick="tpCalSelectDate('${ds}')"
            style="width:100%;text-align:left;background:${isSel?'#e0f2fe':'none'};border:none;border-radius:8px;padding:7px 10px;cursor:pointer;font-size:12.5px;color:${isSel?'#0ea5e9':'#374151'};display:flex;align-items:center;justify-content:space-between;transition:background .1s;font-weight:${isSel?700:400};"
            onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='${isSel?'#e0f2fe':''}'">
            <span>${o.l}</span><span style="color:#9ca3af;font-size:11px;">${fq(o.d)}</span>
        </button>`;
    }).join('')+`<button type="button" onclick="tpCalClear()"
        style="width:100%;text-align:left;background:none;border:none;border-radius:8px;padding:7px 10px;cursor:pointer;font-size:12.5px;color:#ef4444;display:flex;align-items:center;gap:8px;margin-top:2px;transition:background .1s;"
        onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background=''">
        <i class="fas fa-times-circle" style="font-size:12px;"></i>No deadline
    </button>`;
}
window._tpCalQuick=_tpCalQuick; // alias used internally
function _tpCalLocalToISO(){
    const h=_tpCal.selH!==null?_tpCal.selH:9;
    const m=_tpCal.selM!==null?_tpCal.selM:0;
    return new Date(_tpCal.selDate+'T'+String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':00').toISOString();
}
function _tpCalSaveSilent(){
    if(!_tpCal.selDate) return;
    tpUpdateField(_tpCal.taskId,'deadline',_tpCalLocalToISO(),null);
}
function _tpCalSave(){
    if(!_tpCal.selDate) return;
    const _calTid=_tpCal.taskId;
    tpUpdateField(_calTid,'deadline',_tpCalLocalToISO(),()=>{
        tpCalClose();
        fetch(TP_LOCAL_URL+'/'+_calTid,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{ tpRenderLocal(d); kbUpdateCard(_calTid); });
    });
}
window.tpCalSelectDate=function(ds){
    _tpCal.selDate=ds;
    const parts=ds.split('-'); _tpCal.year=parseInt(parts[0]); _tpCal.month=parseInt(parts[1])-1;
    _tpCalRender(); _tpCalQuick();
};
window.tpCalClear=function(){
    _tpCal.selDate=null;
    const _calTid=_tpCal.taskId;
    tpUpdateField(_calTid,'deadline',null,()=>{
        tpCalClose();
        fetch(TP_LOCAL_URL+'/'+_calTid,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{ tpRenderLocal(d); kbUpdateCard(_calTid); });
    });
};
window.tpCalShowTime=function(){
    $('tp-cal-date-view').style.display='none';
    $('tp-cal-time-view').style.display='block';
    _tpCalTimeGrid();
};
function _tpCalTimeGrid(){
    const hBtn=h=>{
        const isSel=h===_tpCal.selH, lbl=h%12||12;
        return `<button type="button" onclick="tpCalPickHour(${h})" style="height:32px;border-radius:7px;border:1.5px solid ${isSel?'#0ea5e9':'#e2e8f0'};background:${isSel?'#0ea5e9':'#fff'};color:${isSel?'#fff':'#374151'};font-size:12px;font-weight:${isSel?700:400};cursor:pointer;transition:all .1s;">${lbl}</button>`;
    };
    $('tp-cal-hours-am').innerHTML=[...Array(12).keys()].map(hBtn).join('');
    $('tp-cal-hours-pm').innerHTML=[...Array(12).keys()].map(h=>hBtn(h+12)).join('');
    const mins=[0,5,10,15,20,25,30,35,40,45,50,55];
    $('tp-cal-minutes').innerHTML=mins.map(m=>{
        const isSel=m===_tpCal.selM;
        return `<button type="button" onclick="tpCalPickMinute(${m})" style="height:32px;border-radius:7px;border:1.5px solid ${isSel?'#0ea5e9':'#e2e8f0'};background:${isSel?'#0ea5e9':'#fff'};color:${isSel?'#fff':'#374151'};font-size:12px;font-weight:${isSel?700:400};cursor:pointer;transition:all .1s;">${String(m).padStart(2,'0')}</button>`;
    }).join('');
}
window.tpCalPickHour=function(h24){ _tpCal.selH=h24; _tpCalTimeGrid(); };
window.tpCalPickMinute=function(m){
    _tpCal.selM=m;
    $('tp-cal-date-view').style.display='flex';
    $('tp-cal-time-view').style.display='none';
    _tpCalTimeLabel(); _tpCalRender();
    _tpCalSave();
};
document.addEventListener('click',function(e){
    const pop=$('tp-cal-popup');
    if(pop&&pop.style.display!=='none'&&!pop.contains(e.target)&&!e.target.closest('[onclick*="tpCalOpen"]')){
        if(_tpCal.selDate){
            _tpCalSave();
        } else {
            const tid=_tpCal.taskId;
            tpCalClose();
            if(tid&&_currentTaskId) fetch(TP_LOCAL_URL+'/'+tid,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>tpRenderLocal(d));
        }
    }
});

/* ─── Assignee block ───────────────────────────────────── */
function renderAssigneeBlock(assignee, taskId, employees) {
    const currentId=assignee?assignee.id:null;
    const current=assignee
        ? `<div style="display:flex;align-items:center;gap:10px;">${uAvatar(assignee,36)}<div><p style="color:#111827;font-size:13.5px;font-weight:600;margin:0;">${esc(assignee.name)}</p>${assignee.position?`<p style="color:#0ea5e9;font-size:11px;margin:2px 0 0;">${esc(assignee.position)}</p>`:''}</div></div>`
        : `<p style="color:#9ca3af;font-size:13px;margin:0;">Unassigned</p>`;
    return sec(`
        ${sLabel('fa-user','Assigned To')}
        <div onclick="tpToggleDropdown('tp-drop-assignee')" style="display:inline-flex;align-items:center;gap:10px;cursor:pointer;padding:6px 8px;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
            ${current}
            <i class="fas fa-chevron-down" style="font-size:9px;color:#9ca3af;margin-left:4px;"></i>
        </div>
        <div id="tp-drop-assignee" class="tp-people-dropdown" style="display:none;margin-top:6px;position:relative;">
            <input type="text" placeholder="Search employee..." oninput="tpFilterDrop('tp-drop-assignee',this.value)">
            <div class="opts">
                <div class="opt${!currentId?' selected':''}" data-name="unassigned" onclick="tpSetAssignee(${taskId},null)">
                    <div style="width:24px;height:24px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-user-slash" style="font-size:10px;color:#9ca3af;"></i></div>
                    <span style="color:#9ca3af;">Unassigned</span>
                    ${!currentId?'<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:#0ea5e9;"></i>':''}
                </div>
                ${employees.map(e=>`<div class="opt${e.id===currentId?' selected':''}" data-name="${esc(e.name.toLowerCase())}" onclick="tpSetAssignee(${taskId},${e.id})">
                    ${uAvatar(e,24)}<span>${esc(e.name)}</span>
                    ${e.id===currentId?'<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:#0ea5e9;"></i>':''}
                </div>`).join('')}
            </div>
        </div>`);
}
window.tpSetAssignee=function(taskId,userId){ tpUpdateField(taskId,'assigned_to',userId,()=>{ fetch(TP_LOCAL_URL+'/'+taskId,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>tpRenderLocal(d)); }); tpToggleDropdown('tp-drop-assignee'); };

/* ─── render Bitrix task ───────────────────────────────── */
function tpRenderB24(data, bxId) {
    const t=data.task, users=data.users||{}, files=data.files||[], chat=data.chat||[];
    const responsible=users[t.responsibleId]||null, creator=users[t.creatorId]||null;
    const participants=(t.accomplices||[]).map(id=>users[id]).filter(Boolean);
    const sMap={'1':'New','2':'Pending','3':'In Progress','4':'Reviewing','5':'Completed','6':'Deferred'};
    const sBg={'1':'#f1f5f9','2':'#fef3c7','3':'#dbeafe','4':'#ede9fe','5':'#dcfce7','6':'#f1f5f9'};
    const sCol={'1':'#475569','2':'#b45309','3':'#1d4ed8','4':'#6d28d9','5':'#15803d','6':'#475569'};

    $('tp-source-badge').innerHTML=`<div style="display:flex;align-items:center;gap:4px;padding:2px 7px;background:#e0f2fe;border:1px solid #bae6fd;border-radius:5px;"><i class="fas fa-bolt" style="font-size:8px;color:#0284c7;"></i><span style="font-size:9px;font-weight:700;color:#0284c7;letter-spacing:.6px;">BITRIX24</span></div>`;
    $('tp-task-id').textContent='#'+t.id;
    $('tp-title').textContent=t.title||'Untitled';

    const desc=(t.description||'')
        .replace(/&amp;/gi,'&').replace(/&lt;/gi,'<').replace(/&gt;/gi,'>').replace(/&nbsp;/gi,' ').replace(/&quot;/gi,'"')
        .replace(/<br\s*\/?>/gi,'\n').replace(/<[^>]+>/g,'')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/\[url=([^\]]+)\]([^\[]*)\[\/url\]/gi,'<a href="$1" target="_blank" rel="noopener" style="color:#0ea5e9;text-decoration:underline;">$2</a>')
        .replace(/\[url\](.*?)\[\/url\]/gi,'<a href="$1" target="_blank" rel="noopener" style="color:#0ea5e9;text-decoration:underline;">$1</a>')
        .replace(/\[.*?\]/g,'')
        .replace(/(?<!href=")(https?:\/\/[^\s<>"'[\]]+)/g,'<a href="$1" target="_blank" rel="noopener noreferrer" style="color:#0ea5e9;text-decoration:underline;word-break:break-all;">$1</a>')
        .replace(/\n/g,'<br>');
    const icons={pdf:'fa-file-pdf',jpg:'fa-file-image',jpeg:'fa-file-image',png:'fa-file-image',doc:'fa-file-word',docx:'fa-file-word',xls:'fa-file-excel',xlsx:'fa-file-excel',zip:'fa-file-archive',rar:'fa-file-archive'};

    $('tp-left-body').innerHTML=
        (desc?sec(`${sLabel('fa-align-left','Description')}<div style="color:#374151;font-size:13px;line-height:1.65;">${desc}</div>`):'') +
        sec(`${sLabel('fa-circle-dot','Status')}<div style="display:flex;gap:6px;flex-wrap:wrap;">${['1','2','3','4','5','6'].map(s=>`<span class="tp-pill${t.status===s?' active':''}" style="background:${sBg[s]};color:${sCol[s]};border-color:${t.status===s?sCol[s]:'transparent'};">${sMap[s]}</span>`).join('')}</div>`) +
        sec(`${sLabel('fa-calendar-alt','Deadline')}${t.deadline?`<p style="color:#111827;font-size:13.5px;font-weight:600;margin:0;">${fmtDate(t.deadline)}</p><p style="color:#9ca3af;font-size:11px;margin:2px 0 0;">${fmtTime(t.deadline)}</p>`:`<p style="color:#9ca3af;font-size:13px;margin:0;">No deadline</p>`}`) +
        sec(`${sLabel('fa-users','People')}
            ${responsible?`<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">${uAvatar(responsible,36)}<div><p style="color:#9ca3af;font-size:9px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 1px;">Assigned To</p><p style="color:#111827;font-size:13px;font-weight:600;margin:0;">${esc(responsible.name)}</p></div></div>`:''}
            ${creator?`<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;">${uAvatar(creator,36)}<div><p style="color:#9ca3af;font-size:9px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 1px;">Task Owner</p><p style="color:#111827;font-size:13px;font-weight:600;margin:0;">${esc(creator.name)}</p></div></div>`:''}
            ${participants.length?`<p style="color:#9ca3af;font-size:9px;text-transform:uppercase;letter-spacing:.5px;margin:0 0 6px;">Participants</p><div style="display:flex;gap:5px;flex-wrap:wrap;">${participants.map(u=>uAvatar(u,28)).join('')}</div>`:''}`) +
        (files.length?sec(`${sLabel('fa-paperclip',`Files (${files.length})`)}<div style="display:flex;flex-direction:column;gap:5px;">${files.map(f=>{const ext=(f.name||'').split('.').pop().toLowerCase();const ic=icons[ext]||'fa-file';return`<a href="${f.downloadUrl||'#'}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:#f8fafc;border:1px solid #e9ecef;border-radius:8px;text-decoration:none;"><i class="fas ${ic}" style="color:#0ea5e9;font-size:15px;width:18px;flex-shrink:0;"></i><p style="color:#374151;font-size:12px;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(f.name)}</p></a>`;}).join('')}</div>`):'') +
        '<div style="height:20px;"></div>';

    $('tp-chat-title').textContent='Task Chat';
    const userMsgs=chat.filter(m=>!m.isSystem);
    $('tp-chat-count').textContent=userMsgs.length?userMsgs.length+' messages':'';
    if(chat.length){
        let lastDay='', lastAuthorId=null;
        const nameColors=['#0ea5e9','#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
        const nameColor = id => nameColors[Math.abs(parseInt(id||0)) % nameColors.length];
        $('tp-messages').innerHTML = '<div style="flex:1;min-height:0;pointer-events:none;"></div>' + chat.map(m=>{
            const iso = m.date || m.created_at || '';
            const day = chatDayKey(iso);
            const div = day !== lastDay ? (lastDay=day, chatDivider(iso)) : '';
            const time= fmtTimeOnly(iso);
            const mText = parseMsg(m.text);
            if(m.isSystem){
                lastAuthorId=null;
                return div + chatBubble({isSystem:true, text:mText, time});
            }
            const a = m.author;
            const authorId = a ? (a.id||a.name) : null;
            const isMine = a && (parseInt(a.id)===ME_B24_ID);
            const showName = !isMine && authorId !== lastAuthorId;
            lastAuthorId = authorId;
            return div + chatBubble({isMine, name:a?a.name:'', nameColor:nameColor(a?a.id:0), text:mText, time, showName});
        }).join('');
    } else {
        $('tp-messages').innerHTML=`<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 0;"><i class="fas fa-comment-slash" style="font-size:28px;color:rgba(255,255,255,.25);margin-bottom:10px;"></i><p style="color:rgba(255,255,255,.45);font-size:12.5px;margin:0;">No messages yet</p></div>`;
    }

    $('tp-comment-footer').innerHTML=`<div style="display:flex;gap:8px;"><div style="flex:1;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:10px 12px;color:#9ca3af;font-size:12px;display:flex;align-items:center;gap:7px;"><i class="fas fa-comment" style="font-size:10px;color:#cbd5e1;"></i><span>Reply in Bitrix24...</span></div><a href="https://thesoftcube.bitrix24.com/tasks/task/view/${t.id}/" target="_blank" rel="noopener" style="background:#e0f2fe;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;color:#0284c7;font-size:12px;font-weight:600;text-decoration:none;display:flex;align-items:center;gap:5px;white-space:nowrap;"><i class="fas fa-external-link-alt" style="font-size:9px;"></i> Open</a></div>`;
    setTimeout(()=>{ const m=$('tp-messages'); m.scrollTop=m.scrollHeight; },50);
}

/* ─── render local task ────────────────────────────────── */
function tpRenderLocal(data) {
    const t=data.task, feed=data.feed||[], employees=data.employees||[];
    const assignee=data.assignee||null, creator=data.creator||null;
    const participants=data.participants||[], observers=data.observers||[];
    const taskId=t.id;

    const statuses=[
        {v:'new',        label:'New',             bg:'#f1f5f9',col:'#475569'},
        {v:'in_progress',label:'In Progress',     bg:'#dbeafe',col:'#1d4ed8'},
        {v:'paused',     label:'Paused',          bg:'#fef3c7',col:'#b45309'},
        {v:'completed',  label:'Completed',       bg:'#dcfce7',col:'#15803d'},
    ];
    const priorities=[
        {v:'low',   label:'Low',   bg:'#f1f5f9',col:'#475569'},
        {v:'medium',label:'Medium',bg:'#dbeafe',col:'#1d4ed8'},
        {v:'high',  label:'High',  bg:'#ffedd5',col:'#c2410c'},
        {v:'urgent',label:'Urgent',bg:'#fee2e2',col:'#b91c1c'},
    ];

    $('tp-source-badge').innerHTML=`<div style="display:flex;align-items:center;gap:4px;padding:2px 7px;background:#ede9fe;border:1px solid #c4b5fd;border-radius:5px;"><i class="fas fa-tasks" style="font-size:8px;color:#6d28d9;"></i><span style="font-size:9px;font-weight:700;color:#6d28d9;letter-spacing:.6px;">LOCAL TASK</span></div>`;
    $('tp-task-id').textContent='#'+t.id;
    $('tp-title').textContent=t.title||'Untitled';
    window._tpCurrentData = data;
    $('tp-header-actions').innerHTML = data.canEdit
        ? `<button onclick="tpEditOpenFull(${taskId}, window._tpCurrentData)" style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;color:#475569;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;" onmouseover="this.style.borderColor='#0ea5e9';this.style.color='#0ea5e9'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#475569'"><i class="fas fa-pen" style="font-size:10px;"></i>Edit</button>`
        : '';

    const desc      = parseDescText(t.description||'');
    const attachSec = parseDescAttachments(t.description||'');

    $('tp-left-body').innerHTML = (()=>{
        const curStatus   = statuses.find(s=>s.v===t.status)   || statuses[0];
        const curPriority = priorities.find(p=>p.v===t.priority)|| priorities[0];

        const infoRow = (label, val, last=false) =>
            `<div style="display:flex;align-items:center;min-height:42px;padding:8px 14px;${last?'':'border-bottom:1px solid #f1f3f5;'}">
                <span style="flex-shrink:0;width:120px;font-size:11.5px;color:#9ca3af;font-weight:500;">${label}</span>
                <div style="flex:1;min-width:0;">${val}</div>
            </div>`;

        /* Creator */
        const creatorVal = creator
            ? `<div style="display:flex;align-items:center;gap:8px;">${uAvatar(creator,26)}<span style="font-size:13px;color:#111827;">${esc(creator.name)}</span></div>`
            : `<span style="color:#9ca3af;font-size:13px;">—</span>`;

        /* Assignee */
        const assigneeVal = assignee
            ? `<div onclick="tpToggleDropdown('tp-drop-assignee')" style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;padding:3px 6px;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                ${uAvatar(assignee,26)}<span style="font-size:13px;color:#111827;">${esc(assignee.name)}</span>
                <i class="fas fa-chevron-down" style="font-size:9px;color:#9ca3af;"></i></div>`
            : `<div onclick="tpToggleDropdown('tp-drop-assignee')" style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;padding:3px 6px;border-radius:8px;transition:background .15s;color:#9ca3af;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                <i class="fas fa-user-plus" style="font-size:11px;"></i><span style="font-size:13px;">Unassigned</span>
                <i class="fas fa-chevron-down" style="font-size:9px;"></i></div>`;
        const assigneeDrop = `<div id="tp-drop-assignee" class="tp-people-dropdown" style="display:none;top:100%;left:0;min-width:240px;z-index:200;">
            <input type="text" placeholder="Search employee..." oninput="tpFilterDrop('tp-drop-assignee',this.value)">
            <div class="opts">
                <div class="opt${!assignee?' selected':''}" data-name="unassigned" onclick="tpSetAssignee(${taskId},null)">
                    <div style="width:24px;height:24px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-user-slash" style="font-size:10px;color:#9ca3af;"></i></div>
                    <span style="color:#9ca3af;">Unassigned</span>${!assignee?'<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:#0ea5e9;"></i>':''}
                </div>
                ${employees.map(e=>`<div class="opt${assignee&&e.id===assignee.id?' selected':''}" data-name="${esc(e.name.toLowerCase())}" onclick="tpSetAssignee(${taskId},${e.id})">
                    ${uAvatar(e,24)}<span>${esc(e.name)}</span>${assignee&&e.id===assignee.id?'<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:#0ea5e9;"></i>':''}
                </div>`).join('')}
            </div>
        </div>`;

        /* Deadline */
        const _dl=t.deadline?new Date(t.deadline):null, _now=new Date();
        const _isOvr=_dl&&_dl<_now&&t.status!=='completed';
        const _daysLeft=_dl?Math.ceil((_dl-_now)/86400000):null;
        const dlBadge=_isOvr
            ?`<span style="margin-left:8px;background:#fee2e2;color:#b91c1c;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;"><i class="fas fa-exclamation-triangle" style="margin-right:2px;font-size:9px;"></i>OVERDUE</span>`
            :_daysLeft===0?`<span style="margin-left:8px;background:#fef3c7;color:#b45309;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;">Due Today</span>`
            :_daysLeft!==null&&_daysLeft>0&&_daysLeft<=7?`<span style="margin-left:8px;background:#dcfce7;color:#15803d;font-size:10px;font-weight:600;padding:2px 8px;border-radius:10px;">${_daysLeft}d left</span>`:'';
        const _dlTimeStr=fmtTime(t.deadline);
        const deadlineVal=_dl
            ?`<div onclick="tpCalOpen(${taskId},this,'${t.deadline||''}')" style="display:inline-flex;align-items:center;cursor:pointer;padding:3px 6px;border-radius:8px;transition:background .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                <div><div style="display:flex;align-items:center;gap:0;"><span style="font-size:13px;color:${_isOvr?'#b91c1c':'#111827'};">${fmtDate(t.deadline)}</span>${dlBadge}</div>${_dlTimeStr?`<div style="color:#9ca3af;font-size:11px;margin-top:1px;">${_dlTimeStr}</div>`:''}</div></div>`
            :`<div onclick="tpCalOpen(${taskId},this,'')" style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;padding:3px 6px;border-radius:8px;transition:background .15s;color:#9ca3af;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                <i class="fas fa-calendar-plus" style="font-size:11px;"></i><span style="font-size:13px;">Set deadline</span></div>`;

        /* Status */
        const statusVal=`<div style="position:relative;display:inline-flex;">
            <div onclick="tpToggleDropdown('tp-drop-status-${taskId}')" style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;padding:4px 10px;border-radius:12px;background:${curStatus.bg};color:${curStatus.col};font-size:12px;font-weight:600;border:1.5px solid ${curStatus.col}33;transition:opacity .15s;" onmouseover="this.style.opacity='.78'" onmouseout="this.style.opacity='1'">
                <i class="fas fa-circle" style="font-size:6px;"></i>${curStatus.label}<i class="fas fa-chevron-down" style="font-size:8px;margin-left:3px;"></i>
            </div>
            <div id="tp-drop-status-${taskId}" class="tp-people-dropdown" style="display:none;top:100%;left:0;min-width:160px;z-index:200;">
                ${statuses.map(s=>`<div class="opt${t.status===s.v?' selected':''}" onclick="tpSetStatus2(${taskId},'${s.v}');tpToggleDropdown('tp-drop-status-${taskId}')">
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:10px;background:${s.bg};color:${s.col};font-size:12px;font-weight:600;"><i class="fas fa-circle" style="font-size:5px;"></i>${s.label}</span>
                    ${t.status===s.v?'<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:#0ea5e9;"></i>':''}
                </div>`).join('')}
            </div>
        </div>`;

        /* Priority */
        const priorityVal=`<div style="position:relative;display:inline-flex;">
            <div onclick="tpToggleDropdown('tp-drop-priority-${taskId}')" style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;padding:4px 10px;border-radius:12px;background:${curPriority.bg};color:${curPriority.col};font-size:12px;font-weight:600;border:1.5px solid ${curPriority.col}33;transition:opacity .15s;" onmouseover="this.style.opacity='.78'" onmouseout="this.style.opacity='1'">
                <i class="fas fa-flag" style="font-size:9px;"></i>${curPriority.label}<i class="fas fa-chevron-down" style="font-size:8px;margin-left:3px;"></i>
            </div>
            <div id="tp-drop-priority-${taskId}" class="tp-people-dropdown" style="display:none;top:100%;left:0;min-width:150px;z-index:200;">
                ${priorities.map(p=>`<div class="opt${t.priority===p.v?' selected':''}" onclick="tpSetPriority2(${taskId},'${p.v}');tpToggleDropdown('tp-drop-priority-${taskId}')">
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:10px;background:${p.bg};color:${p.col};font-size:12px;font-weight:600;"><i class="fas fa-flag" style="font-size:8px;"></i>${p.label}</span>
                    ${t.priority===p.v?'<i class="fas fa-check" style="margin-left:auto;font-size:10px;color:#0ea5e9;"></i>':''}
                </div>`).join('')}
            </div>
        </div>`;

        /* Created */
        const createdStr=t.createdAt||t.created_at||'';
        const createdVal=`<div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:13px;color:#374151;">${fmtShort(createdStr)}</span>
            <span style="font-size:11px;color:#9ca3af;background:#f1f5f9;padding:1px 7px;border-radius:8px;">#${taskId}</span>
            <button onclick="tpCopyTaskId(${taskId},this)" title="Copy ID" style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:2px 5px;border-radius:5px;font-size:11px;line-height:1;transition:color .12s;" onmouseover="this.style.color='#0ea5e9'" onmouseout="this.style.color='#9ca3af'"><i class="fas fa-copy"></i></button>
        </div>`;

        /* Info card */
        const infoCard=`<div style="background:#fff;border:1px solid #e9ecef;border-radius:12px;margin:14px 0;">
            ${infoRow('Task Owner',creatorVal)}
            ${infoRow('Assignee',`<div style="position:relative;display:inline-flex;flex-wrap:wrap;">${assigneeVal}${assigneeDrop}</div>`)}
            ${infoRow('Deadline',deadlineVal)}
            ${infoRow('Status',statusVal)}
            ${infoRow('Priority',priorityVal)}
            ${infoRow('Created',createdVal,true)}
        </div>`;

        /* People card */
        const peopleCard=`<div id="tp-people-card" style="background:#fff;border:1px solid #e9ecef;border-radius:12px;margin:0 0 14px;overflow:visible;">
            <div style="display:flex;align-items:flex-start;padding:10px 14px;border-bottom:1px solid #f1f3f5;">
                <span style="flex-shrink:0;width:120px;font-size:11.5px;color:#9ca3af;font-weight:500;padding-top:5px;">Participants</span>
                <div style="flex:1;min-width:0;position:relative;">${renderChips(participants,taskId,'member',employees)}</div>
            </div>
            <div style="display:flex;align-items:flex-start;padding:10px 14px;">
                <span style="flex-shrink:0;width:120px;font-size:11.5px;color:#9ca3af;font-weight:500;padding-top:5px;">Observers</span>
                <div style="flex:1;min-width:0;position:relative;">${renderChips(observers,taskId,'observer',employees)}</div>
            </div>
        </div>`;

        /* Project card */
        const projectCard=`<div style="background:#fff;border:1px solid #e9ecef;border-radius:12px;margin:0 0 14px;">
            <div style="display:flex;align-items:center;min-height:42px;padding:8px 14px;">
                <span style="flex-shrink:0;width:120px;font-size:11.5px;color:#9ca3af;font-weight:500;">Project</span>
                <span style="font-size:13px;color:${t.project?.name?'#374151':'#9ca3af'};">${esc(t.project?.name||'—')}</span>
            </div>
        </div>`;

        /* Description — bordered card */
        const descSection=desc?`<div style="border:1.5px solid #e2e8f0;border-radius:10px;background:#fff;margin:14px 0 12px;">
            <div style="padding:13px 15px 10px;">
                <div id="tp-desc-wrap" style="position:relative;">
                    <div id="tp-desc-content" style="color:#374151;font-size:13px;line-height:1.7;word-break:break-word;min-height:52px;max-height:220px;overflow:hidden;transition:max-height .3s ease;">${desc}</div>
                    <div id="tp-desc-fade" style="display:none;position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(transparent,#fff);pointer-events:none;"></div>
                </div>
                <button id="tp-desc-btn" onclick="tpToggleDesc()" style="display:none;margin-top:6px;background:none;border:none;color:#0ea5e9;font-size:12px;font-weight:600;cursor:pointer;padding:0;"><i class="fas fa-chevron-down" style="margin-right:4px;font-size:10px;"></i>Show more</button>
            </div>
        </div>`:'';

        /* Files section — always rendered so tab scroll always finds it */
        const localFiles = data.localFiles || [];
        const icons2={pdf:'fa-file-pdf',jpg:'fa-file-image',jpeg:'fa-file-image',png:'fa-file-image',gif:'fa-file-image',doc:'fa-file-word',docx:'fa-file-word',xls:'fa-file-excel',xlsx:'fa-file-excel',zip:'fa-file-archive',rar:'fa-file-archive'};
        const fileClr2={pdf:'#ef4444',doc:'#2563eb',docx:'#2563eb',xls:'#16a34a',xlsx:'#16a34a',ppt:'#ea580c',pptx:'#ea580c',zip:'#ca8a04',rar:'#ca8a04'};
        const fileBg2={pdf:'#fee2e2',doc:'#dbeafe',docx:'#dbeafe',xls:'#dcfce7',xlsx:'#dcfce7',ppt:'#ffedd5',pptx:'#ffedd5',zip:'#fef9c3',rar:'#fef9c3'};
        const localFileHtml = localFiles.length
            ? localFiles.map(f=>{
                const ext=(f.name||'').split('.').pop().toLowerCase();
                const ic=icons2[ext]||'fa-file';
                const clr=fileClr2[ext]||'#6b7280';
                const bg2=fileBg2[ext]||'#f1f5f9';
                const sz=f.size?(f.size>=1048576?(f.size/1048576).toFixed(1)+' MB':Math.round(f.size/1024)+' KB'):'';
                return`<a href="${f.downloadUrl||'#'}" target="_blank" rel="noopener"
                    style="display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 8px 8px;background:#fff;border:1px solid #e9ecef;border-radius:10px;text-decoration:none;width:90px;flex-shrink:0;transition:border-color .15s,box-shadow .15s;"
                    onmouseover="this.style.borderColor='${clr}';this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)'"
                    onmouseout="this.style.borderColor='#e9ecef';this.style.boxShadow='none'">
                    <div style="width:48px;height:58px;background:${bg2};border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas ${ic}" style="color:${clr};font-size:22px;"></i>
                    </div>
                    <p style="color:#374151;font-size:10.5px;font-weight:500;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:82px;width:100%;text-align:center;">${esc(f.name)}</p>
                    ${sz?`<p style="color:#9ca3af;font-size:9.5px;margin:0;">${sz}</p>`:''}
                </a>`;
            }).join('')
            : '';
        const localFilesSec = localFiles.length
            ? sec(`${sLabel('fa-paperclip',`Files (${localFiles.length})`)}<div style="display:flex;flex-wrap:wrap;gap:8px;">${localFileHtml}</div>`)
            : '';
        const filesSection=`<div id="tp-files-section">
            ${attachSec||localFilesSec||sec(`${sLabel('fa-paperclip','Files')}<p style="color:#b0bec5;font-size:12.5px;margin:0;">No files attached yet. Drag files onto this panel to attach them.</p>`)}
        </div>`;

        /* Checklist section — always rendered */
        const checklistSection=`<div id="tp-checklist-section" style="padding:14px 0 4px;">
            <p style="color:#9ca3af;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin:0 0 10px;display:flex;align-items:center;gap:6px;"><i class="fas fa-check-square" style="color:#0ea5e9;font-size:9px;"></i>Checklist</p>
            <div id="tp-cl-list-${taskId}" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px;"></div>
            <div style="display:flex;gap:6px;align-items:center;">
                <input id="tp-cl-input-${taskId}" type="text" placeholder="Add an item..." onkeydown="if(event.key==='Enter')tpClAdd(${taskId})"
                    style="flex:1;border:1.5px solid #e2e8f0;border-radius:8px;padding:6px 10px;font-size:12.5px;color:#374151;outline:none;background:#fff;transition:border-color .15s;font-family:inherit;"
                    onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                <button onclick="tpClAdd(${taskId})" style="padding:6px 14px;border-radius:8px;background:#0ea5e9;border:none;color:#fff;font-size:12px;font-weight:600;cursor:pointer;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'"><i class="fas fa-plus" style="font-size:10px;"></i></button>
            </div>
        </div>`;

        /* Functional tab bar */
        const tabDefs=[
            {label:'Files',        fn:`tpTabScroll('tp-files-section')`},
            {label:'Checklist',    fn:`tpTabScroll('tp-checklist-section')`},
            {label:'Participants', fn:`tpTabPeople('tp-drop-member-${taskId}')`},
            {label:'Observers',    fn:`tpTabPeople('tp-drop-observer-${taskId}')`},
        ];
        const tabBar=`<div style="overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;margin:0 0 16px;"><div style="display:flex;gap:6px;min-width:max-content;">
            ${tabDefs.map(tab=>`<button onclick="${tab.fn}" style="flex-shrink:0;padding:5px 14px;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;color:#6b7280;font-size:12px;font-weight:400;cursor:pointer;white-space:nowrap;transition:all .12s;" onmouseover="this.style.background='#f1f5f9';this.style.borderColor='#cbd5e1';" onmouseout="this.style.background='#f8fafc';this.style.borderColor='#e2e8f0';">${tab.label}</button>`).join('')}
        </div></div>`;

        return descSection+filesSection+checklistSection+infoCard+peopleCard+projectCard+tabBar;
    })();

    // ── Description expand check ──
    if (desc) {
        setTimeout(() => {
            const content = document.getElementById('tp-desc-content');
            const btn     = document.getElementById('tp-desc-btn');
            const fade    = document.getElementById('tp-desc-fade');
            if (content && btn && content.scrollHeight > content.clientHeight + 4) {
                btn.style.display  = 'block';
                fade.style.display = 'block';
            }
        }, 60);
    }

    // ── Bottom action bar ──
    const isCompleted  = t.status === 'completed';
    const isInProgress = t.status === 'in_progress';

    const startBtn = (!isCompleted && !isInProgress)
        ? `<button onclick="tpSetStatusQuick(${taskId},'in_progress',this)" style="padding:7px 18px;border-radius:8px;background:#0ea5e9;border:none;color:#fff;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .15s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'"><i class="fas fa-play" style="margin-right:5px;font-size:11px;"></i>Start</button>`
        : '';
    const completeBtn = !isCompleted
        ? `<button onclick="tpSetStatusQuick(${taskId},'completed',this)" style="padding:7px 18px;border-radius:8px;background:#fff;border:1.5px solid #e2e8f0;color:#374151;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;" onmouseover="this.style.borderColor='#15803d';this.style.color='#15803d'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#374151'"><i class="fas fa-check" style="margin-right:5px;font-size:11px;"></i>Complete</button>`
        : '';
    const resumeBtn = isCompleted
        ? `<button onclick="tpSetStatusQuick(${taskId},'new',this)" style="padding:7px 18px;border-radius:8px;background:#fff;border:1.5px solid #e2e8f0;color:#374151;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;" onmouseover="this.style.borderColor='#0ea5e9';this.style.color='#0ea5e9'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#374151'"><i class="fas fa-redo" style="margin-right:5px;font-size:11px;"></i>Resume</button>`
        : '';
    const actionBtn = resumeBtn + startBtn + completeBtn;

    $('tp-left-footer').innerHTML = `
        <div style="display:flex;align-items:center;gap:8px;">
            ${actionBtn}
            <div style="position:relative;">
                <button onclick="tpToggleDropdown('tp-more-menu')" style="width:34px;height:34px;border-radius:8px;background:#fff;border:1.5px solid #e2e8f0;color:#6b7280;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='#fff'">
                    <i class="fas fa-ellipsis-h" style="font-size:13px;"></i>
                </button>
                <div id="tp-more-menu" class="tp-people-dropdown" style="display:none;bottom:42px;left:0;min-width:170px;">
                    <div class="opt" style="color:#ef4444;"
                         onclick="tpDeleteTask(${taskId})">
                        <i class="fas fa-trash" style="color:#ef4444;width:16px;"></i>Delete Task
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:16px;">
            <span style="color:#9ca3af;font-size:12.5px;cursor:pointer;transition:color .15s;" onmouseover="this.style.color='#f59e0b'" onmouseout="this.style.color='#9ca3af'"><i class="fas fa-star" style="margin-right:4px;font-size:11px;"></i>Rate task</span>
            <span style="color:#9ca3af;font-size:12.5px;display:flex;align-items:center;gap:4px;" title="Observers"><i class="fas fa-eye" style="font-size:11px;"></i>${observers.length}</span>
        </div>`;

    // Load checklists from server data
    _tpChecklists[taskId] = (data.checklists || []);
    tpClRender(taskId);

    tpRenderLocalFeed(data, taskId);
    setTimeout(()=>{ const m=$('tp-messages'); m.scrollTop=m.scrollHeight; },80);
    $('tp-comment-footer').innerHTML=`
        <div style="display:flex;gap:8px;align-items:flex-end;">
            <div style="flex:1;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:9px;overflow:hidden;transition:border-color .15s;" id="tp-comment-box">
                <textarea id="tp-comment-text" rows="2" placeholder="Write a comment..."
                    style="display:block;width:100%;background:none;border:none;padding:9px 12px 6px;color:#111827;font-size:12.5px;resize:none;outline:none;line-height:1.5;font-family:inherit;box-sizing:border-box;"
                    onfocus="document.getElementById('tp-comment-box').style.borderColor='#0ea5e9'"
                    onblur="document.getElementById('tp-comment-box').style.borderColor='#e2e8f0'"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();tpSubmitComment(${taskId});}"></textarea>
                <div style="display:flex;align-items:center;gap:2px;padding:4px 8px;border-top:1px solid #e9ecef;">
                    <button type="button" onclick="emojiToggle('tp-comment-text',this)"
                            title="Emoji" style="background:none;border:none;color:#94a3b8;cursor:pointer;padding:3px 5px;border-radius:6px;font-size:15px;line-height:1;transition:color .12s;"
                            onmouseover="this.style.color='#0ea5e9'" onmouseout="this.style.color='#94a3b8'">
                        <i class="far fa-smile-beam"></i>
                    </button>
                    <button type="button" onclick="document.getElementById('tp-file-input').click()"
                            title="Attach file" style="background:none;border:none;color:#94a3b8;cursor:pointer;padding:3px 5px;border-radius:6px;font-size:14px;line-height:1;transition:color .12s;"
                            onmouseover="this.style.color='#0ea5e9'" onmouseout="this.style.color='#94a3b8'">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <input type="file" id="tp-file-input" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                           onchange="uploadAndInsert('tp-comment-text','tp-file-input','tp-attach-preview')">
                </div>
                <div id="tp-attach-preview" style="display:none;padding:6px 8px 4px;gap:8px;flex-wrap:wrap;border-top:1px solid #e9ecef;"></div>
            </div>
            <button onclick="tpSubmitComment(${taskId})"
                style="background:#0ea5e9;border:none;border-radius:9px;padding:9px 16px;color:#fff;font-size:12.5px;font-weight:700;cursor:pointer;transition:opacity .15s;white-space:nowrap;height:38px;flex-shrink:0;align-self:flex-start;margin-top:2px;"
                onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                <i class="fas fa-paper-plane" style="margin-right:4px;font-size:10px;"></i>Send
            </button>
        </div>
        <p style="color:#9ca3af;font-size:10px;margin:5px 0 0 2px;">Enter to send &nbsp;·&nbsp; Shift+Enter for new line</p>`;
}

/* ─── tab navigation helpers ───────────────────────────── */
window.tpTabScroll = function(id) {
    const el = document.getElementById(id);
    const container = document.getElementById('tp-left-body');
    if (!el || !container) return;
    const rect = el.getBoundingClientRect();
    const cRect = container.getBoundingClientRect();
    container.scrollBy({top: rect.top - cRect.top - 12, behavior: 'smooth'});
};
window.tpTabPeople = function(dropId) {
    const card = document.getElementById('tp-people-card');
    const container = document.getElementById('tp-left-body');
    if (card && container) {
        const rect = card.getBoundingClientRect();
        const cRect = container.getBoundingClientRect();
        container.scrollBy({top: rect.top - cRect.top - 12, behavior: 'smooth'});
    }
    setTimeout(() => tpToggleDropdown(dropId), 280);
};

/* ─── checklist (AJAX) ─────────────────────────────────── */
const _tpChecklists = {};

function tpClRender(taskId) {
    const list = document.getElementById('tp-cl-list-' + taskId);
    if (!list) return;
    const items = _tpChecklists[taskId] || [];
    const done  = items.filter(i => i.is_complete).length;
    const pct   = items.length ? Math.round(done / items.length * 100) : 0;
    list.innerHTML = (items.length ? `
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <div style="flex:1;height:5px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                <div style="height:100%;width:${pct}%;background:#10b981;border-radius:4px;transition:width .3s ease;"></div>
            </div>
            <span style="font-size:11px;color:#6b7280;font-weight:600;white-space:nowrap;">${done}/${items.length}</span>
        </div>` : '') +
        items.map(item => `
        <div style="display:flex;align-items:center;gap:8px;padding:5px 0;">
            <input type="checkbox" ${item.is_complete ? 'checked' : ''} onchange="tpClToggle(${taskId},${item.id},this)"
                style="width:15px;height:15px;accent-color:#10b981;cursor:pointer;flex-shrink:0;">
            <span style="flex:1;font-size:13px;color:${item.is_complete ? '#9ca3af' : '#374151'};${item.is_complete ? 'text-decoration:line-through;' : ''}">${esc(item.text)}</span>
            <button onclick="tpClDelete(${taskId},${item.id},this)" style="background:none;border:none;color:#d1d5db;cursor:pointer;padding:2px 4px;border-radius:4px;transition:color .12s;line-height:1;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#d1d5db'"><i class="fas fa-times" style="font-size:10px;"></i></button>
        </div>`).join('');
}

window.tpClAdd = function(taskId) {
    const inp  = document.getElementById('tp-cl-input-' + taskId);
    const text = (inp.value || '').trim();
    if (!text) { inp.focus(); return; }
    inp.disabled = true;
    fetch(TP_LOCAL_URL + '/' + taskId + '/checklist', {
        method:'POST', headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json','Content-Type':'application/json'},
        body: JSON.stringify({text})
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            if (!_tpChecklists[taskId]) _tpChecklists[taskId] = [];
            _tpChecklists[taskId].push(d.item);
            inp.value = '';
            tpClRender(taskId);
        }
    }).finally(()=>{ inp.disabled=false; inp.focus(); });
};

window.tpClToggle = function(taskId, itemId, cb) {
    fetch(TP_LOCAL_URL + '/' + taskId + '/checklist/' + itemId, {
        method:'PATCH', headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            const item = (_tpChecklists[taskId]||[]).find(i=>i.id===itemId);
            if (item) item.is_complete = d.is_complete;
            tpClRender(taskId);
        }
    }).catch(()=>{ if(cb) cb.checked = !cb.checked; });
};

window.tpClDelete = function(taskId, itemId) {
    fetch(TP_LOCAL_URL + '/' + taskId + '/checklist/' + itemId, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}
    }).then(r=>r.json()).then(d=>{
        if (d.ok) {
            if (_tpChecklists[taskId]) _tpChecklists[taskId] = _tpChecklists[taskId].filter(i=>i.id!==itemId);
            tpClRender(taskId);
        }
    });
};

/* ─── real-time polling ────────────────────────────────── */
let _chatPollInterval = null;
let _pollFeedCount    = -1;
let _pollClHash       = '';
let _pollTaskHash     = '';

function _clHash(items) {
    return (items||[]).map(i=>i.id+':'+i.is_complete).join(',');
}
function _taskHash(data) {
    const t  = data.task       || {};
    const a  = data.assignee   || {};
    const ps = (data.participants||[]).map(p=>p.id).join(',');
    const os = (data.observers ||[]).map(o=>o.id).join(',');
    return [t.status,t.priority,t.deadline,t.title,t.description,
            a.id||'',ps,os, (t.project&&t.project.id)||''].join('|');
}

window.tpStartChatPoll = function(taskId) {
    tpStopChatPoll();
    _pollFeedCount = -1;
    _pollClHash    = _clHash(_tpChecklists[taskId]);
    _pollTaskHash  = '';
    _chatPollInterval = setInterval(function() {
        if (!_currentTaskId) { tpStopChatPoll(); return; }
        fetch(TP_LOCAL_URL + '/' + taskId, {
            headers: {'X-CSRF-TOKEN': TP_CSRF, 'Accept': 'application/json'}
        }).then(r => r.json()).then(data => {

            /* feed / comments */
            const feed = data.feed || [];
            if (feed.length !== _pollFeedCount) {
                _pollFeedCount = feed.length;
                tpRenderLocalFeed(data, taskId);
            }

            /* checklist */
            const newClHash = _clHash(data.checklists);
            if (newClHash !== _pollClHash) {
                _pollClHash = newClHash;
                _tpChecklists[taskId] = data.checklists || [];
                tpClRender(taskId);
            }

            /* task metadata — status, priority, assignee, deadline, participants, observers, title, desc */
            const newTaskHash = _taskHash(data);
            if (_pollTaskHash && newTaskHash !== _pollTaskHash) {
                _pollTaskHash  = newTaskHash;
                _pollClHash    = _clHash(data.checklists);
                _pollFeedCount = feed.length;
                tpRenderLocal(data);
            } else {
                _pollTaskHash = newTaskHash;
            }

        }).catch(() => {});
    }, 3000);
};
window.tpStopChatPoll = function() {
    if (_chatPollInterval) { clearInterval(_chatPollInterval); _chatPollInterval = null; }
};

/* ─── feed renderer (called on open + by poller) ──────── */
window.tpRenderLocalFeed = function(data, taskId) {
    const feed = data.feed || [];
    const comments = feed.filter(f => f.type === 'comment');
    $('tp-chat-title').textContent = 'Comments';
    $('tp-chat-count').textContent = comments.length ? comments.length + ' comments' : '';

    // spacer pushes messages to the bottom when few messages exist
    const _spacer = '<div style="flex:1;min-height:0;pointer-events:none;"></div>';

    if (feed.length) {
        const localColors = ['#0ea5e9','#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
        const localColor = name => localColors[(name||'').charCodeAt(0) % localColors.length];
        let lastDay2 = '', lastAuthor2 = null;
        $('tp-messages').innerHTML = _spacer + feed.map(f => {
            const iso = f.at || f.created_at || '';
            const day = chatDayKey(iso);
            const div = day !== lastDay2 ? (lastDay2 = day, chatDivider(iso)) : '';
            const time = fmtTimeOnly(iso);
            if (f.type === 'activity') {
                const authorName = typeof f.author === 'string' ? f.author : (f.author?.name || 'System');
                const fmtActVal = (v, field) => {
                    if(field === 'deadline' && v) {
                        const d = new Date(v);
                        if(!isNaN(d)) return fmtDate(v) + ' ' + fmtTime(v);
                    }
                    return esc(v);
                };
                let actText = esc(authorName) + ' ';
                if (f.action) actText += f.action.replace(/_/g, ' ');
                if (f.field) actText += ' <span style="opacity:.7;">' + f.field + '</span>';
                if (f.old && f.new) actText += ' from <em>' + fmtActVal(f.old, f.field) + '</em> → <em>' + fmtActVal(f.new, f.field) + '</em>';
                else if (f.new) actText += ' → <em>' + fmtActVal(f.new, f.field) + '</em>';
                lastAuthor2 = null;
                return div + chatBubble({isSystem:true, text:actText, time});
            }
            if (f.isSystem) {
                lastAuthor2 = null;
                return div + chatBubble({isSystem:true, text:parseMsg(f.text||f.content||''), time});
            }
            const u = typeof f.author === 'object' ? f.author : {name: f.author || '?'};
            const isMine = u.id ? parseInt(u.id) === ME_LOCAL_ID : false;
            const showName = !isMine && u.name !== lastAuthor2;
            lastAuthor2 = u.name;
            return div + chatBubble({isMine, name:u.name||'?', nameColor:localColor(u.name||''), text:parseMsg(f.text||f.content||''), time, showName, files:f.files||[]});
        }).join('');
    } else {
        $('tp-messages').innerHTML = _spacer + `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 0;"><i class="fas fa-comment-slash" style="font-size:28px;color:rgba(255,255,255,.25);margin-bottom:10px;"></i><p style="color:rgba(255,255,255,.45);font-size:12.5px;margin:0;">No comments yet — be the first!</p></div>`;
    }

    setTimeout(() => {
        const m = $('tp-messages');
        const nearBottom = m.scrollHeight - m.scrollTop - m.clientHeight < 120;
        if(nearBottom) m.scrollTop = m.scrollHeight;
        const imgs = [...m.querySelectorAll('img[style*="cursor:zoom-in"]')];
        if (imgs.length > 1 && window.registerGallery) {
            const gk = window.registerGallery(imgs.map(i => i.src));
            imgs.forEach((img, idx) => img.setAttribute('onclick', `imgLightbox(${gk},${idx})`));
        }
    }, 50);
};

/* ─── description expand/collapse ─────────────────────── */
window.tpToggleDesc = function() {
    const content  = document.getElementById('tp-desc-content');
    const btn      = document.getElementById('tp-desc-btn');
    const fade     = document.getElementById('tp-desc-fade');
    const expanded = content.style.maxHeight === 'none';
    if (expanded) {
        content.style.maxHeight = '280px';
        fade.style.display      = 'block';
        btn.innerHTML = '<i class="fas fa-chevron-down" style="margin-right:4px;font-size:10px;"></i>Show more';
    } else {
        content.style.maxHeight = 'none';
        fade.style.display      = 'none';
        btn.innerHTML = '<i class="fas fa-chevron-up" style="margin-right:4px;font-size:10px;"></i>Show less';
    }
};

/* ─── status / priority setters ───────────────────────── */
window.tpSetStatus=function(taskId,value,el){
    document.querySelectorAll('#tp-left-body .tp-pill[onclick*="tpSetStatus"]').forEach(p=>{
        p.classList.remove('active');
        p.style.borderColor='transparent';
    });
    el.classList.add('active');
    el.style.borderColor=getComputedStyle(el).color;
    tpUpdateField(taskId,'status',value,()=>{ fetch(TP_LOCAL_URL+'/'+taskId,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>tpRenderLocal(d)); });
};
window.tpSetPriority=function(taskId,value,el){
    document.querySelectorAll('#tp-left-body .tp-pill[onclick*="tpSetPriority"]').forEach(p=>{
        p.classList.remove('active');
        p.style.borderColor='transparent';
    });
    el.classList.add('active');
    el.style.borderColor=getComputedStyle(el).color;
    tpUpdateField(taskId,'priority',value);
};
/* ─── kanban card live update ──────────────────────────── */
function kbUpdateCard(taskId) {
    if (!document.getElementById('kb-col-overdue')) return; // not on kanban page
    fetch('{{ url("/api/kanban-task") }}/' + taskId, {headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}})
    .then(r=>r.json()).then(d=>{
        const card = document.getElementById('kb-task-' + taskId);
        if (!card) return;
        // Update priority/status badges inline
        const badges = card.querySelectorAll('span[style*="font-size:10px"]');
        const priColors = {low:['#e2e8f0','#475569'],medium:['#dbeafe','#1d4ed8'],high:['#ffedd5','#c2410c'],urgent:['#fee2e2','#b91c1c']};
        const stColors  = {new:['#f1f5f9','#334155','#cbd5e1'],in_progress:['#dbeafe','#1d4ed8','#bfdbfe'],paused:['#fef3c7','#b45309','#fde68a'],completed:['#dcfce7','#166534','#bbf7d0']};
        if(badges[0]&&priColors[d.priority]){ badges[0].style.background=priColors[d.priority][0]; badges[0].style.color=priColors[d.priority][1]; badges[0].textContent=d.priority.toUpperCase(); }
        if(badges[1]&&stColors[d.status])  { const sc=stColors[d.status]; badges[1].style.background=sc[0]; badges[1].style.color=sc[1]; badges[1].style.borderColor=sc[2]; badges[1].textContent=d.status.replace('_',' ').replace(/\b\w/g,c=>c.toUpperCase()); }
        // Move card to correct column
        const targetCol = document.getElementById('kb-col-' + d.col);
        if (targetCol && card.parentElement !== targetCol) {
            card.parentElement.removeChild(card);
            targetCol.insertBefore(card, targetCol.firstChild);
            // Update counters
            document.querySelectorAll('[id^="kb-col-"]').forEach(col=>{
                const key = col.id.replace('kb-col-','');
                const cnt = document.getElementById('kb-count-' + key);
                if (cnt) cnt.textContent = col.querySelectorAll('[data-task-id]').length;
            });
        }
    }).catch(()=>{});
}

window.tpSetStatusQuick=function(taskId,value){ tpUpdateField(taskId,'status',value,()=>{ fetch(TP_LOCAL_URL+'/'+taskId,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{ tpRenderLocal(d); kbUpdateCard(taskId); }); }); };
window.tpSetStatus2=function(taskId,value){ tpUpdateField(taskId,'status',value,()=>{ fetch(TP_LOCAL_URL+'/'+taskId,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{ tpRenderLocal(d); kbUpdateCard(taskId); }); }); };
window.tpSetPriority2=function(taskId,value){ tpUpdateField(taskId,'priority',value,()=>{ fetch(TP_LOCAL_URL+'/'+taskId,{headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{ tpRenderLocal(d); kbUpdateCard(taskId); }); }); };
window.tpCopyTaskId=function(id,btn){ navigator.clipboard.writeText('#'+id).then(()=>{ btn.innerHTML='<i class="fas fa-check" style="color:#10b981;"></i>'; setTimeout(()=>{ btn.innerHTML='<i class="fas fa-copy"></i>'; },1200); }); };

window.tpDeleteTask = function(taskId) {
    if (typeof appDeleteConfirm !== 'function') { if (!confirm('Delete this task?')) return; }
    appDeleteConfirm(TP_TASKS_URL + '/' + taskId, function() {
        tpClose();
        setTimeout(function() { location.reload(); }, 200);
    });
};

/* ─── edit task — opens the full create-task panel ────── */
window.tpEditOpenFull = function(taskId, data) {
    const t          = data.task        || {};
    const assignee   = data.assignee    || null;
    const parts      = data.participants|| [];
    const obs        = data.observers   || [];

    // Pre-fill title + description
    const titleEl = document.getElementById('nt-title-input');
    const descEl  = document.getElementById('nt-desc-ta');
    if (titleEl) titleEl.value = t.title                         || '';
    if (descEl)  descEl.value  = parseDescText(t.description || '');

    // Pre-fill existing attachments
    if (window.ntSetAttachments) {
        const re = /\[img\]([\s\S]*?)\[\/img\]|\[file name="([^"]*)"\]([\s\S]*?)\[\/file\]/g;
        const attList = []; let m;
        while((m = re.exec(t.description||'')) !== null){
            if(m[1]!==undefined) attList.push({name: m[1].trim().split('/').pop(), url: m[1].trim()});
            else attList.push({name: m[2], url: m[3].trim()});
        }
        ntSetAttachments(attList);
    }

    // Pre-fill status pill
    const sPill = document.querySelector(`.nt-pill[data-group="status"][data-val="${t.status}"]`);
    if (sPill && window.ntSetPill) ntSetPill(sPill, 'status');

    // Pre-fill priority pill
    const pPill = document.querySelector(`.nt-pill[data-group="priority"][data-val="${t.priority}"]`);
    if (pPill && window.ntSetPill) ntSetPill(pPill, 'priority');

    // Pre-fill deadline
    if (window.ntSetDeadline) ntSetDeadline(t.deadline || null);

    // Pre-fill assignee
    if (window.ntPickAssignee) {
        if (assignee) {
            const colors=['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
            const bg = colors[(assignee.name||'').charCodeAt(0) % colors.length];
            ntPickAssignee(assignee.id, assignee.avatar || '', assignee.name, bg, (assignee.name||'?').charAt(0).toUpperCase());
        } else {
            ntPickAssignee(0,'','');
        }
    }

    // Pre-fill participants checkboxes
    document.querySelectorAll('#nt-participants-opts input[type="checkbox"]').forEach(cb => {
        cb.checked = parts.some(p => String(p.id) === cb.value);
    });
    if (window.ntUpdateParticipantsTrigger) ntUpdateParticipantsTrigger();

    // Pre-fill observers checkboxes
    document.querySelectorAll('#nt-observers-opts input[type="checkbox"]').forEach(cb => {
        cb.checked = obs.some(o => String(o.id) === cb.value);
    });
    if (window.ntUpdateObserversTrigger) ntUpdateObserversTrigger();

    // Pre-fill project
    const projSel = document.querySelector('#nt-form select[name="project_id"]');
    if (projSel) projSel.value = t.project ? t.project.id : '';

    // Enter edit mode (changes badge, button, form action)
    if (window.ntEnterEditMode) {
        ntEnterEditMode(taskId, TP_TASKS_URL + '/' + taskId, function() {
            fetch(TP_LOCAL_URL + '/' + taskId, {headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}})
                .then(r => r.json()).then(d => { tpRenderLocal(d); tpStartChatPoll(taskId); kbUpdateCard(taskId); });
        });
    }

    // Suppress draft restore for this open
    const origRestore = window.ntRestoreDraft;
    window.ntRestoreDraft = function() {};
    if (window.openTaskModal) openTaskModal();
    setTimeout(function(){ window.ntRestoreDraft = origRestore; }, 200);
};

/* ─── edit task modal (simple fallback) ───────────────── */
let _tpEditTaskId = null, _tpEditEmployees = [];

window.tpEditOpen = function(taskId, data) {
    _tpEditTaskId = taskId;
    _tpEditEmployees = data.employees || [];
    const t = data.task || {};

    document.getElementById('tpe-title').value   = t.title       || '';
    document.getElementById('tpe-desc').value    = parseDescText(t.description || '');
    document.getElementById('tpe-status').value  = t.status      || 'new';
    document.getElementById('tpe-priority').value= t.priority    || 'medium';
    document.getElementById('tpe-deadline').value= t.deadline ? fmtLocal(t.deadline) : '';

    const sel = document.getElementById('tpe-assigned');
    sel.innerHTML = '<option value="">Unassigned</option>' +
        _tpEditEmployees.map(e =>
            `<option value="${e.id}"${e.id == t.assigned_to ? ' selected' : ''}>${esc(e.name)}</option>`
        ).join('');

    document.getElementById('tp-edit-modal').style.display = 'flex';
    document.getElementById('tpe-title').focus();
};
window.tpEditClose = function() {
    document.getElementById('tp-edit-modal').style.display = 'none';
};
window.tpEditSubmit = function() {
    const taskId = _tpEditTaskId;
    if (!taskId) return;
    const title = document.getElementById('tpe-title').value.trim();
    if (!title) { document.getElementById('tpe-title').focus(); return; }

    const body = {
        title,
        description: document.getElementById('tpe-desc').value,
        status:      document.getElementById('tpe-status').value,
        priority:    document.getElementById('tpe-priority').value,
        assigned_to: document.getElementById('tpe-assigned').value || null,
        deadline:    document.getElementById('tpe-deadline').value || null,
    };
    const btn = document.getElementById('tpe-save-btn');
    btn.disabled = true; btn.style.opacity = '.6';

    fetch(TP_TASKS_URL + '/' + taskId, {
        method: 'PUT',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'},
        body: JSON.stringify(body),
    }).then(r => r.json()).then(resp => {
        if (resp.success) {
            tpEditClose();
            fetch(TP_LOCAL_URL + '/' + taskId, {headers:{'X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'}})
                .then(r => r.json()).then(d => { tpRenderLocal(d); tpStartChatPoll(taskId); kbUpdateCard(taskId); });
        } else {
            alert(resp.message || 'Could not save changes.');
        }
    }).catch(() => alert('Could not save changes.'))
    .finally(() => { btn.disabled = false; btn.style.opacity = '1'; });
};

/* ─── submit comment ───────────────────────────────────── */
window.tpSubmitComment=function(taskId){
    const ta=$('tp-comment-text');
    const txt=(ta.value||'').trim();
    const attachTags=window.getAttachmentTags?window.getAttachmentTags('tp-comment-text'):'';
    if(!txt&&!attachTags){ ta.focus(); return; }
    const fullContent=txt+(attachTags?(txt?'\n':'')+attachTags:'');
    const btn=$('tp-comment-footer').querySelector('button[onclick*="tpSubmitComment"]');
    if(btn){btn.disabled=true;btn.style.opacity='.5';}
    fetch(TP_LOCAL_URL+'/'+taskId+'/comment',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':TP_CSRF,'Accept':'application/json'},
        body:JSON.stringify({content:fullContent}),
    }).then(r=>r.json()).then(resp=>{
        ta.value='';
        if(window.clearAttachments) window.clearAttachments('tp-comment-text','tp-attach-preview');
        const now=new Date(), time=(''+now.getHours()).padStart(2,'0')+':'+(''+now.getMinutes()).padStart(2,'0');
        const el=document.createElement('div');
        el.innerHTML=chatBubble({isMine:true, text:parseMsg(fullContent), time, showName:false});
        const msgs=$('tp-messages');
        // remove "no comments" placeholder if present
        const ph=msgs.querySelector('[style*="comment-slash"]'); if(ph) ph.closest('div').remove();
        msgs.appendChild(el.firstElementChild); msgs.scrollTop=msgs.scrollHeight;
    }).catch(()=>alert('Could not send comment.')).finally(()=>{ if(btn){btn.disabled=false;btn.style.opacity='1';} });
};

// ── Drag-and-drop on comments area only ──
(function() {
    var zone    = document.getElementById('tp-right');
    var overlay = document.getElementById('tp-drop-overlay');
    if (!zone || !overlay) return;

    function hideOverlay() { overlay.style.display = 'none'; }

    zone.addEventListener('dragenter', function(e) {
        if (!e.dataTransfer || !Array.from(e.dataTransfer.types).includes('Files')) return;
        e.preventDefault();
        overlay.style.display = 'flex';
    });

    zone.addEventListener('dragleave', function(e) {
        // Only hide when cursor truly leaves the zone (not just moves to a child)
        if (!e.relatedTarget || !zone.contains(e.relatedTarget)) hideOverlay();
    });

    zone.addEventListener('dragover', function(e) {
        if (!e.dataTransfer || !Array.from(e.dataTransfer.types).includes('Files')) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    zone.addEventListener('drop', async function(e) {
        e.preventDefault();
        hideOverlay();
        var files = Array.from(e.dataTransfer.files);
        if (!files.length) return;
        var ta = document.getElementById('tp-comment-text');
        if (!ta) { alert('Open a task first to attach files.'); return; }
        for (var i = 0; i < files.length; i++) {
            await window.uploadFileDirect(files[i], 'tp-comment-text', 'tp-attach-preview');
        }
    });

    // Fallback: ESC or drop outside browser — hide overlay
    document.addEventListener('dragend', hideOverlay);
    document.addEventListener('drop', function(e) { if (!zone.contains(e.target)) hideOverlay(); });
})();

})();
</script>
