<?php

namespace App\Support;

use App\Models\Inschrijving;
use App\Models\Resultaat;
use App\Models\Vak;

/**
 * Leerjaar-herbeoordeling (overgangsadvies). Vergelijkt de in het studiejaar
 * behaalde EC met de EC-overgangsdrempel van de opleiding (BSA-/doorstroomnorm).
 *
 * Context (HBO-praktijk): het propedeusejaar telt 60 EC; vanaf studiejaar
 * 2026-2027 geldt landelijk een BSA-norm van 30 EC. De overgangsnorm naar de
 * hoofdfase ligt doorgaans tussen 30 en 45 EC en wordt PER OPLEIDING vastgelegd
 * (`opleidingen.ec_overgang_drempel`). Deze klasse verzint géén norm: ontbreekt
 * de drempel, dan is het advies 'onbekend'.
 */
class Overgangsbeoordeling
{
    /** Onder deze fractie van de drempel is het advies 'voorwaardelijk' i.p.v. negatief. */
    private const VOORWAARDELIJK_FRACTIE = 0.75;

    /**
     * @return array{status: string, behaald: float, drempel: int|null, mogelijk: float}
     *   status: positief | voorwaardelijk | negatief | onbekend
     */
    public static function voor(Inschrijving $inschrijving): array
    {
        $drempel = $inschrijving->opleiding?->ec_overgang_drempel;
        $behaald = self::behaaldeEc($inschrijving);
        $mogelijk = self::mogelijkeEc($inschrijving);

        $status = match (true) {
            $drempel === null => 'onbekend',
            $behaald >= $drempel => 'positief',
            $behaald >= (int) ceil($drempel * self::VOORWAARDELIJK_FRACTIE) => 'voorwaardelijk',
            default => 'negatief',
        };

        return ['status' => $status, 'behaald' => $behaald, 'drempel' => $drempel, 'mogelijk' => $mogelijk];
    }

    /** In dit studiejaar/leerjaar daadwerkelijk behaalde EC (vakken volledig gehaald). */
    public static function behaaldeEc(Inschrijving $inschrijving): float
    {
        $vakken = self::leerjaarVakken($inschrijving);
        if ($vakken->isEmpty()) {
            return 0.0;
        }

        $resultaten = Resultaat::where('inschrijving_id', $inschrijving->id)
            ->whereIn('toetsonderdeel_id', $vakken->flatMap->toetsonderdelen->pluck('id'))
            ->get();

        $vrijVakIds = \App\Models\Vaktoewijzing::where('inschrijving_id', $inschrijving->id)
            ->where('vrijgesteld', true)->pluck('vak_id')->flip();

        $totaal = 0.0;
        foreach ($vakken as $vak) {
            if (isset($vrijVakIds[$vak->id])) {
                $totaal += (float) $vak->ec; // vrijstelling: volledige EC
                continue;
            }
            $eigen = $resultaten->whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'));
            $totaal += EcBerekening::bepaalEc($vak, $eigen, Cijferberekening::voldoendeGrens($vak)) ?? 0.0;
        }

        return round($totaal, 1);
    }

    /** Totaal haalbare EC in dit leerjaar (som van de vak-EC). */
    public static function mogelijkeEc(Inschrijving $inschrijving): float
    {
        return round((float) self::leerjaarVakken($inschrijving)->sum('ec'), 1);
    }

    /**
     * De vakken die voor DEZE student in dit leerjaar meetellen: alle verplichte
     * vakken, plus de keuzevakken die daadwerkelijk aan zijn inschrijving zijn
     * toegewezen. Zou de hele keuzeruimte meetellen, dan lijkt elk leerjaar
     * onhaalbaar; zouden keuzevakken helemaal wegvallen, dan verdwijnen behaalde
     * punten uit het overgangsadvies.
     */
    private static function leerjaarVakken(Inschrijving $inschrijving)
    {
        $gekozen = \App\Models\Vaktoewijzing::where('inschrijving_id', $inschrijving->id)->pluck('vak_id');

        return Vak::where('opleiding_id', $inschrijving->opleiding_id)
            ->where('leerjaar', $inschrijving->leerjaar)
            ->where('actief', true)
            ->where(fn ($q) => $q->where('keuzevak', false)->orWhereIn('id', $gekozen))
            ->with('toetsonderdelen')
            ->get();
    }
}
