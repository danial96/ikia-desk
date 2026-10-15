<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Support\ApiUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => 'required|email',
            'password'    => 'required|string',
            'device_name' => 'nullable|string|max:120',
            'platform'    => 'nullable|in:ios,android',
            'app_version' => 'nullable|string|max:32',
        ]);

        // Same limit as the website: 5 wrong passwords per email + IP per minute.
        $throttleKey = 'api-login|' . Str::transliterate(Str::lower($data['email']) . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json(['error' => 'too_many_attempts', 'message' => "Too many login attempts. Please try again in {$seconds} seconds.", 'retry_after' => $seconds], 429);
        }

        $provider    = Auth::guard('web')->getProvider();
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        $user        = $provider->retrieveByCredentials($credentials);

        if (!$user || !$provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($throttleKey, 60);
            return response()->json(['error' => 'invalid_credentials', 'message' => 'Invalid credentials.'], 422);
        }
        if (!$user->is_active) {
            return response()->json(['error' => 'deactivated', 'message' => 'Your account has been deactivated.'], 403);
        }

        RateLimiter::clear($throttleKey);
        $user->forceFill(['last_login_at' => now()])->save();
        [, $plain] = ApiToken::issue($user, $data['device_name'] ?? null, $data['platform'] ?? null, $data['app_version'] ?? $request->header('X-App-Version'));

        return response()->json(['token' => $plain, 'user' => ApiUser::payload($user)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => ApiUser::payload($request->user())]);
    }

    /** Signs this phone out: its token and the push registration that belonged to it go away. */
    public function logout(Request $request): JsonResponse
    {
        $request->attributes->get('api_token')?->delete();   // device_tokens cascade
        return response()->json(['ok' => true]);
    }

    /** Always answers the same way, so it can't be used to find out which emails have an account. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));
        return response()->json(['ok' => true, 'message' => 'If that email has an account, a reset link is on its way.']);
    }
}
