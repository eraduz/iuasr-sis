<?php

use App\Models\Medewerker;
use Database\Seeders\HrSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vult de draaiende database met de Fase B-startdata (verlofrecht, enkele
 * aanvragen en een ziekmelding) via de idempotente HrSeeder. Guarded: op een
 * verse migratie (tests) bestaan er nog geen medewerkers — dan doet dit niets.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Medewerker::query()->exists()) {
            return;
        }

        (new HrSeeder())->run();
    }

    public function down(): void
    {
        // Bewust geen verwijdering: verlof/verzuim en historie blijven bestaan.
    }
};
