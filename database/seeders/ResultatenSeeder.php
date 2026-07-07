<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Periode;
use App\Models\Resultaat;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use Illuminate\Database\Seeder;

/**
 * Synthetische cijfers voor vak ARA-101 (Arabische grammatica I), zodat het
 * cijferoverzicht en het cijfer-tabblad direct gevuld zijn. Ingevoerd namens
 * de docent. Cesuur 5,5.
 */
class ResultatenSeeder extends Seeder
{
    public function run(): void
    {
        $vak = Vak::where('code', 'ISLTH-ARA-101')->with('toetsonderdelen')->first();
        if (! $vak) {
            return;
        }

        $docent = User::where('rol', Rol::Docent)->first();
        $periode = Periode::where('actief', true)->first();
        $onderdelen = $vak->toetsonderdelen->values(); // SCH, MON, TEN (op volgorde)

        // studentnummer => [schriftelijk, mondeling, tentamen]
        $data = [
            '261001' => [7.5, 8.0, 7.0],
            '261011' => [6.0, 7.0, 6.5],
            '261012' => [4.5, 5.0, 5.5], // onvoldoende deeltoets -> geen EC
            '261013' => [8.5, 9.0, 8.0],
            '261014' => [5.5, 6.0, 5.0], // onvoldoende tentamen -> geen EC
        ];

        foreach ($data as $nr => $cijfers) {
            $student = Student::where('studentnummer', $nr)->first();
            if (! $student) {
                continue;
            }
            $insch = Inschrijving::where('student_id', $student->id)
                ->where('opleiding_id', $vak->opleiding_id)
                ->where('periode_id', $periode?->id)
                ->first();
            if (! $insch) {
                continue;
            }

            foreach ($onderdelen as $idx => $od) {
                $cijfer = $cijfers[$idx];
                Resultaat::create([
                    'inschrijving_id' => $insch->id,
                    'student_id' => $student->id,
                    'toetsonderdeel_id' => $od->id,
                    'poging' => 'tentamen',
                    'poging_nr' => 1,
                    'vrijstelling' => false,
                    'cijfer' => $cijfer,
                    'voldoende' => $cijfer >= 5.5,
                    'toetsdatum' => '2026-11-05',
                    'ingevoerd_door_id' => $docent?->id,
                ]);
            }
        }
    }
}
