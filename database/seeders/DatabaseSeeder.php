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
            VakDocentSeeder::class,
            GebruikerSeeder::class,
            DocentLoginSeeder::class,
            SynthetischeStudentSeeder::class,
            ExtraStudentenSeeder::class,
            VaktoewijzingSeeder::class,
            ResultatenSeeder::class,
            PresentieSeeder::class,
            CollegegeldSeeder::class,
            KennistoetsSeeder::class,
            StageperiodeSeeder::class,
            OrganisatieSeeder::class,
            NieuwsbronSeeder::class,
            HrSeeder::class,
            HrDocentenSeeder::class,
            // Module Balie/Receptie — draait ná de HR-seeders, want de registraties
            // verwijzen naar medewerkers.
            BalieSeeder::class,
            // Module Bibliotheek — draait ná de studenten- en HR-seeders, want de
            // uitleningen verwijzen naar studenten en medewerkers.
            BibliotheekSeeder::class,
            // Module Scriptie Coördinatie — draait ná de studenten-/curriculum-/
            // resultatenseeders, want de toelatingscontrole leest EC en resultaten.
            ScriptieSeeder::class,
            // Module Stichtingsbestuur — onafhankelijk; synthetische bestuursleden.
            StichtingsbestuurSeeder::class,
            // Zijbalk-quotes: de 99 Schone Namen. Onafhankelijk en idempotent op
            // (soort, titel), dus veilig om opnieuw te draaien op een gevulde
            // database — eigen spreuken en geüploade afbeeldingen blijven staan.
            QuoteSeeder::class,
            // Echte personeelslijst (lokaal, gitignored; no-op als het bestand ontbreekt).
            PersoneelSeeder::class,
        ]);
    }
}
