<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voegt de rollen Relatiebeheerder en Stagecoördinator toe aan de enum-kolom
 * users.rol (module Relatiebeheer & Stagebeheer). De waardenlijst komt uit
 * Rol::waarden(), die de nieuwe rollen inmiddels bevat.
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
        // Eventuele accounts met de nieuwe rollen blokkeren een terugdraai; die
        // moeten eerst een andere rol krijgen. Zonder zulke accounts is dit veilig.
        $nieuwe = [Rol::Relatiebeheerder->value, Rol::Stagecoordinator->value];
        $zonderNieuwe = collect(Rol::waarden())
            ->reject(fn ($v) => in_array($v, $nieuwe, true))
            ->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($zonderNieuwe) NOT NULL");
    }
};
