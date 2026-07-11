<?php

use App\Models\Organisatie;
use Database\Seeders\OrganisatieSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vult de draaiende database met de Fase F-startdata (een demo-overeenkomst) via
 * de idempotente OrganisatieSeeder. Guarded: op een verse migratie (tests) doet
 * dit niets — dan verzorgt de OrganisatieSeeder de data.
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
        // Bewust geen verwijdering: overeenkomsten/documenten en historie blijven bestaan.
    }
};
