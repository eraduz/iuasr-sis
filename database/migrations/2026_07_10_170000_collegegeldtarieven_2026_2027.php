<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collegegeldtarieven voor studiejaar 2026-2027 (opdrachtgever, 2026-07-10):
 *   Pre-Master GV (PMGV)                € 3.500
 *   Master GV (MGV, "Master IGV")       € 4.000
 *   PABO — Leraar Basisonderwijs        € 3.500
 *
 * Vijf termijnen (september, november, januari, maart, mei), conform het
 * standaard-facturatieritme.
 *
 * Alleen invoegen wanneer er voor die opleiding en dit studiejaar nog geen
 * tarief bestaat, zodat een later via Opzoektabellen/Collegegeld handmatig
 * gewijzigd bedrag niet wordt overschreven.
 *
 * Let op: voor de Bachelor Islamitische Theologie (ISLTH) is voor 2026-2027 nog
 * GEEN tarief opgegeven; die wordt apart vastgesteld.
 */
return new class extends Migration
{
    private const TARIEVEN = [
        'PMGV' => 3500.00,
        'MGV' => 4000.00,
        'PABO' => 3500.00,
    ];

    public function up(): void
    {
        $periodeId = DB::table('perioden')->where('code', '2026-2027')->value('id');
        if ($periodeId === null) {
            return; // periode ontbreekt; niets te doen
        }

        foreach (self::TARIEVEN as $code => $bedrag) {
            $opleidingId = DB::table('opleidingen')->where('code', $code)->value('id');
            if ($opleidingId === null) {
                continue;
            }

            $bestaat = DB::table('collegegeld_tarieven')
                ->where('periode_id', $periodeId)
                ->where('opleiding_id', $opleidingId)
                ->exists();

            if (! $bestaat) {
                DB::table('collegegeld_tarieven')->insert([
                    'periode_id' => $periodeId,
                    'opleiding_id' => $opleidingId,
                    'bedrag' => $bedrag,
                    'aantal_termijnen' => 5,
                    'ingesteld_door_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $periodeId = DB::table('perioden')->where('code', '2026-2027')->value('id');
        if ($periodeId === null) {
            return;
        }

        $opleidingIds = DB::table('opleidingen')
            ->whereIn('code', array_keys(self::TARIEVEN))->pluck('id');

        DB::table('collegegeld_tarieven')
            ->where('periode_id', $periodeId)
            ->whereIn('opleiding_id', $opleidingIds)
            ->delete();
    }
};
