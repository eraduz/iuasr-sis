<?php

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Elke opleiding houdt de eigen relaties én stages strikt gescheiden bij: per
 * opleiding een eigen relatiebeheerder én een eigen stagecoördinator, elk aan
 * precies één opleiding gekoppeld (opdrachtgever 2026-07-11).
 *
 * Voorheen dekte één stagecoördinator (Tarik Ozan) ISLTH + MGV en had PABO geen
 * stagecoördinator. Deze migratie zet Tarik op ISLTH en voegt de ontbrekende
 * accounts toe. Guarded: op een verse migratie (tests) doet dit niets — dan
 * verzorgt de GebruikerSeeder de accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Opleiding::query()->exists()) {
            return;
        }

        $opl = fn (string $code) => Opleiding::whereIn('code', [$code])->pluck('id')->all();

        // Tarik Ozan is voortaan uitsluitend stagecoördinator van ISLTH.
        $tarik = User::where('email', 't.ozan@iuasr.nl')->first();
        if ($tarik !== null) {
            $tarik->opleidingen()->sync($opl('ISLTH'));
        }

        // Ontbrekende accounts, elk aan precies één opleiding gekoppeld.
        $maak = function (string $naam, string $email, Rol $rol, string $code) use ($opl) {
            $user = User::firstOrCreate(['email' => $email], ['naam' => $naam, 'rol' => $rol]);
            $user->opleidingen()->sync($opl($code));
        };

        $maak('Ilse Vermeer', 'i.vermeer@iuasr.nl', Rol::Stagecoordinator, 'PABO');
        $maak('Karim Belkacem', 'k.belkacem@iuasr.nl', Rol::Relatiebeheerder, 'ISLTH');
        $maak('Amina Cherif', 'a.cherif@iuasr.nl', Rol::Relatiebeheerder, 'MGV');
        $maak('Joost Prins', 'j.prins@iuasr.nl', Rol::Stagecoordinator, 'MGV');
    }

    public function down(): void
    {
        // Bewust geen verwijdering van accounts; koppelingen blijven bestaan.
    }
};
