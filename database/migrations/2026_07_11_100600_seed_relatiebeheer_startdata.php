<?php

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\User;
use Database\Seeders\OrganisatieSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Vult de draaiende database met de startdata voor de module Relatiebeheer &
 * Stagebeheer: de twee rolaccounts (relatiebeheerder, stagecoördinator) met hun
 * opleidingkoppeling, plus de synthetische organisatietypes en organisaties.
 *
 * Guarded: op een VERSE migratie (tests) bestaan er nog geen opleidingen — dan
 * doet deze migratie niets en verzorgen de seeders (GebruikerSeeder /
 * OrganisatieSeeder) de data. Alle inserts zijn idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Opleiding::query()->exists()) {
            return;
        }

        $opl = fn (array $codes) => Opleiding::whereIn('code', $codes)->pluck('id')->all();

        $relatiebeheerder = User::firstOrCreate(
            ['email' => 'l.haddad@iuasr.nl'],
            ['naam' => 'drs. Laila Haddad', 'rol' => Rol::Relatiebeheerder]
        );
        $stagecoordinator = User::firstOrCreate(
            ['email' => 't.ozan@iuasr.nl'],
            ['naam' => 'Tarik Ozan', 'rol' => Rol::Stagecoordinator]
        );

        $relatiebeheerder->opleidingen()->syncWithoutDetaching($opl(['PABO']));
        $stagecoordinator->opleidingen()->syncWithoutDetaching($opl(['ISLTH', 'MGV']));

        // Organisatietypes + synthetische organisaties (idempotent via firstOrCreate).
        (new OrganisatieSeeder())->run();
    }

    public function down(): void
    {
        // Bewust geen verwijdering: relaties en historie blijven bestaan (AVG/historie).
    }
};
