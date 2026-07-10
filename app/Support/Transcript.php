<?php

namespace App\Support;

use App\Models\Resultaat;
use App\Models\Student;
use App\Models\Vak;

/**
 * Bouwt het cijferoverzicht (transcript / cijferlijst) van een student op:
 * per studiejaar de vakken met eindcijfer, EC en status. Wordt gebruikt voor
 * zowel het scherm als de officiële (ondertekende) PDF-cijferlijst.
 *
 * Cijferinzage is voorbehouden aan Docent (eigen vak), Examencommissie en
 * Directie — nooit Studentenzaken. Autorisatie ligt bij de route/controller.
 */
class Transcript
{
    /**
     * @param  bool  $alleenDefinitief  alleen door de examencommissie vastgestelde resultaten meetellen
     * @return array{studiejaren: array<int, array>, behaaldeEc: int, ecTotaal: int|null}
     */
    public static function voor(Student $student, bool $alleenDefinitief = false): array
    {
        $student->loadMissing(['inschrijvingen.opleiding', 'inschrijvingen.periode']);

        $studiejaren = $student->inschrijvingen
            ->sortBy(fn ($i) => $i->inschrijfdatum)
            ->map(function ($insch) use ($alleenDefinitief) {
                $vakken = Vak::where('opleiding_id', $insch->opleiding_id)
                    ->where('leerjaar', $insch->leerjaar)
                    ->where('actief', true)
                    ->with('toetsonderdelen')
                    ->orderBy('blok')->orderBy('code')
                    ->get();

                $resultaten = Resultaat::where('inschrijving_id', $insch->id)
                    ->when($alleenDefinitief, fn ($q) => $q->where('definitief', true))
                    ->get();

                $vrijVakIds = \App\Models\Vaktoewijzing::where('inschrijving_id', $insch->id)
                    ->where('vrijgesteld', true)->pluck('vak_id')->flip();

                $regels = $vakken->map(function ($vak) use ($resultaten, $vrijVakIds) {
                    $eigen = $resultaten->whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'));
                    $vrij = isset($vrijVakIds[$vak->id]);

                    return [
                        'vak' => $vak,
                        'eind' => Cijferberekening::eindcijfer($vak, $eigen, $vrij),
                        'ec' => Cijferberekening::ec($vak, $eigen, $vrij),
                    ];
                })->values();

                return [
                    'inschrijving' => $insch,
                    'regels' => $regels,
                    'behaaldeEc' => round((float) $regels->sum(fn ($r) => $r['ec'] ?? 0), 1),
                    'mogelijkeEc' => round((float) $vakken->sum('ec'), 1),
                ];
            })->values();

        return [
            'studiejaren' => $studiejaren->all(),
            'behaaldeEc' => (int) $studiejaren->sum('behaaldeEc'),
            'ecTotaal' => $student->inschrijvingen->first()?->opleiding?->ec_totaal,
        ];
    }
}
