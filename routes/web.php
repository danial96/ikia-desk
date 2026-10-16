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

    // Payment Terminal: an external site; only people with the permission (and the super admin) are sent on.
    // The address lives only on the server, so nobody else ever sees it in the page.
    Route::get('/payment-terminal', function () {
        if (!auth()->user()->canAccessPaymentTerminal()) abort(403);
        return redirect()->away(config('desk.payment_terminal_url'))->header('Referrer-Policy', 'no-referrer');
    })->name('payment.terminal');

    // Get the phone app (the sidebar's "Download app" button)
    Route::get('/app', fn () => \App\Support\MobileApp::available()
        ? view('app-download', ['links' => \App\Support\MobileApp::links()])
        : abort(404))->name('app.download');

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::post('/permissions/{employee}', [PermissionController::class, 'update'])->name('permissions.update');

    // JSON API shared with the mobile app (routes/shared_api.php); same /api/... paths as ever
    Route::prefix('api')->group(base_path('routes/shared_api.php'));

    // Private file downloads (routes/shared_files.php)
    Route::group([], base_path('routes/shared_files.php'));


    // Docx/xlsx preview — the actual file is fetched and rendered client-side (mammoth.js /
    // SheetJS) from the already-authenticated /uploads/{path} URL, so this route only has to
    // serve the static viewer shell.
    Route::get('/doc-viewer', fn () => view('doc-viewer'))->name('doc.viewer');


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
    // The call routes themselves (/api/calls/...) live in routes/shared_api.php so the phone app can use them too.
    Route::get('/call/window', fn () => \App\Support\Realtime::enabled() ? view('call.window') : abort(404))->name('call.window');

    // ── Realtime (Pusher) channel auth: a browser may only ever subscribe to its OWN user channel ──
    Route::post('/realtime/auth', \App\Http\Controllers\RealtimeAuthController::class)->name('realtime.auth');

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
