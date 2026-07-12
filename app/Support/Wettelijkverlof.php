<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Rekenregels voor wettelijk verlof (Wet arbeid en zorg / WAZO).
 * Bron: rijksoverheid.nl. Levert een voorstel; HR kan het altijd aanpassen
 * (bijv. bij een vroege/late bevalling of gespreid opnemen).
 */
class Wettelijkverlof
{
    /** Standaard zwangerschapsverlof: 6 weken vóór de uitgerekende datum. */
    public const ZWANGERSCHAP_WEKEN_VOOR = 6;

    /** Standaard bevallingsverlof: 10 weken na de bevalling. */
    public const BEVALLING_WEKEN_NA = 10;

    /** Aanvullend geboorteverlof: maximaal 5× de weekuren. */
    public const AANVULLEND_GEBOORTE_MAX_WEKEN = 5;

    /** Ouderschapsverlof: totaal recht van 26× de weekuren per kind. */
    public const OUDERSCHAP_TOTAAL_WEKEN = 26;

    /** Ouderschapsverlof: 9 weken deels betaald (70% via UWV) in het eerste levensjaar. */
    public const OUDERSCHAP_BETAALD_WEKEN = 9;

    /**
     * Voorstel voor zwangerschaps- en bevallingsverlof op basis van de
     * uitgerekende datum: 6 weken ervoor tot 10 weken erna (samen 16 weken).
     *
     * @return array{van: Carbon, tot: Carbon, weken: int}
     */
    public static function zwangerschapEnBevalling(Carbon $uitgerekend): array
    {
        $van = $uitgerekend->copy()->subWeeks(self::ZWANGERSCHAP_WEKEN_VOOR);
        $tot = $uitgerekend->copy()->addWeeks(self::BEVALLING_WEKEN_NA);

        return [
            'van' => $van,
            'tot' => $tot,
            'weken' => self::ZWANGERSCHAP_WEKEN_VOOR + self::BEVALLING_WEKEN_NA,
        ];
    }

    /** Geboorteverlof (partner): eenmaal het aantal werkuren per week. */
    public static function geboorteverlofUren(float $urenPerWeek): float
    {
        return round($urenPerWeek, 1);
    }

    /** Aanvullend geboorteverlof (partner): maximaal 5× de weekuren. */
    public static function aanvullendGeboorteverlofUren(float $urenPerWeek): float
    {
        return round($urenPerWeek * self::AANVULLEND_GEBOORTE_MAX_WEKEN, 1);
    }

    /**
     * Ouderschapsverlof: totaal 26× de weekuren, waarvan 9 weken deels betaald
     * (70% via UWV) en de rest onbetaald.
     *
     * @return array{totaal: float, betaald: float, onbetaald: float}
     */
    public static function ouderschapsverlofUren(float $urenPerWeek): array
    {
        $totaal = round($urenPerWeek * self::OUDERSCHAP_TOTAAL_WEKEN, 1);
        $betaald = round($urenPerWeek * self::OUDERSCHAP_BETAALD_WEKEN, 1);

        return [
            'totaal' => $totaal,
            'betaald' => $betaald,
            'onbetaald' => round($totaal - $betaald, 1),
        ];
    }
}
