<?php

use App\Models\Docent;
use Database\Seeders\DocentLoginSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Maakt op de DRAAIENDE database inlogaccounts (rol Docent) aan voor de docenten
 * die er nog geen hebben, en koppelt hun bestaande HR-dossier eraan. Zo kan elke
 * docent inloggen (Mijn vakken, cijfers, aanwezigheid, Mijn HR). No-op op een
 * verse migratie (nog geen docenten); de seeders regelen dat via DatabaseSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('docenten') || Docent::doesntExist()) {
            return; // verse migratie: DatabaseSeeder regelt dit
        }

        (new DocentLoginSeeder)->run();
    }

    public function down(): void
    {
        // Accounts niet automatisch verwijderen.
    }
};
