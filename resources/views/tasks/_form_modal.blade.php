@php
    $employees      = $employees      ?? \App\Models\User::where('is_active', true)->get();
    $projects       = $projects       ?? \App\Models\Project::all();
    $defaultProject = $defaultProject ?? null;
@endphp

<style>
@keyframes ntSlideUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
#nt-overlay { transition: opacity .18s ease; }
#nt-panel   { animation: ntSlideUp .22s cubic-bezier(.22,1,.36,1) both; }
.nt-sec  { padding:16px 0;border-bottom:1px solid #f1f3f5; }
.nt-lbl  { display:flex;align-items:center;gap:7px;margin-bottom:10px; }
.nt-lbl i    { font-size:10px;color:#9ca3af; }
.nt-lbl span { font-size:10.5px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em; }
.nt-pill {
    display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:20px;
    font-size:11.5px;font-weight:600;cursor:pointer;border:1.5px solid transparent;
    transition:all .15s ease;user-select:none;
}
.nt-pill:hover  { filter:brightness(.93); }
.nt-pill.active { box-shadow:0 0 0 2.5px rgba(0,0,0,.2); }
.nt-select {
    width:100%;border:1.5px solid #e2e8f0;border-radius:9px;padding:8px 12px;
    font-size:13px;color:#374151;background:#fff;outline:none;
    transition:border-color .15s;font-family:inherit;cursor:pointer;
}
.nt-select:focus { border-color:#0ea5e9; }
#nt-assignee-dd .nt-dd-opt,
#nt-participants-dd .nt-dd-opt,
#nt-observers-dd .nt-dd-opt {
    display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:7px;
    cursor:pointer;transition:background .12s;font-size:12.5px;color:#374151;
}
#nt-assignee-dd .nt-dd-opt:hover,
#nt-participants-dd .nt-dd-opt:hover,
#nt-observers-dd .nt-dd-opt:hover { background:#f1f5f9; }
</style>

{{-- Overlay --}}
<div id="nt-overlay"
     onclick="if(event.target===this)closeTaskModal()"
     style="display:none;opacity:0;position:fixed;inset:0;z-index:3000;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);overflow:hidden;margin-right:50px;">

    {{-- Close button (outside panel, top-left) --}}
    <button onclick="closeTaskModal()" type="button"
            style="position:absolute;left:24px;top:70px;width:42px;height:42px;border-radius:50%;background:#0ea5e9;border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(14,165,233,.4);transition:background .15s;z-index:10;"
            onmouseover="this.style.background='#0284c7'" onmouseout="this.style.background='#0ea5e9'">
        <i class="fas fa-times" style="font-size:14px;"></i>
    </button>

    {{-- Two-column panel --}}
    <div id="nt-panel"
         style="position:absolute;top:56px;left:90px;right:0;bottom:0;display:flex;background:#fff;border-radius:16px 0 0 0;overflow:hidden;box-shadow:0 -4px 40px rgba(0,0,0,.15);">

        {{-- Drag-and-drop overlay (shown when dragging files over panel) --}}
        <div id="nt-drop-overlay"
             style="display:none;position:absolute;inset:0;z-index:999;background:rgba(14,165,233,.08);border:3px dashed #0ea5e9;border-radius:16px 0 0 0;pointer-events:none;flex-direction:column;align-items:center;justify-content:center;gap:16px;">
            <div style="width:72px;height:72px;border-radius:50%;background:rgba(14,165,233,.15);display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-paperclip" style="font-size:28px;color:#0ea5e9;"></i>
            </div>
            <p style="font-size:16px;font-weight:700;color:#0ea5e9;margin:0;">Drag your files here</p>
            <p style="font-size:12px;color:#64748b;margin:0;">Release to attach to this task</p>
        </div>

        {{-- ===== LEFT COLUMN — form ===== --}}
        <form action="{{ route('tasks.store') }}" method="POST" id="nt-form"
              style="flex:0 0 660px;width:660px;display:flex;flex-direction:column;border-right:1px solid #e9ecef;overflow:hidden;">
            @csrf
            {{-- Hidden pill values --}}
            <input type="hidden" name="status"   id="nt-status-val"   value="new">
            <input type="hidden" name="priority" id="nt-priority-val" value="medium">
            <input type="hidden" name="assigned_to" id="nt-assigned-val" value="">

            {{-- Header --}}
            <div style="flex-shrink:0;padding:16px 24px;border-bottom:1px solid #f1f3f5;background:#f8fafc;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                    <div id="nt-mode-badge" style="display:flex;align-items:center;gap:4px;padding:2px 7px;background:#dcfce7;border:1px solid #86efac;border-radius:5px;">
                        <i class="fas fa-plus" style="font-size:8px;color:#15803d;"></i>
                        <span style="font-size:9px;font-weight:700;color:#15803d;letter-spacing:.6px;">NEW TASK</span>
                    </div>
                </div>
                <input name="title" required id="nt-title-input"
                       placeholder="Task title..."
                       oninput="ntSaveDraft()"
                       style="width:100%;border:none;outline:none;background:transparent;font-size:16px;font-weight:700;color:#111827;line-height:1.4;font-family:inherit;box-sizing:border-box;">
                {{-- Tab buttons --}}
                <div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap;position:relative;">
                    <button type="button" onclick="ntToggleSsSection()"
                            style="display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border:1.5px solid #e2e8f0;border-radius:7px;background:#fff;color:#374151;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;line-height:1;"
                            onmouseover="this.style.borderColor='#0ea5e9';this.style.color='#0ea5e9'"
                            onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#374151'">
                        <i class="fas fa-clipboard-list" style="font-size:11px;color:#0ea5e9;"></i>
                        Task status summaries
                    </button>
                    <div style="position:relative;">
                        <button type="button" id="nt-files-btn" onclick="ntToggleFilesMenu(event)"
                                style="display:inline-flex;align-items:center;gap:6px;padding:5px 11px;border:1.5px solid #e2e8f0;border-radius:7px;background:#fff;color:#374151;font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;line-height:1;"
                                onmouseover="this.style.borderColor='#6b7280'" onmouseout="this.style.borderColor='#e2e8f0'">
                            <i class="fas fa-paperclip" style="font-size:11px;color:#6b7280;"></i>
                            Files
                        </button>
                        <div id="nt-files-menu" style="display:none;position:absolute;left:0;top:calc(100% + 6px);z-index:50;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.14);padding:5px;min-width:220px;">
                            <div onclick="document.getElementById('nt-desc-file').click();document.getElementById('nt-files-menu').style.display='none';"
                                 style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-radius:7px;cursor:pointer;font-size:13px;color:#374151;transition:background .12s;"
                                 onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                <span>Upload</span>
                                <i class="fas fa-upload" style="font-size:12px;color:#9ca3af;"></i>
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-radius:7px;font-size:13px;color:#c4c9d4;">
                                <span>My Drive</span>
                                <i class="fas fa-cloud" style="font-size:12px;color:#d1d5db;"></i>
                            </div>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-radius:7px;font-size:13px;color:#c4c9d4;">
                                <span>External drives</span>
                                <i class="fas fa-chevron-right" style="font-size:10px;color:#d1d5db;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Scrollable body --}}
            <div style="flex:1;overflow-y:auto;padding:0 24px;scrollbar-width:thin;scrollbar-color:#dee2e6 transparent;background:#fff;">

                {{-- Task status summary (hidden until button clicked) --}}
                <div id="nt-ss-section" style="display:none;padding-top:14px;padding-bottom:4px;">
                    <div id="nt-ss-badge" style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:9px;position:relative;">
                        <div style="width:28px;height:28px;border-radius:7px;background:#0ea5e9;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-clipboard-list" style="font-size:12px;color:#fff;"></i>
                        </div>
                        <span style="font-size:13px;color:#0369a1;font-weight:500;flex:1;">Task status summary is required.</span>
                        <div style="position:relative;flex-shrink:0;">
                            <button type="button" onclick="ntToggleSsMenu(event)"
                                    style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px 7px;border-radius:6px;font-size:13px;line-height:1;transition:color .12s;"
                                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div id="nt-ss-menu" style="display:none;position:absolute;right:0;top:calc(100% + 4px);background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.13);padding:5px;min-width:230px;z-index:40;">
                                <div onclick="ntShowStatusSummary()"
                                     style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:7px;cursor:pointer;font-size:13px;color:#374151;transition:background .12s;"
                                     onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                                    <i class="fas fa-plus" style="font-size:11px;color:#0ea5e9;width:14px;text-align:center;"></i>
                                    <span>Add task status summary</span>
                                </div>
                                <div onclick="ntDismissStatusSummary()"
                                     style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:7px;cursor:pointer;font-size:13px;color:#ef4444;transition:background .12s;"
                                     onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background=''">
                                    <i class="fas fa-times" style="font-size:11px;color:#ef4444;width:14px;text-align:center;"></i>
                                    <span>Don't require task status summary</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <textarea id="nt-ss-ta" name="status_summary" placeholder="Write a brief status summary..."
                              style="display:none;width:100%;margin-top:8px;border:1.5px solid #bae6fd;border-radius:9px;padding:10px 13px;font-size:13px;color:#374151;background:#f0f9ff;resize:vertical;min-height:72px;outline:none;font-family:inherit;box-sizing:border-box;transition:border-color .15s;"
                              onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#bae6fd'" oninput="ntSaveDraft()"></textarea>
                </div>

                {{-- Description --}}
                <div class="nt-sec" style="padding-top:14px;padding-bottom:14px;">
                    <input type="file" id="nt-desc-file" multiple
                           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"
                           style="display:none" onchange="ntDescUploadFiles(this)">
                    <div id="nt-desc-card"
                         style="border:1.5px solid #e2e8f0;border-radius:10px;background:#fff;overflow:hidden;transition:border-color .15s;">
                        <textarea name="description" id="nt-desc-ta" rows="4"
                                  placeholder="Description"
                                  oninput="ntSaveDraft()"
                                  onfocus="document.getElementById('nt-desc-card').style.borderColor='#0ea5e9'" onblur="document.getElementById('nt-desc-card').style.borderColor='#e2e8f0'"
                                  style="display:block;width:100%;border:none;outline:none;background:transparent;padding:12px 14px;font-size:13.5px;color:#374151;resize:none;font-family:inherit;line-height:1.65;box-sizing:border-box;min-height:90px;"></textarea>
                        {{-- Toolbar --}}
                        <div style="display:flex;align-items:center;gap:2px;padding:5px 10px 7px;border-top:1px solid #f1f5f9;">
                            <button type="button" title="Attach file"
                                    onclick="document.getElementById('nt-desc-file').click()"
                                    style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px 6px;border-radius:6px;font-size:14px;line-height:1;transition:color .12s;"
                                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">
                                <i class="fas fa-paperclip"></i>
                            </button>
                            <button type="button" title="Mention"
                                    onmousedown="event.preventDefault(); ntDescInsert('@ ')"
                                    style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px 6px;border-radius:6px;font-size:14px;line-height:1;transition:color .12s;"
                                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">
                                <i class="fas fa-at"></i>
                            </button>
                            <button type="button" title="Bullet list"
                                    onmousedown="event.preventDefault(); ntDescInsert('• ')"
                                    style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px 6px;border-radius:6px;font-size:14px;line-height:1;transition:color .12s;"
                                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">
                                <i class="fas fa-list-ul"></i>
                            </button>
                            <button type="button" title="Numbered list"
                                    onmousedown="event.preventDefault(); ntDescInsertNumbered()"
                                    style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px 6px;border-radius:6px;font-size:14px;line-height:1;transition:color .12s;"
                                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">
                                <i class="fas fa-list-ol"></i>
                            </button>
                            <button type="button" title="More options"
                                    onmousedown="event.preventDefault()"
                                    style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px 6px;border-radius:6px;font-size:13px;line-height:1;transition:color .12s;"
                                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                            <div style="width:1px;height:16px;background:#e5e7eb;margin:0 4px;flex-shrink:0;"></div>
                            <button type="button" title="Checklist"
                                    onmousedown="event.preventDefault(); ntDescChecklist()"
                                    style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px 7px;border-radius:6px;font-size:12px;font-weight:600;line-height:1;transition:color .12s;display:flex;align-items:center;gap:4px;"
                                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">
                                <i class="fas fa-check-square" style="font-size:13px;"></i>
                                <span style="font-size:12px;">Checklist</span>
                            </button>
                            <div style="flex:1;"></div>
                            <button type="button" title="Expand"
                                    onmousedown="event.preventDefault()"
                                    style="background:none;border:none;color:#9ca3af;cursor:pointer;padding:4px 6px;border-radius:6px;font-size:13px;line-height:1;transition:color .12s;"
                                    onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9ca3af'">
                                <i class="fas fa-expand-alt"></i>
                            </button>
                        </div>
                    </div>
                    {{-- Attachment preview strip (below description card) --}}
                    <div id="nt-desc-attachments"
                         style="display:none;flex-wrap:wrap;gap:8px;margin-top:10px;"></div>
                </div>

                {{-- Deadline --}}
                <div class="nt-sec">
                    <div class="nt-lbl"><i class="fas fa-calendar-alt"></i><span>Deadline</span></div>
                    <input type="hidden" name="deadline" id="nt-deadline-val">
                    <div style="position:relative;display:inline-block;">
                        <div id="nt-deadline-trigger" onclick="ntToggleDeadlinePicker()"
                             style="display:inline-flex;align-items:center;gap:9px;padding:5px 14px 5px 6px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;background:#fff;transition:border-color .15s;user-select:none;"
                             onmouseover="this.style.borderColor='#0ea5e9'" onmouseout="this.style.borderColor='#e2e8f0'">
                            <div style="width:28px;height:28px;border-radius:7px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-th" style="font-size:10px;color:#0ea5e9;"></i>
                            </div>
                            <span id="nt-deadline-label" style="font-size:13px;color:#9ca3af;font-weight:500;">No deadline</span>
                        </div>
                        <div id="nt-deadline-picker" style="display:none;position:absolute;left:0;top:calc(100% + 8px);z-index:60;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.14);overflow:hidden;">
                            <div id="nt-picker-date-view" style="display:flex;">
                                <div style="padding:16px 14px;min-width:268px;">
                                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 2px;">
                                        <button type="button" onclick="ntCalPrev()"
                                                style="width:28px;height:28px;border:none;background:none;cursor:pointer;color:#6b7280;border-radius:6px;transition:background .12s;display:flex;align-items:center;justify-content:center;"
                                                onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background=''">
                                            <i class="fas fa-chevron-left" style="font-size:11px;"></i>
                                        </button>
                                        <span id="nt-cal-title" style="font-size:14px;font-weight:700;color:#374151;"></span>
                                        <button type="button" onclick="ntCalNext()"
                                                style="width:28px;height:28px;border:none;background:none;cursor:pointer;color:#6b7280;border-radius:6px;transition:background .12s;display:flex;align-items:center;justify-content:center;"
                                                onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background=''">
                                            <i class="fas fa-chevron-right" style="font-size:11px;"></i>
                                        </button>
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
                                    <div id="nt-cal-grid" style="display:grid;grid-template-columns:repeat(7,36px);"></div>
                                    {{-- Time row: appears after a date is clicked --}}
                                    <div id="nt-time-row" style="display:none;margin-top:10px;padding-top:8px;border-top:1px solid #f1f3f5;">
                                        <div onclick="ntShowTimeView()"
                                             style="display:inline-flex;align-items:center;gap:7px;padding:5px 10px;border:1.5px solid #e2e8f0;border-radius:7px;cursor:pointer;transition:all .12s;"
                                             onmouseover="this.style.borderColor='#0ea5e9';this.style.background='#f0f9ff'"
                                             onmouseout="this.style.borderColor='#e2e8f0';this.style.background=''">
                                            <i class="fas fa-clock" style="font-size:12px;color:#0ea5e9;"></i>
                                            <span id="nt-time-label" style="font-size:13px;color:#374151;font-weight:500;"></span>
                                        </div>
                                    </div>
                                </div>
                                <div style="width:1px;background:#f1f3f5;align-self:stretch;flex-shrink:0;"></div>
                                <div id="nt-cal-quick" style="padding:10px 8px;min-width:192px;display:flex;flex-direction:column;gap:5px;"></div>
                            </div>
                            {{-- Time picker view --}}
                            <div id="nt-picker-time-view" style="display:none;padding:16px 20px;">
                                <div style="display:flex;gap:20px;">
                                    <div>
                                        <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.06em;margin-bottom:5px;">AM</div>
                                        <div id="nt-hours-am" style="display:grid;grid-template-columns:repeat(4,38px);gap:3px;"></div>
                                        <div style="font-size:11px;font-weight:700;color:#9ca3af;letter-spacing:.06em;margin:8px 0 5px;">PM</div>
                                        <div id="nt-hours-pm" style="display:grid;grid-template-columns:repeat(4,38px);gap:3px;"></div>
                                    </div>
                                    <div style="width:1px;background:#f1f3f5;align-self:stretch;flex-shrink:0;"></div>
                                    <div id="nt-minutes" style="display:grid;grid-template-columns:repeat(2,46px);gap:3px;align-content:start;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Assigned To --}}
                <div class="nt-sec">
                    <div class="nt-lbl"><i class="fas fa-user"></i><span>Assigned To</span></div>
                    <div style="position:relative;">
                        <div id="nt-assignee-trigger"
                             onclick="ntToggleAssigneeDd()"
                             style="display:flex;align-items:center;gap:9px;padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;transition:border-color .15s;user-select:none;"
                             onmouseover="this.style.borderColor='#0ea5e9'" onmouseout="this.style.borderColor='#e2e8f0'">
                            <div id="nt-assignee-avatar"
                                 style="width:28px;height:28px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-user" style="font-size:11px;color:#9ca3af;"></i>
                            </div>
                            <span id="nt-assignee-name" style="font-size:13px;color:#9ca3af;flex:1;">Unassigned</span>
                            <i class="fas fa-chevron-down" style="font-size:9px;color:#9ca3af;"></i>
                        </div>
                        <div id="nt-assignee-dd"
                             style="display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:20;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:6px;box-shadow:0 8px 30px rgba(0,0,0,.12);">
                            {{-- Search --}}
                            <div style="padding:2px 2px 6px;">
                                <div style="display:flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:0 10px;">
                                    <i class="fas fa-search" style="font-size:11px;color:#9ca3af;flex-shrink:0;"></i>
                                    <input type="text" id="nt-assignee-search" placeholder="Search..."
                                           oninput="ntFilterAssignee(this.value)"
                                           style="border:none;background:none;outline:none;font-size:12.5px;color:#374151;padding:7px 0;width:100%;font-family:inherit;">
                                </div>
                            </div>
                            {{-- Options list --}}
                            <div id="nt-assignee-opts" style="max-height:180px;overflow-y:auto;">
                                <div class="nt-dd-opt" data-name="unassigned" onclick="ntPickAssignee(0,'','')">
                                    <div style="width:28px;height:28px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="fas fa-user" style="font-size:11px;color:#9ca3af;"></i>
                                    </div>
                                    <span style="color:#9ca3af;">Unassigned</span>
                                </div>
                                @foreach($employees as $emp)
                                @php
                                    $ntColors  = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
                                    $ntBg      = $ntColors[ord($emp->name[0] ?? 'A') % count($ntColors)];
                                    $ntInitial = strtoupper(substr($emp->name, 0, 1));
                                @endphp
                                <div class="nt-dd-opt" data-name="{{ strtolower($emp->name) }}"
                                     onclick="ntPickAssignee({{ $emp->id }}, '{{ addslashes($emp->avatar_url ?? '') }}', '{{ addslashes($emp->name) }}', '{{ $ntBg }}', '{{ $ntInitial }}')">
                                    @if($emp->avatar_url)
                                    <img src="{{ $emp->avatar_url }}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;" alt="">
                                    @else
                                    <div style="width:28px;height:28px;border-radius:50%;background:{{ $ntBg }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;">{{ $ntInitial }}</div>
                                    @endif
                                    {{ $emp->name }}
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Participants --}}
                <div class="nt-sec">
                    <div class="nt-lbl"><i class="fas fa-users"></i><span>Participants</span></div>
                    <div style="position:relative;">
                        {{-- Trigger --}}
                        <div id="nt-participants-trigger"
                             onclick="ntToggleParticipantsDd()"
                             style="display:flex;align-items:center;gap:9px;padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;transition:border-color .15s;user-select:none;min-height:44px;"
                             onmouseover="this.style.borderColor='#0ea5e9'" onmouseout="this.style.borderColor='#e2e8f0'">
                            <div id="nt-participants-avatars"
                                 style="display:flex;align-items:center;flex-shrink:0;">
                                <div style="width:28px;height:28px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-users" style="font-size:11px;color:#9ca3af;"></i>
                                </div>
                            </div>
                            <span id="nt-participants-label" style="font-size:13px;color:#9ca3af;flex:1;">Add participants...</span>
                            <i class="fas fa-chevron-down" style="font-size:9px;color:#9ca3af;"></i>
                        </div>
                        {{-- Dropdown --}}
                        <div id="nt-participants-dd"
                             style="display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:20;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:6px;box-shadow:0 8px 30px rgba(0,0,0,.12);">
                            {{-- Search --}}
                            <div style="padding:2px 2px 6px;">
                                <div style="display:flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:0 10px;">
                                    <i class="fas fa-search" style="font-size:11px;color:#9ca3af;flex-shrink:0;"></i>
                                    <input type="text" id="nt-participants-search" placeholder="Search..."
                                           oninput="ntFilterParticipants(this.value)"
                                           style="border:none;background:none;outline:none;font-size:12.5px;color:#374151;padding:7px 0;width:100%;font-family:inherit;">
                                </div>
                            </div>
                            {{-- Options --}}
                            <div id="nt-participants-opts" style="max-height:200px;overflow-y:auto;">
                                @foreach($employees as $emp)
                                @php
                                    $ntColors2  = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
                                    $ntBg2      = $ntColors2[ord($emp->name[0] ?? 'A') % count($ntColors2)];
                                    $ntInitial2 = strtoupper(substr($emp->name, 0, 1));
                                @endphp
                                <label class="nt-dd-opt" data-name="{{ strtolower($emp->name) }}">
                                    <input type="checkbox" name="members[]" value="{{ $emp->id }}"
                                           onchange="ntUpdateParticipantsTrigger(); ntSaveDraft()"
                                           style="width:15px;height:15px;accent-color:#0ea5e9;cursor:pointer;flex-shrink:0;">
                                    @if($emp->avatar_url)
                                    <img src="{{ $emp->avatar_url }}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;" alt="">
                                    @else
                                    <div style="width:28px;height:28px;border-radius:50%;background:{{ $ntBg2 }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;">{{ $ntInitial2 }}</div>
                                    @endif
                                    <span>{{ $emp->name }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Observers --}}
                <div class="nt-sec">
                    <div class="nt-lbl"><i class="fas fa-eye"></i><span>Observers</span></div>
                    <div style="position:relative;">
                        {{-- Trigger --}}
                        <div id="nt-observers-trigger"
                             onclick="ntToggleObserversDd()"
                             style="display:flex;align-items:center;gap:9px;padding:7px 12px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;transition:border-color .15s;user-select:none;min-height:44px;"
                             onmouseover="this.style.borderColor='#0ea5e9'" onmouseout="this.style.borderColor='#e2e8f0'">
                            <div id="nt-observers-avatars"
                                 style="display:flex;align-items:center;flex-shrink:0;">
                                <div style="width:28px;height:28px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-eye" style="font-size:11px;color:#9ca3af;"></i>
                                </div>
                            </div>
                            <span id="nt-observers-label" style="font-size:13px;color:#9ca3af;flex:1;">Add observers...</span>
                            <i class="fas fa-chevron-down" style="font-size:9px;color:#9ca3af;"></i>
                        </div>
                        {{-- Dropdown --}}
                        <div id="nt-observers-dd"
                             style="display:none;position:absolute;top:calc(100% + 6px);left:0;right:0;z-index:20;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:6px;box-shadow:0 8px 30px rgba(0,0,0,.12);">
                            {{-- Search --}}
                            <div style="padding:2px 2px 6px;">
                                <div style="display:flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;padding:0 10px;">
                                    <i class="fas fa-search" style="font-size:11px;color:#9ca3af;flex-shrink:0;"></i>
                                    <input type="text" id="nt-observers-search" placeholder="Search..."
                                           oninput="ntFilterObservers(this.value)"
                                           style="border:none;background:none;outline:none;font-size:12.5px;color:#374151;padding:7px 0;width:100%;font-family:inherit;">
                                </div>
                            </div>
                            {{-- Options --}}
                            <div id="nt-observers-opts" style="max-height:200px;overflow-y:auto;">
                                @foreach($employees as $emp)
                                @php
                                    $ntColors3  = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899'];
                                    $ntBg3      = $ntColors3[ord($emp->name[0] ?? 'A') % count($ntColors3)];
                                    $ntInitial3 = strtoupper(substr($emp->name, 0, 1));
                                @endphp
                                <label class="nt-dd-opt" data-name="{{ strtolower($emp->name) }}">
                                    <input type="checkbox" name="observers[]" value="{{ $emp->id }}"
                                           onchange="ntUpdateObserversTrigger(); ntSaveDraft()"
                                           style="width:15px;height:15px;accent-color:#0ea5e9;cursor:pointer;flex-shrink:0;">
                                    @if($emp->avatar_url)
                                    <img src="{{ $emp->avatar_url }}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;flex-shrink:0;" alt="">
                                    @else
                                    <div style="width:28px;height:28px;border-radius:50%;background:{{ $ntBg3 }};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;">{{ $ntInitial3 }}</div>
                                    @endif
                                    <span>{{ $emp->name }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Project --}}
                <div class="nt-sec">
                    <div class="nt-lbl"><i class="fas fa-folder"></i><span>Project</span></div>
                    <select name="project_id" class="nt-select"
                            onchange="ntSaveDraft()"
                            onfocus="this.style.borderColor='#0ea5e9'" onblur="this.style.borderColor='#e2e8f0'">
                        <option value="">No Project</option>
                        @foreach($projects as $proj)
                        <option value="{{ $proj->id }}" {{ $defaultProject == $proj->id ? 'selected' : '' }}>{{ $proj->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Status --}}
                <div class="nt-sec">
                    <div class="nt-lbl"><i class="fas fa-circle-dot"></i><span>Status</span></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <span class="nt-pill active" data-group="status" data-val="new"         style="background:#f1f5f9;color:#475569;" onclick="ntSetPill(this,'status')"><i class="fas fa-circle" style="font-size:6px;"></i>New</span>
                        <span class="nt-pill"         data-group="status" data-val="in_progress" style="background:#dbeafe;color:#1d4ed8;" onclick="ntSetPill(this,'status')"><i class="fas fa-circle" style="font-size:6px;"></i>In Progress</span>
                        <span class="nt-pill"         data-group="status" data-val="paused"       style="background:#fef3c7;color:#b45309;" onclick="ntSetPill(this,'status')"><i class="fas fa-circle" style="font-size:6px;"></i>Paused</span>
                        <span class="nt-pill"         data-group="status" data-val="completed"   style="background:#dcfce7;color:#15803d;" onclick="ntSetPill(this,'status')"><i class="fas fa-circle" style="font-size:6px;"></i>Completed</span>
                    </div>
                </div>

                {{-- Priority --}}
                <div class="nt-sec" style="border-bottom:none;">
                    <div class="nt-lbl"><i class="fas fa-flag"></i><span>Priority</span></div>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <span class="nt-pill"         data-group="priority" data-val="low"    style="background:#f1f5f9;color:#475569;" onclick="ntSetPill(this,'priority')"><i class="fas fa-flag" style="font-size:7px;"></i>Low</span>
                        <span class="nt-pill active"  data-group="priority" data-val="medium" style="background:#dbeafe;color:#1d4ed8;" onclick="ntSetPill(this,'priority')"><i class="fas fa-flag" style="font-size:7px;"></i>Medium</span>
                        <span class="nt-pill"         data-group="priority" data-val="high"   style="background:#ffedd5;color:#c2410c;" onclick="ntSetPill(this,'priority')"><i class="fas fa-flag" style="font-size:7px;"></i>High</span>
                        <span class="nt-pill"         data-group="priority" data-val="urgent" style="background:#fee2e2;color:#b91c1c;" onclick="ntSetPill(this,'priority')"><i class="fas fa-flag" style="font-size:7px;"></i>Urgent</span>
                    </div>
                </div>

                <div style="height:20px;"></div>
            </div>

            {{-- Footer --}}
            <div style="flex-shrink:0;padding:10px 20px;border-top:1px solid #f1f3f5;background:#fff;display:flex;align-items:center;gap:10px;">
                <button type="button" onclick="closeTaskModal()"
                        style="padding:8px 22px;border:1.5px solid #e2e8f0;background:#f8fafc;color:#374151;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s;"
                        onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f8fafc'">
                    Cancel
                </button>
                <button type="submit" id="nt-create-btn"
                        style="padding:8px 22px;background:#0ea5e9;border:none;color:#fff;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .15s;display:inline-flex;align-items:center;gap:7px;"
                        onmouseover="if(!this.disabled)this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-plus" style="font-size:11px;"></i>
                    Create Task
                </button>
            </div>
        </form>

        {{-- ===== RIGHT COLUMN — comments placeholder ===== --}}
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;overflow:hidden;background:#f8fafc;">

            {{-- Header --}}
            <div style="flex-shrink:0;padding:14px 20px;border-bottom:1px solid rgba(255,255,255,.4);display:flex;align-items:center;gap:7px;background:rgba(255,255,255,.55);backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);">
                <i class="fas fa-comment-dots" style="color:#0ea5e9;font-size:12px;"></i>
                <span style="color:#374151;font-size:13px;font-weight:700;">Comments</span>
            </div>

            {{-- Pattern area with placeholder --}}
            <div style="flex:1;display:flex;align-items:center;justify-content:center;background-image:url('{{ asset('pattern-chat.svg') }}');background-size:cover;background-position:center;background-repeat:no-repeat;">
                <div style="text-align:center;padding:40px 24px;">
                    <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.18);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                        <i class="fas fa-comment-slash" style="font-size:22px;color:rgba(255,255,255,.5);"></i>
                    </div>
                    <p style="color:rgba(255,255,255,.8);font-size:13px;font-weight:600;margin:0 0 5px;">No comments yet</p>
                    <p style="color:rgba(255,255,255,.4);font-size:12px;margin:0;line-height:1.6;">Save the task first<br>to start a discussion</p>
                </div>
            </div>
        </div>

    </div>{{-- /nt-panel --}}
