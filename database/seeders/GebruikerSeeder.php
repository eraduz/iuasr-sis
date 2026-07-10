<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Docent;
use App\Models\Opleiding;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Synthetische medewerker-accounts, één per rol, zodat de rolscheiding
 * getest kan worden. Geen wachtwoorden (auth loopt via Entra ID).
 */
class GebruikerSeeder extends Seeder
{
    public function run(): void
    {
        User::create(['naam' => 'Fatima Yıldız', 'email' => 'f.yildiz@iuasr.nl', 'rol' => Rol::Studentenzaken]);
        User::create(['naam' => 'Sanne Visser', 'email' => 's.visser@iuasr.nl', 'rol' => Rol::Financien]);

        $aydin = Docent::where('code', 'DOC-001')->first();
        User::create(['naam' => 'dr. Yusuf Aydın', 'email' => 'y.aydin@iuasr.nl', 'rol' => Rol::Docent, 'docent_id' => $aydin?->id]);

        User::create(['naam' => 'prof. Karima Nassar', 'email' => 'k.nassar@iuasr.nl', 'rol' => Rol::Examencommissie]);

        // Directie is opleidinggebonden: elk directielid ziet uitsluitend de eigen
        // opleiding(en). Zo ziet de PABO-directie geen theologie- of GV-studenten
        // en omgekeerd. Een dubbel ingeschreven student is voor beide zichtbaar.
        $theologieDir = User::create(['naam' => 'drs. Bram de Wit', 'email' => 'b.dewit@iuasr.nl', 'rol' => Rol::Directie]);
        $paboDir = User::create(['naam' => 'drs. Mariëlle Groen', 'email' => 'm.groen@iuasr.nl', 'rol' => Rol::Directie]);
        $gvDir = User::create(['naam' => 'dr. Yasin Demir', 'email' => 'y.demir@iuasr.nl', 'rol' => Rol::Directie]);

        $opl = fn (array $codes) => Opleiding::whereIn('code', $codes)->pluck('id')->all();
        // Islamitische Theologie + cursussen (Faculteit Islamitische Wetenschappen).
        $theologieDir->opleidingen()->sync($opl(['ISLTH', 'KRN', 'ARAB']));
        // PABO (Faculteit Onderwijs & Opvoeding).
        $paboDir->opleidingen()->sync($opl(['PABO']));
        // Islamitische Geestelijke Verzorging (pre-master + master).
        $gvDir->opleidingen()->sync($opl(['PMGV', 'MGV']));

        User::create(['naam' => 'mr. Nadia Öztürk', 'email' => 'n.ozturk@iuasr.nl', 'rol' => Rol::Bestuur]);
        User::create(['naam' => 'Ismail Kaya', 'email' => 'i.kaya@iuasr.nl', 'rol' => Rol::Beheerder]);
        // Module Cursussen Administratie.
        User::create(['naam' => 'Hafsa Bakkali', 'email' => 'h.bakkali@iuasr.nl', 'rol' => Rol::Cursusadministratie]);
    }
}
