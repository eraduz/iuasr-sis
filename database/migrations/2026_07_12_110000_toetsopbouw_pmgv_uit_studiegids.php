<?php

use Database\Seeders\ToetsonderdeelSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Past de toetsopbouw uit `toetsonderdelen.csv` opnieuw toe op de DRAAIENDE
 * database, nu de bron ook de PMGV-vakken (Pre-Master GV) bevat. PMGV volgt per
 * vakcode dezelfde toetslogica als de gelijknamige ISLTH-vakken (keuze
 * opdrachtgever 2026-07-12). Idempotent en dataveilig: vakken met bestaande
 * resultaten worden overgeslagen; no-op op een verse migratie.
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
