<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Hoofd-seeder. Uitsluitend SYNTHETISCHE data (AVG) — er wordt nooit
 * productiedata of een echt persoonsgegeven geseed.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferentieSeeder::class,
            GebruikerSeeder::class,
            SynthetischeStudentSeeder::class,
            ResultatenSeeder::class,
        ]);
    }
}
