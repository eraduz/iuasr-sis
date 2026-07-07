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
        $bedrag = 4000.00; // jaarlijks collegegeld

        // Standaardtarief voor het studiejaar (alle opleidingen).
        CollegegeldTarief::create([
            'periode_id' => $periode->id,
            'opleiding_id' => null,
            'bedrag' => $bedrag,
            'aantal_termijnen' => 5,
            'ingesteld_door_id' => $sz?->id,
        ]);

        // De meeste actieve studenten hebben t/m de huidige maand betaald (~11/12
        // van het jaar). Enkele afwijkingen tonen achterstand of terugbetaling.
        $bijgewerkt = round($bedrag / 12 * 11, 2); // voldaan t/m de huidige maand
        $afwijkend = [
            '261001' => 4000.00, // volledig vooruitbetaald -> terugbetaling
            '261011' => 2000.00, // deels betaald -> achterstand
            '261012' => 0.00,    // niets betaald -> achterstand
            '261004' => 2000.00, // uitgeschreven (4 mnd), teveel betaald -> terugbetaling
            '261010' => 0.00,    // aangemeld -> nog niets verschuldigd/betaald
        ];

        foreach (Inschrijving::with('student')->where('periode_id', $periode->id)->get() as $insch) {
            $nr = $insch->student->studentnummer;
            $betaald = $afwijkend[$nr] ?? $bijgewerkt;
            if ($betaald <= 0) {
                continue;
            }

            Betaling::create([
                'inschrijving_id' => $insch->id,
                'student_id' => $insch->student_id,
                'bedrag' => $betaald,
                'datum' => '2025-09-15',
                'betaalwijze' => 'overboeking',
                'geregistreerd_door_id' => $financien?->id,
            ]);
        }
    }
}
