<?php

use App\Models\Organisatie;
use Database\Seeders\OrganisatieSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vult de draaiende database met de synthetische contactpersonen (Fase B). De
 * eerdere startdata-migratie draait niet opnieuw, dus voegen we ze hier toe via
 * de (idempotente) OrganisatieSeeder.
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
        // Bewust geen verwijdering: relaties/contactpersonen en historie blijven bestaan.
    }
};
