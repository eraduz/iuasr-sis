<?php

use App\Models\User;
use Database\Seeders\HrSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vult de draaiende database met de HR-startdata (afdelingen, functies,
 * medewerkers, dienstverbanden en de HR-rolaccounts) via de idempotente HrSeeder.
 *
 * Guarded: op een verse migratie (tests) bestaan er nog geen gebruikers — dan
 * doet dit niets en verzorgt de HrSeeder de data (via DatabaseSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! User::query()->exists()) {
            return;
        }

        (new HrSeeder())->run();
    }

    public function down(): void
    {
        // Bewust geen verwijdering: personeelsdata/historie blijft bestaan.
    }
};
