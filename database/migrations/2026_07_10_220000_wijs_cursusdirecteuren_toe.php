<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wijst in een BESTAANDE database de cursusdirecteuren toe: de cursusadministratie
 * is voortaan per cursus afgeschermd (need-to-know). Elke cursusdirecteur ziet en
 * beheert uitsluitend de eigen cursus(sen).
 *
 * Deze migratie is bewust "guarded": zij doet alleen iets wanneer de medewerkers
 * al bestaan (een reeds geseede database). Op een VERSE migratie draait zij vóór
 * de seeders — dan bestaan er nog geen gebruikers en gebeurt er niets; de
 * GebruikerSeeder wijst de directeuren in dat geval zelf toe. Zo blijft de toewijzing
 * op één plek consistent en ontstaan er geen dubbele accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Alleen voor een bestaande, reeds geseede database.
        if (DB::table('users')->where('rol', 'cursusadministratie')->doesntExist()) {
            return;
        }

        $hafsa = DB::table('users')->where('email', 'h.bakkali@iuasr.nl')->value('id');

        $omar = DB::table('users')->where('email', 'o.faruk@iuasr.nl')->value('id');
        if (! $omar) {
            $omar = DB::table('users')->insertGetId([
                'naam' => 'Omar Faruk',
                'email' => 'o.faruk@iuasr.nl',
                'rol' => 'cursusadministratie',
                'actief' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Wijs alleen toe waar nog geen directeur is gezet (handmatige keuzes blijven staan).
        foreach (['ARAB-TAAL' => $hafsa, 'HIFZ' => $hafsa, 'IJAZA' => $omar] as $code => $directeurId) {
            if ($directeurId) {
                DB::table('cursussen')->where('code', $code)->whereNull('directeur_id')
                    ->update(['directeur_id' => $directeurId]);
            }
        }
    }

    public function down(): void
    {
        // Maak de synthetische toewijzing ongedaan voor de drie standaardcursussen.
        DB::table('cursussen')->whereIn('code', ['ARAB-TAAL', 'HIFZ', 'IJAZA'])
            ->update(['directeur_id' => null]);
    }
};
