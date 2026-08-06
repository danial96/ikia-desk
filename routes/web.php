<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('login'));

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Password Reset
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendLink'])->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showForm'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class)->except(['create', 'edit']);

    // Tasks
    Route::get('/tasks/kanban', [TaskController::class, 'kanban'])->name('tasks.kanban');
    Route::resource('tasks', TaskController::class)->except(['create', 'edit']);

    // Task inline AJAX updates
    Route::patch('/tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
    Route::patch('/tasks/{task}/field', [TaskController::class, 'updateField'])->name('tasks.field');
    Route::post('/tasks/{task}/participants/toggle', [TaskController::class, 'toggleParticipant'])->name('tasks.participants.toggle');
    Route::post('/tasks/{task}/observers/toggle', [TaskController::class, 'toggleObserver'])->name('tasks.observers.toggle');

    // Task Comments
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
    Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');

    // Chat
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{conversation}/send', [ChatController::class, 'sendMessage'])->name('chat.send');
    Route::post('/chat/direct/create', [ChatController::class, 'createDirect'])->name('chat.direct.create');
    Route::post('/chat/group/create', [ChatController::class, 'createGroup'])->name('chat.group.create');

    // Employees
    Route::resource('employees', EmployeeController::class)->except(['create', 'edit', 'show']);
    Route::post('/employees/{employee}/toggle-active', [EmployeeController::class, 'toggleActive'])->name('employees.toggle-active');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/{id}', [ProfileController::class, 'show'])->name('profile.show.user');
    Route::post('/profile/{id}', [ProfileController::class, 'update'])->name('profile.update.user');

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions/{employee}', [PermissionController::class, 'update'])->name('permissions.update');

    // Local task JSON detail
    Route::get('/api/local-task/{id}', function ($id) {
        $task = \App\Models\Task::with([
            'project','assignee','creator',
            'members','observers',
            'comments.user','activities.user',
            'checklists','files',
        ])->findOrFail($id);

        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            $uid = $user->id;
            $ok = $task->created_by === $uid
               || $task->assigned_to === $uid
               || $task->members->contains('id', $uid)
               || $task->observers->contains('id', $uid);
            if (!$ok) return response()->json(['error'=>'Forbidden'],403);
        }

        // Pre-load file records for comments that have attachments
        $commentFileIds = $task->comments->flatMap(fn($c) => $c->files ?? [])->unique()->values();
        $commentFiles = $commentFileIds->isNotEmpty()
            ? \App\Models\TaskFile::whereIn('id', $commentFileIds)->get()->keyBy('id')
            : collect();

        $feed = collect();
        foreach ($task->comments as $c) {
            $attachments = collect($c->files ?? [])->map(fn($fid) => $commentFiles->get($fid))
                ->filter()->map(fn($f) => [
                    'id'          => $f->id,
                    'name'        => $f->name,
                    'size'        => $f->size,
                    'downloadUrl' => $f->download_url,
                ])->values();

            $feed->push(['type'=>'comment','at'=>$c->created_at->toIso8601String(),'id'=>$c->id,
                'author'=>['id'=>$c->user_id,'name'=>$c->user?->name??'','avatar'=>$c->user?->avatar_url??''],
                'text'=>$c->content,
                'files'=>$attachments,
            ]);
        }
        foreach ($task->activities as $a) {
            if ($a->action === 'commented') continue;
            $feed->push(['type'=>'activity','at'=>$a->created_at->toIso8601String(),
                'author'=>$a->user?->name??'System',
                'action'=>$a->action,'field'=>$a->field,'old'=>$a->old_value,'new'=>$a->new_value]);
        }

        $employees = \App\Models\User::where('is_active',true)->orderBy('name')
            ->get(['id','name','first_name','last_name','position'])->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'avatar'=>$u->avatar_url]);

        return response()->json([
            'task'         => ['id'=>$task->id,'title'=>$task->title,'description'=>$task->description,
                               'status'=>$task->status,'priority'=>$task->priority,
                               'assigned_to'=>$task->assigned_to,
                               'deadline'=>$task->deadline?->toIso8601String(),
                               'createdAt'=>$task->created_at->toIso8601String(),
                               'project'=>$task->project?['id'=>$task->project->id,'name'=>$task->project->name]:null],
            'assignee'     => $task->assignee  ? ['id'=>$task->assignee->id,'name'=>$task->assignee->name,'avatar'=>$task->assignee->avatar_url]  : null,
            'creator'      => $task->creator   ? ['id'=>$task->creator->id,'name'=>$task->creator->name,'avatar'=>$task->creator->avatar_url]    : null,
            'participants' => $task->members->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'avatar'=>$u->avatar_url])->values(),
            'observers'    => $task->observers->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'avatar'=>$u->avatar_url])->values(),
            'feed'         => $feed->sortBy('at')->values(),
            'employees'    => $employees->values(),
            'canEdit'      => $user->isSuperAdmin() || $task->created_by === $user->id,
            'checklists'   => $task->checklists->sortBy('sort_index')->values()->map(fn($c)=>[
                'id'=>$c->id,'text'=>$c->text,'is_complete'=>$c->is_complete,
                'completed_by'=>$c->completed_by,
            ])->values(),
            'localFiles'   => $task->files->map(fn($f)=>[
                'id'          => $f->id,
                'name'        => $f->name,
                'mime'        => $f->mime_type,
                'size'        => $f->size,
                'downloadUrl' => $f->download_url,
            ])->values(),
        ]);
    })->name('api.local.task');

    // ── Attach file to existing task ──
    Route::post('/api/local-task/{id}/attach', function ($id, \Illuminate\Http\Request $request) {
        $task = \App\Models\Task::findOrFail($id);
        $user = auth()->user();
        $request->validate(['file' => 'required|file|max:20480']);
        $file     = $request->file('file');
        $origName = $file->getClientOriginalName();
        $mime     = $file->getClientMimeType() ?? '';
        $ext      = strtolower($file->getClientOriginalExtension());
        $fileSize = $file->getSize() ?: 0;
        $filename = 'up_' . uniqid() . '.' . $ext;
        $file->move(public_path('uploads'), $filename);
        $tf = $task->files()->create([
            'uploaded_by' => $user->id,
            'name'        => $origName,
            'size'        => $fileSize,
            'mime_type'   => $mime,
            'disk_path'   => 'uploads/' . $filename,
        ]);
        $task->logActivity($user, 'attached file', null, null, $origName);
        return response()->json([
            'ok'   => true,
            'file' => [
                'id'          => $tf->id,
                'name'        => $tf->name,
                'mime'        => $tf->mime_type,
                'size'        => $tf->size,
                'downloadUrl' => asset('uploads/' . $filename),
            ],
        ]);
    });

    // Checklist — add item
    Route::post('/api/local-task/{id}/checklist', function ($id, \Illuminate\Http\Request $request) {
        $request->validate(['text'=>'required|string|max:1000']);
        $task = \App\Models\Task::findOrFail($id);
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$task->isMember($user)) return response()->json(['error'=>'Forbidden'],403);
        $max = $task->checklists()->max('sort_index') ?? 0;
        $item = $task->checklists()->create(['added_by'=>$user->id,'text'=>$request->text,'sort_index'=>$max+1]);
        return response()->json(['ok'=>true,'item'=>['id'=>$item->id,'text'=>$item->text,'is_complete'=>false]]);
    });

    // Checklist — toggle
    Route::patch('/api/local-task/{id}/checklist/{item}', function ($id, $item, \Illuminate\Http\Request $request) {
        $task = \App\Models\Task::findOrFail($id);
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$task->isMember($user)) return response()->json(['error'=>'Forbidden'],403);
        $cl = $task->checklists()->findOrFail($item);
        $wasComplete = $cl->is_complete;
        $cl->update([
            'is_complete'  => !$wasComplete,
            'completed_by' => !$wasComplete ? $user->id : null,
            'completed_at' => !$wasComplete ? now() : null,
        ]);
        $action = $wasComplete ? 'unchecked checklist item' : 'checked checklist item';
        $task->logActivity($user, $action, null, null, $cl->text);
        return response()->json(['ok'=>true,'is_complete'=>$cl->fresh()->is_complete]);
    });

    // Checklist — delete
    Route::delete('/api/local-task/{id}/checklist/{item}', function ($id, $item) {
        $task = \App\Models\Task::findOrFail($id);
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$task->isMember($user)) return response()->json(['error'=>'Forbidden'],403);
        $task->checklists()->findOrFail($item)->delete();
        return response()->json(['ok'=>true]);
    });

    // Kanban column data (for real-time card movement)
    Route::get('/api/kanban-task/{id}', function ($id) {
        $task = \App\Models\Task::select('id','title','status','priority','deadline','assigned_to','project_id')->with(['assignee:id,name','project:id,name'])->findOrFail($id);
        $user = auth()->user();
        $today    = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $weekEnd  = now()->endOfWeek();
        $nextWeekEnd = now()->addWeek()->endOfWeek();
        $col = 'no_deadline';
        if ($task->status === 'completed') {
            $col = 'completed';
        } elseif ($task->deadline) {
            $dl = $task->deadline;
            if      ($dl->lt($today))       $col = 'overdue';
            elseif  ($dl->lte($todayEnd))   $col = 'due_today';
            elseif  ($dl->lte($weekEnd))    $col = 'due_this_week';
            elseif  ($dl->lte($nextWeekEnd))$col = 'due_next_week';
            else                            $col = 'due_over_two_weeks';
        }
        return response()->json([
            'id'       => $task->id,
            'title'    => $task->title,
            'status'   => $task->status,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->toIso8601String(),
            'assignee' => $task->assignee ? ['name'=>$task->assignee->name,'avatar'=>$task->assignee->avatar_url] : null,
            'project'  => $task->project  ? ['name'=>$task->project->name] : null,
            'col'      => $col,
        ]);
    });

    // Post comment via AJAX
    Route::post('/api/local-task/{id}/comment', function ($id, \Illuminate\Http\Request $request) {
        $request->validate(['content'=>'required|string|max:5000']);
        $task    = \App\Models\Task::findOrFail($id);
        $comment = $task->comments()->create(['user_id'=>auth()->id(),'content'=>$request->content,'mentions'=>[]]);
        $task->logActivity(auth()->user(),'commented',null,null,$request->content);

        // Notify creator + assignee + members + observers about new comment
        $task->load(['members','observers']);
        $recipientIds = collect()
            ->push($task->created_by)
            ->push($task->assigned_to)
            ->merge($task->members->pluck('id'))
            ->merge($task->observers->pluck('id'))
            ->filter()->unique()->values()->toArray();
        \App\Models\Notification::notify($recipientIds, auth()->user(), 'task_comment', $task,
            auth()->user()->name . ' commented on "' . $task->title . '"');

        return response()->json([
            'ok'      => true,
            'comment' => ['id'=>$comment->id,'text'=>$comment->content,'at'=>$comment->created_at->toIso8601String(),
                          'author'=>['name'=>auth()->user()->name,'avatar'=>auth()->user()->avatar_url]],
        ]);
    })->name('api.local.comment');

    // ── File Upload API ──
    Route::post('/api/upload', function (\Illuminate\Http\Request $request) {
        try {
            $request->validate(['file' => 'required|file|max:51200']);
            $file     = $request->file('file');
            $origName = $file->getClientOriginalName();
            $mime     = $file->getClientMimeType() ?? '';
            $ext      = strtolower($file->getClientOriginalExtension()) ?: 'bin';
            $filename = 'up_' . uniqid() . '.' . $ext;

            $uploadPath = public_path('uploads');
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $file->move($uploadPath, $filename);

            return response()->json([
                'url'  => asset('uploads/' . $filename),
                'name' => $origName,
                'mime' => $mime,
                'ext'  => $ext,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'path' => public_path('uploads')], 500);
        }
    });

    // ── Chat Panel API ──
    Route::get('/api/chat/convs', function () {
        $user = auth()->user();
        $user->forceFill(['last_seen_at' => now()])->save();

        $convs = $user->conversations()->with(['lastMessage.user','members'])->get()
            ->filter(fn($c) => $c->lastMessage !== null)
            ->sortByDesc(fn($c) => $c->lastMessage->created_at)->values();

        $memberRows = \App\Models\ConversationMember::where('user_id', $user->id)->get()->keyBy('conversation_id');

        // Single query for ALL unread counts — replaces N per-conv count queries
        $unreadCounts = \Illuminate\Support\Facades\DB::table('messages')
            ->join('conversation_members', function ($j) use ($user) {
                $j->on('messages.conversation_id', '=', 'conversation_members.conversation_id')
                  ->where('conversation_members.user_id', '=', $user->id);
            })
            ->where('messages.user_id', '!=', $user->id)
            ->whereNull('messages.deleted_at')
            ->whereRaw('messages.created_at > COALESCE(conversation_members.last_read_at, "2000-01-01")')
            ->groupBy('messages.conversation_id')
            ->selectRaw('messages.conversation_id, COUNT(*) as cnt')
            ->pluck('cnt', 'messages.conversation_id');

        $general = \App\Models\Conversation::where('type','general')->with(['lastMessage.user','members'])->first();
        $list = [];
        if ($general) {
            $lm     = $general->lastMessage;
            $list[] = ['id'=>$general->id,'type'=>'general','name'=>'General Chat','avatar'=>null,'members'=>0,
                'unread'  => $unreadCounts->get($general->id, 0),
                'online'  => false,
                'lastMsg' => $lm ? ['text'=>$lm->content,'byMe'=>$lm->user_id===$user->id,'senderName'=>$lm->user?->name,'time'=>$lm->created_at->format('H:i')] : null];
        }
        foreach ($convs as $c) {
            if ($c->type==='general') continue;
            $other  = $c->type==='direct' ? $c->members->where('id','!=',$user->id)->first() : null;
            $lm     = $c->lastMessage;
            $online = $other && $other->last_seen_at && $other->last_seen_at->diffInMinutes(now()) < 5;
            $list[] = ['id'=>$c->id,'type'=>$c->type,
                'name'          => $c->type==='direct' ? ($other?->name??'Unknown') : ($c->name??'Group'),
                'other_user_id' => $c->type==='direct' ? ($other?->id) : null,
                'avatar'        => $c->type==='direct' ? ($other?->avatar_url??null) : null,
                'members'       => $c->members->count(),
                'unread'        => $unreadCounts->get($c->id, 0),
                'online'        => $online,
                'lastMsg'       => $lm ? ['text'=>$lm->content,'byMe'=>$lm->user_id===$user->id,'senderName'=>$lm->user?->name,'time'=>$lm->created_at->format('H:i')] : null,
            ];
        }
        return response()->json(['convs'=>$list]);
    });

    Route::get('/api/chat/convs/{id}/msgs', function ($id, \Illuminate\Http\Request $request) {
        $user   = auth()->user();
        $conv   = \App\Models\Conversation::with('members')->findOrFail($id);
        if ($conv->type !== 'general' && !$conv->members->contains('id', $user->id))
            return response()->json(['error'=>'Forbidden'],403);

        $afterId = (int)($request->query('after', 0));
        $msgFmt  = fn($m) => [
            'id'        => $m->id,
            'text'      => $m->content,
            'isMine'    => $m->user_id===$user->id,
            'time'      => $m->created_at->format('H:i'),
            'date'      => $m->created_at->format('Y-m-d'),
            'createdTs' => $m->created_at->timestamp,
            'editedAt'  => $m->edited_at?->format('H:i'),
            'deletedFor'=> $m->deleted_for ?? [],
            'author'    => ['id'=>$m->user_id,'name'=>$m->user?->name??'','avatar'=>$m->user?->avatar_url??''],
        ];

        if ($afterId > 0) {
            // Incremental poll — only new messages after given ID
            $msgs = $conv->messages()->with('user')->where('id','>',$afterId)->orderBy('id')->limit(50)->get();
            if ($msgs->isNotEmpty()) {
                \App\Models\ConversationMember::where('conversation_id',$conv->id)->where('user_id',$user->id)
                    ->update(['last_read_at'=>now()]);
            }
            return response()->json(['messages' => $msgs->map($msgFmt)->values()]);
        }

        // Full load
        \App\Models\ConversationMember::where('conversation_id',$conv->id)->where('user_id',$user->id)
            ->update(['last_read_at'=>now()]);
        $msgs  = $conv->messages()->with('user')->latest()->limit(100)->get()
                     ->sortBy(fn($m) => $m->created_at->timestamp)->values();
        $other  = $conv->type==='direct' ? $conv->members->where('id','!=',$user->id)->first() : null;
        $online = $other && $other->last_seen_at && $other->last_seen_at->diffInMinutes(now()) < 5;

        return response()->json([
            'conv' => ['id'=>$conv->id,'type'=>$conv->type,
                'name'    => $conv->type==='direct' ? ($other?->name??'Unknown') : ($conv->name??'Group'),
                'avatar'  => $conv->type==='direct' ? ($other?->avatar_url??null) : null,
                'members' => $conv->members->count(),
                'online'  => $online,
                'last_seen' => $other?->last_seen_at?->diffForHumans()],
            'messages' => $msgs->map($msgFmt),
        ]);
    });

    // ── Edit message ──
    Route::patch('/api/chat/msgs/{id}', function ($id, \Illuminate\Http\Request $request) {
        $request->validate(['content'=>'required|string|max:5000']);
        $user = auth()->user();
        $msg  = \App\Models\Message::findOrFail($id);
        if ((int)$msg->user_id !== (int)$user->id) return response()->json(['error'=>'Forbidden'],403);
        if ($msg->created_at->diffInMinutes(now()) > 5) return response()->json(['error'=>'Too late to edit'],403);
        $msg->update(['content'=>$request->content,'edited_at'=>now()]);
        return response()->json(['ok'=>true,'editedAt'=>$msg->edited_at->format('H:i')]);
    });

    // ── Delete message ──
    Route::delete('/api/chat/msgs/{id}', function (\Illuminate\Http\Request $request, $id) {
        $user = auth()->user();
        $msg  = \App\Models\Message::findOrFail($id);
        if ((int)$msg->user_id !== (int)$user->id) return response()->json(['error'=>'Forbidden'],403);
        $scope = $request->query('scope','everyone');
        if ($scope === 'everyone') {
            $msg->delete(); // soft delete
        } else {
            $for = $msg->deleted_for ?? [];
            if (!in_array($user->id, $for)) { $for[] = $user->id; }
            $msg->update(['deleted_for'=>$for]);
        }
        return response()->json(['ok'=>true]);
    });

    Route::post('/api/chat/convs/{id}/leave', function ($id) {
        $user = auth()->user();
        $conv = \App\Models\Conversation::findOrFail($id);
        if ($conv->type === 'general') return response()->json(['error'=>'Cannot leave general chat'],422);
        \App\Models\ConversationMember::where('conversation_id',$conv->id)->where('user_id',$user->id)->delete();
        return response()->json(['ok'=>true]);
    });

    Route::post('/api/chat/convs/{id}/send', function (\Illuminate\Http\Request $request, $id) {
        $request->validate(['content'=>'required|string|max:5000']);
        $user = auth()->user();
        $conv = \App\Models\Conversation::with('members')->findOrFail($id);
        if ($conv->type !== 'general' && !$conv->members->contains('id',$user->id))
            return response()->json(['error'=>'Forbidden'],403);

        $msg = $conv->messages()->create(['user_id'=>$user->id,'content'=>$request->content,'mentions'=>[]]);
        \App\Models\ConversationMember::where('conversation_id',$conv->id)->where('user_id',$user->id)
            ->update(['last_read_at'=>now()]);

        return response()->json(['ok'=>true,'message'=>[
            'id'=>$msg->id,'text'=>$msg->content,'isMine'=>true,
            'time'=>$msg->created_at->format('H:i'),'date'=>$msg->created_at->format('Y-m-d'),
            'author'=>['id'=>$user->id,'name'=>$user->name,'avatar'=>$user->avatar_url],
        ]]);
    });

    Route::post('/api/chat/direct', function (\Illuminate\Http\Request $request) {
        $request->validate(['user_id'=>'required|exists:users,id']);
        $user = auth()->user(); $otherId = $request->user_id;
        $existing = \App\Models\Conversation::where('type','direct')
            ->whereHas('members',fn($q)=>$q->where('user_id',$user->id))
            ->whereHas('members',fn($q)=>$q->where('user_id',$otherId))->first();
        if ($existing) return response()->json(['conv_id'=>$existing->id]);
        $conv = \App\Models\Conversation::create(['type'=>'direct','created_by'=>$user->id]);
        $conv->members()->attach([$user->id,$otherId]);
        return response()->json(['conv_id'=>$conv->id]);
    });

    Route::post('/api/chat/group', function (\Illuminate\Http\Request $request) {
        $request->validate(['name'=>'required|string|max:255','members'=>'required|array|min:1']);
        $user = auth()->user();
        $conv = \App\Models\Conversation::create(['type'=>'group','name'=>$request->name,'created_by'=>$user->id]);
        $conv->members()->attach(array_unique(array_merge($request->members,[$user->id])));
        return response()->json(['conv_id'=>$conv->id]);
    });

    Route::get('/api/employees-list', function () {
        return response()->json(\App\Models\User::where('is_active',true)->where('id','!=',auth()->id())
            ->orderBy('name')->get()->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'avatar'=>$u->avatar_url]));
    });

    Route::get('/api/online-status', function () {
        return response()->json(\App\Models\User::where('is_active',true)->where('id','!=',auth()->id())
            ->get(['id','last_seen_at'])->map(fn($u)=>['id'=>$u->id,'online'=>$u->last_seen_at && $u->last_seen_at->diffInMinutes(now())<5]));
    });

    // ── Notifications API ──
    Route::get('/api/notifications', function () {
        $notifications = \App\Models\Notification::with('actor')
            ->where('user_id', auth()->id())
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'task_id'    => $n->task_id,
                'task_title' => $n->task_title,
                'message'    => $n->message,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
                'actor'      => $n->actor ? ['name' => $n->actor->name, 'avatar' => $n->actor->avatar_url] : null,
            ]);

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->count(),
        ]);
    })->name('api.notifications');

    Route::post('/api/notifications/{id}/read', function ($id) {
        \App\Models\Notification::where('id', $id)->where('user_id', auth()->id())->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    })->name('api.notifications.read');

    Route::post('/api/notifications/read-all', function () {
        \App\Models\Notification::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    })->name('api.notifications.read-all');

    Route::post('/api/notifications/task/{taskId}/read', function ($taskId) {
        \App\Models\Notification::where('user_id', auth()->id())
            ->where('task_id', $taskId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        return response()->json(['ok' => true]);
    })->name('api.notifications.task-read');

    // Bitrix24 live tasks proxy
    Route::get('/api/bitrix-tasks', function () {
        $wh = env('BITRIX_WEBHOOK');
        $params = http_build_query([
            'order'  => ['DEADLINE' => 'ASC'],
            'filter' => ['STATUS' => ['1','2','3']],
            'select' => ['ID','TITLE','DESCRIPTION','STATUS','PRIORITY','DEADLINE',
                         'CREATED_DATE','RESPONSIBLE_ID','CREATOR_ID','GROUP_ID',
                         'UF_TASK_WEBDAV_FILES'],
            'start'  => 0,
            'limit'  => 12,
        ]);

        $ch = curl_init($wh . 'tasks.task.list.json?' . $params);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 10]);
        $tasksRaw = curl_exec($ch);
        curl_close($ch);

        $tasksData = json_decode($tasksRaw, true);
        $tasks = $tasksData['result']['tasks'] ?? [];

        // Fetch responsible user details in one batch call
        $userIds = array_unique(array_filter(array_column($tasks, 'responsibleId')));
        $users = [];
        if ($userIds) {
            $userParams = http_build_query(['ID' => $userIds, 'select' => ['ID','NAME','LAST_NAME','PERSONAL_PHOTO','WORK_POSITION']]);
            $ch2 = curl_init($wh . 'user.get.json?' . $userParams);
            curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 8]);
            $usersRaw = curl_exec($ch2);
            curl_close($ch2);
            $usersData = json_decode($usersRaw, true);
            foreach ($usersData['result'] ?? [] as $u) {
                $users[$u['ID']] = [
                    'id'           => $u['ID'],
                    'name'         => trim($u['NAME'] . ' ' . $u['LAST_NAME']),
                    'workPosition' => $u['WORK_POSITION'] ?? '',
                    'icon'         => $u['PERSONAL_PHOTO'] ?? null,
                ];
            }
        }

        // Attach user data to each task
        foreach ($tasks as &$task) {
            $rid = $task['responsibleId'] ?? null;
            $task['responsible'] = $rid && isset($users[$rid]) ? $users[$rid] : null;
        }

        return response()->json([
            'result' => ['tasks' => $tasks],
            'total'  => $tasksData['total'] ?? count($tasks),
        ]);
    })->name('api.bitrix.tasks');

    // Bitrix24 single task full detail
    Route::get('/api/bitrix-task/{id}', function ($id) {
        $wh = env('BITRIX_WEBHOOK');

        // Full task fields including CHAT_ID for IM chat
        $fields = ['ID','TITLE','DESCRIPTION','STATUS','PRIORITY','DEADLINE','CREATED_DATE',
                   'RESPONSIBLE_ID','CREATOR_ID','ACCOMPLICES','AUDITORS','UF_TASK_WEBDAV_FILES','CHAT_ID'];
        $params = http_build_query(['taskId' => $id, 'select' => $fields]);
        $ch = curl_init($wh . 'tasks.task.get.json?' . $params);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 10]);
        $taskData = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $task = $taskData['result']['task'] ?? [];
        if (!$task) return response()->json(['error' => 'Task not found'], 404);

        // Fetch task IM chat messages via CHAT_ID
        $rawMessages = [];
        $chatId = $task['chatId'] ?? null;
        if ($chatId) {
            $ch5 = curl_init($wh . 'im.dialog.messages.get.json?DIALOG_ID=chat' . $chatId . '&LIMIT=50');
            curl_setopt_array($ch5, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 8]);
            $msgData = json_decode(curl_exec($ch5), true);
            curl_close($ch5);
            $rawMessages = $msgData['result']['messages'] ?? [];
        }

        // Collect all user IDs: task people + chat authors (exclude 0 = system)
        $chatAuthorIds = array_filter(array_unique(array_column($rawMessages, 'author_id')), fn($id) => $id > 0);
        $userIds = array_values(array_unique(array_filter(array_merge(
            [$task['responsibleId'] ?? null, $task['creatorId'] ?? null],
            (array)($task['accomplices'] ?? []),
            (array)($task['auditors']    ?? []),
            $chatAuthorIds
        ))));

        // Batch fetch users
        $users = [];
        if ($userIds) {
            $uParams = http_build_query(['ID' => $userIds, 'select' => ['ID','NAME','LAST_NAME','PERSONAL_PHOTO','WORK_POSITION']]);
            $ch2 = curl_init($wh . 'user.get.json?' . $uParams);
            curl_setopt_array($ch2, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 8]);
            $uData = json_decode(curl_exec($ch2), true);
            curl_close($ch2);
            foreach ($uData['result'] ?? [] as $u) {
                $users[$u['ID']] = [
                    'id'           => $u['ID'],
                    'name'         => trim($u['NAME'] . ' ' . $u['LAST_NAME']),
                    'workPosition' => $u['WORK_POSITION'] ?? '',
                    'icon'         => $u['PERSONAL_PHOTO'] ?? null,
                ];
            }
        }

        // Fetch file details
        $files = [];
        $fileIds = array_filter((array)($task['ufTaskWebdavFiles'] ?? []));
        foreach ($fileIds as $fid) {
            $ch3 = curl_init($wh . 'disk.file.get.json?id=' . $fid);
            curl_setopt_array($ch3, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 5]);
            $fData = json_decode(curl_exec($ch3), true);
            curl_close($ch3);
            if ($fData['result'] ?? null) {
                $f = $fData['result'];
                $files[] = [
                    'id'          => $f['ID'],
                    'name'        => $f['NAME'],
                    'size'        => $f['SIZE'] ?? 0,
                    'mimeType'    => $f['TYPE'] ?? '',
                    'downloadUrl' => $f['DOWNLOAD_URL'] ?? null,
                ];
            }
        }

        // Format chat: API returns newest-first, reverse to oldest-first for display
        $chat = [];
        foreach (array_reverse($rawMessages) as $m) {
            $uid      = $m['author_id'] ?? 0;
            $isSystem = $uid === 0;
            $chat[]   = [
                'id'       => $m['id'],
                'text'     => $m['text'],
                'date'     => $m['date'],
                'isSystem' => $isSystem,
                'author'   => $isSystem ? null : ($users[$uid] ?? ['id' => $uid, 'name' => 'User #'.$uid, 'icon' => null, 'workPosition' => '']),
            ];
        }

        return response()->json([
            'task'  => $task,
            'users' => $users,
            'files' => $files,
            'chat'  => $chat,
        ]);
    })->name('api.bitrix.task');
});
