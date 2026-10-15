<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Registers this phone's push (FCM) token so the server knows where to send alerts. */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'push_token'  => 'required|string|max:4096',
            'platform'    => 'required|in:ios,android',
            'app_version' => 'nullable|string|max:32',
        ]);

        // Same phone, another login -> the registration moves to whoever is signed in now.
        DeviceToken::updateOrCreate(
            ['push_token_hash' => DeviceToken::hashFor($data['push_token'])],
            [
                'user_id'      => $request->user()->id,
                'api_token_id' => $request->attributes->get('api_token')?->id,
                'push_token'   => $data['push_token'],
                'platform'     => $data['platform'],
                'app_version'  => $data['app_version'] ?? $request->header('X-App-Version'),
                'last_used_at' => now(),
            ]
        );
        DeviceToken::where('last_used_at', '<', now()->subDays(90))->delete();   // phones that never came back

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['push_token' => 'required|string|max:4096']);
        DeviceToken::where('push_token_hash', DeviceToken::hashFor($request->push_token))
            ->where('user_id', $request->user()->id)->delete();

        return response()->json(['ok' => true]);
    }
}
