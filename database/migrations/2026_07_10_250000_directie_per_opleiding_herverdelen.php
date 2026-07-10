<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Directie per opleiding (opdrachtgever): elk directielid ziet en beheert
 * uitsluitend de eigen opleiding(en). Verdeling:
 *  - Bachelor Islamitische Theologie (ISLTH) + Pre-Master GV (PMGV) → één directeur
 *  - Master GV (MGV) → eigen directeur
 *  - PABO → eigen directeur
 *
 * Guarded: draait alleen op een reeds geseede database (er bestaat al minstens één
 * directielid). Op een verse migratie draait deze vóór de seeders — er zijn dan
 * nog geen gebruikers en er gebeurt niets; de GebruikerSeeder legt dan de verdeling
 * zelf vast. Zo blijft de toewijzing op één plek consistent en ontstaan er geen
 * dubbele accounts.
 *
 * De cursus-opleidingen KRN/ARAB worden bewust NIET aan een directie gekoppeld
 * (buiten de opdracht); Beheer kan dat desgewenst doen via Gebruikers & rollen.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->where('rol', 'directie')->doesntExist()) {
            return;
        }

        $bram = $this->zorgVoorDirectie('b.dewit@iuasr.nl', 'drs. Bram de Wit');
        $marielle = $this->zorgVoorDirectie('m.groen@iuasr.nl', 'drs. Mariëlle Groen');
        $yasin = $this->zorgVoorDirectie('y.demir@iuasr.nl', 'dr. Yasin Demir');

        $this->syncOpleidingen($bram, ['ISLTH', 'PMGV']);
        $this->syncOpleidingen($marielle, ['PABO']);
        $this->syncOpleidingen($yasin, ['MGV']);
    }

    public function down(): void
    {
        // Terug naar de oude verdeling (Bram: ISLTH+KRN+ARAB, Yasin: PMGV+MGV).
        $bram = DB::table('users')->where('email', 'b.dewit@iuasr.nl')->value('id');
        $yasin = DB::table('users')->where('email', 'y.demir@iuasr.nl')->value('id');
        if ($bram) {
            $this->syncOpleidingenVoorId($bram, ['ISLTH', 'KRN', 'ARAB']);
        }
        if ($yasin) {
            $this->syncOpleidingenVoorId($yasin, ['PMGV', 'MGV']);
        }
    }

    private function zorgVoorDirectie(string $email, string $naam): int
    {
        $id = DB::table('users')->where('email', $email)->value('id');
        if ($id) {
            return $id;
        }

        return DB::table('users')->insertGetId([
            'naam' => $naam, 'email' => $email, 'rol' => 'directie',
            'actief' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array<int,string>  $codes */
    private function syncOpleidingen(int $userId, array $codes): void
    {
        $this->syncOpleidingenVoorId($userId, $codes);
    }

    /** @param  array<int,string>  $codes */
    private function syncOpleidingenVoorId(int $userId, array $codes): void
    {
        DB::table('directie_opleidingen')->where('user_id', $userId)->delete();
        $ids = DB::table('opleidingen')->whereIn('code', $codes)->pluck('id');
        foreach ($ids as $opleidingId) {
            DB::table('directie_opleidingen')->insert([
                'user_id' => $userId, 'opleiding_id' => $opleidingId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
