<?php

namespace App\Support;

use App\Models\Stage;

/**
 * Genereert het leesbare stagenummer: prefix 'S' + 2-cijferige jaarprefix +
 * volgnummer (bijv. S260007). Uniek, leesbaar VELD — nooit een koppelsleutel.
 */
class StagenummerGenerator
{
    public static function genereer(?int $jaar = null): string
    {
        $jaar ??= (int) date('Y');
        $prefix = 'S'.substr((string) $jaar, -2);
        $volgLengte = (int) config('sis.relatienummer.volgnummer_lengte', 4);

        $laatste = Stage::query()
            ->where('stagenummer', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(stagenummer) DESC')
            ->orderByDesc('stagenummer')
            ->value('stagenummer');

        $volgnummer = 1;
        if ($laatste !== null) {
            $volgnummer = ((int) substr($laatste, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $volgnummer, $volgLengte, '0', STR_PAD_LEFT);
    }
}
