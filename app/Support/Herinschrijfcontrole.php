<?php

namespace App\Support;

use App\Models\Inschrijving;
use Illuminate\Support\Carbon;

/**
 * Doorstroomtoets bij herinschrijven (opdrachtgever 2026-07-22). Bepaalt of een
 * student mag doorstromen naar een HÓGER leerjaar in DEZELFDE opleiding.
 *
 * Twee regels, in volgorde:
 *  1. Geldigheidsduur EC — is de pauze sinds de vorige inschrijving langer dan
 *     `sis.herinschrijving.ec_geldigheid_jaren` (standaard 5), dan zijn de EC
 *     vervallen: doorstromen kan niet, de student moet opnieuw op leerjaar 1
 *     beginnen. Harde regel, geen uitzondering.
 *  2. Slaag-eis — het vorige leerjaar moet zijn gehaald (overgangsadvies van
 *     {@see Overgangsbeoordeling}). 'positief' mag door; 'voorwaardelijk' mag door
 *     met waarschuwing; 'negatief' is geblokkeerd maar de Beheerder mag namens de
 *     examencommissie met een reden vrijgeven; 'onbekend' (drempel niet ingesteld)
 *     mag door met een notitie.
 *
 * Studiewissel (andere opleiding), een jaar overdoen (zelfde leerjaar) of een
 * lager leerjaar vallen buiten de toets.
 */
class Herinschrijfcontrole
{
    /**
     * @return array{
     *   toegestaan: bool, blokkade: ?string, override_mogelijk: bool,
     *   verplicht_leerjaar: ?int, melding: ?string, waarschuwing: ?string,
     *   overgang: ?array, pauze_jaren: ?int, geldigheid_jaren: int, is_doorstroom: bool
     * }
     */
    public static function beoordeel(Inschrijving $huidige, int $doelOpleidingId, int $doelLeerjaar, string $inschrijfdatum): array
    {
        $geldigheid = (int) config('sis.herinschrijving.ec_geldigheid_jaren', 5);

        $resultaat = [
            'toegestaan' => true, 'blokkade' => null, 'override_mogelijk' => false,
            'verplicht_leerjaar' => null, 'melding' => null, 'waarschuwing' => null,
            'overgang' => null, 'pauze_jaren' => null, 'geldigheid_jaren' => $geldigheid,
            'is_doorstroom' => false,
        ];

        $studiewissel = $doelOpleidingId !== (int) $huidige->opleiding_id;
        // Studiewissel of (opnieuw) beginnen op leerjaar 1: geen toets — dan bouwt
        // de student niet voort op eerder behaalde EC.
        if ($studiewissel || $doelLeerjaar <= 1) {
            return $resultaat;
        }

        // Vanaf hier: dezelfde opleiding, leerjaar >= 2 (voortzetten van de studie,
        // hetzij hervatten van hetzelfde jaar, hetzij doorstromen naar een hoger jaar).
        $isDoorstroom = $doelLeerjaar > (int) $huidige->leerjaar;
        $resultaat['is_doorstroom'] = $isDoorstroom;

        // 1) Geldigheidsduur EC — is de pauze te lang, dan vervallen ALLE EC en moet
        // de student opnieuw op leerjaar 1 beginnen. Geldt zowel bij doorstromen als
        // bij het hervatten van hetzelfde jaar; harde regel, geen uitzondering.
        $pauze = self::pauzeJaren($huidige, $inschrijfdatum);
        $resultaat['pauze_jaren'] = $pauze;
        if ($pauze > $geldigheid) {
            $resultaat['toegestaan'] = false;
            $resultaat['blokkade'] = 'ec_verlopen';
            $resultaat['verplicht_leerjaar'] = 1;
            $resultaat['melding'] = "De vorige inschrijving is {$pauze} jaar geleden (langer dan {$geldigheid} jaar). "
                .'De eerder behaalde EC zijn vervallen; de student kan de studie niet vervolgen en moet opnieuw beginnen op leerjaar 1. '
                .'De oude resultaten blijven als historie bewaard.';

            return $resultaat;
        }

        // 2) Slaag-eis — alleen bij doorstromen naar een HÓGER leerjaar. Hetzelfde
        // jaar hervatten/overdoen mag altijd (binnen de geldigheidsduur).
        if (! $isDoorstroom) {
            return $resultaat;
        }

        $overgang = Overgangsbeoordeling::voor($huidige);
        $resultaat['overgang'] = $overgang;

        // Kent het vorige leerjaar geen vakken/EC (curriculum nog niet ingericht),
        // dan valt er niets te toetsen: doorstroom toestaan met een notitie.
        if (($overgang['mogelijk'] ?? 0) <= 0) {
            $resultaat['waarschuwing'] = 'Het vorige leerjaar kent nog geen vakken om de doorstroom aan te toetsen; '
                .'controleer of het curriculum is ingericht.';

            return $resultaat;
        }

        switch ($overgang['status']) {
            case 'positief':
                break;
            case 'voorwaardelijk':
                $resultaat['waarschuwing'] = "Voorwaardelijke doorstroom: {$overgang['behaald']} van de {$overgang['drempel']} vereiste EC behaald. "
                    .'Ga na of de examencommissie voorwaardelijke bevordering toestaat.';
                break;
            case 'negatief':
                $resultaat['toegestaan'] = false;
                $resultaat['blokkade'] = 'niet_geslaagd';
                $resultaat['override_mogelijk'] = true;
                $resultaat['melding'] = "De student heeft het vorige leerjaar niet gehaald: {$overgang['behaald']} van de "
                    ."{$overgang['drempel']} vereiste EC. Doorstromen naar leerjaar {$doelLeerjaar} is geblokkeerd. "
                    .'De Beheerder kan dit namens de examencommissie met een reden vrijgeven.';
                break;
            default: // onbekend — drempel niet ingesteld
                $resultaat['waarschuwing'] = 'De EC-overgangsdrempel is voor deze opleiding niet ingesteld; de doorstroom '
                    .'kon niet automatisch worden getoetst. Beheer stelt de drempel in via Opzoektabellen → Opleidingen.';
                break;
        }

        return $resultaat;
    }

    /** Aantal volle jaren tussen de vorige inschrijving en de nieuwe inschrijfdatum. */
    private static function pauzeJaren(Inschrijving $huidige, string $inschrijfdatum): int
    {
        if ($huidige->inschrijfdatum === null) {
            return 0;
        }

        $van = Carbon::parse($huidige->inschrijfdatum);
        $tot = Carbon::parse($inschrijfdatum);
        if ($tot->lessThan($van)) {
            return 0;
        }

        return (int) $van->diffInYears($tot);
    }
}
