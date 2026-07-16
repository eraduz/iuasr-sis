<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Module Stichtingsbestuur in gebruik nemen (opdrachtgever 2026-07-16):
 *  1. de nieuwe rol `stichtingsbestuur` toevoegen aan de users.rol-enum;
 *  2. de module registreren (nieuw, actief);
 *  3. een synthetisch account aanmaken (login via Entra/dev-login).
 *
 * Idempotent, zodat een productie-DB de module via `php artisan migrate` krijgt.
 */
return new class extends Migration
{
    public function up(): void
    {
        $waarden = collect(Rol::waarden())->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($waarden) NOT NULL");

        if (! DB::table('modules')->where('sleutel', 'stichtingsbestuur')->exists()) {
            DB::table('modules')->insert([
                'sleutel' => 'stichtingsbestuur',
                'naam' => 'Stichtingsbestuur',
                'omschrijving' => 'Bestuursleden, de Raad van Toezicht en de vergaderingen van de stichting.',
                'icoon' => 'cert',
                'actief' => true,
                'volgorde' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! DB::table('users')->where('email', 'stichtingsbestuur@iuasr.nl')->exists()) {
            DB::table('users')->insert([
                'naam' => 'Ibrahim Öztürk',
                'email' => 'stichtingsbestuur@iuasr.nl',
                'rol' => Rol::Stichtingsbestuur->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'stichtingsbestuur@iuasr.nl')->delete();
        DB::table('modules')->where('sleutel', 'stichtingsbestuur')->delete();

        $zonder = collect(Rol::waarden())
            ->reject(fn ($v) => $v === Rol::Stichtingsbestuur->value)
            ->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($zonder) NOT NULL");
    }
};
