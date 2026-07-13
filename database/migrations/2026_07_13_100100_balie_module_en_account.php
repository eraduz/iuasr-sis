<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Zet de module Balie/Receptie op het keuzescherm en maakt het bijbehorende
 * rolaccount aan, zodat een bestaande database (test op de RDP, Plesk) de module
 * met alleen `php artisan migrate` krijgt — zonder seeders te hoeven draaien.
 *
 * Idempotent: bestaat de modulerij of het account al, dan gebeurt er niets.
 * Er wordt geen wachtwoord gezet; authenticatie verloopt via Entra ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        $nu = now();

        if (! DB::table('modules')->where('sleutel', 'balie')->exists()) {
            DB::table('modules')->insert([
                'sleutel' => 'balie',
                'naam' => 'Balie / Receptie',
                'omschrijving' => 'Telefoongesprekken, bezoekers en in- en uitgaande post aan de ingang.',
                'icoon' => 'phone',
                'actief' => true,
                'volgorde' => 6,
                'created_at' => $nu,
                'updated_at' => $nu,
            ]);
        }

        if (! DB::table('users')->where('email', 'balie@iuasr.nl')->exists()) {
            DB::table('users')->insert([
                'naam' => 'Rania Aydin',
                'email' => 'balie@iuasr.nl',
                'rol' => 'balie',
                'created_at' => $nu,
                'updated_at' => $nu,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'balie@iuasr.nl')->delete();
        DB::table('modules')->where('sleutel', 'balie')->delete();
    }
};
