<?php

use App\Models\Afdeling;
use App\Models\Medewerker;
use Database\Seeders\HrSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vult de draaiende database met de Fase D-startdata (het PABO-team onder
 * Onderwijs) via de idempotente HrSeeder, en verplaatst de bestaande teamleden
 * naar dat team zodat de organisatiestructuur klopt. Guarded: op een verse
 * migratie (tests) doet dit niets.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Medewerker::query()->exists()) {
            return;
        }

        (new HrSeeder())->run();

        $team = Afdeling::where('code', 'ONDW-PABO')->first();
        if ($team !== null) {
            Medewerker::whereIn('personeelsnummer', ['P260003', 'P260004'])->update(['afdeling_id' => $team->id]);
        }
    }

    public function down(): void
    {
        // Bewust geen verwijdering.
    }
};
