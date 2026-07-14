<?php

namespace App\Support;

/**
 * Vergelijkt twee titels op gelijkenis (0-1). Gebruikt bij het opsporen van
 * dubbele tijdschriften: "The Moslim world" en "The Muslim World" zijn hetzelfde
 * blad, "Islamic Studies" en "Islamic Quarterly" niet.
 *
 * Bewust géén slimme trucs: kleine letters, diakrieten en leestekens weg, en dan
 * de Levenshtein-afstand ten opzichte van de lengte. Voorspelbaar en uitlegbaar —
 * en het oordeel blijft bij de mens: dit levert alleen een VOORSTEL.
 */
class Titelvergelijker
{
    public static function gelijkenis(string $a, string $b): float
    {
        $a = self::normaliseer($a);
        $b = self::normaliseer($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        // levenshtein() werkt op bytes; lange titels afkappen (limiet 255).
        $afstand = levenshtein(mb_substr($a, 0, 200), mb_substr($b, 0, 200));
        $lengte = max(mb_strlen($a), mb_strlen($b));

        return $lengte > 0 ? max(0.0, 1 - ($afstand / $lengte)) : 0.0;
    }

    /** Kleine letters, diakrieten en leestekens weg, dubbele spaties weg. */
    public static function normaliseer(string $tekst): string
    {
        $tekst = mb_strtolower(trim($tekst));

        $tekst = strtr($tekst, [
            'ı' => 'i', 'İ' => 'i', 'ş' => 's', 'ğ' => 'g', 'ç' => 'c', 'ö' => 'o', 'ü' => 'u',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ï' => 'i', 'á' => 'a', 'à' => 'a', 'ä' => 'a',
            '’' => "'", '‘' => "'",
        ]);

        $tekst = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $tekst);

        return trim(preg_replace('/\s+/', ' ', (string) $tekst));
    }
}
