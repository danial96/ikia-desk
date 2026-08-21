@extends('layouts.app')
@section('title', 'Messenger')
@section('page-title', 'Messenger')

@section('content')
<script>
// Remove main padding so chat fills the full content area
document.addEventListener('DOMContentLoaded', function() {
    const mc = document.getElementById('main-content');
    if (mc) { mc.style.overflow = 'hidden'; mc.style.height = '100vh'; mc.style.minHeight = 'unset'; }
    const main = document.querySelector('#main-content main');
    if (main) { main.style.padding = '0'; main.style.overflow = 'hidden'; main.style.display = 'flex'; main.style.flexDirection = 'column'; main.style.flex = '1'; main.style.minHeight = '0'; }
});
</script>
{{-- Fill the full available height --}}
<div id="cp-wrap" style="display:flex;flex:1;min-height:0;overflow:hidden;">

    {{-- ═══ LEFT: Conversation list ═══ --}}
    <div id="cp-left" style="width:300px;flex-shrink:0;display:flex;flex-direction:column;background:rgba(10,15,60,.96);border-right:1px solid rgba(255,255,255,.1);">

        {{-- Header --}}
        <div style="padding:16px 16px 12px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:8px;flex-shrink:0;">
            <i class="fas fa-comment-dots" style="color:#00D4E8;font-size:17px;"></i>
            <span style="color:#fff;font-size:15px;font-weight:700;flex:1;">Messenger</span>
        </div>

        {{-- Search --}}
        <div style="padding:12px 14px 8px;flex-shrink:0;">
            <div style="display:flex;align-items:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.1);border-radius:9px;padding:0 12px;gap:7px;">
                <i class="fas fa-search" style="font-size:12px;color:rgba(255,255,255,.3);"></i>
                <input type="text" id="cp-search" placeholder="Search chats..."
                       oninput="cpFilterConvs(this.value)"
                       style="background:none;border:none;outline:none;color:#fff;font-size:13px;padding:8px 0;width:100%;font-family:inherit;">
            </div>
        </div>

        {{-- New chat buttons --}}
        <div style="padding:0 14px 10px;display:flex;gap:6px;flex-shrink:0;">
            <button onclick="cpNewDirect()" style="flex:1;background:rgba(0,212,232,.12);border:1px solid rgba(0,212,232,.28);color:#00D4E8;border-radius:8px;padding:6px 0;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;"
                    onmouseover="this.style.background='rgba(0,212,232,.22)'" onmouseout="this.style.background='rgba(0,212,232,.12)'">
                <i class="fas fa-user-plus" style="font-size:10px;margin-right:4px;"></i>Direct
            </button>
            <button onclick="cpNewGroup()" style="flex:1;background:rgba(139,92,246,.12);border:1px solid rgba(139,92,246,.28);color:#a78bfa;border-radius:8px;padding:6px 0;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;"
                    onmouseover="this.style.background='rgba(139,92,246,.22)'" onmouseout="this.style.background='rgba(139,92,246,.12)'">
                <i class="fas fa-users" style="font-size:10px;margin-right:4px;"></i>Group
            </button>
        </div>

        {{-- Conversation list --}}
        <div id="cp-conv-list" style="flex:1;overflow-y:auto;">
            <div style="padding:40px 16px;text-align:center;color:rgba(255,255,255,.6);font-size:12px;">
                <i class="fas fa-spinner fa-spin" style="display:block;font-size:22px;margin-bottom:10px;"></i>Loading...
            </div>
        </div>
    </div>

    {{-- ═══ RIGHT: Active conversation ═══ --}}
    <div id="cp-right" style="flex:1;display:flex;flex-direction:column;background:rgba(8,12,45,.97);min-width:0;position:relative;">

        {{-- Drag-and-drop overlay --}}
        <div id="cp-drop-overlay"
             style="display:none;position:absolute;inset:0;z-index:999;background:rgba(5,10,40,.72);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:3px dashed rgba(0,212,232,.85);pointer-events:none;flex-direction:column;align-items:center;justify-content:center;gap:20px;">
            <div style="width:90px;height:90px;border-radius:50%;background:rgba(0,212,232,.2);border:2px solid rgba(0,212,232,.45);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-cloud-upload-alt" style="font-size:36px;color:#00D4E8;"></i>
            </div>
            <div style="text-align:center;">
                <p style="color:#fff;font-size:20px;font-weight:800;margin:0 0 8px;letter-spacing:.3px;">Drop files to attach</p>
                <p style="color:rgba(255,255,255,.65);font-size:13px;margin:0;">Images, PDF, DOCX, XLS and more</p>
            </div>
        </div>

        {{-- Empty state --}}
        <div id="cp-right-empty" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;background-image:url('{{ asset('pattern-chat.svg') }}');background-size:cover;background-position:center;">
            <i class="fas fa-comments" style="font-size:56px;margin-bottom:16px;color:rgba(255,255,255,.6);"></i>
            <p style="font-size:14px;margin:0;font-weight:600;color:rgba(255,255,255,.75);">Select a conversation to start chatting</p>
        </div>

        {{-- Header --}}
        <div id="cp-right-head" style="display:none;padding:13px 20px;border-bottom:1px solid rgba(255,255,255,.08);align-items:center;gap:12px;background:rgba(255,255,255,.04);flex-shrink:0;">
            <div id="cp-rh-avatar" style="width:38px;height:38px;border-radius:50%;overflow:hidden;flex-shrink:0;background:rgba(0,212,232,.2);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-user" style="font-size:16px;color:#00D4E8;"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <p id="cp-rh-name" style="margin:0;color:#fff;font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></p>
                <p id="cp-rh-sub" style="margin:0;color:rgba(255,255,255,.35);font-size:11.5px;"></p>
            </div>
        </div>

        {{-- Messages --}}
        <div id="cp-msg-area" style="display:none;flex:1;overflow-y:auto;padding:20px;background-image:url('{{ asset('pattern-chat.svg') }}');background-size:cover;background-position:center;flex-direction:column;"></div>

        {{-- Input --}}
        <div id="cp-input-area" style="display:none;padding:10px 16px 14px;border-top:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.04);flex-shrink:0;">
            {{-- Normal input --}}
            <div id="cp-normal-input" style="display:flex;align-items:flex-end;gap:8px;">
                <div style="flex:1;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:12px;overflow:hidden;">
                    <textarea id="cp-textarea" rows="1" placeholder="Type a message..."
                              style="display:block;width:100%;background:none;border:none;color:#fff;font-size:13.5px;padding:10px 14px 7px;outline:none;resize:none;font-family:inherit;line-height:1.5;max-height:140px;overflow-y:auto;box-sizing:border-box;"
                              onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();cpSend();}"
                              oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,140)+'px'"></textarea>
                    <div style="display:flex;align-items:center;gap:2px;padding:4px 10px;border-top:1px solid rgba(255,255,255,.07);">
                        <button type="button" onclick="emojiToggle('cp-textarea',this)"
                                title="Emoji" style="background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;padding:4px 6px;border-radius:6px;font-size:16px;line-height:1;transition:color .12s;"
                                onmouseover="this.style.color='#00D4E8'" onmouseout="this.style.color='rgba(255,255,255,.4)'">
                            <i class="far fa-smile-beam"></i>
                        </button>
                        <button type="button" onclick="document.getElementById('cp-file-input').click()"
                                title="Attach file" style="background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;padding:4px 6px;border-radius:6px;font-size:15px;line-height:1;transition:color .12s;"
                                onmouseover="this.style.color='#00D4E8'" onmouseout="this.style.color='rgba(255,255,255,.4)'">
                            <i class="fas fa-paperclip"></i>
                        </button>
                        <input type="file" id="cp-file-input" style="display:none" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                               onchange="uploadAndInsert('cp-textarea','cp-file-input','cp-attach-preview')">
                    </div>
                    <div id="cp-attach-preview" style="display:none;padding:6px 10px 4px;gap:8px;flex-wrap:wrap;border-top:1px solid rgba(255,255,255,.08);"></div>
                </div>
                <button onclick="vnStart('cp')" title="Voice note"
                        style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.6);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;"
                        onmouseover="this.style.background='rgba(255,255,255,.18)';this.style.color='#fff'" onmouseout="this.style.background='rgba(255,255,255,.1)';this.style.color='rgba(255,255,255,.6)'">
                    <i class="fas fa-microphone" style="font-size:14px;"></i>
                </button>
                <button onclick="cpSend()" style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#00C4D8,#1B72E8);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-paper-plane" style="font-size:14px;"></i>
                </button>
            </div>
            {{-- Recording UI --}}
            <div id="cp-vn-rec" style="display:none;align-items:center;gap:8px;">
                <button onclick="vnCancel('cp')" title="Cancel"
                        style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.6);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;"
                        onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='rgba(255,255,255,.6)'">
                    <i class="fas fa-trash" style="font-size:13px;"></i>
                </button>
                <div style="flex:1;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:10px 14px;">
                    <div style="width:8px;height:8px;border-radius:50%;background:#ef4444;animation:vnPulse 1.2s ease-in-out infinite;flex-shrink:0;"></div>
                    <span style="color:rgba(255,255,255,.55);font-size:12px;">Recording</span>
                    <span id="cp-vn-timer" style="color:#fff;font-weight:700;font-size:13px;min-width:30px;margin-left:auto;">0:00</span>
                </div>
                <button onclick="vnSend('cp')"
                        style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#22c55e,#16a34a);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="fas fa-check" style="font-size:14px;"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══ New Direct overlay ═══ --}}
    <div id="cp-direct-modal" style="display:none;position:absolute;inset:0;z-index:10;background:rgba(8,12,45,.98);flex-direction:column;">
        <div style="padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;flex-shrink:0;">
            <button onclick="cpCloseNewDirect()" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:15px;"><i class="fas fa-arrow-left"></i></button>
            <span style="color:#fff;font-size:14px;font-weight:700;">New Direct Message</span>
        </div>
        <div style="padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.08);flex-shrink:0;">
            <div style="display:flex;align-items:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:9px;padding:0 12px;gap:7px;">
                <i class="fas fa-search" style="font-size:12px;color:rgba(255,255,255,.3);"></i>
                <input type="text" id="cp-emp-search" placeholder="Search employees..."
                       oninput="cpFilterEmps(this.value)"
                       style="background:none;border:none;outline:none;color:#fff;font-size:13px;padding:9px 0;width:100%;font-family:inherit;">
            </div>
        </div>
        <div id="cp-emp-list" style="flex:1;overflow-y:auto;padding:8px 0;">
            <div style="padding:40px;text-align:center;color:rgba(255,255,255,.25);font-size:13px;"><i class="fas fa-spinner fa-spin"></i></div>
        </div>
    </div>

    {{-- ═══ New Group overlay ═══ --}}
    <div id="cp-group-modal" style="display:none;position:absolute;inset:0;z-index:10;background:rgba(8,12,45,.98);flex-direction:column;">
        <div style="padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.1);display:flex;align-items:center;gap:10px;flex-shrink:0;">
            <button onclick="cpCloseNewGroup()" style="background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:15px;"><i class="fas fa-arrow-left"></i></button>
            <span style="color:#fff;font-size:14px;font-weight:700;">New Group Chat</span>
        </div>
        <div style="padding:16px 20px;flex:1;overflow-y:auto;">
            <div style="margin-bottom:16px;">
                <label style="display:block;color:rgba(255,255,255,.45);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">Group Name</label>
                <input type="text" id="cp-group-name" placeholder="e.g. Marketing Team"
                       style="width:100%;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:9px;color:#fff;font-size:13px;padding:10px 14px;outline:none;font-family:inherit;box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block;color:rgba(255,255,255,.45);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px;">Members</label>
                <div id="cp-group-members" style="display:flex;flex-direction:column;gap:5px;max-height:400px;overflow-y:auto;"></div>
            </div>
        </div>
        <div style="padding:14px 20px;border-top:1px solid rgba(255,255,255,.08);flex-shrink:0;">
            <button onclick="cpCreateGroup()" style="width:100%;background:linear-gradient(135deg,#00C4D8,#1B72E8);border:none;color:#fff;border-radius:10px;padding:11px;font-size:13.5px;font-weight:700;cursor:pointer;">
                <i class="fas fa-users" style="margin-right:7px;"></i>Create Group
            </button>
        </div>
    </div>
