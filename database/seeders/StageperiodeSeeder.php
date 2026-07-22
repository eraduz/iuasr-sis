<?php

namespace Database\Seeders;

use App\Models\Opleiding;
use App\Models\Stageperiode;
use Illuminate\Database\Seeder;

/**
 * Stageperioden per opleiding, zoals vastgesteld met de opdrachtgever (2026-07-22):
 *
 *  Bachelor Islamitische Theologie (ISLTH) — drie stages:
 *    jaar 2: Verkennende stage  (140 u)
 *    jaar 3: Stage 1            (280 u)
 *    jaar 4: Grote Stage 2      (560 u)
 *
 *  Master Islamitische Geestelijke Verzorging (MGV) — twee stages:
 *    Snuffelstage  (40 u)
 *    Grote stage   (480 u)
 *
 * PABO volgt zodra de opdrachtgever die gegevens aanlevert; Beheer kan perioden
 * ook zelf toevoegen via Opzoektabellen → Stageperioden.
 */
class StageperiodeSeeder extends Seeder
{
    public function run(): void
    {
        $perioden = [
            'ISLTH' => [
                ['naam' => 'Verkennende stage', 'code' => 'STAGE-VERK', 'leerjaar' => 2, 'verplichte_uren' => 140, 'volgorde' => 1],
                ['naam' => 'Stage 1', 'code' => 'STAGE-1', 'leerjaar' => 3, 'verplichte_uren' => 280, 'volgorde' => 2],
                ['naam' => 'Grote Stage 2', 'code' => 'STAGE-2', 'leerjaar' => 4, 'verplichte_uren' => 560, 'volgorde' => 3],
            ],
            'MGV' => [
                ['naam' => 'Snuffelstage', 'code' => 'STAGE-SNUF', 'leerjaar' => null, 'verplichte_uren' => 40, 'volgorde' => 1],
                ['naam' => 'Grote stage', 'code' => 'STAGE-GROOT', 'leerjaar' => null, 'verplichte_uren' => 480, 'volgorde' => 2],
            ],
        ];

        foreach ($perioden as $code => $rijen) {
            $opleidingId = Opleiding::where('code', $code)->value('id');
            if ($opleidingId === null) {
                continue;
            }

            foreach ($rijen as $rij) {
                Stageperiode::updateOrCreate(
                    ['opleiding_id' => $opleidingId, 'naam' => $rij['naam']],
                    $rij + ['actief' => true],
                );
            }
        }
    }
}
