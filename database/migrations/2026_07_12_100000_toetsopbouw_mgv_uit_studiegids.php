<?php

use Database\Seeders\ToetsonderdeelSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Past de (uitgebreide) toetsopbouw uit `toetsonderdelen.csv` opnieuw toe op de
 * DRAAIENDE database, nu de bron ook de MGV-vakken (Master IGV) bevat. De vorige
 * migratie draaide toen alleen ISLTH in de bron stond; deze her-run vult de
 * MGV-vakken. Idempotent en dataveilig: vakken met bestaande resultaten worden
 * overgeslagen, de rest krijgt exact dezelfde onderdelen (delete + recreate).
 * No-op op een verse migratie (de vakken bestaan dan nog niet).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ToetsonderdeelSeeder)->run();
    }

    public function down(): void
    {
        // Referentiedata, geen schema: niet terugdraaien.
    }
};
