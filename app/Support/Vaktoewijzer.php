<?php

namespace App\Support;

use App\Models\Inschrijving;
use App\Models\Vak;
use App\Models\Vaktoewijzing;

/**
 * Wijst automatisch de VERPLICHTE vakken van het betreffende studiejaar
 * (leerjaar) toe aan een inschrijving. Wordt aangeroepen bij eerste inschrijving
 * én bij herinschrijving. Idempotent: dubbele toewijzing wordt voorkomen.
 *
 * Vakken uit de keuzeruimte (`keuzevak`) worden NIET automatisch toegewezen —
 * de student kiest daaruit en Studentenzaken legt die keuze vast via
 * 'Vakken aanpassen'.
 */
class Vaktoewijzer
{
    /** @return int aantal nieuw toegewezen vakken */
    public static function wijsToe(Inschrijving $inschrijving): int
    {
        $vakIds = Vak::query()
            ->where('opleiding_id', $inschrijving->opleiding_id)
            ->where('actief', true)
            ->where('keuzevak', false)
            ->when($inschrijving->leerjaar, fn ($q) => $q->where('leerjaar', $inschrijving->leerjaar))
            ->pluck('id');

        $nieuw = 0;
        foreach ($vakIds as $vakId) {
            $toewijzing = Vaktoewijzing::firstOrCreate(
                ['inschrijving_id' => $inschrijving->id, 'vak_id' => $vakId],
                ['automatisch' => true],
            );
            if ($toewijzing->wasRecentlyCreated) {
                $nieuw++;
            }
        }

        return $nieuw;
    }
}
