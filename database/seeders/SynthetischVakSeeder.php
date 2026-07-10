<?php

namespace Database\Seeders;

use App\Models\Docent;
use App\Models\Opleiding;
use App\Models\Toetsonderdeel;
use App\Models\Vak;
use Illuminate\Database\Seeder;

/**
 * Synthetische voorbeeldvakken (codes ISLTH-*) met een rijkere toetsopbouw
 * (deelresultaten + weging). Uitsluitend bedoeld als TESTFIXTURE voor de
 * geautomatiseerde tests en voor demonstraties.
 *
 * Deze seeder hoort NIET in DatabaseSeeder: het echte curriculum staat in
 * {@see CurriculumSeeder}. Zouden deze nepvakken naast het echte curriculum
 * actief staan, dan tellen zij mee in de EC-totalen per leerjaar en worden zij
 * automatisch aan elke ISLTH-student toegewezen.
 */
class SynthetischVakSeeder extends Seeder
{
    public function run(): void
    {
        $theologie = Opleiding::where('code', 'ISLTH')->first();
        $aydin = Docent::where('code', 'DOC-001')->first();
        $boujat = Docent::where('code', 'DOC-002')->first();

        if (! $theologie) {
            return;
        }

        // Voorbeeldvak met genormaliseerde toetsstructuur (deelresultaten + weging).
        $vak = Vak::create([
            'opleiding_id' => $theologie->id, 'docent_id' => $aydin?->id,
            'code' => 'ISLTH-ARA-201', 'naam' => 'Arabische grammatica II',
            'ec' => 6, 'leerjaar' => 2, 'blok' => 1, 'actief' => true,
        ]);
        Toetsonderdeel::create(['vak_id' => $vak->id, 'code' => 'TEN', 'naam' => 'Schriftelijk tentamen', 'type' => 'tentamen', 'weging' => 0.60, 'telt_mee' => true, 'volgorde' => 1]);
        Toetsonderdeel::create(['vak_id' => $vak->id, 'code' => 'WST', 'naam' => 'Werkstuk', 'type' => 'werkstuk', 'weging' => 0.25, 'telt_mee' => true, 'volgorde' => 2]);
        Toetsonderdeel::create(['vak_id' => $vak->id, 'code' => 'PRE', 'naam' => 'Presentatie', 'type' => 'presentatie', 'weging' => 0.15, 'telt_mee' => true, 'volgorde' => 3]);

        // Leerjaar-1 vak (waar de meeste synthetische studenten zitten), met de
        // toetsopbouw uit het design: schriftelijk 40% / mondeling 25% / tentamen 35%.
        $vak1 = Vak::create([
            'opleiding_id' => $theologie->id, 'docent_id' => $aydin?->id,
            'code' => 'ISLTH-ARA-101', 'naam' => 'Arabische grammatica I',
            'ec' => 6, 'leerjaar' => 1, 'blok' => 1, 'actief' => true,
        ]);
        Toetsonderdeel::create(['vak_id' => $vak1->id, 'code' => 'SCH', 'naam' => 'Deeltoets schriftelijk', 'type' => 'tentamen', 'weging' => 0.40, 'telt_mee' => true, 'volgorde' => 1]);
        Toetsonderdeel::create(['vak_id' => $vak1->id, 'code' => 'MON', 'naam' => 'Mondeling (recitatie)', 'type' => 'mondeling', 'weging' => 0.25, 'telt_mee' => true, 'volgorde' => 2]);
        Toetsonderdeel::create(['vak_id' => $vak1->id, 'code' => 'TEN', 'naam' => 'Eindtentamen', 'type' => 'tentamen', 'weging' => 0.35, 'telt_mee' => true, 'volgorde' => 3]);

        $overig = [
            // [code, naam, ec, leerjaar, blok, docent]
            ['ISLTH-KRN-110', 'Inleiding Koranwetenschappen', 6, 1, 1, $boujat],
            ['ISLTH-FIQ-110', 'Islamitisch recht I', 6, 1, 2, $boujat],
            ['ISLTH-HIS-120', 'Geschiedenis van de Islam', 5, 1, 2, $aydin],
            ['ISLTH-ARA-102', 'Arabische grammatica I-b', 6, 1, 3, $aydin],
            ['ISLTH-SIR-140', 'Sīra (biografie van de Profeet)', 5, 1, 4, $boujat],
            ['ISLTH-FIQ-210', 'Usul al-Fiqh', 6, 2, 2, $aydin],
            ['ISLTH-TAF-220', 'Tafsīr I', 6, 2, 3, $boujat],
        ];
        foreach ($overig as [$code, $naam, $ec, $leerjaar, $blok, $docent]) {
            $v = Vak::create([
                'opleiding_id' => $theologie->id, 'docent_id' => $docent?->id,
                'code' => $code, 'naam' => $naam, 'ec' => $ec,
                'leerjaar' => $leerjaar, 'blok' => $blok, 'actief' => true,
            ]);
            // Standaard toetsopbouw zodat het vak direct beoordeelbaar is.
            Toetsonderdeel::create(['vak_id' => $v->id, 'code' => 'TEN', 'naam' => 'Tentamen', 'type' => 'tentamen', 'weging' => 1.00, 'telt_mee' => true, 'volgorde' => 1]);
        }
    }
}
