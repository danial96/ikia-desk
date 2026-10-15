<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets us retire old app builds: when the app sends "X-App-Version" and it is older than DESK_MOBILE_MIN_VERSION the
 * API answers 426, and the app shows its "please update" screen. No header (a browser, a test) is never blocked.
 */
class EnforceAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $sent = (string) $request->header('X-App-Version', '');
        $min  = (string) config('desk.mobile.min_version', '0.0.0');

        if ($sent !== '' && version_compare($sent, $min, '<')) {
            return response()->json([
                'error'       => 'update_required',
                'message'     => 'Please update IKIA Desk to keep using it.',
                'min_version' => $min,
                'store'       => config('desk.mobile.store'),
            ], 426);
        }

        return $next($request);
    }
}