</div>

{{-- Context menu --}}
<div id="cp-ctx-menu" style="display:none;position:absolute;z-index:200;background:rgba(15,23,42,.97);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:5px;min-width:140px;box-shadow:0 8px 30px rgba(0,0,0,.5);"></div>

{{-- Conversation context menu --}}
<div id="cp-conv-ctx">
    <div class="cp-cctx-item cp-cctx-del" data-caction="delete"><i class="fas fa-trash-alt" style="font-size:12px;opacity:.7;"></i>Delete Chat</div>
</div>

{{-- Delete dialog (WhatsApp-style) --}}
<div id="cp-del-dlg" style="display:none;position:absolute;inset:0;z-index:300;background:rgba(0,0,0,.65);align-items:center;justify-content:center;backdrop-filter:blur(2px);">
    <div onclick="event.stopPropagation()" style="background:#1e2a3a;border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:0;width:320px;box-shadow:0 24px 60px rgba(0,0,0,.7);overflow:hidden;">
        <div style="padding:20px 20px 14px;">
            <p style="color:#fff;font-size:14.5px;font-weight:700;margin:0 0 5px;">Delete message?</p>
            <p style="color:rgba(255,255,255,.4);font-size:12.5px;margin:0;">This action cannot be undone.</p>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,.07);display:flex;flex-direction:column;">
            <button id="cp-del-everyone" style="padding:14px 20px;background:none;border:none;border-bottom:1px solid rgba(255,255,255,.07);color:#ef4444;font-size:13.5px;font-weight:600;cursor:pointer;text-align:left;transition:background .12s;" onmouseover="this.style.background='rgba(239,68,68,.1)'" onmouseout="this.style.background='none'">
                <i class="fas fa-users" style="width:20px;margin-right:8px;font-size:12px;"></i>Delete for Everyone
            </button>
            <button id="cp-del-me" style="padding:14px 20px;background:none;border:none;border-bottom:1px solid rgba(255,255,255,.07);color:rgba(255,255,255,.8);font-size:13.5px;font-weight:600;cursor:pointer;text-align:left;transition:background .12s;" onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background='none'">
                <i class="fas fa-user" style="width:20px;margin-right:8px;font-size:12px;"></i>Delete for Me
            </button>
            <button id="cp-del-cancel" style="padding:13px 20px;background:none;border:none;color:rgba(255,255,255,.35);font-size:13px;cursor:pointer;text-align:left;transition:background .12s;" onmouseover="this.style.background='rgba(255,255,255,.04)'" onmouseout="this.style.background='none'">
                <i class="fas fa-times" style="width:20px;margin-right:8px;font-size:12px;"></i>Cancel
            </button>
        </div>
    </div>
