<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collegegeldtarief Bachelor Islamitische Theologie (ISLTH) voor 2026-2027:
 * € 3.500 (opdrachtgever, 2026-07-10). Vult de opleiding aan die bij de eerste
 * ronde nog niet was opgegeven.
 *
 * Alleen invoegen wanneer er nog geen tarief voor ISLTH in dit studiejaar staat,
 * zodat een handmatig gewijzigd bedrag niet wordt overschreven.
 */
return new class extends Migration
{
    public function up(): void
    {
        $periodeId = DB::table('perioden')->where('code', '2026-2027')->value('id');
        $opleidingId = DB::table('opleidingen')->where('code', 'ISLTH')->value('id');
        if ($periodeId === null || $opleidingId === null) {
            return;
        }

        $bestaat = DB::table('collegegeld_tarieven')
            ->where('periode_id', $periodeId)
            ->where('opleiding_id', $opleidingId)
            ->exists();

        if (! $bestaat) {
            DB::table('collegegeld_tarieven')->insert([
                'periode_id' => $periodeId,
                'opleiding_id' => $opleidingId,
                'bedrag' => 3500.00,
                'aantal_termijnen' => 5,
                'ingesteld_door_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $periodeId = DB::table('perioden')->where('code', '2026-2027')->value('id');
        $opleidingId = DB::table('opleidingen')->where('code', 'ISLTH')->value('id');
        if ($periodeId === null || $opleidingId === null) {
            return;
        }

        DB::table('collegegeld_tarieven')
            ->where('periode_id', $periodeId)
            ->where('opleiding_id', $opleidingId)
            ->where('bedrag', 3500.00)
            ->delete();
    }
};
