<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * De module HR / Personeelszaken is nu gebouwd (Fase A): zet haar op actief en
 * werk de omschrijving bij, zodat zij op het keuzescherm klikbaar wordt voor de
 * rollen die er toegang toe hebben (HR-medewerker, Manager, Bestuur, Beheer).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')->where('sleutel', 'hr')->update([
            'naam' => 'HR / Personeelszaken',
            'omschrijving' => 'Medewerkers, dienstverband, verlof & verzuim, gesprekken en rapportages.',
            'icoon' => 'users',
            'actief' => true,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('sleutel', 'hr')->update(['actief' => false]);
    }
};