</div>{{-- /nt-overlay --}}

<script>
(function(){
    // Pick a pill and store its value in the hidden input
    window.ntSetPill = function(el, group) {
        document.querySelectorAll('.nt-pill[data-group="'+group+'"]').forEach(function(p) {
            p.classList.remove('active');
            p.style.borderColor = 'transparent';
        });
        el.classList.add('active');
        el.style.borderColor = getComputedStyle(el).color;
        document.getElementById('nt-' + group + '-val').value = el.dataset.val;
        ntSaveDraft();
    };

    // Pick assignee from dropdown
    window.ntPickAssignee = function(id, avatarUrl, name, bg, initial) {
        document.getElementById('nt-assigned-val').value = id || '';
        var avatarDiv  = document.getElementById('nt-assignee-avatar');
        var nameSpan   = document.getElementById('nt-assignee-name');
        if (id && avatarUrl) {
            avatarDiv.innerHTML = '<img src="'+avatarUrl+'" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">';
        } else if (id) {
            avatarDiv.innerHTML = '<div style="width:28px;height:28px;border-radius:50%;background:'+bg+';display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;">'+initial+'</div>';
        } else {
            avatarDiv.innerHTML = '<i class="fas fa-user" style="font-size:11px;color:#9ca3af;"></i>';
        }
        nameSpan.textContent  = name || 'Unassigned';
        nameSpan.style.color  = id ? '#374151' : '#9ca3af';
        document.getElementById('nt-assignee-dd').style.display = 'none';
        // Persist assignee data for restore
        localStorage.setItem('nt_assigned_id',      id      || '');
        localStorage.setItem('nt_assigned_name',    name    || '');
        localStorage.setItem('nt_assigned_avatar',  avatarUrl || '');
        localStorage.setItem('nt_assigned_bg',      bg      || '');
        localStorage.setItem('nt_assigned_initial', initial || '');
    };

    var _ntRestoring = false;

    // Save all draft fields to localStorage
    window.ntSaveDraft = function() {
        if (_ntRestoring) return;
        var title    = document.getElementById('nt-title-input');
        var desc     = document.getElementById('nt-desc-ta');
        var status   = document.getElementById('nt-status-val');
        var priority = document.getElementById('nt-priority-val');
        var deadline = document.querySelector('#nt-form input[name="deadline"]');
        var project  = document.querySelector('#nt-form select[name="project_id"]');
        if (title)    localStorage.setItem('nt_title',    title.value);
        if (desc)     localStorage.setItem('nt_desc',     desc.value);
        if (status)   localStorage.setItem('nt_status',   status.value);
        if (priority) localStorage.setItem('nt_priority', priority.value);
        if (deadline) localStorage.setItem('nt_deadline', deadline.value);
        if (project)  localStorage.setItem('nt_project',  project.value);
        var memberIds = Array.from(document.querySelectorAll('#nt-form input[name="members[]"]:checked')).map(function(i){ return i.value; });
        localStorage.setItem('nt_members', JSON.stringify(memberIds));
        var observerIds = Array.from(document.querySelectorAll('#nt-form input[name="observers[]"]:checked')).map(function(i){ return i.value; });
        localStorage.setItem('nt_observers', JSON.stringify(observerIds));
        localStorage.setItem('nt_attachments', JSON.stringify(_ntAtt.filter(function(a){ return !a.uploading; })));
        var ssSec = document.getElementById('nt-ss-section');
        var ssTa  = document.getElementById('nt-ss-ta');
        localStorage.setItem('nt_ss_open',    ssSec && ssSec.style.display !== 'none' ? '1' : '0');
        localStorage.setItem('nt_ss_ta_show', ssTa  && ssTa.style.display  !== 'none' ? '1' : '0');
        localStorage.setItem('nt_ss_val',     ssTa  ? ssTa.value : '');
    };

    // Restore draft from localStorage into the form
    window.ntRestoreDraft = function() {
        _ntRestoring = true;
        // Read ALL saved values first, before any side-effect calls (e.g. ntSetDeadline→ntSaveDraft)
        var savedTitle     = localStorage.getItem('nt_title');
        var savedDesc      = localStorage.getItem('nt_desc');
        var savedStatus    = localStorage.getItem('nt_status')     || 'new';
        var savedPriority  = localStorage.getItem('nt_priority')   || 'medium';
        var savedDeadline  = localStorage.getItem('nt_deadline');
        var savedProject   = localStorage.getItem('nt_project');
        var savedMembers   = JSON.parse(localStorage.getItem('nt_members')    || '[]');
        var savedObservers = JSON.parse(localStorage.getItem('nt_observers')  || '[]');
        var savedAtt       = JSON.parse(localStorage.getItem('nt_attachments')|| '[]');
        var savedSsOpen    = localStorage.getItem('nt_ss_open')    === '1';
        var savedSsTaShow  = localStorage.getItem('nt_ss_ta_show') === '1';
        var savedSsVal     = localStorage.getItem('nt_ss_val')     || '';

        var title    = document.getElementById('nt-title-input');
        var desc     = document.getElementById('nt-desc-ta');
        var deadline = document.querySelector('#nt-form input[name="deadline"]');
        var project  = document.querySelector('#nt-form select[name="project_id"]');

        if (title   && savedTitle)   title.value   = savedTitle;
        if (desc    && savedDesc)    desc.value    = savedDesc;
        if (project && savedProject) project.value = savedProject;
        if (savedDeadline) {
            if (window.ntSetDeadline) ntSetDeadline(savedDeadline);
            else if (deadline) deadline.value = savedDeadline;
        }

        // Restore status pills
        document.querySelectorAll('.nt-pill[data-group="status"]').forEach(function(p) {
            var active = p.dataset.val === savedStatus;
            p.classList.toggle('active', active);
            p.style.borderColor = active ? getComputedStyle(p).color : 'transparent';
        });
        var sv = document.getElementById('nt-status-val');   if(sv) sv.value = savedStatus;

        // Restore priority pills
        document.querySelectorAll('.nt-pill[data-group="priority"]').forEach(function(p) {
            var active = p.dataset.val === savedPriority;
            p.classList.toggle('active', active);
            p.style.borderColor = active ? getComputedStyle(p).color : 'transparent';
        });
        var pv = document.getElementById('nt-priority-val'); if(pv) pv.value = savedPriority;

        // Restore assignee
        var aid     = localStorage.getItem('nt_assigned_id');
        var aname   = localStorage.getItem('nt_assigned_name');
        var aavatar = localStorage.getItem('nt_assigned_avatar');
        var abg     = localStorage.getItem('nt_assigned_bg');
        var ainit   = localStorage.getItem('nt_assigned_initial');
        if (aid && window.ntPickAssignee) ntPickAssignee(aid, aavatar, aname, abg, ainit);

        // Restore member checkboxes
        document.querySelectorAll('#nt-form input[name="members[]"]').forEach(function(cb) {
            cb.checked = savedMembers.indexOf(cb.value) !== -1;
        });
        ntUpdateParticipantsTrigger();

        // Restore observer checkboxes
        document.querySelectorAll('#nt-form input[name="observers[]"]').forEach(function(cb) {
            cb.checked = savedObservers.indexOf(cb.value) !== -1;
        });
        if (window.ntUpdateObserversTrigger) ntUpdateObserversTrigger();

        // Restore attachments (read at top to avoid stale overwrite from ntSetDeadline→ntSaveDraft)
        if (savedAtt.length) { _ntAtt = savedAtt; ntRenderAttachments(); }

        // Restore status summary section
        var ssSec = document.getElementById('nt-ss-section');
        var ssTa  = document.getElementById('nt-ss-ta');
        var ssBdg = document.getElementById('nt-ss-badge');
        if (ssSec) ssSec.style.display  = savedSsOpen ? 'block' : 'none';
        if (ssTa)  { ssTa.style.display = savedSsTaShow ? 'block' : 'none'; ssTa.value = savedSsVal; }
        if (ssBdg) ssBdg.style.display  = (savedSsOpen && !savedSsTaShow) ? 'flex' : 'none';

        _ntRestoring = false;
    };

    // Toggle assignee dropdown + focus search
    window.ntToggleAssigneeDd = function() {
        var dd = document.getElementById('nt-assignee-dd');
        var isOpen = dd.style.display !== 'none';
        dd.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) {
            var s = document.getElementById('nt-assignee-search');
            if (s) { s.value = ''; ntFilterAssignee(''); setTimeout(function(){ s.focus(); }, 30); }
        }
    };

    // Filter assignee dropdown options
    window.ntFilterAssignee = function(q) {
        var opts = document.querySelectorAll('#nt-assignee-opts .nt-dd-opt');
        q = (q || '').toLowerCase().trim();
        opts.forEach(function(el) {
            var name = (el.dataset.name || '');
            el.style.display = (!q || name.includes(q)) ? '' : 'none';
        });
    };

    // Toggle participants dropdown + focus search
    window.ntToggleParticipantsDd = function() {
        var dd = document.getElementById('nt-participants-dd');
        var isOpen = dd.style.display !== 'none';
        dd.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) {
            var s = document.getElementById('nt-participants-search');
            if (s) { s.value = ''; ntFilterParticipants(''); setTimeout(function(){ s.focus(); }, 30); }
        }
    };

    // Filter participants dropdown options
    window.ntFilterParticipants = function(q) {
        var labels = document.querySelectorAll('#nt-participants-opts label[data-name]');
        q = (q || '').toLowerCase().trim();
        labels.forEach(function(el) {
            el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
        });
    };

    // Update trigger display after checkbox change
    window.ntUpdateParticipantsTrigger = function() {
        var checked = Array.from(document.querySelectorAll('#nt-participants-opts input[type="checkbox"]:checked'));
        var avatarsEl = document.getElementById('nt-participants-avatars');
        var labelEl   = document.getElementById('nt-participants-label');
        if (!avatarsEl || !labelEl) return;
        if (!checked.length) {
            avatarsEl.innerHTML = '<div style="width:28px;height:28px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;"><i class="fas fa-users" style="font-size:11px;color:#9ca3af;"></i></div>';
            labelEl.textContent = 'Add participants...';
            labelEl.style.color = '#9ca3af';
            return;
        }
        // Show stacked avatars (up to 4)
        var shown = checked.slice(0, 4);
        var avatarHtml = '<div style="display:flex;align-items:center;">';
        shown.forEach(function(cb, i) {
            var label = cb.closest('label');
            var img   = label ? label.querySelector('img') : null;
            var av    = label ? label.querySelector('div[style*="border-radius:50%"]') : null;
            var avHtml = img
                ? '<img src="'+img.src+'" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid #fff;flex-shrink:0;'+(i>0?'margin-left:-8px':'')+'">'
                : (av ? '<div style="'+av.getAttribute('style')+';border:2px solid #fff;'+(i>0?'margin-left:-8px':'')+';width:26px;height:26px;">'+av.textContent+'</div>' : '');
            avatarHtml += avHtml;
        });
        avatarHtml += '</div>';
        avatarsEl.innerHTML = avatarHtml;
        labelEl.textContent = checked.length === 1
            ? checked[0].closest('label').querySelector('span').textContent.trim()
            : checked.length + ' participants';
        labelEl.style.color = '#374151';
    };

    // Toggle observers dropdown + focus search
    window.ntToggleObserversDd = function() {
        var dd = document.getElementById('nt-observers-dd');
        var isOpen = dd.style.display !== 'none';
        dd.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) {
            var s = document.getElementById('nt-observers-search');
            if (s) { s.value = ''; ntFilterObservers(''); setTimeout(function(){ s.focus(); }, 30); }
        }
    };

    // Filter observers dropdown options
    window.ntFilterObservers = function(q) {
        var labels = document.querySelectorAll('#nt-observers-opts label[data-name]');
        q = (q || '').toLowerCase().trim();
        labels.forEach(function(el) {
            el.style.display = (!q || el.dataset.name.includes(q)) ? '' : 'none';
        });
    };

    // Update observers trigger display after checkbox change
    window.ntUpdateObserversTrigger = function() {
        var checked = Array.from(document.querySelectorAll('#nt-observers-opts input[type="checkbox"]:checked'));
        var avatarsEl = document.getElementById('nt-observers-avatars');
        var labelEl   = document.getElementById('nt-observers-label');
        if (!avatarsEl || !labelEl) return;
        if (!checked.length) {
            avatarsEl.innerHTML = '<div style="width:28px;height:28px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;"><i class="fas fa-eye" style="font-size:11px;color:#9ca3af;"></i></div>';
            labelEl.textContent = 'Add observers...';
            labelEl.style.color = '#9ca3af';
            return;
        }
        var shown = checked.slice(0, 4);
        var avatarHtml = '<div style="display:flex;align-items:center;">';
        shown.forEach(function(cb, i) {
            var label = cb.closest('label');
            var img   = label ? label.querySelector('img') : null;
            var av    = label ? label.querySelector('div[style*="border-radius:50%"]') : null;
            var avHtml = img
                ? '<img src="'+img.src+'" style="width:26px;height:26px;border-radius:50%;object-fit:cover;border:2px solid #fff;flex-shrink:0;'+(i>0?'margin-left:-8px':'')+'">'
                : (av ? '<div style="'+av.getAttribute('style')+';border:2px solid #fff;'+(i>0?'margin-left:-8px':'')+';width:26px;height:26px;">'+av.textContent+'</div>' : '');
            avatarHtml += avHtml;
        });
        avatarHtml += '</div>';
        avatarsEl.innerHTML = avatarHtml;
        labelEl.textContent = checked.length === 1
            ? checked[0].closest('label').querySelector('span').textContent.trim()
            : checked.length + ' observers';
        labelEl.style.color = '#374151';
    };

    // ── Deadline calendar + time picker ──
    var _ntCal = { year: 0, month: 0, selected: null, selHour: -1, selMin: 0 };
    var _ntDlM = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var _ntDlD = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

    function ntFmtTime(h, m) {
        var per = h >= 12 ? 'pm' : 'am';
        var h12 = h % 12 || 12;
        return h12 + ':' + String(m).padStart(2,'0') + ' ' + per;
    }

    window.ntToggleDeadlinePicker = function() {
        var p = document.getElementById('nt-deadline-picker');
        if (!p) return;
        var open = p.style.display !== 'none';
        p.style.display = open ? 'none' : 'block';
        if (!open) {
            var dv = document.getElementById('nt-picker-date-view');
            var tv = document.getElementById('nt-picker-time-view');
            if (dv) dv.style.display = 'flex';
            if (tv) tv.style.display = 'none';
            var now = new Date();
            if (!_ntCal.year) { _ntCal.year = now.getFullYear(); _ntCal.month = now.getMonth(); }
            ntCalRender();
            ntCalQuick();
        }
    };
    window.ntCalPrev = function() {
        _ntCal.month--; if (_ntCal.month < 0) { _ntCal.month = 11; _ntCal.year--; } ntCalRender();
    };
    window.ntCalNext = function() {
        _ntCal.month++; if (_ntCal.month > 11) { _ntCal.month = 0; _ntCal.year++; } ntCalRender();
    };
    window.ntCalRender = function() {
        var year = _ntCal.year, month = _ntCal.month;
        var now   = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var titleEl = document.getElementById('nt-cal-title');
        if (titleEl) titleEl.textContent = _ntDlM[month] + ' ' + year;
        var firstDay = new Date(year, month, 1).getDay();
        var dim      = new Date(year, month + 1, 0).getDate();
        var diPrev   = new Date(year, month, 0).getDate();
        var cells = [];
        for (var i = firstDay - 1; i >= 0; i--)
            cells.push({ date: new Date(year, month - 1, diPrev - i), other: true });
        for (var d = 1; d <= dim; d++)
            cells.push({ date: new Date(year, month, d), other: false });
        var nx = 1;
        while (cells.length < 42)
            cells.push({ date: new Date(year, month + 1, nx++), other: true });
        var grid = document.getElementById('nt-cal-grid');
        if (!grid) return;
        grid.innerHTML = '';
        cells.forEach(function(c) {
            var ts    = c.date.getTime();
            var isTd  = ts === today.getTime();
            var isSel = _ntCal.selected && ts === _ntCal.selected.getTime();
            var isWk  = c.date.getDay() === 0 || c.date.getDay() === 6;
            var el = document.createElement('div');
            el.style.cssText = 'display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:13px;transition:background .1s;box-sizing:border-box;';
            if (isSel)        { el.style.background='#0ea5e9'; el.style.color='#fff'; el.style.fontWeight='700'; }
            else if (isTd)    { el.style.border='2px solid #0ea5e9'; el.style.color='#0ea5e9'; el.style.fontWeight='700'; }
            else if (c.other) { el.style.color='#d1d5db'; }
            else if (isWk)    { el.style.color='#ef4444'; }
            else              { el.style.color='#374151'; }
            el.textContent = c.date.getDate();
            var ds = c.date.getFullYear()+'-'+String(c.date.getMonth()+1).padStart(2,'0')+'-'+String(c.date.getDate()).padStart(2,'0');
            el.onclick = function() { ntSelectDate(ds); };
            if (!isSel) {
                el.onmouseover = function() { this.style.background='#f0f9ff'; };
                el.onmouseout  = function() { this.style.background=''; };
            }
            grid.appendChild(el);
        });
    };
    window.ntCalQuick = function() {
        var qEl = document.getElementById('nt-cal-quick');
        if (!qEl) return;
        var now   = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        function add(d,n){ var r=new Date(d); r.setDate(r.getDate()+n); return r; }
        function fri(d){ var df=(5-d.getDay()+7)%7; return add(d,df); }
        function eom(d){ return new Date(d.getFullYear(), d.getMonth()+1, 0); }
        function fmt(d){ return _ntDlD[d.getDay()]+', '+_ntDlM[d.getMonth()]+' '+d.getDate(); }
        function ds(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
        var opts = [
            { label:'Today',           sub:fmt(today),        dateStr:ds(today) },
            { label:'Tomorrow',        sub:fmt(add(today,1)), dateStr:ds(add(today,1)) },
            { label:'Late this week',  sub:fmt(fri(today)),   dateStr:ds(fri(today)) },
            { label:'In a week',       sub:fmt(add(today,7)), dateStr:ds(add(today,7)) },
            { label:'Late this month', sub:fmt(eom(today)),   dateStr:ds(eom(today)) },
        ];
        qEl.innerHTML = '';
        opts.forEach(function(opt) {
            var el = document.createElement('div');
            el.style.cssText = 'padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:9px;cursor:pointer;transition:all .12s;';
            el.innerHTML = '<div style="font-size:13px;font-weight:600;color:#374151;white-space:nowrap;">'+opt.label+'</div>'
                         + '<div style="font-size:11px;color:#9ca3af;margin-top:2px;white-space:nowrap;">'+opt.sub+'</div>';
            el.onclick = function() { ntSelectDate(opt.dateStr); };
            el.onmouseover = function(){ this.style.borderColor='#0ea5e9'; this.style.background='#f0f9ff'; };
            el.onmouseout  = function(){ this.style.borderColor='#e2e8f0'; this.style.background=''; };
            qEl.appendChild(el);
        });
    };
    window.ntSelectDate = function(dateStr) {
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return;
        _ntCal.selected = d; _ntCal.year = d.getFullYear(); _ntCal.month = d.getMonth();
        // Default to current local time if not yet set
        if (_ntCal.selHour < 0) {
            var now = new Date();
            _ntCal.selHour = now.getHours();
            _ntCal.selMin  = Math.floor(now.getMinutes() / 5) * 5;
        }
        var tl = document.getElementById('nt-time-label');
        if (tl) tl.textContent = ntFmtTime(_ntCal.selHour, _ntCal.selMin);
        var tr = document.getElementById('nt-time-row');
        if (tr) tr.style.display = 'block';
        ntCalRender();
        // Immediately set the deadline value + update trigger label
        var mo = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        var dateOnly = d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
        var fullDt   = dateOnly+' '+String(_ntCal.selHour).padStart(2,'0')+':'+String(_ntCal.selMin).padStart(2,'0')+':00';
        var input = document.getElementById('nt-deadline-val');
        var label = document.getElementById('nt-deadline-label');
        if (input) input.value = fullDt;
        if (label) {
            label.textContent = mo[d.getMonth()]+' '+d.getDate()+', '+d.getFullYear()+' · '+ntFmtTime(_ntCal.selHour, _ntCal.selMin);
            label.style.color = '#374151';
        }
        if (typeof ntSaveDraft === 'function') ntSaveDraft();
    };
    window.ntShowTimeView = function() {
        var dv = document.getElementById('nt-picker-date-view');
        var tv = document.getElementById('nt-picker-time-view');
        if (dv) dv.style.display = 'none';
        if (tv) tv.style.display = 'block';
        ntRenderTimeGrid();
    };
    window.ntRenderTimeGrid = function() {
        function renderH(id, h24arr, disp12) {
            var el = document.getElementById(id); if (!el) return; el.innerHTML = '';
            h24arr.forEach(function(h24, i) {
                var isSel = _ntCal.selHour === h24;
                var btn = document.createElement('div');
                btn.style.cssText = 'width:38px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:7px;cursor:pointer;font-size:13px;transition:background .1s;background:'+(isSel?'#0ea5e9':'')+';color:'+(isSel?'#fff':'#374151')+';font-weight:'+(isSel?'700':'400')+';';
                btn.textContent = disp12[i];
                btn.onclick = function() { ntPickHour(h24); };
                if (!isSel) { btn.onmouseover=function(){this.style.background='#f0f9ff';}; btn.onmouseout=function(){this.style.background='';}; }
                el.appendChild(btn);
            });
        }
        renderH('nt-hours-am',[0,1,2,3,4,5,6,7,8,9,10,11],[12,1,2,3,4,5,6,7,8,9,10,11]);
        renderH('nt-hours-pm',[12,13,14,15,16,17,18,19,20,21,22,23],[12,1,2,3,4,5,6,7,8,9,10,11]);
        var mEl = document.getElementById('nt-minutes'); if (!mEl) return; mEl.innerHTML = '';
        [0,5,10,15,20,25,30,35,40,45,50,55].forEach(function(m) {
            var isSel = _ntCal.selMin === m;
            var btn = document.createElement('div');
            btn.style.cssText = 'width:46px;height:34px;display:flex;align-items:center;justify-content:center;border-radius:7px;cursor:pointer;font-size:13px;transition:background .1s;background:'+(isSel?'#0ea5e9':'')+';color:'+(isSel?'#fff':'#374151')+';font-weight:'+(isSel?'700':'400')+';';
            btn.textContent = String(m).padStart(2,'0');
            btn.onclick = function() { ntPickMinute(m); };
            if (!isSel) { btn.onmouseover=function(){this.style.background='#f0f9ff';}; btn.onmouseout=function(){this.style.background='';}; }
            mEl.appendChild(btn);
        });
    };
    window.ntPickHour = function(h24) {
        _ntCal.selHour = h24; ntRenderTimeGrid();
        var tl = document.getElementById('nt-time-label');
        if (tl) tl.textContent = ntFmtTime(h24, _ntCal.selMin);
    };
    window.ntPickMinute = function(m) {
        _ntCal.selMin = m;
        if (!_ntCal.selected) return;
        var d = _ntCal.selected;
        var dateOnly = d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
        var fullDt   = dateOnly+' '+String(_ntCal.selHour).padStart(2,'0')+':'+String(m).padStart(2,'0')+':00';
        var input = document.getElementById('nt-deadline-val');
        var label = document.getElementById('nt-deadline-label');
        if (input) input.value = fullDt;
        if (label) {
            var mo = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            label.textContent = mo[d.getMonth()]+' '+d.getDate()+', '+d.getFullYear()+' · '+ntFmtTime(_ntCal.selHour, m);
            label.style.color = '#374151';
        }
        var picker = document.getElementById('nt-deadline-picker');
        if (picker) picker.style.display = 'none';
        var dv = document.getElementById('nt-picker-date-view');
        var tv = document.getElementById('nt-picker-time-view');
        if (dv) dv.style.display = 'flex';
        if (tv) tv.style.display = 'none';
        if (typeof ntSaveDraft === 'function') ntSaveDraft();
    };
    window.ntSetDeadline = function(dateStr) {
        var input = document.getElementById('nt-deadline-val');
        var label = document.getElementById('nt-deadline-label');
        if (dateStr) {
            var parts    = dateStr.replace('T',' ').split(' ');
            var dateOnly = parts[0];
            var timePart = parts[1] || null;
            var d = new Date(dateOnly + 'T00:00:00');
            if (isNaN(d.getTime())) {
                _ntCal.selected=null; _ntCal.selHour=-1; _ntCal.selMin=0;
                if (input) input.value='';
                if (label) { label.textContent='No deadline'; label.style.color='#9ca3af'; }
                return;
            }
            _ntCal.selected=d; _ntCal.year=d.getFullYear(); _ntCal.month=d.getMonth();
            if (timePart) {
                var tp = timePart.split(':');
                _ntCal.selHour = parseInt(tp[0])||0;
                _ntCal.selMin  = parseInt(tp[1])||0;
            }
            if (input) input.value = dateStr;
            if (label) {
                var mo = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                var td = (_ntCal.selHour >= 0 && timePart) ? ' · '+ntFmtTime(_ntCal.selHour,_ntCal.selMin) : '';
                label.textContent = mo[d.getMonth()]+' '+d.getDate()+', '+d.getFullYear()+td;
                label.style.color = '#374151';
            }
            var tr = document.getElementById('nt-time-row');
            var tl = document.getElementById('nt-time-label');
            if (tr && timePart) tr.style.display = 'block';
            if (tl && _ntCal.selHour >= 0) tl.textContent = ntFmtTime(_ntCal.selHour, _ntCal.selMin);
        } else {
            _ntCal.selected=null; _ntCal.selHour=-1; _ntCal.selMin=0;
            if (input) input.value='';
            if (label) { label.textContent='No deadline'; label.style.color='#9ca3af'; }
            var tr2 = document.getElementById('nt-time-row');
            if (tr2) tr2.style.display = 'none';
        }
        ntCalRender();
        var p = document.getElementById('nt-deadline-picker');
        if (p) p.style.display = 'none';
        if (typeof ntSaveDraft === 'function') ntSaveDraft();
    };

    // Task status summary
    window.ntToggleSsSection = function() {
        var sec = document.getElementById('nt-ss-section');
        if (!sec) return;
        var open = sec.style.display !== 'none';
        sec.style.display = open ? 'none' : 'block';
        if (!open) {
            // reset to badge view when reopening
            var badge = document.getElementById('nt-ss-badge');
            var ta    = document.getElementById('nt-ss-ta');
            if (badge) badge.style.display = 'flex';
            if (ta)    ta.style.display    = 'none';
        }
        ntSaveDraft();
    };
    window.ntToggleSsMenu = function(e) {
        e.stopPropagation();
        var m = document.getElementById('nt-ss-menu');
        if (m) m.style.display = m.style.display === 'none' ? 'block' : 'none';
    };
    window.ntShowStatusSummary = function() {
        document.getElementById('nt-ss-menu').style.display  = 'none';
        document.getElementById('nt-ss-badge').style.display = 'none';
        var ta = document.getElementById('nt-ss-ta');
        ta.style.display = 'block';
        ta.focus();
        ntSaveDraft();
    };
    window.ntDismissStatusSummary = function() {
        document.getElementById('nt-ss-menu').style.display    = 'none';
        document.getElementById('nt-ss-section').style.display = 'none';
        ntSaveDraft();
    };

    // Files dropdown
    window.ntToggleFilesMenu = function(e) {
        e.stopPropagation();
        var m = document.getElementById('nt-files-menu');
        if (m) m.style.display = m.style.display === 'none' ? 'block' : 'none';
    };

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
        var add = document.getElementById('nt-assignee-dd'),    atr = document.getElementById('nt-assignee-trigger');
        var pdd = document.getElementById('nt-participants-dd'), ptr = document.getElementById('nt-participants-trigger');
        var odd = document.getElementById('nt-observers-dd'),   otr = document.getElementById('nt-observers-trigger');
        var ssm = document.getElementById('nt-ss-menu');
        var fm  = document.getElementById('nt-files-menu'),     fb  = document.getElementById('nt-files-btn');
        var dlp = document.getElementById('nt-deadline-picker'), dlt = document.getElementById('nt-deadline-trigger');
        if (add && atr && !add.contains(e.target) && !atr.contains(e.target)) add.style.display = 'none';
        if (pdd && ptr && !pdd.contains(e.target) && !ptr.contains(e.target)) pdd.style.display = 'none';
        if (odd && otr && !odd.contains(e.target) && !otr.contains(e.target)) odd.style.display = 'none';
        if (ssm && !ssm.contains(e.target)) ssm.style.display = 'none';
        if (fm  && fb  && !fm.contains(e.target) && !fb.contains(e.target))  fm.style.display  = 'none';
        if (dlp && dlt && !dlp.contains(e.target) && !dlt.contains(e.target)) dlp.style.display = 'none';
    });

    /* ── Attachment system ── */
    var _ntAtt = []; // {name, url, isImage, ext, uploading}

    function ntFileStyle(ext) {
        var map = {
            doc:'#2b579a', docx:'#2b579a',
            xls:'#217346', xlsx:'#217346',
            pdf:'#e53e3e',
            zip:'#d97706', rar:'#d97706',
            txt:'#6b7280', csv:'#6b7280',
            ppt:'#d24726', pptx:'#d24726',
            html:'#6b7280', htm:'#6b7280',
        };
        return { color: map[ext] || '#6b7280', label: (ext || 'file').toUpperCase().slice(0,5) };
    }

    function ntFileCardHtml(a, idx) {
        if (a.uploading) {
            return '<div style="width:110px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;padding:12px 8px 8px;display:flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0;">'
                + '<div style="width:52px;height:62px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-spinner fa-spin" style="font-size:22px;color:#9ca3af;"></i></div>'
                + '<span style="font-size:10.5px;color:#9ca3af;">Uploading…</span></div>';
        }
        var shortName = a.name.length > 16 ? a.name.slice(0,13)+'…' : a.name;
        var iconHtml;
        if (a.isImage) {
            iconHtml = '<img src="'+a.url+'" style="width:52px;height:62px;object-fit:cover;border-radius:6px;border:1px solid #e2e8f0;">';
        } else {
            var fs = ntFileStyle(a.ext);
            iconHtml = '<div style="width:52px;height:62px;position:relative;flex-shrink:0;">'
                + '<svg viewBox="0 0 52 62" style="width:52px;height:62px;" xmlns="http://www.w3.org/2000/svg">'
                + '<path d="M4,0 L36,0 L52,16 L52,58 Q52,62 48,62 L4,62 Q0,62 0,58 L0,4 Q0,0 4,0 Z" fill="#f8fafc" stroke="#e2e8f0" stroke-width="1.5"/>'
                + '<path d="M36,0 L36,16 L52,16 Z" fill="#e2e8f0"/>'
                + '<rect x="6" y="32" width="40" height="20" rx="3" fill="'+fs.color+'"/>'
                + '<text x="26" y="46" text-anchor="middle" fill="#fff" font-size="9" font-weight="700" font-family="sans-serif">'+fs.label+'</text>'
                + '</svg></div>';
        }
        return '<div style="position:relative;width:110px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:12px 8px 8px;display:flex;flex-direction:column;align-items:center;gap:6px;flex-shrink:0;transition:box-shadow .12s;" onmouseover="this.style.boxShadow=\'0 2px 8px rgba(0,0,0,.08)\'" onmouseout="this.style.boxShadow=\'none\'">'
            + '<button type="button" onclick="ntRemoveAttachment('+idx+')" style="position:absolute;top:5px;right:6px;background:none;border:none;color:#9ca3af;font-size:14px;cursor:pointer;line-height:1;padding:0;transition:color .12s;" onmouseover="this.style.color=\'#ef4444\'" onmouseout="this.style.color=\'#9ca3af\'">×</button>'
            + iconHtml
            + '<span title="'+a.name+'" style="font-size:10.5px;color:#374151;text-align:center;line-height:1.35;width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'+shortName+'</span>'
            + '</div>';
    }

    window.ntRenderAttachments = function() {
        var container = document.getElementById('nt-desc-attachments');
        if (!container) return;
        if (!_ntAtt.length) { container.style.display = 'none'; container.innerHTML = ''; return; }
        var cards = _ntAtt.map(function(a, i){ return ntFileCardHtml(a, i); }).join('');
        container.style.display = 'flex';
        container.innerHTML =
            '<div style="width:100%;display:flex;align-items:center;gap:7px;margin-bottom:8px;">'
            + '<i class="fas fa-paperclip" style="color:#9ca3af;font-size:12px;"></i>'
            + '<span style="font-size:13px;font-weight:600;color:#374151;">Files: '+_ntAtt.filter(function(a){return !a.uploading;}).length+'</span>'
            + '<div style="flex:1;"></div>'
            + '<button type="button" onclick="document.getElementById(\'nt-desc-file\').click()" style="width:26px;height:26px;border:1.5px solid #e2e8f0;border-radius:7px;background:#f8fafc;color:#6b7280;font-size:16px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .12s;" onmouseover="this.style.borderColor=\'#0ea5e9\';this.style.color=\'#0ea5e9\'" onmouseout="this.style.borderColor=\'#e2e8f0\';this.style.color=\'#6b7280\'">+</button>'
            + '</div>'
            + '<div style="display:flex;flex-wrap:wrap;gap:8px;">'+cards+'</div>';
    };

    window.ntRemoveAttachment = function(idx) {
        _ntAtt.splice(idx, 1);
        ntRenderAttachments();
        ntSaveDraft();
    };

    window.ntResetAttachments = function() {
        _ntAtt = [];
        ntRenderAttachments();
    };

    window.ntSetAttachments = function(list) {
        _ntAtt = (list || []).map(function(a) {
            var ext = (a.name || a.url || '').split('.').pop().toLowerCase();
            var isImage = /^(jpe?g|png|gif|webp|svg)$/.test(ext);
            return { name: a.name || a.url.split('/').pop(), url: a.url, isImage: isImage, ext: ext, uploading: false };
        });
        ntRenderAttachments();
    };

    window.ntDescUploadFiles = async function(input) {
        if (!input.files.length) return;
        var files = Array.from(input.files);
        input.value = '';
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            var ext = (file.name.split('.').pop() || '').toLowerCase();
            var isImage = /^(jpe?g|png|gif|webp|svg)$/.test(ext);
            var placeholderIdx = _ntAtt.length;
            _ntAtt.push({ name: file.name, url: null, isImage: isImage, ext: ext, uploading: true });
            ntRenderAttachments();
            try {
                var fd = new FormData();
                fd.append('file', file);
                fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                var r = await fetch(API_BASE + '/api/upload', { method: 'POST', body: fd });
                var d = await r.json();
                if (!d.url) throw new Error('no url');
                _ntAtt[placeholderIdx] = { name: file.name, url: d.url, isImage: isImage, ext: ext, uploading: false };
            } catch(e) {
                _ntAtt.splice(placeholderIdx, 1);
            }
            ntRenderAttachments();
            ntSaveDraft();
        }
    };

    /* ── Drag-and-drop upload on panel ── */
    (function() {
        var panel    = document.getElementById('nt-panel');
        var overlay  = document.getElementById('nt-drop-overlay');
        var dragCount = 0;
        if (!panel || !overlay) return;

        panel.addEventListener('dragenter', function(e) {
            if (!e.dataTransfer.types.includes('Files')) return;
            e.preventDefault();
            dragCount++;
            overlay.style.display = 'flex';
        });
        panel.addEventListener('dragleave', function(e) {
            dragCount--;
            if (dragCount <= 0) { dragCount = 0; overlay.style.display = 'none'; }
        });
        panel.addEventListener('dragover', function(e) {
            if (!e.dataTransfer.types.includes('Files')) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
        });
        panel.addEventListener('drop', async function(e) {
            e.preventDefault();
            dragCount = 0;
            overlay.style.display = 'none';
            var files = Array.from(e.dataTransfer.files);
            if (!files.length) return;
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var ext = (file.name.split('.').pop() || '').toLowerCase();
                var isImage = /^(jpe?g|png|gif|webp|svg)$/.test(ext);
                var placeholderIdx = _ntAtt.length;
                _ntAtt.push({ name: file.name, url: null, isImage: isImage, ext: ext, uploading: true });
                ntRenderAttachments();
                try {
                    var fd = new FormData();
                    fd.append('file', file);
                    fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);
                    var r = await fetch(API_BASE + '/api/upload', { method: 'POST', body: fd });
                    var d = await r.json();
                    if (!d.url) throw new Error('no url');
                    _ntAtt[placeholderIdx] = { name: file.name, url: d.url, isImage: isImage, ext: ext, uploading: false };
                } catch(err) {
                    _ntAtt.splice(placeholderIdx, 1);
                }
                ntRenderAttachments();
                if (typeof ntSaveDraft === 'function') ntSaveDraft();
            }
        });
    })();

    // Edit mode state
    var _ntEditMode = false;
    var _ntEditOnSaved = null;

    window.ntEnterEditMode = function(taskId, updateUrl, onSaved) {
        _ntEditMode    = true;
        _ntEditOnSaved = onSaved || null;
        // Change form action + add method spoof
        var form = document.getElementById('nt-form');
        if (form) form.setAttribute('data-edit-action', updateUrl);
        // Badge → EDIT TASK
        var badge = document.getElementById('nt-mode-badge');
        if (badge) badge.innerHTML = '<i class="fas fa-pen" style="font-size:8px;color:#6d28d9;"></i><span style="font-size:9px;font-weight:700;color:#6d28d9;letter-spacing:.6px;">EDIT TASK</span>';
        if (badge) { badge.style.background='#ede9fe'; badge.style.border='1px solid #c4b5fd'; }
        // Button → Save Changes
        var btn = document.getElementById('nt-create-btn');
        if (btn) btn.innerHTML = '<i class="fas fa-save" style="font-size:11px;"></i>&nbsp;Save Changes';
    };
    window.ntExitEditMode = function() {
        _ntEditMode    = false;
        _ntEditOnSaved = null;
        var form = document.getElementById('nt-form');
        if (form) form.removeAttribute('data-edit-action');
        // Badge → NEW TASK
        var badge = document.getElementById('nt-mode-badge');
        if (badge) badge.innerHTML = '<i class="fas fa-plus" style="font-size:8px;color:#15803d;"></i><span style="font-size:9px;font-weight:700;color:#15803d;letter-spacing:.6px;">NEW TASK</span>';
        if (badge) { badge.style.background='#dcfce7'; badge.style.border='1px solid #86efac'; }
        // Button → Create Task
        var btn = document.getElementById('nt-create-btn');
        if (btn) btn.innerHTML = '<i class="fas fa-plus" style="font-size:11px;"></i>&nbsp;Create Task';
    };

    // AJAX form submit — no page reload
    document.getElementById('nt-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var form    = this;
        var btn     = document.getElementById('nt-create-btn');
        var isEdit  = _ntEditMode;
        var editUrl = form.getAttribute('data-edit-action');

        // Inject attachment tags into description (create and edit mode)
        var tags = _ntAtt.filter(function(a){ return a.url; }).map(function(a){
            return a.isImage ? '[img]'+a.url+'[/img]' : '[file name="'+a.name+'"]'+a.url+'[/file]';
        }).join('\n');
        if (tags) {
            var ta = document.getElementById('nt-desc-ta');
            if (ta) ta.value = (ta.value ? ta.value + '\n' : '') + tags;
        }

        // Loading state
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size:11px;"></i>&nbsp;' + (isEdit ? 'Saving…' : 'Creating…'); }

        var fetchUrl    = isEdit ? editUrl : form.action;
        var fetchMethod = isEdit ? 'PUT'   : 'POST';
        var fetchBody;
        if (isEdit) {
            // Send JSON for PUT
            var csrf = document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').content : '';
            var titleEl  = document.getElementById('nt-title-input');
            var descEl   = document.getElementById('nt-desc-ta');
            var dlVal    = document.getElementById('nt-deadline-val');
            var statusV  = document.getElementById('nt-status-val');
            var priorityV= document.getElementById('nt-priority-val');
            var assignedV= document.getElementById('nt-assigned-val');
            var projSel  = form.querySelector('select[name="project_id"]');
            var memberIds   = Array.from(document.querySelectorAll('#nt-participants-opts input[type="checkbox"]:checked')).map(function(i){ return i.value; });
            var observerIds = Array.from(document.querySelectorAll('#nt-observers-opts input[type="checkbox"]:checked')).map(function(i){ return i.value; });
            fetchBody = JSON.stringify({
                title:       titleEl   ? titleEl.value   : '',
                description: descEl    ? descEl.value    : '',
                deadline:    dlVal     ? dlVal.value     : null,
                status:      statusV   ? statusV.value   : 'new',
                priority:    priorityV ? priorityV.value : 'medium',
                assigned_to: assignedV ? (assignedV.value || null) : null,
                project_id:  projSel   ? (projSel.value || null)   : null,
                members:     memberIds,
                observers:   observerIds,
            });
        } else {
            fetchBody = new FormData(form);
        }

        var headers = isEdit
            ? { 'Content-Type':'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]')||{}).content||'', 'Accept':'application/json' }
            : { 'X-Requested-With': 'XMLHttpRequest' };

        fetch(fetchUrl, { method: fetchMethod, headers: headers, body: fetchBody })
        .then(function(res) { if (!res.ok) throw new Error(res.status); return res.json(); })
        .then(function() {
            if (isEdit) {
                var cb = _ntEditOnSaved;
                ntExitEditMode();
                closeTaskModal();
                ntShowToast('Task updated successfully!');
                if (cb) cb();
            } else {
                ['nt_open','nt_title','nt_desc','nt_status','nt_priority','nt_deadline','nt_project',
                 'nt_assigned_id','nt_assigned_name','nt_assigned_avatar','nt_assigned_bg','nt_assigned_initial',
                 'nt_members','nt_observers','nt_attachments','nt_ss_open','nt_ss_ta_show','nt_ss_val']
                .forEach(function(k){ localStorage.removeItem(k); });
                closeTaskModal();
                ntShowToast('Task created successfully!');
                ntRefreshTaskList();
            }
        })
        .catch(function() {
            if (btn) { btn.disabled = false; btn.innerHTML = isEdit ? '<i class="fas fa-save" style="font-size:11px;"></i>&nbsp;Save Changes' : '<i class="fas fa-plus" style="font-size:11px;"></i>&nbsp;Create Task'; }
            ntShowToast('Failed to ' + (isEdit ? 'update' : 'create') + ' task. Please try again.', true);
        });
    });

    window.ntRefreshTaskList = function() {
        var cur = document.getElementById('nt-tasks-content');
        if (!cur) return;
        fetch(location.href, { credentials: 'same-origin' })
        .then(function(r) { return r.text(); })
        .then(function(html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var fresh = doc.getElementById('nt-tasks-content');
            if (fresh) cur.innerHTML = fresh.innerHTML;
        })
        .catch(function() {});
    };

    window.ntShowToast = function(msg, isError) {
        var t = document.createElement('div');
        t.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(16px);z-index:99999;'
            + 'background:'+(isError?'#ef4444':'#10b981')+';color:#fff;padding:11px 22px;border-radius:11px;'
            + 'font-size:13.5px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.2);opacity:0;'
            + 'transition:opacity .2s ease,transform .2s ease;display:flex;align-items:center;gap:8px;pointer-events:none;white-space:nowrap;';
        t.innerHTML = '<i class="fas '+(isError?'fa-times-circle':'fa-check-circle')+'"></i>' + msg;
        document.body.appendChild(t);
        requestAnimationFrame(function(){
            t.style.opacity = '1'; t.style.transform = 'translateX(-50%) translateY(0)';
        });
        setTimeout(function(){
            t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(16px)';
            setTimeout(function(){ t.remove(); }, 250);
        }, 3000);
    };

    // Insert text at textarea cursor position
    window.ntDescInsert = function(prefix) {
        var ta = document.getElementById('nt-desc-ta');
        if (!ta) return;
        var start = ta.selectionStart, end = ta.selectionEnd;
        var val   = ta.value;
        // If cursor is mid-line, jump to start of next line first
        var before = val.slice(0, start);
        var needsNewline = before.length > 0 && before[before.length - 1] !== '\n';
        var insert = (needsNewline ? '\n' : '') + prefix;
        ta.value = before + insert + val.slice(end);
        var pos = before.length + insert.length;
        ta.setSelectionRange(pos, pos);
        ta.focus();
        ta.dispatchEvent(new Event('input'));
    };

    // Numbered list — finds next number automatically
    window.ntDescInsertNumbered = function() {
        var ta = document.getElementById('nt-desc-ta');
        if (!ta) return;
        var lines  = ta.value.slice(0, ta.selectionStart).split('\n');
        var num    = 1;
        for (var i = lines.length - 1; i >= 0; i--) {
            var m = lines[i].match(/^(\d+)\.\s/);
            if (m) { num = parseInt(m[1]) + 1; break; }
        }
        window.ntDescInsert(num + '. ');
    };

    // Checklist — insert [ ] item and auto-continue on Enter
    window.ntDescChecklist = function() {
        window.ntDescInsert('[ ] ');
        var ta = document.getElementById('nt-desc-ta');
        if (!ta || ta._checklistBound) return;
        ta._checklistBound = true;
        ta.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            var val   = this.value;
            var pos   = this.selectionStart;
            var line  = val.slice(0, pos).split('\n').pop();
            // Continue checklist if current line starts with [ ] or [x]
            if (/^\[([ xX])\] /.test(line)) {
                e.preventDefault();
                var insert = '\n[ ] ';
                this.value = val.slice(0, pos) + insert + val.slice(this.selectionEnd);
                var np = pos + insert.length;
                this.setSelectionRange(np, np);
            }
        });
    };

    // Apply border-color to default active pills on load
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.nt-pill.active').forEach(function(p) {
            p.style.borderColor = getComputedStyle(p).color;
        });
    });
})();
</script>
