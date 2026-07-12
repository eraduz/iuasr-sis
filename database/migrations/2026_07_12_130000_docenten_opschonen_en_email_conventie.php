<?php

use App\Enums\Rol;
use App\Models\Docent;
use App\Models\User;
use App\Models\Vak;
use Database\Seeders\DocentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Docenten opschonen op de DRAAIENDE database (opdrachtgever 2026-07-12):
 *  1. E-mailconventie {achternaam}@iuasr.nl toepassen op alle docenten
 *     (dus abba@iuasr.nl i.p.v. a.abba@iuasr.nl), met de uitzonderingen uit
 *     DocentSeeder (Galal Ali → amer@iuasr.nl).
 *  2. De dummy-docenten Yusuf Aydın en Salima Boujat verwijderen; hun eventuele
 *     vakkoppelingen loskoppelen. Het docent-login van Aydın vervangen door een
 *     account voor Galal Ali (echte docent), zodat "Mijn vakken" getest kan worden.
 *
 * Guard: no-op op een verse migratie (nog geen docenten) — de seeders leveren dan
 * al de juiste stand. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('docenten') || Docent::doesntExist()) {
            return; // verse migratie: seeders regelen dit
        }

        // Dummies identificeren op naam (vóór de e-mail wordt herschreven).
        $dummies = Docent::where(fn ($q) => $q
            ->where(fn ($w) => $w->where('voornaam', 'Yusuf')->where('achternaam', 'Aydın'))
            ->orWhere(fn ($w) => $w->where('voornaam', 'Salima')->where('achternaam', 'Boujat')))
            ->get();

        // 1. E-mailconventie toepassen op alle (blijvende) docenten.
        foreach (Docent::all() as $docent) {
            $nieuw = DocentSeeder::emailVoor($docent->achternaam);
            if ($docent->email !== $nieuw) {
                $docent->update(['email' => $nieuw]);
            }
        }

        // 2a. Docent-login: Aydın verwijderen, Galal Ali aanmaken.
        User::where('email', 'y.aydin@iuasr.nl')->delete();

        $ali = Docent::where('achternaam', 'Ali')->first();
        if ($ali !== null && ! User::where('email', 'amer@iuasr.nl')->exists()) {
            User::create([
                'naam' => 'drs. Galal Ali',
                'email' => 'amer@iuasr.nl',
                'rol' => Rol::Docent,
                'docent_id' => $ali->id,
            ]);
        }

        // 2b. Vakken die naar een dummy verwezen loskoppelen (veiligheid).
        // 2c. Dummy-docenten verwijderen.
        foreach ($dummies as $dummy) {
            Vak::where('docent_id', $dummy->id)->update(['docent_id' => null]);
            $dummy->delete();
        }
    }

    public function down(): void
    {
        // Opschoning niet terugdraaien.
    }
};
