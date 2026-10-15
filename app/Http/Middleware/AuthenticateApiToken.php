<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a request in from its "Authorization: Bearer <token>" header. No session and no cookie are involved, so the
 * handlers shared with the website (which call auth()->user()) work unchanged.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        $token = $plain ? ApiToken::with('user')->where('token_hash', ApiToken::hashFor($plain))->first() : null;

        if (!$token || !$token->user) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'Please sign in again.'], 401);
        }
        if (!$token->user->is_active) {
            return response()->json(['error' => 'deactivated', 'message' => 'Your account has been deactivated.'], 401);
        }

        // A write on every call would be pure overhead; once a minute is plenty for "last used".
        if (!$token->last_used_at || $token->last_used_at->diffInSeconds(now()) >= 60) {
            $token->forceFill(['last_used_at' => now()])->save();
        }

        Auth::guard('web')->setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
