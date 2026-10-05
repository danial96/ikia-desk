<?php

namespace App\Http\Middleware;

use App\Support\Build;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Tells every browser which deployed version answered, so a stale open tab can offer a reload. */
class AddBuildHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-App-Build', Build::id());
        return $response;
    }
}
