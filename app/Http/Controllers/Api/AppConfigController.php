<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;

/** What the app needs before anyone has signed in: whether this build is still allowed, and where to get the new one. */
class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'min_version'    => config('desk.mobile.min_version'),
            'latest_version' => config('desk.mobile.latest_version'),
            'store'          => config('desk.mobile.store'),
            'realtime'       => Realtime::clientConfig(),   // Pusher key + cluster, or null when realtime is off
            'server_time'    => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }
}
