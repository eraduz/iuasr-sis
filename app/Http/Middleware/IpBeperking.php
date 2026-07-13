<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Netwerkbeperking: het systeem draait intern en is IP-beperkt (Plan van Aanpak).
 *
 * De lijst staat in `config/sis.php` → `sis.toegestane_ips` (env
 * `SIS_TOEGESTANE_IPS`, komma-gescheiden IP-adressen of CIDR-bereiken).
 *
 * LEEG = GEEN FILTER. Dat is bewust: bij lokale ontwikkeling wil je geen slot op
 * de deur. Maar op de test- en productieomgeving hoort deze lijst gevuld te zijn;
 * staat hij daar leeg, dan is het systeem vanaf elk netwerk bereikbaar.
 *
 * Het IP komt uit `$request->ip()`, dat achter de vertrouwde reverse-proxy het
 * echte client-IP oplevert (zie trustProxies in bootstrap/app.php) — anders zou
 * iedereen het IP van de proxy hebben en zou het filter niets betekenen.
 */
class IpBeperking
{
    public function handle(Request $request, Closure $next): Response
    {
        $toegestaan = config('sis.toegestane_ips', []);

        if ($toegestaan === []) {
            return $next($request); // geen filter ingesteld
        }

        if (! IpUtils::checkIp((string) $request->ip(), $toegestaan)) {
            abort(403, 'Dit systeem is alleen bereikbaar vanaf het interne netwerk van IUASR.');
        }

        return $next($request);
    }
}
