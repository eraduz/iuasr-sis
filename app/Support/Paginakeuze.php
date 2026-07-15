<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Het aantal rijen per pagina, door de gebruiker te kiezen. Eén plek zodat de
 * catalogusschermen dezelfde opties en dezelfde standaard hanteren.
 */
class Paginakeuze
{
    /** De toegestane keuzes; een andere waarde valt terug op de standaard. */
    public const OPTIES = [25, 50, 100, 200];

    public const STANDAARD = 25;

    /** Het gekozen aantal, begrensd tot de toegestane opties. */
    public static function aantal(Request $request, string $sleutel = 'per'): int
    {
        $gekozen = (int) $request->query($sleutel, self::STANDAARD);

        return in_array($gekozen, self::OPTIES, true) ? $gekozen : self::STANDAARD;
    }
}
