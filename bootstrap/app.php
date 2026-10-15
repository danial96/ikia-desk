<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\AddBuildHeader::class,
        ]);

        // Mobile API (/api/v1): JSON only, no session or cookies; each phone signs in with its own token.
        $middleware->api(prepend: [
            \App\Http\Middleware\ApiJson::class,
            \App\Http\Middleware\EnforceAppVersion::class,
        ]);
        $middleware->throttleApi('mobile');
        $middleware->alias(['api.token' => \App\Http\Middleware\AuthenticateApiToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
