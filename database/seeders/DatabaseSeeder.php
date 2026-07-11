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
            CurriculumSeeder::class,
            ToetsonderdeelSeeder::class,
            DocentSeeder::class,
            GebruikerSeeder::class,
            SynthetischeStudentSeeder::class,
            VaktoewijzingSeeder::class,
            ResultatenSeeder::class,
            PresentieSeeder::class,
            CollegegeldSeeder::class,
            KennistoetsSeeder::class,
            OrganisatieSeeder::class,
            HrSeeder::class,
        ]);
    }
}
