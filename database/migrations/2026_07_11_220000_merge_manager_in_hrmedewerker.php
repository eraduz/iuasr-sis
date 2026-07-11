<?php

use App\Enums\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Voegt de rollen Manager en HR-medewerker samen tot één gecombineerde rol
 * (Rol::Hrmedewerker). Bij IUASR zijn de leidinggevende en de HR-medewerker
 * dezelfde persoon; het onderscheid verviel daarom.
 *
 * Stappen: (1) bestaande 'manager'-accounts omzetten naar 'hrmedewerker',
 * (2) de enum-kolom users.rol opnieuw definiëren zonder 'manager' — de nieuwe
 * waardenlijst komt uit Rol::waarden(), die 'manager' inmiddels niet meer bevat.
 * Op een verse migratie (tests) is er geen 'manager'-rij en is de enum al zonder
 * 'manager' aangemaakt; deze migratie is dan een no-op-bevestiging.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('rol', 'manager')->update(['rol' => Rol::Hrmedewerker->value]);

        $waarden = collect(Rol::waarden())->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($waarden) NOT NULL");
    }

    public function down(): void
    {
        // 'manager' opnieuw als toegestane waarde toevoegen. De data-conversie is
        // niet omkeerbaar (welke hrmedewerker was manager?), dus alleen de enum.
        $waarden = collect(Rol::waarden())->push('manager')
            ->map(fn ($v) => "'".$v."'")->implode(',');
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM($waarden) NOT NULL");
    }
};
