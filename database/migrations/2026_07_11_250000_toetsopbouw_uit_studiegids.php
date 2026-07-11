<?php

use Database\Seeders\ToetsonderdeelSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Laadt de werkelijke toetsopbouw (toetsonderdelen + weging) uit de studiegids
 * in de DRAAIENDE database, zodat bestaande installaties de echte weging krijgen
 * i.p.v. de standaard "Tentamen 100%". Op een verse migratie (test) bestaan de
 * vakken nog niet en is dit een no-op; de seeder slaat vakken met bestaande
 * resultaten over (geen cijferverlies). Draaien we opnieuw, dan is het resultaat
 * gelijk (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ToetsonderdeelSeeder)->run();
    }

    public function down(): void
    {
        // De toetsopbouw wordt niet teruggedraaid: dit is referentiedata, geen schema.
    }
};
