<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mobile API always speaks JSON: validation errors come back as 422 JSON (never a redirect), and the
 * shared handlers that branch on "is this an AJAX call" (`$request->ajax()`) answer with JSON too.
 */
class ApiJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');
        $request->attributes->set('via_api', true);   // lets shared handlers skip website-only side effects

        return $next($request);
    }
}
