<?php

use App\Models\Opleiding;
use Database\Seeders\NieuwsbronSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Zet de nieuwsbronnen klaar op de DRAAIENDE database (geen internet-fetch hier;
 * dat doet `nieuws:ophalen` via de scheduler). No-op op een verse migratie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opleidingen') || Opleiding::doesntExist()) {
            return; // verse migratie: DatabaseSeeder regelt dit
        }

        (new NieuwsbronSeeder)->run();
    }

    public function down(): void
    {
        // Referentiedata: niet terugdraaien.
    }
};
