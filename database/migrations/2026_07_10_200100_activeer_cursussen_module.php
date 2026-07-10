<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * De Cursussen-module is nu gebouwd (Fase B): zet haar op actief, zodat zij op
 * het keuzescherm klikbaar wordt voor de rollen die er toegang toe hebben
 * (Cursusadministratie, Financiële Administratie, Beheer).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')->where('sleutel', 'cursussen')->update(['actief' => true]);
    }

    public function down(): void
    {
        DB::table('modules')->where('sleutel', 'cursussen')->update(['actief' => false]);
    }
};
