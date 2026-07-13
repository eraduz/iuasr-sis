<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Module Bibliotheek — de rol, de modulerij en het rolaccount.
 *
 * De enum-kolom users.rol moet expliciet worden uitgebreid: een BESTAANDE
 * database houdt de oude waardenlijst, terwijl een verse database de enum uit
 * Rol::waarden() opbouwt. Zonder deze ALTER faalt het aanmaken van het account
 * met "Data truncated for column 'rol'" (zoals bij de rol balie gebeurde).
 *
 * Idempotent: bestaat de modulerij of het account al, dan gebeurt er niets.
 * Er wordt geen wachtwoord gezet; authenticatie verloopt via Entra ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        $waarden = collect(Rol::waarden())->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($waarden) NOT NULL");

        $nu = now();

        if (! DB::table('modules')->where('sleutel', 'bibliotheek')->exists()) {
            DB::table('modules')->insert([
                'sleutel' => 'bibliotheek',
                'naam' => 'Bibliotheek',
                'omschrijving' => 'Publicaties, tijdschriftartikelen, uitlenen en innemen.',
                'icoon' => 'book',
                'actief' => true,
                'volgorde' => 7,
                'created_at' => $nu,
                'updated_at' => $nu,
            ]);
        }

        if (! DB::table('users')->where('email', 'bibliotheek@iuasr.nl')->exists()) {
            DB::table('users')->insert([
                'naam' => 'Zeynep Aksoy',
                'email' => 'bibliotheek@iuasr.nl',
                'rol' => Rol::Bibliotheek->value,
                'created_at' => $nu,
                'updated_at' => $nu,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'bibliotheek@iuasr.nl')->delete();
        DB::table('modules')->where('sleutel', 'bibliotheek')->delete();

        $zonderBibliotheek = collect(Rol::waarden())
            ->reject(fn ($v) => $v === Rol::Bibliotheek->value)
            ->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($zonderBibliotheek) NOT NULL");
    }
};
