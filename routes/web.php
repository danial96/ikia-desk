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

// Signed-in people go straight to where they will end up (login -> /tasks -> board -> default filter was 3-4
// redirects, each a full round trip); everyone else to the login page.
Route::get('/', function () {
    $user = auth()->user();
    if (!$user) return redirect()->route('login');
    return $user->task_view === 'kanban'
        ? redirect()->route('tasks.kanban', ['status' => 'in_progress'])
        : redirect()->route('tasks.index', ['status' => 'in_progress']);
});

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Password Reset
Route::get('/forgot-password', [ForgotPasswordController::class, 'showForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showForm'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');

// Protected routes
// Fresh CSRF token + whether the session is still logged in (used by the page's fetch wrapper).
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token(), 'auth' => auth()->check()])
    ->header('Cache-Control', 'no-store'))->name('csrf.token');

// Initials avatar for users without an uploaded photo — served locally and cached for a year.
Route::get('/avatar-initials', function (\Illuminate\Http\Request $r) {
    $name  = trim((string) $r->query('n', '?'));
    $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $ini   = mb_strtoupper(mb_substr($words[0], 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect width="128" height="128" fill="#6366f1"/>'
         . '<text x="64" y="64" dy=".35em" text-anchor="middle" fill="#fff" font-family="Arial,Helvetica,sans-serif" font-size="' . (mb_strlen($ini) > 1 ? 52 : 62) . '" font-weight="600">' . e($ini) . '</text></svg>';
    return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=31536000, immutable']);
})->name('avatar.initials');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class)->except(['create', 'edit']);

    // Tasks
    Route::get('/tasks/kanban', [TaskController::class, 'kanban'])->name('tasks.kanban');
    Route::get('/tasks/kanban/load-completed', [TaskController::class, 'loadCompleted'])->name('tasks.kanban.completed');
    Route::get('/tasks/kanban/version', [TaskController::class, 'kanbanVersion'])->name('tasks.kanban.version');
    Route::get('/tasks/trash', [TaskController::class, 'trash'])->name('tasks.trash');
    Route::delete('/tasks/{id}/force', [TaskController::class, 'forceDestroy'])->whereNumber('id')->name('tasks.force');
    Route::post('/tasks/{id}/restore', [TaskController::class, 'restore'])->whereNumber('id')->name('tasks.restore');
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
    Route::post('/employees/{employee}/handover-delete', [EmployeeController::class, 'handoverDelete'])->name('employees.handover-delete');

    // Profile
    Route::post('/profile/theme', [\App\Http\Controllers\ThemeController::class, 'set'])->name('theme.set');
    Route::post('/profile/theme/custom', [\App\Http\Controllers\ThemeController::class, 'custom'])->name('theme.custom');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
    Route::get('/profile/{id}', [ProfileController::class, 'show'])->name('profile.show.user');
    Route::post('/profile/{id}', [ProfileController::class, 'update'])->name('profile.update.user');

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions/{employee}', [PermissionController::class, 'update'])->name('permissions.update');

    // Local task JSON detail
    Route::get('/api/local-task/{id}', function ($id, \Illuminate\Http\Request $request) {
        $task = \App\Models\Task::with([
            'project','assignee','creator',
            'members','observers',
            'comments.user','activities.user',
            'checklists','files',
        ])->findOrFail($id);

        $user = auth()->user();
        if (!$task->canBeOpenedBy($user)) {
            return response()->json(['error'=>'Forbidden'],403);
        }
        // Bitrix-style read receipt: record that this user just opened the task (upsert, so
        // reopening only refreshes the timestamp instead of piling up rows). Skipped on the task
        // panel's own 3s background poll (?background=1) — that fires for every open task panel,
        // for every user, the whole time it's open, and "viewed" doesn't need sub-minute freshness;
        // writing it every single tick was pure unnecessary load with no visible benefit.
        if (!$request->boolean('background')) {
            $task->recordViewedBy($user);
        }

        // Collect all file IDs that belong to comments (to exclude from task-level file list)
        $commentFileIds = $task->comments->flatMap(fn($c) => $c->files ?? [])->unique()->values();
        $commentFiles = $commentFileIds->isNotEmpty()
            ? \App\Models\TaskFile::whereIn('id', $commentFileIds)->get()->keyBy('id')
            : collect();

        $commentsById = $task->comments->keyBy('id');
        $plainSnippet = function (?string $t): string {
            $t = preg_replace(['/\[img\].*?\[\/img\]/s', '/\[file name="[^"]*"\].*?\[\/file\]/s', '/\[voice[^\]]*\].*?\[\/voice\]/s'], ['[image]', '[file]', '[voice]'], (string) $t);
            return mb_substr(trim(preg_replace('/\s+/u', ' ', $t)), 0, 100);
        };

        $feed = collect();
        foreach ($task->comments as $c) {
            $attachments = collect($c->files ?? [])->map(fn($fid) => $commentFiles->get($fid))
                ->filter()->map(fn($f) => [
                    'id'          => $f->id,
                    'name'        => $f->name,
                    'size'        => $f->size,
                    'downloadUrl' => $f->download_url,
                ])->values();

            $parent = $c->parent_id ? $commentsById->get($c->parent_id) : null;
            $reactions = $c->reactions ?? [];

            $feed->push(['type'=>'comment','at'=>$c->created_at->toIso8601String(),'id'=>$c->id,
                'isSystem' => (bool)$c->is_system,
                'author'=>['id'=>$c->user_id,'name'=>$c->user?->name??'','avatar'=>$c->user?->avatar_url??''],
                'text'=>$c->content,
                'editedAt' => $c->edited_at?->format('g:i a'),
                'parentId' => $c->parent_id,
                'parentPreview' => $parent ? ['author' => $parent->user?->name ?? '', 'text' => $plainSnippet($parent->content)] : null,
                'reactions' => $reactions,
                'myReactions' => array_keys(array_filter($reactions, fn($ids) => in_array($user->id, (array) $ids))),
                'files'=>$attachments->map(fn($f2) => array_merge($f2, [
                    'isLocal' => !empty($commentFiles->get($f2['id'])?->disk_path),
                ]))->values(),
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

        // "Viewed by" (Bitrix-style eye icon): everyone who has ever opened this task, most
        // recent first, each shown in the VIEWING user's own timezone.
        $viewRows = $task->views()->with('user')->orderByDesc('viewed_at')->get();
        $viewedBy = $viewRows->map(fn($v) => [
            'id'       => $v->user_id,
            'name'     => $v->user?->name ?? '',
            'avatar'   => $v->user?->avatar_url ?? '',
            'viewedAt' => \App\Support\Tz::forViewer($v->viewed_at, $user)->toIso8601String(),
        ])->values();

        // Single "✓✓ Viewed by X" line under the latest activity/comment — the most recently
        // active OTHER viewer, provided they've actually seen something newer than it exists yet.
        // Same instants $feed was built from — reuse the Carbon objects directly instead of
        // re-parsing every feed item's ISO string on every request (this endpoint is hit every 3s
        // by the task panel's background poll, for every open task, for every user).
        $lastFeedAt = collect([$task->comments->max('created_at'), $task->activities->max('created_at'), $task->created_at])
            ->filter()->max();
        $lastSeenBy = $viewRows->where('user_id', '!=', $user->id)
            ->filter(fn($v) => $v->viewed_at->gte($lastFeedAt)) // gte: DB timestamps are second-precision, so a view in the very same second as the activity still counts as having seen it
            ->sortByDesc('viewed_at')->first();

        return response()->json([
            'task'         => ['id'=>$task->id,'bitrixId'=>$task->bitrix_id,'title'=>$task->title,'description'=>$task->description,
                               'status'=>$task->status,'priority'=>$task->priority,
                               'assigned_to'=>$task->assigned_to,
                               'deadline'=>$task->deadline?->toIso8601String(),
                               'createdAt'=>$task->created_at->toIso8601String(),
                               'project'=>$task->project?['id'=>$task->project->id,'name'=>$task->project->name]:null],
            'assignee'     => $task->assignee  ? ['id'=>$task->assignee->id,'name'=>$task->assignee->name,'avatar'=>$task->assignee->avatar_url,'position'=>$task->assignee->position]  : null,
            'creator'      => $task->creator   ? ['id'=>$task->creator->id,'name'=>$task->creator->name,'avatar'=>$task->creator->avatar_url,'position'=>$task->creator->position]    : null,
            'participants' => $task->members->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'avatar'=>$u->avatar_url,'position'=>$u->position])->values(),
            'observers'    => $task->observers->map(fn($u)=>['id'=>$u->id,'name'=>$u->name,'avatar'=>$u->avatar_url,'position'=>$u->position])->values(),
            'feed'         => $feed->sortBy('at')->values(),
            'viewedBy'     => $viewedBy,
            'lastSeenBy'   => $lastSeenBy ? ['name' => $lastSeenBy->user?->name ?? '', 'viewedAt' => \App\Support\Tz::forViewer($lastSeenBy->viewed_at, $user)->toIso8601String()] : null,
            'employees'    => $employees->values(),
            'canEdit'      => $user->isSuperAdmin() || $task->created_by === $user->id,
            'checklists'   => $task->checklists->sortBy('sort_index')->values()->map(fn($c)=>[
                'id'=>$c->id,'text'=>$c->text,'is_complete'=>$c->is_complete,
                'completed_by'=>$c->completed_by,
            ])->values(),
            // Only show files directly attached to the task — exclude comment attachments
            'localFiles'   => $task->files
                ->reject(fn($f) => $commentFileIds->contains($f->id))
                ->map(fn($f)=>[
                    'id'          => $f->id,
                    'name'        => $f->name,
                    'mime'        => $f->mime_type,
                    'size'        => $f->size,
                    'downloadUrl' => $f->download_url,
                    'isLocal'     => !empty($f->disk_path),
                ])->values(),
        ]);
    })->name('api.local.task');

    // ── "Download all": several attachments as one .zip ──
    $zipOut = function (array $files, string $zipName) {
        // $files: list of [display name, absolute path]; names are made unique inside the archive
        if (!$files) abort(404, 'Nothing to download.');
        $total = array_sum(array_map(fn($f) => (int) @filesize($f[1]), $files));
        if ($total > 600 * 1024 * 1024) abort(413, 'These files are too large to zip together.');
        $tmp = tempnam(sys_get_temp_dir(), 'dz');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) abort(500, 'Could not create the zip.');
        $used = [];
        foreach ($files as [$name, $path]) {
            $name = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]/', '_', (string) $name) ?: 'file';
            $base = pathinfo($name, PATHINFO_FILENAME); $ext = pathinfo($name, PATHINFO_EXTENSION);
            $final = $name; $i = 1;
            while (isset($used[strtolower($final)])) { $final = $base . ' (' . $i++ . ')' . ($ext !== '' ? '.' . $ext : ''); }
            $used[strtolower($final)] = true;
            $zip->addFile($path, $final);
            $zip->setCompressionName($final, \ZipArchive::CM_STORE);      // photos/PDFs are already compressed — store, don't waste CPU
        }
        $zip->close();
        return response()->download($tmp, preg_replace('/[^A-Za-z0-9._ -]/', '_', $zipName) . '.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    };

    // every file attached to a task (the ones shown in its "Files" card)
    Route::get('/api/local-task/{id}/files-zip', function ($id) use ($zipOut) {
        $task = \App\Models\Task::with(['comments', 'files'])->findOrFail($id);
        if (!\App\Models\Task::visibleTo(auth()->user())->whereKey($task->id)->exists()) abort(403);
        $commentFileIds = $task->comments->flatMap(fn($c) => $c->files ?? [])->unique()->all();

        $files = [];
        foreach ($task->files->reject(fn($f) => in_array($f->id, $commentFileIds)) as $f) {
            $rel = preg_replace('#^(?:.*?/)?uploads/#', '', (string) $f->disk_path);
            $abs = $rel !== '' ? \App\Support\Uploads::resolve($rel) : null;
            if ($abs) $files[] = [$f->name ?: basename($abs), $abs];
        }
        return $zipOut($files, 'Task ' . $task->id . ' files');
    })->name('api.local.task.files.zip');

    // any list of uploaded files (used for the attachments of one comment / message)
    Route::post('/api/download-zip', function (\Illuminate\Http\Request $request) use ($zipOut) {
        $request->validate(['urls' => 'required|array|min:1|max:200', 'urls.*' => 'string|max:500', 'name' => 'nullable|string|max:80']);
        $files = [];
        foreach ($request->input('urls') as $u) {
            $path = parse_url($u, PHP_URL_PATH) ?: $u;
            if (!preg_match('#/uploads/(.+)$#', $path, $m)) continue;
            // The real name may live in a decorative trailing path segment (Uploads::urlWithName)
            // or in ?name= — our own uploads only ever get a real name from one of those, since
            // the file on disk is just a generated id with nothing else to go on.
            [$abs, $niceFromPath] = \App\Support\Uploads::resolveWithName(rawurldecode($m[1]));
            if (!$abs) continue;
            parse_str(parse_url($u, PHP_URL_QUERY) ?: '', $qs);
            // drop the import prefixes (chat_123_, tatt_456_ …) so people get the original file name
            $nice = $niceFromPath ?? ($qs['name'] ?? null) ?? preg_replace('/^(?:chat|tatt|tdirect|disk)_\d+_/', '', basename($abs));
            $files[] = [$nice, $abs];
        }
        return $zipOut($files, $request->input('name') ?: 'attachments');
    })->name('api.download.zip');

    // ── Serve uploaded/imported files (private storage, auth required) ──
    Route::get('/uploads/{path}', function ($path, \Illuminate\Http\Request $request) {
        // New links append a decorative "/Original Name.ext" segment after the real storage
        // path (e.g. /uploads/up_xxx.jpg/Bussiness-card-01.jpg) purely so a browser's own
        // Save-As picks up the real name too — Chrome's native image viewer, for one, saves
        // by the URL's last path segment and ignores Content-Disposition when you view an
        // image as its own tab and press Ctrl+S. Existing nested storage paths (Bitrix synced
        // files under uploads/bitrix/...) resolve directly and never hit that fallback.
        [$full, $niceFromPath] = \App\Support\Uploads::resolveWithName($path);
        if (!$full) abort(404);

        // The file on disk is stored under a generated id (up_<uniqid>.ext), not the name the user
        // uploaded it as — callers pass the real name via the path above or ?name= so downloads
        // show that instead.
        $niceName = $niceFromPath ?? trim((string) $request->query('name'));
        $niceName = $niceName !== '' ? basename(str_replace(['/', '\\'], '', $niceName)) : basename($full);

        // Anything that can carry a <script> and render in a browser tab (html/svg/xml/...) must
        // download rather than execute inline — otherwise an uploaded file could run script in an
        // authenticated user's session under our own origin (stored XSS via file upload).
        $renderRisk = ['html','htm','xhtml','shtml','mhtml','xml','svg'];
        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        if (in_array($ext, $renderRisk)) {
            return response()->download($full, $niceName, ['Content-Type' => 'application/octet-stream']);
        }
        // setContentDisposition() builds the required ASCII fallback itself; calling HeaderUtils
        // directly threw "filename fallback cannot contain %/non-ASCII" (a 500) for any file whose
        // real name had a % or accented/Urdu characters.
        // Stored files never change under the same URL (each upload gets a unique name), so let the browser keep them:
        // reopening a task or chat shows its images and attachments from the local cache instead of downloading them again.
        // 'private': only this browser may keep it, never a shared cache or CDN (these files are for signed-in people only).
        $file = response()->file($full)->setContentDisposition('inline', $niceName)
            ->setPrivate()->setMaxAge(2592000)->setImmutable()
            ->setEtag(md5($full . '|' . filemtime($full) . '|' . filesize($full)));
        $file->isNotModified($request);      // browser already has this exact file: answer 304 without sending it again
        return $file;
    })->where('path', '.*')->name('uploads.show');

    // Docx/xlsx preview — the actual file is fetched and rendered client-side (mammoth.js /
    // SheetJS) from the already-authenticated /uploads/{path} URL, so this route only has to
    // serve the static viewer shell.
    Route::get('/doc-viewer', fn () => view('doc-viewer'))->name('doc.viewer');

    // ── Bitrix Disk file proxy (download on-demand, cache locally) ──
    Route::get('/api/disk-file/{id}', function ($id) {
        $id = (int)$id;

        // Only serve Bitrix files referenced by a task the user can see (description, comment or attachment)
        $user = auth()->user();
        if (!$user->canViewAllTasks()) {
            $refs = ["%id=n{$id}]%", "%id=n{$id} %"];
            $refMatch = fn($q, string $col) => $q->where(fn($w) => $w->where($col, 'like', $refs[0])->orWhere($col, 'like', $refs[1]));
            $ok = \App\Models\Task::visibleTo($user)->where(function ($q) use ($id, $refMatch) {
                $refMatch($q, 'description');
                $q->orWhereHas('comments', fn($c) => $refMatch($c, 'content'))
                  ->orWhereHas('files', fn($f) => $f->where('bitrix_file_id', $id));
            })->exists();
            if (!$ok) abort(403);
        }

        // Serve from filesystem cache if already downloaded
        $cacheDir = \App\Support\Uploads::path('bitrix');
        $pattern  = $cacheDir . '/disk_' . $id . '_*';
        $existing = glob($pattern);
        if ($existing) {
            return redirect(asset('uploads/bitrix/' . basename($existing[0])));
        }

        $webhook = rtrim(env('BITRIX_WEBHOOK', ''), '/') . '/';
        if (!$webhook || $webhook === '/') abort(404);

        // Step 1: get file metadata (DOWNLOAD_URL) via curl
        $ch = curl_init($webhook . 'disk.file.get?id=' . $id);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $json = curl_exec($ch);
        curl_close($ch);
        $resp = $json ? json_decode($json, true) : null;

        $downloadUrl = $resp['result']['DOWNLOAD_URL'] ?? null;
        $name        = $resp['result']['NAME'] ?? ('file_' . $id);
        if (!$downloadUrl) abort(404);

        // Step 2: download the actual file via curl
        @mkdir($cacheDir, 0755, true);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $diskPath = 'uploads/bitrix/disk_' . $id . '_' . substr($safeName, 0, 80);
        $savePath = \App\Support\Uploads::path('bitrix/disk_' . $id . '_' . substr($safeName, 0, 80));

        $fp = fopen($savePath, 'wb');
        if (!$fp) abort(500);

        $ch2 = curl_init($downloadUrl);
        curl_setopt_array($ch2, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'Mozilla/5.0',
        ]);
        $ok   = curl_exec($ch2);
        $code = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_close($ch2);
        fclose($fp);

        if (!$ok || $code < 200 || $code >= 300 || filesize($savePath) === 0) {
            @unlink($savePath);
            abort(502);
        }

        return redirect(asset($diskPath));
    })->name('disk.file');

    // ── Attach file to existing task ──
    Route::post('/api/local-task/{id}/attach', function ($id, \Illuminate\Http\Request $request) {
        $task = \App\Models\Task::findOrFail($id);
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$task->isMember($user) && $task->created_by !== $user->id && $task->assigned_to !== $user->id) {
            abort(403);
        }
        $request->validate(['file' => 'required|file|max:20480']);
        $file     = $request->file('file');
        $origName = $file->getClientOriginalName();
        $mime     = $file->getClientMimeType() ?? '';
        $ext      = strtolower($file->getClientOriginalExtension());
        $blocked  = ['exe','bat','cmd','com','msi','scr','dll','sh','bin','apk','jar','js','mjs','vbs','ps1','php','phtml','php3','php4','php5','cgi','pl','py','asp','aspx','jsp'];
        if (in_array($ext, $blocked)) abort(422, 'File type not allowed.');
        $fileSize = $file->getSize() ?: 0;
        $filename = 'up_' . uniqid() . '.' . $ext;
        @mkdir(\App\Support\Uploads::path(), 0755, true);
        $file->move(\App\Support\Uploads::path(), $filename);
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
                'downloadUrl' => \App\Support\Uploads::urlWithName('uploads/' . $filename, $origName),
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
        $task = \App\Models\Task::select('id','title','status','priority','deadline','assigned_to','project_id','created_by')->with(['assignee:id,name','project:id,name'])->findOrFail($id);
        $user = auth()->user();
        // Same rule as opening the task itself (people with the "view all tasks" permission, and people who were
        // @mentioned in it, can see its card on the board too). A narrower rule here made the card refresh 403.
        if (!$task->canBeOpenedBy($user)) {
            abort(403);
        }
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
        $request->validate([
            'content'    => 'required|string|max:' . \App\Models\Message::MAX_CHARS,
            'mentions'   => 'nullable|array',
            'mentions.*' => 'integer',
            'parent_id'  => 'nullable|integer',
        ]);
        $task    = \App\Models\Task::findOrFail($id);
        $authUser = auth()->user();
        // A person @mentioned in this task's comments may reply even though they're not a member —
        // that's how they got here in the first place.
        if (!$task->canBeOpenedBy($authUser)) {
            abort(403);
        }
        $mentions = array_values(array_unique(array_map('intval', (array) $request->mentions)));
        $parent   = $request->filled('parent_id') ? $task->comments()->find($request->parent_id) : null;
        $comment  = $task->comments()->create(['user_id'=>auth()->id(),'content'=>$request->content,'mentions'=>$mentions,'parent_id'=>$parent?->id]);
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

        // Extra "mentioned you" notification for anyone @mentioned (even if not a member)
        if ($mentions) {
            \App\Models\Notification::mention($mentions, auth()->user(),
                auth()->user()->name . ' mentioned you in a comment on "' . $task->title . '"', $task);
        }

        // Replying notifies the original commenter directly, even if they're not otherwise on the task
        // (they were already able to see it, since they commented on it) and even if they were already
        // notified above as a member — a reply is a distinct, more specific thing to know about.
        if ($parent && $parent->user_id && (int) $parent->user_id !== (int) $authUser->id) {
            \App\Models\Notification::mention([$parent->user_id], $authUser,
                $authUser->name . ' replied to your comment on "' . $task->title . '"', $task);
        }

        return response()->json([
            'ok'      => true,
            'comment' => ['id'=>$comment->id,'text'=>$comment->content,'at'=>$comment->created_at->toIso8601String(),
                          'author'=>['name'=>auth()->user()->name,'avatar'=>auth()->user()->avatar_url]],
        ]);
    })->name('api.local.comment');

    // Edit your own task comment (same 24h window as chat message edits)
    Route::patch('/api/local-task/comments/{id}', function ($id, \Illuminate\Http\Request $request) {
        $request->validate(['content' => 'required|string|max:' . \App\Models\Message::MAX_CHARS]);
        $user    = auth()->user();
        $comment = \App\Models\TaskComment::findOrFail($id);
        if ((int) $comment->user_id !== (int) $user->id) return response()->json(['error' => 'Forbidden'], 403);
        if ($comment->created_at->diffInHours(now()) > 24) return response()->json(['error' => 'Too late to edit'], 403);
        $comment->update(['content' => $request->content, 'edited_at' => now()]);
        $comment->task?->broadcastChange('comment', $user->id);
        return response()->json(['ok' => true, 'editedAt' => $comment->edited_at->format('g:i a')]);
    });

    // Toggle a reaction on a task comment
    Route::post('/api/local-task/comments/{id}/react', function ($id, \Illuminate\Http\Request $request) {
        $request->validate(['emoji' => 'required|string|max:8']);
        $user    = auth()->user();
        $comment = \App\Models\TaskComment::with('task')->findOrFail($id);
        $task    = $comment->task;
        if (!$task || !$task->canBeOpenedBy($user)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $emoji = $request->emoji;
        $rxns  = $comment->reactions ?? [];
        $ids   = array_values((array) ($rxns[$emoji] ?? []));
        $adding = !in_array($user->id, $ids);
        if ($adding) $ids[] = $user->id;
        else $ids = array_values(array_filter($ids, fn($v) => $v !== $user->id));
        if (empty($ids)) unset($rxns[$emoji]); else $rxns[$emoji] = $ids;
        $comment->update(['reactions' => empty($rxns) ? null : $rxns]);
        $task->broadcastChange('comment', $user->id);

        if ($adding && !in_array($comment->user_id, [null, $user->id], true)) {
            \App\Models\Notification::mention([$comment->user_id], $user,
                $user->name . ' reacted ' . $emoji . ' to your comment on "' . $task->title . '"', $task);
        }

        return response()->json(['ok' => true, 'reactions' => $rxns]);
    })->name('api.local.comment.react');

    // ── File Upload API ──
    Route::post('/api/upload', function (\Illuminate\Http\Request $request) {
        try {
            $request->validate(['file' => 'required|file|max:51200']);
            $file     = $request->file('file');
            $origName = $file->getClientOriginalName();
            $mime     = $file->getClientMimeType() ?? '';
            $ext      = strtolower($file->getClientOriginalExtension()) ?: 'bin';
            // Internal team tool (Bitrix replacement) — people attach all sorts of work files
            // (xml exports, html reports, etc.), so block only what's actually dangerous to
            // serve back rather than maintaining an allowlist that keeps missing legitimate types.
            $blocked  = ['exe','bat','cmd','com','msi','scr','dll','sh','bin','apk','jar','js','mjs','vbs','ps1','php','phtml','php3','php4','php5','cgi','pl','py','asp','aspx','jsp'];
            if (in_array($ext, $blocked)) return response()->json(['error'=>'File type not allowed.'],422);
            $filename = 'up_' . uniqid() . '.' . $ext;

            $uploadPath = \App\Support\Uploads::path();
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $file->move($uploadPath, $filename);

            // The stored filename is a generated id (up_<uniqid>.ext), not what the user picked —
            // carry the original name in the URL so a later download shows it instead of the id.
            return response()->json([
                'url'  => \App\Support\Uploads::urlWithName('uploads/' . $filename, $origName),
                'name' => $origName,
                'mime' => $mime,
                'ext'  => $ext,
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => 'Upload failed. Please try again.'], 500);
        }
    });

    // Release notes for the "new version available" banner
    Route::get('/api/whats-new', fn (\Illuminate\Http\Request $r) => response()->json(['releases' => \App\Support\WhatsNew::since((int) $r->query('since', 0))]))->name('whatsnew');

    // ── Anonymous-ish per-page performance reports from browsers (see the layout), written to their own log ──
    Route::post('/api/perf', function (\Illuminate\Http\Request $request) {
        $num = fn ($k, $max = 10000000) => $request->filled($k) && is_numeric($request->input($k)) ? max(0, min($max, +$request->input($k))) : null;
        $row = array_filter([
            'user' => auth()->id(),
            'page' => substr(preg_replace('/[^A-Za-z0-9\/_\-.]/', '', (string) $request->input('page')), 0, 60),
            'ttfb' => $num('ttfb'), 'dcl' => $num('dcl'), 'load' => $num('load'), 'kb' => $num('kb'), 'reqs' => $num('reqs'),
            'long' => $num('long'), 'longMs' => $num('longMs'), 'longest' => $num('longest'), 'cls' => $num('cls', 100),
            'dom' => $num('dom'), 'heap' => $num('heap'), 'cores' => $num('cores', 256), 'mem' => $num('mem', 1024),
            'net' => in_array($request->input('net'), ['slow-2g', '2g', '3g', '4g'], true) ? $request->input('net') : null,
            'rtt' => $num('rtt', 100000), 'down' => $num('down', 100000), 'rt' => $request->boolean('rt'), 'stay' => $num('stay', 86400),
            'lite' => $request->boolean('lite'), 'frames' => $num('frames'), 'susp' => $num('susp'), 'j50' => $num('j50'), 'j100' => $num('j100'), 'maxGap' => $num('maxGap'), 'dpr' => $num('dpr', 20),
            'scr' => preg_match('/^\d{3,5}x\d{3,5}$/', (string) $request->input('scr')) ? $request->input('scr') : null,
            'win' => preg_match('/^\d{2,5}x\d{2,5}$/', (string) $request->input('win')) ? $request->input('win') : null,
            'ua' => substr(preg_replace('/\s+/', ' ', (string) $request->userAgent()), 0, 90),
        ], fn ($v) => $v !== null && $v !== '');
        \Illuminate\Support\Facades\Log::build(['driver' => 'single', 'path' => storage_path('logs/perf.log'), 'level' => 'info'])->info('perf', $row);
        return response()->noContent();
    })->middleware('throttle:30,1')->name('perf.report');

    // ── Voice calls (WebRTC; signalling relayed over the realtime channel) ──
    Route::get('/call/window', fn () => \App\Support\Realtime::enabled() ? view('call.window') : abort(404))->name('call.window');
    Route::get('/api/calls/ice', [\App\Http\Controllers\CallController::class, 'ice'])->name('calls.ice');
    Route::post('/api/calls', [\App\Http\Controllers\CallController::class, 'start'])->name('calls.start');
    Route::get('/api/calls/{id}', [\App\Http\Controllers\CallController::class, 'show'])->whereNumber('id')->name('calls.show');
    Route::post('/api/calls/{id}/accept', [\App\Http\Controllers\CallController::class, 'accept'])->whereNumber('id')->name('calls.accept');
    Route::post('/api/calls/{id}/end', [\App\Http\Controllers\CallController::class, 'end'])->whereNumber('id')->name('calls.end');
    Route::post('/api/calls/{id}/ping', [\App\Http\Controllers\CallController::class, 'ping'])->whereNumber('id')->name('calls.ping');
    Route::post('/api/calls/{id}/diag', [\App\Http\Controllers\CallController::class, 'diag'])->whereNumber('id')->name('calls.diag');
    Route::post('/api/calls/{id}/signal', [\App\Http\Controllers\CallController::class, 'signal'])->whereNumber('id')->name('calls.signal');

    // ── Realtime (Pusher) channel auth: a browser may only ever subscribe to its OWN user channel ──
    Route::post('/realtime/auth', function (\Illuminate\Http\Request $request) {
        if (!\App\Support\Realtime::enabled()) abort(404);
        $request->validate([
            'socket_id'    => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => 'required|string|max:100',
        ]);
        if ($request->channel_name !== \App\Support\Realtime::userChannel((int) auth()->id())) abort(403);
        return response()->json(['auth' => \App\Support\Realtime::authToken($request->socket_id, $request->channel_name)]);
    })->name('realtime.auth');

    // ── Browser push (alerts while Desk is closed) ──
    Route::post('/push/subscribe', function (\Illuminate\Http\Request $request) {
        if (!\App\Support\WebPush::enabled()) abort(404);
        $data = $request->validate([
            'endpoint'    => ['required', 'string', 'max:2000', 'regex:/^https:\/\//'],
            'keys.p256dh' => 'required|string|max:200',
            'keys.auth'   => 'required|string|max:100',
        ]);
        // Same browser, different login -> the subscription moves to whoever is signed in now.
        \App\Models\PushSubscription::updateOrCreate(
            ['endpoint_hash' => \App\Models\PushSubscription::hashFor($data['endpoint'])],
            ['user_id' => auth()->id(), 'endpoint' => $data['endpoint'], 'p256dh' => $data['keys']['p256dh'],
             'auth' => $data['keys']['auth'], 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'last_used_at' => now()]
        );
        \App\Models\PushSubscription::where('last_used_at', '<', now()->subDays(90))->delete();   // browsers that never came back
        return response()->json(['ok' => true]);
    })->name('push.subscribe');

    Route::post('/push/unsubscribe', function (\Illuminate\Http\Request $request) {
        $request->validate(['endpoint' => 'required|string|max:2000']);
        \App\Models\PushSubscription::where('endpoint_hash', \App\Models\PushSubscription::hashFor($request->endpoint))
            ->where('user_id', auth()->id())->delete();
        return response()->json(['ok' => true]);
    })->name('push.unsubscribe');

    Route::post('/push/test', function () {
        if (!\App\Support\WebPush::enabled()) abort(404);
        $devices = \App\Models\PushSubscription::where('user_id', auth()->id())->count();
        \App\Support\WebPush::sendToUsers([auth()->id()], [
            'title' => 'IKIA Desk', 'body' => 'Notifications are working on this device.',
            'url' => '/', 'tag' => 'push-test', 'always' => true,
        ]);
        return response()->json(['ok' => true, 'devices' => $devices]);
    })->name('push.test');

    // ── Chat Panel API ──
    Route::get('/api/chat/convs', function () {
        $user = auth()->user();
        // This runs on every poll from every open tab — a DB write each time was pure overhead.
        // 20s granularity is plenty for the "online" dot and the delivered tick (which compares
        // against it), so only write when the stored value has actually gone stale.
        if (!$user->last_seen_at || $user->last_seen_at->diffInSeconds(now()) >= 20) {
            $user->forceFill(['last_seen_at' => now()])->save();
        }

        // Personal "Notes" chat (Bitrix parity): one private conversation per user, only they are in it
        if (!$user->conversations()->where('conversations.type', 'notes')->exists()) {
            $notes = \App\Models\Conversation::create(['type' => 'notes', 'name' => 'Notes', 'created_by' => $user->id]);
            $notes->members()->attach($user->id);
        }

        $convs = $user->conversations()->with(['lastMessage.user','members'])->get()
            ->filter(fn($c) => $c->type === 'notes' || $c->lastMessage !== null)
            ->sortByDesc(fn($c) => $c->type === 'notes' ? PHP_INT_MAX : $c->lastMessage->created_at->timestamp)->values();

        $memberRows = \App\Models\ConversationMember::where('user_id', $user->id)->get()->keyBy('conversation_id');

        // Unread counts. The old single JOIN compared created_at against a per-row COALESCE(), which
        // MySQL can't turn into an index range — it counted every message of every conversation the
        // user is in (the big General chat especially), ~300ms on every poll. A conversation can only
        // have unread messages if its latest message is newer than the user's last_read_at, and for
        // those the cutoff is a plain literal, so the count becomes a tiny indexed range scan.
        $unreadCounts = collect();
        foreach ($convs as $c) {
            $row = $memberRows->get($c->id);
            if (!$row || !$c->lastMessage) continue;
            $since = $row->last_read_at ?? '2000-01-01';
            if ($c->lastMessage->created_at->lte(\Carbon\Carbon::parse($since))) continue;
            $unreadCounts[$c->id] = \Illuminate\Support\Facades\DB::table('messages')
                ->where('conversation_id', $c->id)
                ->where('user_id', '!=', $user->id)
                ->whereNull('deleted_at')
                ->where('created_at', '>', $since)
                ->count();
        }

        $list = [];
        foreach ($convs as $c) {
            $other  = $c->type==='direct' ? $c->members->where('id','!=',$user->id)->first() : null;
            $lm     = $c->lastMessage;
            $online = $other && $other->last_seen_at && $other->last_seen_at->diffInMinutes(now()) < 5;
            $list[] = ['id'=>$c->id,'type'=>$c->type,
                'name'          => $c->type==='general' ? 'General Chat' : ($c->type==='notes' ? 'Notes' : ($c->type==='direct' ? ($other?->name??'Unknown') : ($c->name??'Group'))),
                'other_user_id' => $c->type==='direct' ? ($other?->id) : null,
                'avatar'        => $c->type==='direct' ? ($other?->avatar_url??null) : null,
                'position'      => $c->type==='direct' ? ($other?->position) : null,
                'members'       => $c->members->count(),
                'unread'        => $unreadCounts->get($c->id, 0),
                'online'        => $online,
                'lastMsg'       => $lm ? ['text'=>mb_substr($lm->content, 0, 600),'byMe'=>$lm->user_id===$user->id,'senderName'=>$lm->user?->name,'time'=>\App\Support\Tz::forViewer($lm->created_at, $user)->format('g:i a')] : null,
            ];
        }
        return response()->json(['convs'=>$list]);
    });

    // ── "About chat" data (Bitrix-style side panel): links, files & media, shared tasks ──
    Route::get('/api/chat/convs/{id}/about', function ($id, \Illuminate\Http\Request $request) {
        $user = auth()->user();
        $conv = \App\Models\Conversation::with('members')->findOrFail($id);
        if ($conv->type !== 'general' && !$conv->members->contains('id', $user->id))
            return response()->json(['error' => 'Forbidden'], 403);

        $names  = \App\Models\User::whereIn('id', $conv->members->pluck('id'))->pluck('name', 'id');
        $other  = $conv->type === 'direct' ? $conv->members->where('id', '!=', $user->id)->first() : null;
        $authorOf = fn($uid) => $names[$uid] ?? \App\Models\User::where('id', $uid)->value('name') ?? '';
        $strip = fn(string $t) => preg_replace(['/\[img\].*?\[\/img\]/s', '/\[file name="[^"]*"\].*?\[\/file\]/s', '/\[voice[^\]]*\].*?\[\/voice\]/s'], '', $t);

        // links (newest first; capped scan so huge chats stay fast)
        $links = [];
        $rows = $conv->messages()->reorder()->where('content', 'like', '%http%')
            ->orderByDesc('created_at')->orderByDesc('id')->limit(1500)->get(['id', 'user_id', 'content', 'created_at']);
        foreach ($rows as $m) {
            if (!preg_match_all('#https?://[^\s\[\]<>"\']+#i', $strip((string) $m->content), $mm)) continue;
            foreach (array_unique($mm[0]) as $u) {
                $links[] = ['url' => rtrim($u, '.,;)'), 'messageId' => $m->id, 'author' => $authorOf($m->user_id), 'date' => \App\Support\Tz::forViewer($m->created_at, $user)->format('j M Y')];
                if (count($links) >= 300) break 2;
            }
        }

        // files & media
        $media = [];
        $rows = $conv->messages()->reorder()
            ->where(fn($q) => $q->where('content', 'like', '%[img]%')->orWhere('content', 'like', '%[file name=%'))
            ->orderByDesc('created_at')->orderByDesc('id')->limit(400)->get(['id', 'user_id', 'content', 'created_at']);
        foreach ($rows as $m) {
            preg_match_all('/\[img\](.*?)\[\/img\]|\[file name="([^"]*)"\](.*?)\[\/file\]/s', (string) $m->content, $mm, PREG_SET_ORDER);
            foreach ($mm as $x) {
                $isImg = ($x[1] ?? '') !== '';
                $url   = $isImg ? $x[1] : ($x[3] ?? '');
                if ($url === '') continue;
                $media[] = ['type' => $isImg ? 'img' : 'file', 'url' => $url, 'name' => $isImg ? basename(parse_url($url, PHP_URL_PATH) ?: 'image') : $x[2],
                            'messageId' => $m->id, 'author' => $authorOf($m->user_id), 'date' => \App\Support\Tz::forViewer($m->created_at, $user)->format('j M Y')];
                if (count($media) >= 200) break 2;
            }
        }

        // tasks the two of them share (direct chats only)
        $tasks = [];
        if ($other) {
            $tasks = \App\Models\Task::where(function ($q) use ($user, $other) {
                    $q->where(fn($a) => $a->where('created_by', $user->id)->where('assigned_to', $other->id))
                      ->orWhere(fn($a) => $a->where('created_by', $other->id)->where('assigned_to', $user->id));
                })->latest()->limit(6)->get(['id', 'title', 'status'])
                ->map(fn($t) => ['id' => $t->id, 'title' => $t->title, 'status' => $t->status])->all();
        }

        return response()->json([
            'type'     => $conv->type,
            'name'     => $conv->type === 'notes' ? 'Notes' : ($conv->type === 'direct' ? ($other?->name ?? '') : ($conv->name ?? 'Group')),
            'avatar'   => $other?->avatar_url,
            'subtitle' => $other?->position,
            'members'  => $conv->members->count(),
            'links'    => $links,
            'media'    => $media,
            'tasks'    => $tasks,
        ]);
    });

    // ── Search inside one conversation (Bitrix-style side panel) ──
    Route::get('/api/chat/convs/{id}/search', function ($id, \Illuminate\Http\Request $request) {
        $user = auth()->user();
        $conv = \App\Models\Conversation::with('members')->findOrFail($id);
        if ($conv->type !== 'general' && !$conv->members->contains('id', $user->id))
            return response()->json(['error' => 'Forbidden'], 403);

        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json(['results' => [], 'q' => $q]);

        $like = '%' . addcslashes($q, '\\%_') . '%';
        $rows = $conv->messages()->with('user')->reorder()
            ->where('content', 'like', $like)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(80)->get()
            ->reject(fn($m) => in_array($user->id, (array) ($m->deleted_for ?? [])));

        $plain = function (string $t): string {
            $t = preg_replace('/\[img\].*?\[\/img\]/s', '📷 Photo', $t);
            $t = preg_replace('/\[file name="([^"]*)"\].*?\[\/file\]/s', '📎 $1', $t);
            $t = preg_replace('/\[voice[^\]]*\].*?\[\/voice\]/s', '🎤 Voice message', $t);
            return trim(preg_replace('/\s+/u', ' ', $t));
        };

        return response()->json([
            'q' => $q,
            'results' => $rows->map(function ($m) use ($user, $plain) {
                $atViewer = \App\Support\Tz::forViewer($m->created_at, $user);
                return [
                    'id'        => $m->id,
                    'text'      => mb_substr($plain((string) $m->content), 0, 300),
                    'author'    => $m->user?->name ?? '',
                    'avatar'    => $m->user?->avatar_url ?? '',
                    'isMine'    => $m->user_id === $user->id,
                    'date'      => $atViewer->format('j M Y'),
                    'time'      => $atViewer->format('g:i a'),
                    'createdTs' => $m->created_at->timestamp,
                ];
            })->values(),
        ]);
    });

    Route::get('/api/chat/convs/{id}/msgs', function ($id, \Illuminate\Http\Request $request) {
        $user   = auth()->user();
        $conv   = \App\Models\Conversation::with('members')->findOrFail($id);
        if ($conv->type !== 'general' && !$conv->members->contains('id', $user->id))
            return response()->json(['error'=>'Forbidden'],403);

        $afterId  = (int)($request->query('after', 0));
        $beforeTs = (int)($request->query('before_ts', 0));
        $plainMsgSnippet = function (?string $t): string {
            $t = preg_replace(['/\[img\].*?\[\/img\]/s', '/\[file name="[^"]*"\].*?\[\/file\]/s', '/\[voice[^\]]*\].*?\[\/voice\]/s'], ['[image]', '[file]', '[voice]'], (string) $t);
            return mb_substr(trim(preg_replace('/\s+/u', ' ', $t)), 0, 100);
        };
        $msgFmt   = function ($m) use ($user, $plainMsgSnippet) {
            // 'time'/'date' are shown as-is (no client-side re-conversion), so they must already
            // be in the VIEWING user's own timezone — a message near midnight can land on a
            // different calendar day for them than for Asia/Karachi.
            $atViewer = \App\Support\Tz::forViewer($m->created_at, $user);
            return [
                'id'        => $m->id,
                'text'      => $m->content,
                'isMine'    => $m->user_id===$user->id,
                'time'      => $atViewer->format('g:i a'),
                'date'      => $atViewer->format('Y-m-d'),
                'createdTs' => $m->created_at->timestamp,
                'editedAt'  => $m->edited_at?->format('g:i a'),
                'deletedFor'=> $m->deleted_for ?? [],
                'reactions' => $m->reactions ?? [],
                'myReactions'=> array_keys(array_filter($m->reactions ?? [], fn($ids) => in_array($user->id, (array)$ids))),
                'author'    => ['id'=>$m->user_id,'name'=>$m->user?->name??'','avatar'=>$m->user?->avatar_url??''],
                'parentId'  => $m->parent_id,
                'parentPreview' => $m->parent ? ['author' => $m->parent->user?->name ?? '', 'text' => $plainMsgSnippet($m->parent->content)] : null,
            ];
        };

        $other = $conv->type === 'direct' ? $conv->members->where('id', '!=', $user->id)->first() : null;
        $otherLastReadTs = $other
            ? \App\Models\ConversationMember::where('conversation_id', $conv->id)->where('user_id', $other->id)->value('last_read_at')
            : null;
        $otherLastReadTs = $otherLastReadTs ? \Carbon\Carbon::parse($otherLastReadTs)->timestamp : null;
        // "Delivered" (WhatsApp-style double grey tick, before the recipient has actually opened
        // this conversation): there's no push/ack channel here, so the best available signal is
        // the recipient's own last_seen_at heartbeat — updated on every poll their browser makes,
        // regardless of which conversation (if any) they currently have open.
        $otherLastSeenTs = $other?->last_seen_at?->timestamp;

        // Stamp this conversation as read for me and, when that actually cleared something the other
        // person sent, tell them right away so their ticks update without waiting for a poll.
        $markRead = function () use ($conv, $user, $other) {
            $q    = \App\Models\ConversationMember::where('conversation_id', $conv->id)->where('user_id', $user->id);
            $prev = $q->value('last_read_at');
            $q->update(['last_read_at' => now()]);
            if ($other && $conv->messages()->reorder()->where('user_id', '!=', $user->id)->where('created_at', '>', $prev ?? '2000-01-01')->exists()) {
                \App\Support\Realtime::publishToUsers([$other->id], 'chat.changed', ['c' => $conv->id, 'k' => 'read', 'by' => $user->id]);
            }
        };

        if ($afterId > 0) {
            // Incremental poll — only new messages after given ID
            $msgs = $conv->messages()->with(['user','parent.user'])->reorder()->where('id','>',$afterId)->orderBy('id')->limit(50)->get();
            if ($msgs->isNotEmpty()) $markRead();
            return response()->json(['messages' => $msgs->map($msgFmt)->values(), 'otherLastReadTs' => $otherLastReadTs, 'otherLastSeenTs' => $otherLastSeenTs]);
        }

        if ($beforeTs > 0) {
            // Scroll-up pagination — load older messages before given timestamp
            // Fetch limit+1 to detect whether more exist without a separate COUNT query
            $beforeDt = \Carbon\Carbon::createFromTimestamp($beforeTs, config('app.timezone'));   // same zone the rows are stored in
            $beforeId = (int) $request->query('before_id', 0);
            $raw = $conv->messages()->with(['user','parent.user'])->reorder()
                        ->where(function ($q) use ($beforeDt, $beforeId) {
                            $q->where('created_at', '<', $beforeDt);
                            // same-second messages (bulk imports): continue by id so none are skipped
                            if ($beforeId > 0) $q->orWhere(fn($q2) => $q2->where('created_at', $beforeDt)->where('id', '<', $beforeId));
                        })
                        ->orderByDesc('created_at')->orderByDesc('id')->limit(51)->get();
            $hasMore = $raw->count() > 50;
            $msgs = $raw->take(50)->sortBy([['created_at', 'asc'], ['id', 'asc']])->values();
            return response()->json([
                'messages' => $msgs->map($msgFmt)->values(),
                'hasMore'  => $hasMore,
            ]);
        }

        // Full load — latest 50 messages by created_at (reorder() clears the relationship's default ASC scope)
        $markRead();
        $raw   = $conv->messages()->with(['user','parent.user'])->reorder()->latest('created_at')->limit(51)->get();
        $hasMore = $raw->count() > 50;
        $msgs  = $raw->take(50)->sortBy(fn($m) => $m->created_at->timestamp)->values();
        $online = $other && $other->last_seen_at && $other->last_seen_at->diffInMinutes(now()) < 5;

        return response()->json([
            'conv' => ['id'=>$conv->id,'type'=>$conv->type,
                'name'    => $conv->type==='notes' ? 'Notes' : ($conv->type==='direct' ? ($other?->name??'Unknown') : ($conv->name??'Group')),
                'avatar'  => $conv->type==='direct' ? ($other?->avatar_url??null) : null,
                'position'=> $conv->type==='direct' ? ($other?->position) : null,
                'members' => $conv->members->count(),
                'online'  => $online,
                'last_seen' => $other?->last_seen_at?->diffForHumans()],
            'messages'       => $msgs->map($msgFmt),
            'hasMore'        => $hasMore,
            'otherLastReadTs'=> $otherLastReadTs,
            'otherLastSeenTs'=> $otherLastSeenTs,
        ]);
    });

    // ── Edit message ──
    Route::patch('/api/chat/msgs/{id}', function ($id, \Illuminate\Http\Request $request) {
        $request->validate(['content'=>'required|string|max:' . \App\Models\Message::MAX_CHARS]);
        $user = auth()->user();
        $msg  = \App\Models\Message::findOrFail($id);
        if ((int)$msg->user_id !== (int)$user->id) return response()->json(['error'=>'Forbidden'],403);
        if ($msg->created_at->diffInHours(now()) > 24) return response()->json(['error'=>'Too late to edit'],403);
        $msg->update(['content'=>$request->content,'edited_at'=>now()]);
        $conv = $msg->conversation()->with('members')->first();
        if ($conv) \App\Support\Realtime::publishToUsers($conv->audienceIds(), 'chat.changed', ['c' => $conv->id, 'k' => 'edit', 'm' => $msg->id, 'by' => $user->id]);
        return response()->json(['ok'=>true,'editedAt'=>$msg->edited_at->format('g:i a')]);
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
        // "for everyone" changes what all members see; "for me" only my own other tabs/devices.
        $conv = \App\Models\Conversation::with('members')->find($msg->conversation_id);
        if ($conv) \App\Support\Realtime::publishToUsers($scope === 'everyone' ? $conv->audienceIds() : [$user->id], 'chat.changed', ['c' => $conv->id, 'k' => 'delete', 'm' => $msg->id, 'by' => $user->id]);
        return response()->json(['ok'=>true]);
    });

    // ── React to message (toggle emoji reaction) ──
    Route::post('/api/chat/msgs/{id}/react', function (\Illuminate\Http\Request $request, $id) {
        $request->validate(['emoji' => 'required|string|max:8']);
        $user  = auth()->user();
        $msg   = \App\Models\Message::with('conversation.members')->findOrFail($id);
        $conv  = $msg->conversation;
        if (!$conv || ($conv->type !== 'general' && !$conv->members->contains('id', $user->id)))
            return response()->json(['error'=>'Forbidden'],403);
        $emoji = $request->emoji;
        $rxns  = $msg->reactions ?? [];
        $ids   = array_values((array)($rxns[$emoji] ?? []));
        if (in_array($user->id, $ids)) {
            $ids = array_values(array_filter($ids, fn($v) => $v !== $user->id));
        } else {
            $ids[] = $user->id;
        }
        if (empty($ids)) {
            unset($rxns[$emoji]);
        } else {
            $rxns[$emoji] = $ids;
        }
        $msg->update(['reactions' => empty($rxns) ? null : $rxns]);
        \App\Support\Realtime::publishToUsers($conv->audienceIds(), 'chat.changed', ['c' => $conv->id, 'k' => 'react', 'm' => $msg->id, 'by' => $user->id]);
        return response()->json(['ok' => true, 'reactions' => $rxns]);
    });

    Route::post('/api/chat/convs/{id}/leave', function ($id) {
        $user = auth()->user();
        $conv = \App\Models\Conversation::findOrFail($id);
        if ($conv->type === 'general') return response()->json(['error'=>'Cannot leave general chat'],422);
        \App\Models\ConversationMember::where('conversation_id',$conv->id)->where('user_id',$user->id)->delete();
        return response()->json(['ok'=>true]);
    });

    Route::post('/api/chat/convs/{id}/send', function (\Illuminate\Http\Request $request, $id) {
        $request->validate([
            'content'     => 'required|string|max:' . \App\Models\Message::MAX_CHARS,
            'mentions'    => 'nullable|array',
            'mentions.*'  => 'integer',
            'parent_id'   => 'nullable|integer',
        ]);
        $user = auth()->user();
        $conv = \App\Models\Conversation::with('members')->findOrFail($id);
        if ($conv->type !== 'general' && !$conv->members->contains('id',$user->id))
            return response()->json(['error'=>'Forbidden'],403);

        // Mentions come as explicit user IDs picked from the @dropdown; only notify people
        // who can actually see this conversation.
        $memberIds = $conv->type === 'general'
            ? \App\Models\User::where('is_active', true)->pluck('id')->toArray()
            : $conv->members->pluck('id')->toArray();
        $mentions = array_values(array_intersect(array_map('intval', (array) $request->mentions), $memberIds));
        if ($conv->type === 'notes') $mentions = [];

        // A reply's parent must be a real message in this same conversation, never trust the client blindly.
        $parent = $request->filled('parent_id') ? $conv->messages()->find($request->parent_id) : null;

        $msg = $conv->messages()->create(['user_id'=>$user->id,'content'=>$request->content,'mentions'=>$mentions,'parent_id'=>$parent?->id]);
        \App\Models\ConversationMember::where('conversation_id',$conv->id)->where('user_id',$user->id)
            ->update(['last_read_at'=>now()]);
        // Push the "new message" signal to everyone in the conversation (the sender's own other tabs
        // included). Ids only — each browser then fetches the text from us.
        \App\Support\Realtime::publishToUsers($memberIds, 'chat.changed', ['c' => $conv->id, 'k' => 'new', 'm' => $msg->id, 'by' => $user->id]);

        $convName = $conv->type === 'general' ? 'General Chat'
            : ($conv->type === 'group' ? ($conv->name ?? 'a group') : 'a chat');

        $chatUrl = '/chat?conv=' . $conv->id;

        if ($mentions) {
            \App\Models\Notification::mention($mentions, $user,
                $user->name . ' mentioned you in ' . $convName, null, $chatUrl);
        }
        $repliedTo = [];
        if ($parent && $parent->user_id !== $user->id && !in_array($parent->user_id, $mentions)) {
            $repliedTo = [$parent->user_id];
            \App\Models\Notification::mention($repliedTo, $user,
                $user->name . ' replied to you in ' . $convName, null, $chatUrl);
        }
        // Everyone else gets a plain "new message" push (mentioned / replied-to people already got
        // their own, more specific one above).
        if ($conv->type !== 'notes' && \App\Support\WebPush::enabled()) {
            \App\Support\WebPush::sendToUsers(
                \App\Models\User::whereIn('id', array_diff($memberIds, [$user->id], $mentions, $repliedTo))
                    ->where('notify_messages', true)->pluck('id')->all(),
                \App\Support\WebPush::chatPayload($conv, $user, $request->content, $chatUrl)
            );
        }

        $plainMsgSnippet = function (?string $t): string {
            $t = preg_replace(['/\[img\].*?\[\/img\]/s', '/\[file name="[^"]*"\].*?\[\/file\]/s', '/\[voice[^\]]*\].*?\[\/voice\]/s'], ['[image]', '[file]', '[voice]'], (string) $t);
            return mb_substr(trim(preg_replace('/\s+/u', ' ', $t)), 0, 100);
        };

        $sentAtViewer = \App\Support\Tz::forViewer($msg->created_at, $user);
        return response()->json(['ok'=>true,'message'=>[
            'id'=>$msg->id,'text'=>$msg->content,'isMine'=>true,
            'time'=>$sentAtViewer->format('g:i a'),'date'=>$sentAtViewer->format('Y-m-d'),
            'createdTs'=>$msg->created_at->timestamp,'editedAt'=>null,
            'author'=>['id'=>$user->id,'name'=>$user->name,'avatar'=>$user->avatar_url],
            'parentId'=>$parent?->id,
            'parentPreview'=>$parent ? ['author'=>$parent->user?->name??'', 'text'=>$plainMsgSnippet($parent->content)] : null,
        ]]);
    });

    Route::post('/api/chat/direct', function (\Illuminate\Http\Request $request) {
        $request->validate(['user_id'=>'required|exists:users,id']);
        $user = auth()->user(); $otherId = $request->user_id;
        if ($otherId == $user->id) return response()->json(['error'=>'Cannot chat with yourself.'],422);
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
        // Live Bitrix24 proxy exposes any Bitrix task — admins only
        if (!auth()->user()->isAdmin()) abort(403);
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
        // Live Bitrix24 proxy exposes any Bitrix task and its chat — admins only
        if (!auth()->user()->isAdmin()) abort(403);
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
