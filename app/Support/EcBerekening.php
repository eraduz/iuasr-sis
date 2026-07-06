<?php

namespace App\Support;

use App\Models\Resultaat;
use App\Models\Vak;

/**
 * EC-berekening (PvA §6). EC worden afgeleid uit de resultaten: pas wanneer
 * ALLE meetellende toetsonderdelen voldoende zijn, wordt de volledige EC van
 * het vak toegekend — anders 0.
 *
 * De voldoende-grens is een OPENSTAANDE PARAMETER per opleiding. Deze klasse
 * verzint géén grens: ontbreekt de grens, dan is het resultaat onbepaald (null)
 * in plaats van een aanname.
 */
class EcBerekening
{
    /**
     * @param  iterable<Resultaat>  $resultaten  resultaten van deze student voor dit vak/periode
     * @return int|null  toegekende EC, of null als de voldoende-grens niet is vastgesteld
     */
    public static function bepaalEc(Vak $vak, iterable $resultaten, ?float $voldoendeGrens): ?int
    {
        // Zonder vastgestelde grens kan niet worden bepaald wat 'voldoende' is.
        if ($voldoendeGrens === null) {
            return null;
        }

        $meetellend = $vak->toetsonderdelen->where('telt_mee', true);
        if ($meetellend->isEmpty()) {
            return 0;
        }

        foreach ($meetellend as $onderdeel) {
            $besteGeldige = self::besteGeldigePoging($resultaten, $onderdeel->id);

            // Geen (geldig) resultaat, of onder de grens → geen EC voor het vak.
            if ($besteGeldige === null) {
                return 0;
            }
            if ($besteGeldige->vrijstelling) {
                continue; // vrijstelling telt als behaald
            }
            if ($besteGeldige->cijfer === null || (float) $besteGeldige->cijfer < $voldoendeGrens) {
                return 0;
            }
        }

        return (int) $vak->ec;
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
