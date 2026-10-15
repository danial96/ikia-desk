<?php

namespace App\Http\Controllers;

use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Realtime (Pusher) channel auth: a client may only ever subscribe to its OWN user channel. Used by the website and the app. */
class RealtimeAuthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!Realtime::enabled()) abort(404);
        $request->validate([
            'socket_id'    => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => 'required|string|max:100',
        ]);
        if ($request->channel_name !== Realtime::userChannel((int) auth()->id())) abort(403);
        return response()->json(['auth' => Realtime::authToken($request->socket_id, $request->channel_name)]);
    }
}
