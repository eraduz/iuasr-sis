<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Betaling;
use App\Models\CollegegeldTarief;
use App\Models\Inschrijving;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Synthetisch collegegeldtarief + betalingen. De meeste studenten hebben
 * betaald; enkele hebben een achterstand, zodat de signalering en blokkades
 * zichtbaar zijn.
 */
class CollegegeldSeeder extends Seeder
{
    public function run(): void
    {
        $periode = Periode::where('actief', true)->first();
        if (! $periode) {
            return;
        }

        $sz = User::where('rol', Rol::Studentenzaken)->first();
        $financien = User::where('rol', Rol::Financien)->first();
        $bedrag = 2530.00;

        // Standaardtarief voor het studiejaar (alle opleidingen).
        CollegegeldTarief::create([
            'periode_id' => $periode->id,
            'opleiding_id' => null,
            'bedrag' => $bedrag,
            'aantal_termijnen' => 5,
            'ingesteld_door_id' => $sz?->id,
        ]);

        // Afwijkende bedragen per student (rest betaalt volledig).
        $afwijkend = [
            '261011' => 1500.00, // deels betaald -> achterstand
            '261012' => 0.00,    // niet betaald  -> achterstand
            '261004' => 0.00,    // uitgeschreven met openstaande schuld
        ];

        foreach (Inschrijving::with('student')->where('periode_id', $periode->id)->get() as $insch) {
            $nr = $insch->student->studentnummer;
            $betaald = $afwijkend[$nr] ?? $bedrag;
            if ($betaald <= 0) {
                continue;
            }

            Betaling::create([
                'inschrijving_id' => $insch->id,
                'student_id' => $insch->student_id,
                'bedrag' => $betaald,
                'datum' => '2026-09-15',
                'betaalwijze' => 'overboeking',
                'geregistreerd_door_id' => $financien?->id,
            ]);
        }
    }
}
