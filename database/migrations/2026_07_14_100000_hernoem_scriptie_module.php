<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * De module heet voortaan "Scriptie Coördinatie" in plaats van "Scriptie
 * Administratie" (opdrachtgever 2026-07-14): de rol coördineert de begeleiding
 * en beoordeling, hij administreert niet alleen.
 *
 * Alleen de zichtbare naam verandert; de sleutel `scriptie` blijft ongewijzigd,
 * zodat routes, rolrechten en verwijzingen intact blijven.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')->where('sleutel', 'scriptie')->update([
            'naam' => 'Scriptie Coördinatie',
            'omschrijving' => 'Scriptiebegeleiding, -coördinatie en -beoordeling.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('modules')->where('sleutel', 'scriptie')->update([
            'naam' => 'Scriptie Administratie',
            'omschrijving' => 'Scriptiebegeleiding en -beoordeling.',
            'updated_at' => now(),
        ]);
    }
};
