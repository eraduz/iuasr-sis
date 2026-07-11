<?php

namespace App\Support;

use App\Models\Resultaat;
use App\Models\Vak;
use Illuminate\Support\Collection;

/**
 * EC-berekening (PvA §6). EC worden afgeleid uit de resultaten. Er zijn twee
 * modellen (instelbaar per opleiding, terugval op config('sis.cijfers.ec_model');
 * zie de studiegids-analyse 2026-07-11 — de bindende regel staat in het OER):
 *
 *  - 'knockout'        : ALLE meetellende toetsonderdelen moeten ≥ cesuur zijn,
 *                        anders 0 EC (geen compensatie tussen onderdelen).
 *  - 'compensatorisch' : het GEWOGEN eindcijfer over de meetellende onderdelen
 *                        moet ≥ cesuur zijn; een onvoldoende onderdeel kan door
 *                        een hoger ander onderdeel worden gecompenseerd.
 *
 * De voldoende-grens is een OPENSTAANDE PARAMETER per opleiding. Deze klasse
 * verzint géén grens: ontbreekt de grens, dan is het resultaat onbepaald (null)
 * in plaats van een aanname.
 */
class EcBerekening
{
    /**
     * @param  iterable<Resultaat>  $resultaten  resultaten van deze student voor dit vak/periode
     * @param  string  $model  'knockout' (default) of 'compensatorisch'
     * @return float|null  toegekende EC (kan 2,5 zijn), of null als de voldoende-grens niet is vastgesteld
     */
    public static function bepaalEc(Vak $vak, iterable $resultaten, ?float $voldoendeGrens, string $model = 'knockout'): ?float
    {
        // Zonder vastgestelde grens kan niet worden bepaald wat 'voldoende' is.
        if ($voldoendeGrens === null) {
            return null;
        }

        $meetellend = $vak->toetsonderdelen->where('telt_mee', true);
        if ($meetellend->isEmpty()) {
            return 0.0;
        }

        return $model === 'compensatorisch'
            ? self::compensatorisch($vak, $meetellend, $resultaten, $voldoendeGrens)
            : self::knockout($vak, $meetellend, $resultaten, $voldoendeGrens);
    }

    /** Knock-out: elk meetellend onderdeel moet zelfstandig ≥ cesuur zijn. */
    private static function knockout(Vak $vak, Collection $meetellend, iterable $resultaten, float $grens): float
    {
        foreach ($meetellend as $onderdeel) {
            $besteGeldige = self::besteGeldigePoging($resultaten, $onderdeel->id);

            // Geen (geldig) resultaat, of onder de grens → geen EC voor het vak.
            if ($besteGeldige === null) {
                return 0.0;
            }
            if ($besteGeldige->vrijstelling) {
                continue; // vrijstelling telt als behaald
            }
            if ($besteGeldige->cijfer === null || (float) $besteGeldige->cijfer < $grens) {
                return 0.0;
            }
        }

        return (float) $vak->ec;
    }

    /**
     * Compensatorisch: het gewogen eindcijfer over de meetellende onderdelen moet
     * ≥ cesuur zijn. Een vrijgesteld onderdeel telt als behaald en blijft buiten
     * het gemiddelde; ontbreekt een onderdeelresultaat, dan is het vak onvolledig
     * (0 EC). Zijn álle meetellende onderdelen vrijgesteld, dan is het vak behaald.
     */
    private static function compensatorisch(Vak $vak, Collection $meetellend, iterable $resultaten, float $grens): float
    {
        $som = 0.0;
        $gewicht = 0.0;
        $allesVrijgesteld = true;

        foreach ($meetellend as $onderdeel) {
            $besteGeldige = self::besteGeldigePoging($resultaten, $onderdeel->id);
            if ($besteGeldige === null) {
                return 0.0; // onvolledig: geen resultaat voor dit onderdeel
            }
            if ($besteGeldige->vrijstelling) {
                continue; // behaald; buiten het gewogen gemiddelde
            }
            $allesVrijgesteld = false;
            if ($besteGeldige->cijfer === null) {
                return 0.0;
            }
            $som += (float) $besteGeldige->cijfer * (float) $onderdeel->weging;
            $gewicht += (float) $onderdeel->weging;
        }

        if ($allesVrijgesteld) {
            return (float) $vak->ec;
        }
        if ($gewicht <= 0) {
            return 0.0;
        }

        $eindcijfer = round($som / $gewicht, 1);

        return $eindcijfer >= $grens ? (float) $vak->ec : 0.0;
    }

    /**
     * De beste geldige poging voor een toetsonderdeel: hoogste cijfer, waarbij
     * een vrijstelling altijd als behaald geldt.
     *
     * @param  iterable<Resultaat>  $resultaten
     */
    private static function besteGeldigePoging(iterable $resultaten, int $toetsonderdeelId): ?Resultaat
    {
        $beste = null;
        foreach ($resultaten as $r) {
            if ($r->toetsonderdeel_id !== $toetsonderdeelId) {
                continue;
            }
            if ($r->vrijstelling) {
                return $r;
            }
            if ($beste === null || (float) $r->cijfer > (float) $beste->cijfer) {
                $beste = $r;
            }
        }

        return $beste;
    }
}
