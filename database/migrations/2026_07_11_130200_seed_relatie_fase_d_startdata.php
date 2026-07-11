<?php

use App\Models\Organisatie;
use Database\Seeders\OrganisatieSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vult de draaiende database met de Fase D-startdata (stageplaatsen en een
 * demo-stage) via de idempotente OrganisatieSeeder.
 *
 * Guarded: op een VERSE migratie (tests) bestaan er nog geen organisaties — dan
 * doet deze migratie niets en verzorgt de OrganisatieSeeder de data.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Organisatie::query()->exists()) {
            return;
        }

        (new OrganisatieSeeder())->run();
    }

    public function down(): void
    {
        // Bewust geen verwijdering: stages en historie blijven bestaan.
    }
};
