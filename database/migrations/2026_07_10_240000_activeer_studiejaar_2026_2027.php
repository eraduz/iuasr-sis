<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jaarovergang: activeert op de DRAAIENDE database studiejaar 2026-2027 en
 * deactiveert het afgelopen jaar 2025-2026. Er is altijd precies één actief
 * studiejaar.
 *
 * Guarded: draait alleen wanneer de periode 2026-2027 al bestaat (een reeds
 * geseede database). Op een verse migratie draait deze vóór de seeders — de
 * tabel `perioden` is dan leeg en er gebeurt niets; de ReferentieSeeder legt
 * dan de basislijn (2025-2026 actief) vast, zodat de testfixture en de bestaande
 * tests ongewijzigd blijven. De verdere jaarovergangen stuurt Beheer via
 * Opzoektabellen → Studiejaren (het `Periode::saved`-event deactiveert het vorige).
 *
 * NB: bewuste raw updates — een migratie vuurt geen Eloquent-events, dus we
 * zetten alle jaren expliciet op inactief en daarna het nieuwe jaar op actief.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('perioden')->where('code', '2026-2027')->doesntExist()) {
            return;
        }

        DB::table('perioden')->update(['actief' => false]);
        DB::table('perioden')->where('code', '2026-2027')->update(['actief' => true]);
    }

    public function down(): void
    {
        if (DB::table('perioden')->where('code', '2025-2026')->doesntExist()) {
            return;
        }

        DB::table('perioden')->update(['actief' => false]);
        DB::table('perioden')->where('code', '2025-2026')->update(['actief' => true]);
    }
};
