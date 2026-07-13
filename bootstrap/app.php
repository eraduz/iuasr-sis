<?php

use App\Http\Middleware\RolMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Vertrouw de reverse-proxy (Apache/nginx) zodat de app het juiste
        // schema (https) en de hostnaam uit X-Forwarded-* headers overneemt.
        // Nodig achter de lokale HTTPS-vhost en achter Plesk/nginx.
        $middleware->trustProxies(at: '*', headers:
            Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );

        // Rolscheiding wordt server-side afgedwongen. Registreer de alias
        // zodat routes kunnen worden vergrendeld met ->middleware('rol:docent').
        $middleware->alias([
            'rol' => RolMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
