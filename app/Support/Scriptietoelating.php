<?php

namespace App\Support;

use App\Models\Inschrijving;
use App\Models\Student;
use App\Models\Vak;
use App\Models\Vaktoewijzing;

/**
 * Toelatingscontrole voor de scriptie (stap 1 van het traject). Een student mag
 * beginnen als hij (a) minimaal de EC-norm heeft behaald (config, standaard 180 EC)
 * en (b) Methoden en Technieken I én II heeft afgerond. De M&T-vakken en het
 * scriptievak worden per opleiding op VAKCODE herkend (de naam wijkt in de bron af).
 *
 * Alleen opleidingen met een scriptie zijn geconfigureerd (config sis.scriptie.
 * toelating_vakken). Voor overige opleidingen geldt 'ondersteund = false'.
 */
class Scriptietoelating
{
    /**
     * De opleidingcodes met een scriptietraject.
     *
     * @return array<int, string>
     */
    public static function ondersteundeOpleidingcodes(): array
    {
        return array_keys((array) config('sis.scriptie.toelating_vakken', []));
    }

    /**
     * Beoordeel of de student van deze inschrijving aan de toelatingseisen voldoet.
     *
     * @return array{ondersteund: bool, ec: int, ec_norm: float, ec_voldaan: bool,
     *               mt1: bool|null, mt2: bool|null, voldoet: bool}
     */
    public static function voor(Student $student, Inschrijving $inschrijving): array
    {
        $norm = (float) config('sis.scriptie.toelating_ec', 180);
        $behaaldeEc = Transcript::voor($student)['behaaldeEc'];
        $ecVoldaan = $behaaldeEc >= $norm;

        $code = $inschrijving->opleiding?->code;
        $vakken = config("sis.scriptie.toelating_vakken.$code");

        if (! is_array($vakken)) {
            return [
                'ondersteund' => false,
                'ec' => $behaaldeEc, 'ec_norm' => $norm, 'ec_voldaan' => $ecVoldaan,
                'mt1' => null, 'mt2' => null, 'voldoet' => false,
            ];
        }

        $mt1 = self::vakBehaald($student, (int) $inschrijving->opleiding_id, $vakken['mt1']);
        $mt2 = self::vakBehaald($student, (int) $inschrijving->opleiding_id, $vakken['mt2']);

        return [
            'ondersteund' => true,
            'ec' => $behaaldeEc, 'ec_norm' => $norm, 'ec_voldaan' => $ecVoldaan,
            'mt1' => $mt1, 'mt2' => $mt2,
            'voldoet' => $ecVoldaan && $mt1 && $mt2,
        ];
    }

    /**
     * Heeft de student een geslaagd resultaat voor het vak met deze code binnen deze
     * opleiding? Gebruikt de bestaande cesuur-/EC-logica: EC toegekend (> 0) = behaald
     * (respecteert de per-opleiding cesuur, het EC-model en vrijstellingen).
     */
    public static function vakBehaald(Student $student, int $opleidingId, string $code): bool
    {
        $vak = Vak::where('opleiding_id', $opleidingId)
            ->where('code', $code)
            ->where('actief', true)
            ->with('toetsonderdelen')
            ->first();

        if ($vak === null) {
            return false;
        }

        $student->loadMissing(['resultaten', 'inschrijvingen']);

        $eigen = $student->resultaten
            ->whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'));

        $vrij = Vaktoewijzing::whereIn('inschrijving_id', $student->inschrijvingen->pluck('id'))
            ->where('vak_id', $vak->id)
            ->where('vrijgesteld', true)
            ->exists();

        return (Cijferberekening::ec($vak, $eigen, $vrij) ?? 0) > 0;
    }
}
