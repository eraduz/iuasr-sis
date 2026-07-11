<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Beperkt een route tot één of meer rollen. Gebruik: ->middleware('rol:docent')
 * of ->middleware('rol:examencommissie,directie'). Autorisatie wordt hier
 * server-side afgedwongen, onafhankelijk van wat de UI toont.
 */
class RolMiddleware
{
    public function handle(Request $request, Closure $next, string ...$rollen): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        // Multi-rol: toegang zodra één van de rollen van de gebruiker volstaat.
        if (count(array_intersect($user->rolSleutels(), $rollen)) === 0) {
            abort(403, 'Uw rol heeft geen toegang tot deze functie.');
        }

        return $next($request);
    }
}
