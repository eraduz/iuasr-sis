<?php

namespace App\Support;

use App\Models\Scriptie;

/**
 * Genereert het leesbare scriptienummer: prefix (config, standaard 'S') +
 * 2-cijferige jaarprefix + volgnummer (bijv. S260007). Een uniek, leesbaar VELD —
 * nooit een koppelsleutel.
 */
class Scriptienummer
{
    public static function genereer(?int $jaar = null): string
    {
        $jaar ??= (int) date('Y');
        $prefix = config('sis.scriptie.scriptienummer.prefix', 'S').substr((string) $jaar, -2);
        $volgLengte = (int) config('sis.scriptie.scriptienummer.volgnummer_lengte', 4);

        $laatste = Scriptie::query()
            ->where('scriptienummer', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(scriptienummer) DESC')
            ->orderByDesc('scriptienummer')
            ->value('scriptienummer');

        $volgnummer = 1;
        if ($laatste !== null) {
            $volgnummer = ((int) substr($laatste, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $volgnummer, $volgLengte, '0', STR_PAD_LEFT);
    }
}
