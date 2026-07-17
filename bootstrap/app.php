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

        // Netwerkbeperking: het systeem draait intern en is IP-beperkt (PvA).
        // Leeg gelaten lijst = geen filter (lokale ontwikkeling); op test en
        // productie hoort SIS_TOEGESTANE_IPS gevuld te zijn. Staat vóór de
        // authenticatie: wie niet op het netwerk hoort, ziet niet eens een login.
        $middleware->web(prepend: [
            \App\Http\Middleware\IpBeperking::class,
        ]);

        // Rolscheiding wordt server-side afgedwongen. Registreer de alias
        // zodat routes kunnen worden vergrendeld met ->middleware('rol:docent').
        $middleware->alias([
            'rol' => RolMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Bij een validatiefout flasht Laravel de request-input terug naar de
        // sessie, zodat het formulier ingevuld blijft. Standaard worden alleen
        // velden overgeslagen die 'password' HETEN — dit project gebruikt
        // Nederlandse veldnamen, dus 'wachtwoord' moet er expliciet bij. Zonder
        // deze regel belandt het noodwachtwoord (break-glass) in leesbare vorm in
        // de sessiestore bij elke mislukte inlogpoging. Zie NoodaccountTest.
        $exceptions->dontFlash([
            'wachtwoord',
            'wachtwoord_confirmation',
            'huidig_wachtwoord',
            'current_password',
            'password',
            'password_confirmation',
        ]);
    })->create();
