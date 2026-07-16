<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Boete van EUR 10,00 per boek NOEMEN in de te-laat-mail voor studenten
 * (opdrachtgever 2026-07-15). Het bedrag zelf staat in config
 * (`sis.bibliotheek.boete_per_boek`) en wordt via de nieuwe sjabloonvariabele
 * {{Boete}} ingevuld; het systeem int of administreert de boete NIET.
 *
 * GUARDED / dataveilig:
 *  - Op een VERSE database heeft de create-migratie de nieuwe tekst (met
 *    {{Boete}}) al ingevoegd → deze migratie vindt {{Boete}} en doet niets.
 *  - Heeft de Beheerder het sjabloon zelf aangepast (de ankerzin ontbreekt of
 *    er staat al 'boete' in), dan blijft die aanpassing ONGEMOEID.
 */
return new class extends Migration
{
    private const ANKER = 'Lever de publicatie zo spoedig mogelijk in.';

    private const BOETEZIN = 'Voor een te laat ingeleverd boek brengt de bibliotheek een boete van {{Boete}} per boek in rekening. ';

    public function up(): void
    {
        if (! Schema::hasTable('bibliotheek_emailsjablonen')) {
            return;
        }

        $sjabloon = DB::table('bibliotheek_emailsjablonen')
            ->where('soort', 'te_laat_student')
            ->first();

        if ($sjabloon === null) {
            return;
        }

        // Al een boetevermelding? (verse DB via de create-migratie, of een eigen
        // aanpassing van de Beheerder) → niets doen.
        if (str_contains($sjabloon->inhoud, '{{Boete}}') || stripos($sjabloon->inhoud, 'boete') !== false) {
            return;
        }

        // Alleen bijwerken als de standaard-ankerzin er nog in staat.
        if (! str_contains($sjabloon->inhoud, self::ANKER)) {
            return;
        }

        DB::table('bibliotheek_emailsjablonen')
            ->where('id', $sjabloon->id)
            ->update([
                'inhoud' => str_replace(self::ANKER, self::BOETEZIN.self::ANKER, $sjabloon->inhoud),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('bibliotheek_emailsjablonen')) {
            return;
        }

        $sjabloon = DB::table('bibliotheek_emailsjablonen')
            ->where('soort', 'te_laat_student')
            ->first();

        if ($sjabloon === null || ! str_contains($sjabloon->inhoud, self::BOETEZIN)) {
            return;
        }

        DB::table('bibliotheek_emailsjablonen')
            ->where('id', $sjabloon->id)
            ->update([
                'inhoud' => str_replace(self::BOETEZIN, '', $sjabloon->inhoud),
                'updated_at' => now(),
            ]);
    }
};
