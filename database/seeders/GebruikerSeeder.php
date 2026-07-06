<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Docent;
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

        $aydin = Docent::where('code', 'DOC-001')->first();
        User::create(['naam' => 'dr. Yusuf Aydın', 'email' => 'y.aydin@iuasr.nl', 'rol' => Rol::Docent, 'docent_id' => $aydin?->id]);

        User::create(['naam' => 'prof. Karima Nassar', 'email' => 'k.nassar@iuasr.nl', 'rol' => Rol::Examencommissie]);
        User::create(['naam' => 'drs. Bram de Wit', 'email' => 'b.dewit@iuasr.nl', 'rol' => Rol::Directie]);
        User::create(['naam' => 'Ismail Kaya', 'email' => 'i.kaya@iuasr.nl', 'rol' => Rol::Beheerder]);
    }
}
