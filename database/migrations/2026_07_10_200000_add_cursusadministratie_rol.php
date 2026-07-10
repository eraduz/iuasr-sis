<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voegt de rol Cursusadministratie toe aan de enum-kolom users.rol. De
 * cursusdirecteur-rol volgt in een latere fase (samen met de directeuren).
 *
 * De waardenlijst komt uit Rol::waarden(), die de nieuwe rol inmiddels bevat.
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
        // Eventuele cursusadministratie-accounts blokkeren een terugdraai; die
        // moeten eerst een andere rol krijgen. Zonder zulke accounts is dit veilig.
        $zonderNieuwe = collect(Rol::waarden())
            ->reject(fn ($v) => $v === Rol::Cursusadministratie->value)
            ->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($zonderNieuwe) NOT NULL");
    }
};
