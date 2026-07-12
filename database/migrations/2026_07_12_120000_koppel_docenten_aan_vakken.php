<?php

use App\Models\Vak;
use Database\Seeders\DocentSeeder;
use Database\Seeders\VakDocentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Koppelt de docenten aan de vakken op de DRAAIENDE database (ISLTH + MGV, uit de
 * studiegidsen). Draait eerst DocentSeeder (idempotent — voegt o.a. de ontbrekende
 * docent Bouyazdouzen toe), daarna VakDocentSeeder die `vakken.docent_id` zet.
 *
 * Guard: op een VERSE migratie (nog geen vakken) doet dit niets — de seeders
 * vullen alles al, en DocentSeeder mag hier niet draaien omdat ReferentieSeeder
 * later de vaste codes DOC-001/DOC-002 hardcodeert (die zouden anders botsen).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vakken') || Vak::doesntExist()) {
            return; // verse migratie: seeders regelen dit
        }

        (new DocentSeeder)->run();
        (new VakDocentSeeder)->run();
    }

    public function down(): void
    {
        // Koppeling niet terugdraaien: referentiedata, geen schema.
    }
};
