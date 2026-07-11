<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * De module Relatiebeheer & Stagebeheer is nu gebouwd (Fase A): zet haar op
 * actief, zodat zij op het keuzescherm klikbaar wordt voor de rollen die er
 * toegang toe hebben (Relatiebeheerder, Stagecoördinator, Directie, Bestuur,
 * Beheer).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')->where('sleutel', 'relatiebeheer')->update(['actief' => true]);
    }

    public function down(): void
    {
        DB::table('modules')->where('sleutel', 'relatiebeheer')->update(['actief' => false]);
    }
};
