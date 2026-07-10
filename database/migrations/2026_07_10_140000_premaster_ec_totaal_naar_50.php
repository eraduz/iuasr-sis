<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * De Pre-Master Islamitische Geestelijke Verzorging telt 50 EC, niet 60.
 * Bevestigd door de opdrachtgever (2026-07-10) en in overeenstemming met het
 * curriculum: 12 vakken die samen precies 50 EC opleveren.
 *
 * `ec_totaal` stuurt de noemer op de cijferlijst (transcript) en de
 * voortgangsbalk in het EC-rapport; met 60 leek een afgeronde pre-master
 * onvoltooid (50/60).
 *
 * Alleen bijwerken wanneer de waarde nog op de oude 60 staat, zodat een
 * handmatige correctie via Opzoektabellen niet wordt overschreven.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('opleidingen')
            ->where('code', 'PMGV')
            ->where('ec_totaal', 60)
            ->update(['ec_totaal' => 50]);
    }

    public function down(): void
    {
        DB::table('opleidingen')
            ->where('code', 'PMGV')
            ->where('ec_totaal', 50)
            ->update(['ec_totaal' => 60]);
    }
};
