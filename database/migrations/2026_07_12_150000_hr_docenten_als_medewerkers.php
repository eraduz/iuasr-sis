<?php

use App\Models\Docent;
use Database\Seeders\HrDocentenSeeder;
use Database\Seeders\HrSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * HR-simulatie op de DRAAIENDE database: de docenten worden medewerkers. Draait
 * eerst HrSeeder (idempotent — afdelingen/functies/HR-accounts) en daarna
 * HrDocentenSeeder (per docent een personeelsdossier + dienstverband + verlof/
 * verzuim/gesprekken). No-op op een verse migratie (nog geen docenten); de
 * seeders leveren dan al de juiste stand via DatabaseSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('docenten') || Docent::doesntExist()) {
            return; // verse migratie: DatabaseSeeder regelt dit
        }

        (new HrSeeder)->run();
        (new HrDocentenSeeder)->run();
    }

    public function down(): void
    {
        // Synthetische testdata: niet automatisch terugdraaien.
    }
};
