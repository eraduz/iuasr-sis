<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voegt de rol Balie/Receptie toe aan de enum-kolom users.rol (module
 * Balie/Receptie). De waardenlijst komt uit Rol::waarden(), die de nieuwe rol
 * inmiddels bevat.
 *
 * Nodig omdat een BESTAANDE database de oude enum-waarden houdt: bij een verse
 * database bouwt de create-migratie de enum al met alle huidige rollen, maar op
 * de test- en productiedatabase moet de kolom expliciet worden uitgebreid.
 * Zonder deze migratie faalt het aanmaken van het balie-account met
 * "Data truncated for column 'rol'". Draait daarom vóór 100100.
 */
return new class extends Migration
{
    public function up(): void
    {
        $waarden = collect(Rol::waarden())->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($waarden) NOT NULL");
    }

    public function down(): void
    {
        $zonderBalie = collect(Rol::waarden())
            ->reject(fn ($v) => $v === Rol::Balie->value)
            ->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($zonderBalie) NOT NULL");
    }
};
