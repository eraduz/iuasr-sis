<?php

use App\Models\Medewerker;
use Database\Seeders\HrSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vult de draaiende database met de Fase C-startdata (HR-gesprekken met doelen en
 * competenties) via de idempotente HrSeeder. Guarded: op een verse migratie
 * (tests) doet dit niets.
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
        // Bewust geen verwijdering: gesprekken/historie blijven bestaan.
    }
};
