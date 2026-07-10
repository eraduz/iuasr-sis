<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Herverdeelt de cursusdirecteuren (opdrachtgever): Arabische Taal is een aparte
 * cursus met een eigen directeur (Hafsa); Hifz én Ijaaza worden door dezelfde
 * directeur (Omar) beheerd. Voorheen dirigeerde Hafsa Arabisch + Hifz en Omar
 * alleen Ijaaza.
 *
 * Guarded: draait alleen op een reeds geseede database. Op een verse migratie
 * (vóór de seeders) gebeurt er niets; de GebruikerSeeder wijst dan zelf toe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->where('rol', 'cursusadministratie')->doesntExist()) {
            return;
        }

        $hafsa = DB::table('users')->where('email', 'h.bakkali@iuasr.nl')->value('id');
        $omar = DB::table('users')->where('email', 'o.faruk@iuasr.nl')->value('id');

        if ($hafsa) {
            DB::table('cursussen')->where('code', 'ARAB-TAAL')->update(['directeur_id' => $hafsa]);
        }
        if ($omar) {
            DB::table('cursussen')->whereIn('code', ['HIFZ', 'IJAZA'])->update(['directeur_id' => $omar]);
        }
    }

    public function down(): void
    {
        // Terug naar de vorige verdeling (Hafsa: Arabisch + Hifz, Omar: Ijaaza).
        $hafsa = DB::table('users')->where('email', 'h.bakkali@iuasr.nl')->value('id');
        $omar = DB::table('users')->where('email', 'o.faruk@iuasr.nl')->value('id');
        if ($hafsa) {
            DB::table('cursussen')->whereIn('code', ['ARAB-TAAL', 'HIFZ'])->update(['directeur_id' => $hafsa]);
        }
        if ($omar) {
            DB::table('cursussen')->where('code', 'IJAZA')->update(['directeur_id' => $omar]);
        }
    }
};
