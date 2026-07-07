<?php

namespace App\Support;

use App\Models\Resultaat;
use App\Models\Vak;
use Illuminate\Support\Collection;

/**
 * Berekent het gewogen eindcijfer van een vak uit de deelresultaten en werkt
 * samen met EcBerekening voor de EC-toekenning. Cesuur (voldoende-grens) komt
 * van de opleiding, met terugval naar config('sis.cijfers.voldoende_grens_terugval').
 */
class Cijferberekening
{
    /** Cesuur voor een vak (opleiding-specifiek, met terugval op config). */
    public static function voldoendeGrens(Vak $vak): ?float
    {
        $grens = $vak->opleiding?->voldoende_grens;

        return $grens !== null
            ? (float) $grens
            : config('sis.cijfers.voldoende_grens_terugval');
    }

    /**
     * Eindcijfer-status van een vak voor één student.
     *
     * @param  Collection<int, Resultaat>  $resultaten  resultaten van de student voor dit vak
     * @return array{status: 'vr'|'onvolledig'|'leeg'|'cijfer', cijfer: float|null}
     */
    public static function eindcijfer(Vak $vak, Collection $resultaten): array
    {
        $onderdelen = $vak->toetsonderdelen;
        if ($onderdelen->isEmpty()) {
            return ['status' => 'leeg', 'cijfer' => null];
        }

        $beste = [];
        foreach ($onderdelen as $od) {
            $beste[$od->id] = self::beste($resultaten, $od->id);
        }

        // Vrijstelling geldt voor het hele vak (VR) — geen numeriek eindcijfer.
        foreach ($beste as $r) {
            if ($r && $r->vrijstelling) {
                return ['status' => 'vr', 'cijfer' => null];
            }
        }

        // Helemaal niets ingevoerd?
        if (collect($beste)->filter()->isEmpty()) {
            return ['status' => 'leeg', 'cijfer' => null];
        }

        $som = 0.0;
        $gewicht = 0.0;
        foreach ($onderdelen as $od) {
            $r = $beste[$od->id];
            if ($r === null || $r->cijfer === null) {
                return ['status' => 'onvolledig', 'cijfer' => null];
            }
            $som += (float) $r->cijfer * (float) $od->weging;
            $gewicht += (float) $od->weging;
        }

        if ($gewicht <= 0) {
            return ['status' => 'onvolledig', 'cijfer' => null];
        }

        return ['status' => 'cijfer', 'cijfer' => round($som / $gewicht, 1)];
    }

    /** EC toegekend voor een student/vak (0 of vak.ec; null als grens ontbreekt). */
    public static function ec(Vak $vak, Collection $resultaten): ?int
    {
        return EcBerekening::bepaalEc($vak, $resultaten, self::voldoendeGrens($vak));
    }

    /** Beste geldige resultaat voor een toetsonderdeel (hoogste cijfer; vrijstelling wint). */
    public static function beste(Collection $resultaten, int $toetsonderdeelId): ?Resultaat
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
