<?php

namespace App\Support;

use App\Models\Cursist;

/**
 * Genereert het leesbare cursistnummer: prefix 'C' + 2-cijferige jaarprefix +
 * volgnummer (bijv. C260007). Bewust anders dan het studentnummer, zodat
 * cursisten en studenten niet door elkaar lopen.
 *
 * Het nummer is een uniek, leesbaar VELD — nooit een koppelsleutel.
 */
class CursistnummerGenerator
{
    public static function genereer(?int $jaar = null): string
    {
        $jaar ??= (int) date('Y');
        $prefix = 'C'.substr((string) $jaar, -2);
        $volgLengte = 4;

        $laatste = Cursist::query()
            ->where('cursistnummer', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(cursistnummer) DESC')
            ->orderByDesc('cursistnummer')
            ->value('cursistnummer');

        $volgnummer = 1;
        if ($laatste !== null) {
            $volgnummer = ((int) substr($laatste, strlen($prefix))) + 1;
        }

        return $prefix.str_pad((string) $volgnummer, $volgLengte, '0', STR_PAD_LEFT);
    }
}