</div>

<style>
#cp-wrap { position:relative; }
#cp-direct-modal.cp-show, #cp-group-modal.cp-show { display:flex !important; }
.cp-conv-item { display:flex;align-items:center;gap:11px;padding:11px 16px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,.04);transition:background .12s;position:relative; }
.cp-conv-item:hover { background:rgba(255,255,255,.06); }
.cp-conv-item.active { background:rgba(0,212,232,.1);border-left:3px solid #00D4E8;padding-left:13px; }
.cp-conv-item.unread { background:rgba(0,212,232,.06);border-left:3px solid #25D366;padding-left:13px; }
.cp-conv-item.unread:hover { background:rgba(0,212,232,.11); }
@keyframes cp-flash { 0%{background:rgba(37,211,102,.22)} 100%{background:rgba(0,212,232,.06)} }
.cp-conv-item.flash { animation:cp-flash .6s ease-out forwards; }
#cp-conv-ctx { position:fixed;z-index:500;background:#1a2535;border:1px solid rgba(255,255,255,.1);border-radius:9px;padding:4px 0;min-width:150px;box-shadow:0 8px 28px rgba(0,0,0,.55);display:none; }
.cp-cctx-item { display:flex;align-items:center;gap:9px;padding:9px 16px;color:rgba(255,255,255,.8);font-size:13px;cursor:pointer;transition:background .1s; }
.cp-cctx-item:hover { background:rgba(255,255,255,.07); }
.cp-cctx-del { color:#ff5252; }
#cp-conv-list::-webkit-scrollbar { width:3px; }
#cp-conv-list::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1);border-radius:3px; }
#cp-msg-area::-webkit-scrollbar { width:4px; }
#cp-msg-area::-webkit-scrollbar-thumb { background:rgba(255,255,255,.15);border-radius:4px; }
#cp-textarea::placeholder, #cp-search::placeholder, #cp-emp-search::placeholder { color:rgba(255,255,255,.28); }
.cp-ctx-item { display:flex;align-items:center;gap:10px;padding:9px 14px;border-radius:7px;color:rgba(255,255,255,.85);font-size:13px;cursor:pointer;transition:background .1s;white-space:nowrap; }
.cp-ctx-item:hover { background:rgba(255,255,255,.09); }
.cp-ctx-item i { width:15px;text-align:center;font-size:12px;opacity:.65; }
.cp-ctx-del { color:#f87171; }
.cp-ctx-del:hover { background:rgba(239,68,68,.12) !important; }
#cp-ctx-menu { animation:ctxFade .1s ease; }
@keyframes ctxFade { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:scale(1)} }
</style>

{{-- Edit bar shown above input when editing --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBar = document.createElement('div');
    editBar.id = 'cp-edit-bar';
    editBar.style.cssText = 'display:none;align-items:center;gap:8px;padding:6px 16px 4px;background:rgba(0,212,232,.08);border-top:1px solid rgba(0,212,232,.2);';
    editBar.innerHTML = '<i class="fas fa-pen" style="font-size:11px;color:#00D4E8;"></i><span style="flex:1;color:rgba(255,255,255,.6);font-size:12px;">Editing message</span><button onclick="cpCancelEdit()" style="background:none;border:none;color:rgba(255,255,255,.4);cursor:pointer;font-size:13px;padding:2px 6px;"><i class="fas fa-times"></i></button>';
    const inputArea = document.getElementById('cp-input-area');
    inputArea.insertBefore(editBar, inputArea.firstChild);

    // Also move ctx menu and del dlg inside cp-wrap (already there via blade)
    const wrap = document.getElementById('cp-wrap');
    const ctx  = document.getElementById('cp-ctx-menu');
    const dlg  = document.getElementById('cp-del-dlg');
    if (ctx && ctx.parentElement !== wrap) wrap.appendChild(ctx);
    if (dlg && dlg.parentElement !== wrap) wrap.appendChild(dlg);
});
</script>

<script>
(function(){
const ME_ID = {{ auth()->id() }};
let _cpActiveConvId = null;
let _cpAllConvs     = [];
let _cpAllEmps      = [];
let _cpPollTimer    = null;
let _cpMsgCount     = 0;
let _cpLastMsgId    = 0;
let _cpFirstMsgTs   = 0;
let _cpHasMore      = false;
let _cpLoadingOlder = false;
let _cpLoaded       = false;
let _cpSelecting    = false;
let _cpSending      = false;

function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function linkify(text) {
    // Handles [URL=href]label[/URL], [URL]href[/URL], raw https:// links, and BBCode formatting
    const re = /\[URL=([^\]]+)\]([\s\S]*?)\[\/URL\]|\[URL\]([\s\S]*?)\[\/URL\]|(https?:\/\/[^\s<>"'[\]]+)/gi;
    const parts = [];
    let lastIdx = 0, m;
    const src = String(text||'');
    re.lastIndex = 0;
    while ((m = re.exec(src)) !== null) {
        if (m.index > lastIdx) parts.push({t:'plain', s: src.slice(lastIdx, m.index)});
        if      (m[1] !== undefined) parts.push({t:'url', href:m[1], label:m[2]||m[1]});
        else if (m[3] !== undefined) parts.push({t:'url', href:m[3], label:m[3]});
        else                         parts.push({t:'url', href:m[4], label:m[4]});
        lastIdx = m.index + m[0].length;
    }
    if (lastIdx < src.length) parts.push({t:'plain', s: src.slice(lastIdx)});
    return parts.map(p => {
        if (p.t === 'url') {
            const h = p.href.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
            const l = p.label.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return `<a href="${h}" target="_blank" rel="noopener noreferrer" style="color:#0ea5e9;text-decoration:underline;word-break:break-all;">${l}</a>`;
        }
        let t = p.s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        t = t.replace(/\[B\]([\s\S]*?)\[\/B\]/gi, '<strong>$1</strong>');
        t = t.replace(/\[I\]([\s\S]*?)\[\/I\]/gi, '<em>$1</em>');
        t = t.replace(/\[S\]([\s\S]*?)\[\/S\]/gi, '<s>$1</s>');
        t = t.replace(/\[U\]([\s\S]*?)\[\/U\]/gi, '<u>$1</u>');
        t = t.replace(/\[CODE\]([\s\S]*?)\[\/CODE\]/gi, '<code style="background:rgba(0,0,0,.25);padding:1px 5px;border-radius:3px;font-family:monospace;font-size:.9em;">$1</code>');
        return t;
    }).join('');
}
function cpPreviewText(t) {
    return (t||'')
        .replace(/\[img\].*?\[\/img\]/gs,    '📷 Photo')
        .replace(/\[file name="[^"]*"\].*?\[\/file\]/gs, '📎 File')
        .replace(/\[voice(?:\s+dur="[^"]*")?\].*?\[\/voice\]/gs, '🎤 Voice')
        .replace(/\[URL=[^\]]+\]([\s\S]*?)\[\/URL\]/gi, '$1')
        .replace(/\[URL\]([\s\S]*?)\[\/URL\]/gi, '$1')
        .replace(/\[\/?(B|I|S|U|CODE)\]/gi, '');
}

/* ── Context menu (event delegation — no inline oncontextmenu needed) ── */
let _ctxMsgId = null;
function cpHideCtx() {
    document.getElementById('cp-ctx-menu').style.display = 'none';
    _ctxMsgId = null;
}
function cpShowCtxAt(e, msgId, canEdit) {
    e.preventDefault();
    cpHideCtx();
    _ctxMsgId = msgId;
    const menu = document.getElementById('cp-ctx-menu');
    menu.innerHTML =
        (canEdit ? `<div class="cp-ctx-item" data-action="edit"><i class="fas fa-pen"></i>Edit</div>` : '') +
        `<div class="cp-ctx-item" data-action="reply"><i class="fas fa-reply"></i>Reply</div>` +
        `<div class="cp-ctx-item" data-action="copy"><i class="fas fa-copy"></i>Copy</div>` +
        `<div class="cp-ctx-item cp-ctx-del" data-action="delete"><i class="fas fa-trash"></i>Delete</div>`;
    menu.style.display = 'block';
    const wrapRect = document.getElementById('cp-wrap').getBoundingClientRect();
    let x = e.clientX - wrapRect.left;
    let y = e.clientY - wrapRect.top;
    if (x + 165 > wrapRect.width)  x -= 170;
    if (y + 130 > wrapRect.height) y -= 130;
    if (x < 4) x = 4;
    if (y < 4) y = 4;
    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
}

// Delegate contextmenu from the message area
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('cp-msg-area').addEventListener('contextmenu', function(e) {
        const row = e.target.closest('[data-msg-id]');
        if (!row || !row.dataset.msgId) return;
        const msgId = +row.dataset.msgId;
        if (!msgId) return;
        const nowTs = Math.floor(Date.now()/1000);
        const createdTs = +(row.dataset.createdTs || 0);
        const isMine = row.dataset.mine === '1';
        const canEdit = isMine && (nowTs - createdTs) < 300;
        cpShowCtxAt(e, msgId, canEdit);
    });

    // Delegate clicks on context menu items
    document.getElementById('cp-ctx-menu').addEventListener('click', function(e) {
        const item = e.target.closest('[data-action]');
        if (!item) return;
        const action = item.dataset.action;
        const msgId  = _ctxMsgId;
        cpHideCtx();
        if (!msgId) return;
        if (action === 'edit') {
            const row = document.querySelector(`[data-msg-id="${msgId}"]`);
            if (!row) return;
            const textEl = row.querySelector('[data-msg-text]');
            _editingMsgId = msgId;
            const ta = document.getElementById('cp-textarea');
            ta.value = textEl ? textEl.dataset.raw || '' : '';
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight, 140) + 'px';
            ta.focus();
            document.getElementById('cp-edit-bar').style.display = 'flex';
        } else if (action === 'reply') {
            const row = document.querySelector(`[data-msg-id="${msgId}"]`);
            if (!row) return;
            const textEl = row.querySelector('[data-msg-text]');
            const raw = textEl ? (textEl.dataset.raw || '').replace(/\[img\].*?\[\/img\]/g,'[image]').substring(0,80) : '';
            const ta = document.getElementById('cp-textarea');
            ta.value = (raw ? `> ${raw}\n\n` : '') + ta.value;
            ta.focus();
        } else if (action === 'copy') {
            const row = document.querySelector(`[data-msg-id="${msgId}"]`);
            const textEl = row ? row.querySelector('[data-msg-text]') : null;
            const raw = textEl ? textEl.dataset.raw || '' : '';
            if (navigator.clipboard) navigator.clipboard.writeText(raw).catch(()=>{});
        } else if (action === 'delete') {
            cpAskDelete(msgId);
        }
    });
});

document.addEventListener('click', function(e) {
    cpHideCtx();
    if (!e.target.closest('#cp-conv-ctx')) cpHideConvCtx();
});
document.addEventListener('keydown', function(e) { if(e.key==='Escape') { cpHideCtx(); cpHideConvCtx(); cpCancelEdit(); } });

/* ── Conversation context menu ── */
let _ctxConvId = null;
function cpHideConvCtx() { document.getElementById('cp-conv-ctx').style.display='none'; _ctxConvId=null; }

document.getElementById('cp-conv-list').addEventListener('contextmenu', function(e) {
    const row = e.target.closest('.cp-conv-item[data-id]');
    if (!row) return;
    e.preventDefault();
    _ctxConvId = +row.dataset.id;
    const menu = document.getElementById('cp-conv-ctx');
    menu.style.display = 'block';
    const x = Math.min(e.clientX, window.innerWidth  - menu.offsetWidth  - 8);
    const y = Math.min(e.clientY, window.innerHeight - menu.offsetHeight - 8);
    menu.style.left = x + 'px';
    menu.style.top  = y + 'px';
});

document.getElementById('cp-conv-ctx').addEventListener('click', async function(e) {
    const item = e.target.closest('[data-caction]');
    if (!item) return;
    const convId = _ctxConvId;
    cpHideConvCtx();
    if (!convId) return;
    if (item.dataset.caction === 'delete') {
        if (!confirm('Delete this chat from your list?')) return;
        await fetch(API_BASE + '/api/chat/convs/' + convId + '/leave', {method:'POST',headers:{'X-CSRF-TOKEN':CSRF}});
        if (_cpActiveConvId === convId) {
            _cpActiveConvId = null;
            try { localStorage.removeItem('cp_active_conv'); } catch(e) {}
            document.getElementById('cp-right-empty').style.display = 'flex';
            document.getElementById('cp-right-head').style.display  = 'none';
            document.getElementById('cp-msg-area').style.display    = 'none';
            document.getElementById('cp-input-area').style.display  = 'none';
        }
        await cpLoad();
    }
});

/* ── Edit ── */
let _editingMsgId = null;
window.cpCancelEdit = function() {
    _editingMsgId = null;
    document.getElementById('cp-textarea').value = '';
    document.getElementById('cp-textarea').style.height = 'auto';
    document.getElementById('cp-edit-bar').style.display = 'none';
};

/* ── Delete dialog ── */
window.cpAskDelete = function(msgId) {
    const dlg = document.getElementById('cp-del-dlg');
    dlg.style.display = 'flex';
    document.getElementById('cp-del-everyone').onclick = () => { dlg.style.display='none'; cpDoDelete(msgId,'everyone'); };
    document.getElementById('cp-del-me').onclick       = () => { dlg.style.display='none'; cpDoDelete(msgId,'me'); };
    document.getElementById('cp-del-cancel').onclick   = () => { dlg.style.display='none'; };
};
async function cpDoDelete(msgId, scope) {
    await fetch(API_BASE + '/api/chat/msgs/' + msgId + '?scope=' + scope, {
        method:'DELETE', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}
    });
    if (_cpActiveConvId) {
        const r = await fetch(API_BASE + '/api/chat/convs/' + _cpActiveConvId + '/msgs');
        const d = await r.json();
        cpRenderMsgs(d.messages || [], false);
    }
}

function renderContent(text, isMine) {
    const parts = (text||'').split(/(\[img\].*?\[\/img\]|\[file name="[^"]*"\].*?\[\/file\]|\[voice(?:\s+dur="[^"]*")?\].*?\[\/voice\])/g);
    const allImgs = [];
    parts.forEach(p => { const m = p.match(/^\[img\](.*?)\[\/img\]$/); if (m) allImgs.push(m[1]); });
    const galKey = allImgs.length > 1 && window.registerGallery ? window.registerGallery(allImgs) : null;
    let out = '', imgIdx = 0;
    parts.forEach(part => {
        const imgM   = part.match(/^\[img\](.*?)\[\/img\]$/);
        const fileM  = part.match(/^\[file name="([^"]*)"\](.*?)\[\/file\]$/);
        const voiceM = part.match(/^\[voice(?:\s+dur="([^"]*)")?\](.*?)\[\/voice\]$/);
        if (imgM) {
            const ci = imgIdx++;
            const fn = galKey !== null ? `imgLightbox(${galKey},${ci})` : `imgLightbox('${esc(imgM[1])}',0)`;
            out += `<img src="${esc(imgM[1])}" style="max-width:280px;max-height:220px;object-fit:cover;border-radius:8px;display:block;margin:4px 0;cursor:zoom-in;transition:opacity .15s;" loading="lazy" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'" onclick="${fn}">`;
        } else if (fileM) {
            const _fn = fileM[1], _url = fileM[2];
            const _ext = (_fn.split('.').pop()||'').toLowerCase();
            const _ic = {pdf:['fa-file-pdf','#ef4444'],doc:['fa-file-word','#2563eb'],docx:['fa-file-word','#2563eb'],xls:['fa-file-excel','#16a34a'],xlsx:['fa-file-excel','#16a34a'],ppt:['fa-file-powerpoint','#ea580c'],pptx:['fa-file-powerpoint','#ea580c'],zip:['fa-file-archive','#ca8a04'],rar:['fa-file-archive','#ca8a04'],txt:['fa-file-alt','#64748b']};
            const [_ico, _bg] = _ic[_ext] || ['fa-file','#0ea5e9'];
            out += `<a href="${esc(_url)}" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.13);border-radius:10px;padding:10px 14px;text-decoration:none;margin:4px 0;min-width:190px;max-width:280px;">
                <div style="width:40px;height:40px;border-radius:8px;background:${_bg};display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas ${_ico}" style="color:#fff;font-size:18px;"></i></div>
                <div style="min-width:0;flex:1;overflow:hidden;"><p style="font-size:12.5px;font-weight:600;color:rgba(255,255,255,.9);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(_fn)}</p><p style="font-size:10.5px;color:rgba(255,255,255,.4);margin:2px 0 0;letter-spacing:.4px;">${_ext.toUpperCase()}</p></div>
                <i class="fas fa-download" style="color:rgba(255,255,255,.3);font-size:11px;flex-shrink:0;"></i>
            </a>`;
        } else if (voiceM) {
            out += window.voiceBubbleHtml ? window.voiceBubbleHtml(voiceM[2]||'', voiceM[1]||'0:00', !!isMine) : '';
        } else if (part) {
            out += `<span style="white-space:pre-wrap;word-break:break-word;">${linkify(part)}</span>`;
        }
    });
    return out;
}

function bubble(m) {
    const {isMine, name, avatar, text, time, showName=true, msgId, editedAt, createdTs, isDeleted} = m;
    const dataAttrs = msgId ? `data-msg-id="${msgId}" data-mine="${isMine?'1':'0'}" data-created-ts="${createdTs||0}"` : '';

    if (isDeleted) {
        const align = isMine ? 'flex-end' : 'flex-start';
        return `<div ${dataAttrs} style="display:flex;justify-content:${align};margin-bottom:2px;user-select:none;">
            <div style="max-width:70%;background:rgba(255,255,255,.06);border-radius:10px;padding:8px 13px;border:1px dashed rgba(255,255,255,.15);">
                <span style="font-size:12.5px;color:rgba(255,255,255,.3);font-style:italic;"><i class="fas fa-ban" style="font-size:10px;margin-right:5px;"></i>This message was deleted</span>
            </div>
        </div>`;
    }

    const editedHtml = editedAt ? `<span style="font-size:9.5px;color:rgba(255,255,255,.3);margin-right:4px;font-style:italic;">edited</span>` : '';
    const content = renderContent(text, isMine);

    if (isMine) {
        return `<div ${dataAttrs} style="display:flex;justify-content:flex-end;margin-bottom:2px;">
            <div style="max-width:70%;">
                <div style="background:rgba(200,240,210,.92);border-radius:14px 4px 14px 14px;padding:9px 13px 7px;cursor:default;">
                    <div data-msg-text data-raw="${esc(text)}" style="font-size:13.5px;color:#1a3025;line-height:1.5;">${content}</div>
                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:4px;margin-top:3px;">
                        ${editedHtml}<span style="font-size:10.5px;color:rgba(0,0,0,.38);">${time}</span>
                        <i class="fas fa-check-double" style="font-size:9px;color:rgba(0,140,90,.6);"></i>
                    </div>
                </div>
            </div>
        </div>`;
    }
    const av = avatar
        ? `<img src="${avatar}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;margin-top:2px;">`
        : `<div style="width:28px;height:28px;border-radius:50%;background:rgba(100,116,139,.3);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;font-size:11px;color:#94a3b8;">${(name||'?')[0]}</div>`;
    const nm = showName ? `<span style="font-size:11.5px;color:rgba(255,255,255,.45);font-weight:600;display:block;margin-bottom:2px;">${esc(name)}</span>` : '';
    const editedHtmlOther = editedAt ? `<span style="font-size:9.5px;color:rgba(0,0,0,.3);margin-right:4px;font-style:italic;">edited</span>` : '';
    return `<div ${dataAttrs} style="display:flex;align-items:flex-start;gap:8px;margin-bottom:2px;${showName?'margin-top:6px':''}">
        ${av}
        <div style="max-width:70%;">
            ${nm}
            <div style="background:rgba(255,255,255,.88);border-radius:4px 14px 14px 14px;padding:9px 13px 7px;cursor:default;">
                <div data-msg-text data-raw="${esc(text)}" style="font-size:13.5px;color:#1e293b;line-height:1.5;">${content}</div>
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:2px;margin-top:3px;">
                    ${editedHtmlOther}<span style="font-size:10.5px;color:rgba(0,0,0,.35);">${time}</span>
                </div>
            </div>
        </div>
    </div>`;
}

/* ── Conversation avatar with online dot ── */
function convAvatar(c, size) {
    const s = size+'px';
    let inner = '';
    if (c.type==='general') inner = `<div style="width:${s};height:${s};border-radius:50%;background:rgba(0,212,232,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-globe" style="font-size:${Math.round(size*.42)}px;color:#00D4E8;"></i></div>`;
    else if (c.type==='group') inner = `<div style="width:${s};height:${s};border-radius:50%;background:rgba(139,92,246,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-users" style="font-size:${Math.round(size*.38)}px;color:#a78bfa;"></i></div>`;
    else if (c.avatar) inner = `<img src="${c.avatar}" style="width:${s};height:${s};border-radius:50%;object-fit:cover;flex-shrink:0;">`;
    else {
        const initials = (c.name||'?').split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
        inner = `<div style="width:${s};height:${s};border-radius:50%;background:rgba(27,114,232,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#60a5fa;font-size:${Math.round(size*.38)}px;font-weight:700;">${initials}</div>`;
    }
    const dot = (c.type==='direct')
        ? `<div style="position:absolute;bottom:0;right:0;width:11px;height:11px;border-radius:50%;background:${c.online?'#22c55e':'#f97316'};border:2px solid rgba(10,15,60,.96);"></div>`
        : '';
    return `<div style="position:relative;flex-shrink:0;">${inner}${dot}</div>`;
}

/* ── Load conversations ── */
async function cpLoad() {
    try {
        const r = await fetch(API_BASE + '/api/chat/convs');
        const d = await r.json();
        const prevUnread = _cpLoaded ? Object.fromEntries(_cpAllConvs.map(c => [c.id, c.unread || 0])) : null;
        _cpAllConvs = d.convs || [];
        _cpLoaded   = true;
        cpRenderConvs(_cpAllConvs);
        // Flash + sound for conversations where unread count went up (skip active conv — cpPoll handles its sound)
        if (prevUnread) {
            _cpAllConvs.forEach(c => {
                if ((c.unread || 0) > (prevUnread[c.id] || 0) && c.id !== _cpActiveConvId) {
                    if (typeof chatPlaySound === 'function') chatPlaySound();
                    const row = document.querySelector(`.cp-conv-item[data-id="${c.id}"]`);
                    if (row) { row.classList.remove('flash'); void row.offsetWidth; row.classList.add('flash'); }
                }
            });
        }
    } catch(e) {
        document.getElementById('cp-conv-list').innerHTML = '<div style="padding:30px;text-align:center;color:rgba(255,82,82,.7);font-size:12px;">Failed to load</div>';
    }
}

function cpRenderConvs(list) {
    const el = document.getElementById('cp-conv-list');
    if (!list.length) {
        el.innerHTML = '<div style="padding:40px 16px;text-align:center;color:rgba(255,255,255,.6);font-size:12px;">No conversations yet</div>';
        return;
    }
    el.innerHTML = list.map(c => {
        const av      = convAvatar(c, 36);
        const lm      = c.lastMsg;
        const unread  = (c.unread && _cpActiveConvId !== c.id) ? c.unread : 0;
        const lastLine = lm
            ? `<span style="color:${unread?'#fff':'rgba(255,255,255,.35)'};font-size:11.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;${unread?'font-weight:600;':''}">${lm.byMe?'You: ':(c.type!=='direct'?esc(lm.senderName||'')+': ':'')}${esc(cpPreviewText(lm.text).substring(0,45))}</span>`
            : `<span style="color:rgba(255,255,255,.5);font-size:11.5px;">No messages yet</span>`;
        const timeStr = lm ? `<span style="color:${unread?'#00D4E8':'rgba(255,255,255,.55)'};font-size:10.5px;flex-shrink:0;">${lm.time}</span>` : '';
        const badge   = unread ? `<div style="min-width:18px;height:18px;border-radius:9px;background:#00D4E8;color:#000;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 4px;flex-shrink:0;">${unread>99?'99+':unread}</div>` : '';
        const unreadClass = (_cpActiveConvId!==c.id && unread) ? ' unread' : '';
        return `<div class="cp-conv-item${_cpActiveConvId===c.id?' active':''}${unreadClass}" onclick="cpSelect(${c.id})" data-id="${c.id}">
            ${av}
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
                    <span style="color:#fff;font-size:13px;font-weight:${unread?'700':'600'};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;">${esc(c.name)}</span>
                    ${timeStr}
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:4px;">
                    <div style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${lastLine}</div>
                    ${badge}
                </div>
            </div>
        </div>`;
    }).join('');
}

window.cpFilterConvs = function(q) {
    if (!q) { cpRenderConvs(_cpAllConvs); return; }
    cpRenderConvs(_cpAllConvs.filter(c => c.name.toLowerCase().includes(q.toLowerCase())));
};

/* ── Select conversation ── */
window.cpSelect = async function(id) {
    _cpSelecting    = true;
    _cpActiveConvId = id;
    _cpMsgCount     = 0;
    _cpLastMsgId    = 0;
    _cpFirstMsgTs   = 0;
    _cpHasMore      = false;
    _cpLoadingOlder = false;
    try { localStorage.setItem('cp_active_conv', id); } catch(e) {}
    cpCancelEdit();
    document.querySelectorAll('.cp-conv-item').forEach(el => el.classList.toggle('active', +el.dataset.id === id));
    const msgArea = document.getElementById('cp-msg-area');
    msgArea.innerHTML = '<div style="flex:1 0 auto;display:flex;align-items:center;justify-content:center;"><i class="fas fa-spinner fa-spin" style="font-size:22px;color:rgba(255,255,255,.3);"></i></div>';
    document.getElementById('cp-right-empty').style.display = 'none';
    document.getElementById('cp-right-head').style.display  = 'flex';
    msgArea.style.display        = 'flex';
    msgArea.style.flexDirection  = 'column';
    document.getElementById('cp-input-area').style.display  = 'block';

    try {
        const r = await fetch(API_BASE + '/api/chat/convs/' + id + '/msgs');
        const d = await r.json();
        cpUpdateHeader(d.conv);
        const _initMsgs = d.messages || [];
        _cpHasMore = !!d.hasMore;
        cpRenderMsgs(_initMsgs, true);
        if (_initMsgs.length) {
            _cpLastMsgId  = Math.max(..._initMsgs.map(m => m.id));
            _cpFirstMsgTs = Math.min(..._initMsgs.map(m => m.createdTs));
        }
        cpRenderConvs(_cpAllConvs); // clear unread badge
        const ta = document.getElementById('cp-textarea');
        if (ta) ta.focus();
    } catch(e) {
        msgArea.innerHTML = '<div style="padding:30px;text-align:center;color:rgba(255,82,82,.7);font-size:12px;">Failed to load</div>';
    } finally {
        _cpSelecting = false;
    }
};

function cpUpdateHeader(conv) {
    const avEl   = document.getElementById('cp-rh-avatar');
    const nameEl = document.getElementById('cp-rh-name');
    const subEl  = document.getElementById('cp-rh-sub');
    nameEl.textContent = conv.name || 'Chat';
    if (conv.type === 'general') {
        avEl.innerHTML = '<i class="fas fa-globe" style="font-size:18px;color:#00D4E8;"></i>';
        avEl.style.background = 'rgba(0,212,232,.15)';
        subEl.innerHTML = 'General channel';
    } else if (conv.type === 'group') {
        avEl.innerHTML = '<i class="fas fa-users" style="font-size:16px;color:#a78bfa;"></i>';
        avEl.style.background = 'rgba(139,92,246,.15)';
        subEl.innerHTML = conv.members + ' members';
    } else {
        const c = _cpAllConvs.find(x => x.id === conv.id);
        if (c && c.avatar) {
            avEl.innerHTML = `<img src="${c.avatar}" style="width:100%;height:100%;object-fit:cover;">`;
            avEl.style.background = 'transparent';
        } else {
            const ini = (conv.name||'?').split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
            avEl.innerHTML = `<span style="font-size:14px;font-weight:700;color:#60a5fa;">${ini}</span>`;
            avEl.style.background = 'rgba(27,114,232,.2)';
        }
        const dot = `<span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:${conv.online?'#22c55e':'#f97316'};margin-right:5px;vertical-align:middle;"></span>`;
        subEl.innerHTML = dot + (conv.online ? 'Online' : (conv.last_seen ? 'Last seen ' + conv.last_seen : 'Offline'));
    }
}

function cpDayLabel(dateStr) {
    const today = new Date().toISOString().slice(0,10);
    const yest  = new Date(Date.now()-86400000).toISOString().slice(0,10);
    if (dateStr === today) return 'Today';
    if (dateStr === yest)  return 'Yesterday';
    return new Date(dateStr).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'});
}

function cpRenderMsgs(msgs, scrollToBottom) {
    const el = document.getElementById('cp-msg-area');
    const wasNearBottom = (el.scrollHeight - el.scrollTop) < (el.clientHeight + 140);

    if (!msgs.length) {
        el.innerHTML = '<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:rgba(255,255,255,.7);font-size:13px;font-weight:500;"><i class="fas fa-comment-dots" style="font-size:32px;color:rgba(255,255,255,.4);"></i>No messages yet — say hello!</div>';
        return;
    }

    let html = '', prevDate = null, prevAuthor = null;
    msgs.forEach(m => {
        const deletedForMe = Array.isArray(m.deletedFor) && m.deletedFor.includes(ME_ID);
        const isDeleted    = deletedForMe || !m.text || m.text === 'This message has been deleted.';
        if (m.date !== prevDate) {
            html += `<div style="display:flex;align-items:center;gap:10px;margin:14px 0 8px;">
                <div style="flex:1;height:1px;background:rgba(255,255,255,.18);"></div>
                <span style="color:rgba(255,255,255,.8);font-size:11.5px;white-space:nowrap;background:rgba(0,0,0,.32);padding:2px 12px;border-radius:20px;font-weight:600;letter-spacing:.3px;">${cpDayLabel(m.date)}</span>
                <div style="flex:1;height:1px;background:rgba(255,255,255,.18);"></div>
            </div>`;
            prevDate = m.date; prevAuthor = null;
        }
        const showName = !m.isMine && prevAuthor !== m.author.id;
        html += bubble({isMine:m.isMine, name:m.author.name, avatar:m.author.avatar,
            text:m.text||'', time:m.time, showName, msgId:m.id,
            editedAt:m.editedAt, createdTs:m.createdTs, isDeleted});
        prevAuthor = m.author.id;
    });

    el.innerHTML = `<div id="cp-msg-inner" style="display:flex;flex-direction:column;gap:2px;margin-top:auto;">${html}</div>`;

    const newCount = msgs.length;
    const hasNew   = newCount > _cpMsgCount;
    const doScroll = scrollToBottom || hasNew || wasNearBottom;
    if (hasNew) _cpMsgCount = newCount;

    const goBottom = () => { el.scrollTop = el.scrollHeight + 9999; };
    if (doScroll) {
        requestAnimationFrame(function() {
            goBottom();
            const inner = document.getElementById('cp-msg-inner');
            if (inner) {
                inner.querySelectorAll('img').forEach(function(img) {
                    if (!img.complete) img.addEventListener('load', goBottom, { once: true });
                });
            }
        });
    }

    const _cpImgs = [...el.querySelectorAll('img[style*="cursor:zoom-in"]')];
    if (_cpImgs.length > 1 && window.registerGallery) {
        const _cpGk = window.registerGallery(_cpImgs.map(i => i.src));
        _cpImgs.forEach((img, idx) => img.setAttribute('onclick', `imgLightbox(${_cpGk},${idx})`));
    }
}

/* ── Send ── */
window.cpSend = async function() {
    if (!_cpActiveConvId) return;
    const ta = document.getElementById('cp-textarea');
    const text = ta.value.trim();
    const attachTags = window.getAttachmentTags ? window.getAttachmentTags('cp-textarea') : '';

    // Handle edit mode
    if (_editingMsgId) {
        if (!text) return;
        cpCancelEdit();
        await fetch(API_BASE + '/api/chat/msgs/' + _editingMsgId, {
            method:'PATCH',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({content:text}),
        });
        const r = await fetch(API_BASE + '/api/chat/convs/' + _cpActiveConvId + '/msgs');
        const d = await r.json();
        cpRenderMsgs(d.messages||[], false);
        return;
    }

    if (!text && !attachTags) return;
    ta.value = ''; ta.style.height = 'auto';
    if (window.clearAttachments) window.clearAttachments('cp-textarea', 'cp-attach-preview');
    if (typeof chatSendSound === 'function') chatSendSound();
    const fullText = text + (attachTags ? (text ? '\n' : '') + attachTags : '');
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
    const el = document.getElementById('cp-msg-area');
    const _inner = document.getElementById('cp-msg-inner') || el;

    _inner.insertAdjacentHTML('beforeend', bubble({isMine:true, name:'Me', avatar:'', text:fullText, time:timeStr, showName:false}));
    el.scrollTop = el.scrollHeight;
    _cpMsgCount++;
    _cpSending = true;
    try {
        const r = await fetch(API_BASE + '/api/chat/convs/' + _cpActiveConvId + '/send', {
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({content:fullText}),
        });
        const d = await r.json();
        if (d.message?.id) _cpLastMsgId = Math.max(_cpLastMsgId, d.message.id);
        cpLoad();
    } catch(e) {} finally { _cpSending = false; }
};

/* ── Send raw tag (voice note) ── */
window.cpSendRaw = async function(tag) {
    if (!_cpActiveConvId) return;
    const now = new Date();
    const timeStr = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
    const el = document.getElementById('cp-msg-area');
    const _inner2 = document.getElementById('cp-msg-inner') || el;
    _inner2.style.paddingTop = '0';
    _inner2.insertAdjacentHTML('beforeend', bubble({isMine:true, name:'Me', avatar:'', text:tag, time:timeStr, showName:false}));
    el.scrollTop = el.scrollHeight;
    _cpMsgCount++;
    _cpSending = true;
    try {
        const r2 = await fetch(API_BASE + '/api/chat/convs/' + _cpActiveConvId + '/send', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
            body: JSON.stringify({content:tag}),
        });
        const d2 = await r2.json();
        if (d2.message?.id) _cpLastMsgId = Math.max(_cpLastMsgId, d2.message.id);
        cpLoad();
    } catch(e) {} finally { _cpSending = false; }
};

/* ── New Direct ── */
window.cpNewDirect = async function() {
    document.getElementById('cp-direct-modal').classList.add('cp-show');
    document.getElementById('cp-emp-search').value = '';
    if (!_cpAllEmps.length) {
        const r = await fetch(API_BASE + '/api/employees-list');
        _cpAllEmps = await r.json();
    }
    cpFilterEmps('');
};
window.cpCloseNewDirect = function() { document.getElementById('cp-direct-modal').classList.remove('cp-show'); };
window.cpFilterEmps = function(q) {
    const list = q ? _cpAllEmps.filter(e => e.name.toLowerCase().includes(q.toLowerCase())) : _cpAllEmps;
    document.getElementById('cp-emp-list').innerHTML = list.map(e =>
        `<div onclick="cpStartDirect(${e.id})"
              style="display:flex;align-items:center;gap:12px;padding:11px 20px;cursor:pointer;transition:background .12s;"
              onmouseover="this.style.background='rgba(255,255,255,.06)'" onmouseout="this.style.background='none'">
            <img src="${e.avatar}" style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;">
            <span style="color:#fff;font-size:13.5px;font-weight:500;">${esc(e.name)}</span>
        </div>`
    ).join('');
};
window.cpStartDirect = async function(userId) {
    const r = await fetch(API_BASE + '/api/chat/direct',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({user_id:userId})});
    const d = await r.json();
    cpCloseNewDirect();
    await cpLoad();
    if (d.conv_id) cpSelect(d.conv_id);
};

/* ── New Group ── */
window.cpNewGroup = async function() {
    document.getElementById('cp-group-modal').classList.add('cp-show');
    document.getElementById('cp-group-name').value = '';
    if (!_cpAllEmps.length) {
        const r = await fetch(API_BASE + '/api/employees-list');
        _cpAllEmps = await r.json();
    }
    document.getElementById('cp-group-members').innerHTML = _cpAllEmps.map(e =>
        `<label style="display:flex;align-items:center;gap:10px;padding:8px 4px;cursor:pointer;">
            <input type="checkbox" value="${e.id}" style="width:16px;height:16px;accent-color:#00D4E8;flex-shrink:0;">
            <img src="${e.avatar}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
            <span style="color:rgba(255,255,255,.85);font-size:13px;">${esc(e.name)}</span>
        </label>`
    ).join('');
};
window.cpCloseNewGroup = function() { document.getElementById('cp-group-modal').classList.remove('cp-show'); };
window.cpCreateGroup = async function() {
    const name    = document.getElementById('cp-group-name').value.trim();
    if (!name) { alert('Enter a group name'); return; }
    const members = [...document.querySelectorAll('#cp-group-members input:checked')].map(i => +i.value);
    if (!members.length) { alert('Select at least one member'); return; }
    const r = await fetch(API_BASE + '/api/chat/group',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({name,members})});
    const d = await r.json();
    cpCloseNewGroup();
    await cpLoad();
    if (d.conv_id) cpSelect(d.conv_id);
};

/* ── Polling ── */
function cpPoll() {
    cpLoad();
    if (!_cpActiveConvId || _cpSelecting || _cpSending) return;
    const url = API_BASE + '/api/chat/convs/' + _cpActiveConvId + '/msgs'
              + (_cpLastMsgId ? '?after=' + _cpLastMsgId : '');
    fetch(url).then(r=>r.json()).then(d => {
        const msgs = d.messages || [];
        if (!msgs.length) return;
        if (_cpLastMsgId === 0) {
            cpRenderMsgs(msgs, false);
        } else {
            if (msgs.some(m => !m.isMine) && typeof chatPlaySound === 'function') chatPlaySound();
            cpAppendMsgs(msgs);
        }
        _cpLastMsgId = Math.max(...msgs.map(m => m.id));
    }).catch(()=>{});
}
function cpAppendMsgs(msgs) {
    const el    = document.getElementById('cp-msg-area');
    const inner = document.getElementById('cp-msg-inner');
    if (!inner) { cpRenderMsgs(msgs, true); return; }
    const wasNearBottom = (el.scrollHeight - el.scrollTop) < (el.clientHeight + 140);
    msgs.forEach(m => {
        inner.insertAdjacentHTML('beforeend', bubble({
            isMine: m.isMine, name: m.author.name, avatar: m.author.avatar,
            text: m.text || '', time: m.time, showName: !m.isMine,
            msgId: m.id, editedAt: m.editedAt, createdTs: m.createdTs,
            isDeleted: Array.isArray(m.deletedFor) && m.deletedFor.includes(ME_ID),
        }));
    });
    _cpMsgCount += msgs.length;
    if (wasNearBottom) el.scrollTop = el.scrollHeight + 9999;
}

/* ── Load older messages on scroll up ── */
async function cpLoadOlderMsgs() {
    if (_cpLoadingOlder || !_cpHasMore || !_cpFirstMsgTs || !_cpActiveConvId) return;
    _cpLoadingOlder = true;

    const el = document.getElementById('cp-msg-area');
    const inner = document.getElementById('cp-msg-inner');

    // Show loader at top
    const loader = document.createElement('div');
    loader.id = 'cp-older-loader';
    loader.style.cssText = 'text-align:center;padding:10px;color:rgba(255,255,255,.4);font-size:12px;';
    loader.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right:6px;"></i>Loading older messages...';
    if (inner) inner.prepend(loader); else el.prepend(loader);

    try {
        const r = await fetch(API_BASE + '/api/chat/convs/' + _cpActiveConvId + '/msgs?before_ts=' + _cpFirstMsgTs);
        const d = await r.json();
        const msgs = d.messages || [];
        _cpHasMore = !!d.hasMore;

        if (msgs.length) {
            _cpFirstMsgTs = Math.min(...msgs.map(m => m.createdTs));

            // Remember scroll position so page doesn't jump
            const prevHeight = el.scrollHeight;

            // Build HTML for older messages
            let html = '', prevDate = null, prevAuthor = null;
            msgs.forEach(m => {
                const deletedForMe = Array.isArray(m.deletedFor) && m.deletedFor.includes(ME_ID);
                const isDeleted    = deletedForMe || !m.text || m.text === 'This message has been deleted.';
                if (m.date !== prevDate) {
                    html += `<div style="display:flex;align-items:center;gap:10px;margin:14px 0 8px;">
                        <div style="flex:1;height:1px;background:rgba(255,255,255,.18);"></div>
                        <span style="color:rgba(255,255,255,.8);font-size:11.5px;white-space:nowrap;background:rgba(0,0,0,.32);padding:2px 12px;border-radius:20px;font-weight:600;letter-spacing:.3px;">${cpDayLabel(m.date)}</span>
                        <div style="flex:1;height:1px;background:rgba(255,255,255,.18);"></div>
                    </div>`;
                    prevDate = m.date; prevAuthor = null;
                }
                const showName = !m.isMine && prevAuthor !== m.author.id;
                html += bubble({isMine:m.isMine, name:m.author.name, avatar:m.author.avatar,
                    text:m.text||'', time:m.time, showName, msgId:m.id,
                    editedAt:m.editedAt, createdTs:m.createdTs, isDeleted});
                prevAuthor = m.author.id;
            });

            // Remove loader then prepend messages
            loader.remove();
            const tmp = document.createElement('div');
            tmp.innerHTML = html;
            if (inner) {
                while (tmp.firstChild) inner.prepend(tmp.firstChild);
            }

            // Restore scroll position so view doesn't jump
            el.scrollTop = el.scrollHeight - prevHeight;
        } else {
            loader.remove();
        }
    } catch(e) {
        loader.remove();
    }

    _cpLoadingOlder = false;
}

/* ── Init ── */
document.addEventListener('DOMContentLoaded', function() {
    cpLoad().then(function() {
        const params    = new URLSearchParams(location.search);
        const fromQuery = params.get('conv');
        const fromStore = localStorage.getItem('cp_active_conv');
        const toOpen    = fromQuery ? +fromQuery : (fromStore ? +fromStore : null);
        if (toOpen) setTimeout(() => cpSelect(toOpen), 100);
    });
    _cpPollTimer = setInterval(cpPoll, 5000);

    // Scroll-up listener for loading older messages
    const msgArea = document.getElementById('cp-msg-area');
    if (msgArea) {
        msgArea.addEventListener('scroll', function() {
            if (this.scrollTop < 80) cpLoadOlderMsgs();
        });
    }
});
})();

