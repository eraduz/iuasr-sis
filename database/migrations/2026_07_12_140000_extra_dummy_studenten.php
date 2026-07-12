<?php

use App\Models\Student;
use Database\Seeders\ExtraStudentenSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Voegt de ruimere synthetische studentenpopulatie (alle leerjaren) toe aan de
 * DRAAIENDE database, zodat er met collega's getest kan worden. De seeder is
 * idempotent (bestaande studentnummers worden overgeslagen). No-op op een verse
 * migratie: dan bestaan de opleidingen/periode nog niet en levert de seeder niets.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('studenten') || Student::doesntExist()) {
            return; // verse migratie: DatabaseSeeder regelt dit
        }

        (new ExtraStudentenSeeder)->run();
    }

    public function down(): void
    {
        // Synthetische testdata: niet automatisch terugdraaien.
    }
};
