<?php

use App\Http\Controllers\Api\AppConfigController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DirectoryController;
use App\Http\Controllers\Api\TaskApiController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RealtimeAuthController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

/*
 * Mobile app API — served at /api/v1/... (bootstrap/app.php sets the prefix), token auth, JSON only, no session.
 *
 * Most of what the app does is done by the SAME handlers the website uses, so the rules (who may see or change
 * what) live in one place only:
 *   - TaskController / ProfileController / RealtimeAuthController, mounted below;
 *   - routes/shared_api.php (chat, notifications, task detail/comments/checklists/files, upload) and
 *     routes/shared_files.php (private file downloads).
 * The ApiJson middleware makes those handlers answer with JSON.
 */
Route::name('v1.')->group(function () {
    // Open: no sign-in yet.
    Route::get('app/config', [AppConfigController::class, 'show']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');

    Route::middleware('api.token')->group(function () {
        // Session / device
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('devices', [DeviceController::class, 'store']);
        Route::delete('devices', [DeviceController::class, 'destroy']);
        Route::post('me/notifications', [ProfileController::class, 'updateNotifications']);
        Route::post('realtime/auth', RealtimeAuthController::class);

        // Pick-lists
        Route::get('users', [DirectoryController::class, 'users']);
        Route::get('projects', [DirectoryController::class, 'projects']);

        // Tasks (one task's full detail, its comments, checklist and files are the shared routes: /local-task/{id}/...)
        Route::get('tasks', [TaskApiController::class, 'index']);
        Route::get('tasks/board', [TaskController::class, 'kanban']);
        Route::get('tasks/board/version', [TaskController::class, 'kanbanVersion']);
        Route::get('tasks/trash', [TaskApiController::class, 'trash']);
        Route::delete('tasks/{id}/force', [TaskApiController::class, 'forceDestroy'])->whereNumber('id');
        Route::post('tasks/{id}/restore', [TaskApiController::class, 'restore'])->whereNumber('id');
        Route::post('tasks', [TaskController::class, 'store']);
        Route::put('tasks/{task}', [TaskController::class, 'update']);
        Route::delete('tasks/{task}', [TaskApiController::class, 'destroy']);
        Route::patch('tasks/{task}/move', [TaskController::class, 'move']);
        Route::patch('tasks/{task}/field', [TaskController::class, 'updateField']);
        Route::post('tasks/{task}/participants/toggle', [TaskController::class, 'toggleParticipant']);
        Route::post('tasks/{task}/observers/toggle', [TaskController::class, 'toggleObserver']);

        // Shared with the website
        Route::group([], base_path('routes/shared_api.php'));
        Route::group([], base_path('routes/shared_files.php'));
    });
});