/* ── Chat drag-and-drop ── */
(function() {
    var right   = document.getElementById('cp-right');
    var overlay = document.getElementById('cp-drop-overlay');
    if (!right || !overlay) return;

    function hideOverlay() { overlay.style.display = 'none'; }

    right.addEventListener('dragenter', function(e) {
        if (!e.dataTransfer || !Array.from(e.dataTransfer.types).includes('Files')) return;
        if (!_cpActiveConvId) return;
        e.preventDefault();
        overlay.style.display = 'flex';
    });

    right.addEventListener('dragleave', function(e) {
        if (!e.relatedTarget || !right.contains(e.relatedTarget)) hideOverlay();
    });

    right.addEventListener('dragover', function(e) {
        if (!e.dataTransfer || !Array.from(e.dataTransfer.types).includes('Files')) return;
        if (!_cpActiveConvId) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    right.addEventListener('drop', async function(e) {
        e.preventDefault();
        hideOverlay();
        if (!_cpActiveConvId) return;
        const files = Array.from(e.dataTransfer.files);
        for (const file of files) {
            await window.uploadFileDirect(file, 'cp-textarea', 'cp-attach-preview');
        }
        const ta = document.getElementById('cp-textarea');
        if (ta) ta.focus();
    });

    // Fallback: ESC or drop outside browser
    document.addEventListener('dragend', hideOverlay);
    document.addEventListener('drop', function(e) { if (!right.contains(e.target)) hideOverlay(); });
})();
</script>
@endsection
