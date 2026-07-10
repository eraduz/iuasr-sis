<?php

namespace Database\Seeders;

use App\Models\Inschrijving;
use App\Support\Vaktoewijzer;
use Illuminate\Database\Seeder;

/**
 * Wijst de vakken van het studiejaar automatisch toe aan alle bestaande
 * (synthetische) inschrijvingen — zoals dat ook bij een echte inschrijving gebeurt.
 */
class VaktoewijzingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Inschrijving::all() as $inschrijving) {
            Vaktoewijzer::wijsToe($inschrijving);
        }

        $this->demoVrijstelling();
    }

    /** Synthetisch voorbeeld: één vastgelegde vrijstelling (idempotent). */
    private function demoVrijstelling(): void
    {
        $student = \App\Models\Student::where('studentnummer', '261001')->first();
        $sz = \App\Models\User::where('rol', \App\Enums\Rol::Studentenzaken)->first();
        $insch = $student?->inschrijvingen()->orderByDesc('inschrijfdatum')->first();
        $vt = $insch?->vaktoewijzingen()->where('vrijgesteld', false)->with('vak')->first();

        if ($vt && $vt->vak && $sz) {
            $vt->update([
                'vrijgesteld' => true,
                'vrijstelling_grondslag' => \App\Enums\VrijstellingGrondslag::EerderBehaald,
                'vrijstelling_besluit' => 'EC-2026-014',
                'vrijstelling_besluit_datum' => '2026-09-15',
                'vrijstelling_toelichting' => 'Vrijstelling o.b.v. eerder behaald vergelijkbaar vak (synthetisch voorbeeld).',
                'vrijstelling_ec' => (float) $vt->vak->ec,
                'vrijgesteld_door_id' => $sz->id,
                'vrijgesteld_op' => now(),
            ]);
        }
    }
}
