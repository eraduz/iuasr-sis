<?php

namespace App\Support;

use App\Models\Medewerker;

/**
 * Genereert het leesbare personeelsnummer: prefix (config, standaard 'P') +
 * 2-cijferige jaarprefix + volgnummer (bijv. P260007). Uniek, leesbaar VELD —
 * nooit een koppelsleutel.
 */
class PersoneelsnummerGenerator
{
    public static function genereer(?int $jaar = null): string
    {
        $jaar ??= (int) date('Y');
        $prefix = config('sis.hr.personeelsnummer.prefix', 'P').substr((string) $jaar, -2);
        $volgLengte = (int) config('sis.hr.personeelsnummer.volgnummer_lengte', 4);

        $laatste = Medewerker::query()
            ->where('personeelsnummer', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(personeelsnummer) DESC')
            ->orderByDesc('personeelsnummer')
            ->value('personeelsnummer');

        $volgnummer = $laatste !== null ? ((int) substr($laatste, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $volgnummer, $volgLengte, '0', STR_PAD_LEFT);
    }
}
