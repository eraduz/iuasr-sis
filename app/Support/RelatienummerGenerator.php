<?php

namespace App\Support;

use App\Models\Organisatie;

/**
 * Genereert het leesbare relatienummer voor een organisatie: prefix (config,
 * standaard 'R') + 2-cijferige jaarprefix + volgnummer (bijv. R260007).
 *
 * Het nummer is een uniek, leesbaar VELD — nooit een koppelsleutel.
 */
class RelatienummerGenerator
{
    public static function genereer(?int $jaar = null): string
    {
        $jaar ??= (int) date('Y');
        $prefix = config('sis.relatienummer.prefix', 'R').substr((string) $jaar, -2);
        $volgLengte = (int) config('sis.relatienummer.volgnummer_lengte', 4);

        $laatste = Organisatie::query()
            ->where('relatienummer', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(relatienummer) DESC')
            ->orderByDesc('relatienummer')
            ->value('relatienummer');

        $volgnummer = 1;
        if ($laatste !== null) {
            $volgnummer = ((int) substr($laatste, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $volgnummer, $volgLengte, '0', STR_PAD_LEFT);
    }
}
