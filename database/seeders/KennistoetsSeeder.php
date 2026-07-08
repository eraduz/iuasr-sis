<?php

namespace Database\Seeders;

use App\Models\Kennistoets;
use App\Models\Kennistoetsresultaat;
use App\Models\Opleiding;
use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * Landelijke kennistoetsen voor de PABO: de RWT (Reken- en Wiskundetoets, sinds
 * 2024 opvolger van WISCAT) en de Landelijke Kennisbasistoetsen (LKT) taal en
 * rekenen. Idempotent. Plus één synthetisch voorbeeldresultaat.
 */
class KennistoetsSeeder extends Seeder
{
    public function run(): void
    {
        $pabo = Opleiding::where('code', 'PABO')->first();
        if (! $pabo) {
            return;
        }

        $toetsen = [
            ['code' => 'RWT', 'naam' => 'RWT — Landelijke Reken- en Wiskundetoets', 'volgorde' => 1],
            ['code' => 'LKT-TAAL', 'naam' => 'LKT — Kennisbasistoets Nederlandse taal', 'volgorde' => 2],
            ['code' => 'LKT-REK', 'naam' => 'LKT — Kennisbasistoets rekenen-wiskunde', 'volgorde' => 3],
        ];
        foreach ($toetsen as $t) {
            Kennistoets::updateOrCreate(
                ['opleiding_id' => $pabo->id, 'code' => $t['code']],
                ['naam' => $t['naam'], 'volgorde' => $t['volgorde'], 'actief' => true],
            );
        }

        // Demo: PABO jaar-2 student heeft de RWT al behaald (synthetisch).
        $demo = Student::where('studentnummer', '261005')->first();
        $rwt = Kennistoets::where('opleiding_id', $pabo->id)->where('code', 'RWT')->first();
        if ($demo && $rwt) {
            Kennistoetsresultaat::updateOrCreate(
                ['student_id' => $demo->id, 'kennistoets_id' => $rwt->id],
                ['behaald_op' => '2025-11-15'],
            );
        }
    }
}
