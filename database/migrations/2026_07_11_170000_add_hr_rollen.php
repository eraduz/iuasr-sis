<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voegt de rollen HR-medewerker en Manager toe aan de enum-kolom users.rol
 * (module HR / Personeelszaken). De waardenlijst komt uit Rol::waarden(), die de
 * nieuwe rollen inmiddels bevat.
 *
 * NB: de rol 'manager' is later samengevoegd met 'hrmedewerker' (zie de migratie
 * merge_manager_in_hrmedewerker). De down() gebruikt hier daarom letterlijke
 * strings i.p.v. de inmiddels verwijderde enum-case Rol::Manager.
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
        $nieuwe = [Rol::Hrmedewerker->value, 'manager'];
        $zonderNieuwe = collect(Rol::waarden())
            ->reject(fn ($v) => in_array($v, $nieuwe, true))
            ->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($zonderNieuwe) NOT NULL");
    }
};
