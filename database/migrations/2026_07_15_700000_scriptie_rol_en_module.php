<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Module Scriptie Coördinatie in gebruik nemen (opdrachtgever 2026-07-15):
 *  1. de nieuwe rol `scriptiecoordinator` toevoegen aan de users.rol-enum
 *     (ALTER, herbouwd uit Rol::waarden() zodat bestaande DB's meekomen);
 *  2. de reeds bestaande, nog inactieve module `scriptie` op `actief` zetten;
 *  3. een synthetisch coördinator-account aanmaken (login via Entra/dev-login).
 *
 * Idempotent, zodat een productie-DB de module via `php artisan migrate` krijgt.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Rolwaarde toevoegen aan de enum-kolom.
        $waarden = collect(Rol::waarden())->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($waarden) NOT NULL");

        // 2. Module activeren (bestaat al als inactieve rij; naam is eerder gezet).
        DB::table('modules')->where('sleutel', 'scriptie')->update([
            'naam' => 'Scriptie Coördinatie',
            'omschrijving' => 'Scriptiebegeleiding, -coördinatie en -beoordeling: het volledige scriptietraject in elf stappen.',
            'icoon' => 'cert',
            'actief' => true,
            'updated_at' => now(),
        ]);

        // 3. Coördinator-account (synthetisch; geen wachtwoord, auth via Entra ID).
        if (! DB::table('users')->where('email', 'scriptie@iuasr.nl')->exists()) {
            DB::table('users')->insert([
                'naam' => 'Nadia el Amrani',
                'email' => 'scriptie@iuasr.nl',
                'rol' => Rol::Scriptiecoordinator->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'scriptie@iuasr.nl')->delete();

        DB::table('modules')->where('sleutel', 'scriptie')->update([
            'actief' => false,
            'updated_at' => now(),
        ]);

        $zonder = collect(Rol::waarden())
            ->reject(fn ($v) => $v === Rol::Scriptiecoordinator->value)
            ->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($zonder) NOT NULL");
    }
};
