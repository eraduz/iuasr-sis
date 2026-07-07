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
    }
}
