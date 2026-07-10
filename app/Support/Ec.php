<?php

namespace App\Support;

/**
 * Weergave van studiepunten. Het curriculum kent halve EC (2,5). In de UI en in
 * documenten wordt de Nederlandse decimaalkomma gebruikt, en wordt een heel
 * getal zonder decimalen getoond: 5 (niet 5,0) en 2,5.
 */
class Ec
{
    public static function toon(int|float|string|null $ec, string $leeg = '—'): string
    {
        if ($ec === null || $ec === '') {
            return $leeg;
        }

        $waarde = round((float) $ec, 1);

        return floor($waarde) == $waarde
            ? (string) (int) $waarde
            : number_format($waarde, 1, ',', '');
    }
}
