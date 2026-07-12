<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Docent;
use App\Models\Medewerker;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Inlogaccounts (rol Docent) voor de docenten, zodat elke docent zich kan
 * aanmelden en zijn eigen schermen ziet: "Mijn vakken", cijfer-/aanwezigheids-
 * invoer én de HR-zelfservice "Mijn HR". Login verloopt via Entra ID / de
 * dev-login; er worden geen wachtwoorden gezet.
 *
 * E-mail = het docentadres ({achternaam}@iuasr.nl, met de uitzonderingen uit
 * DocentSeeder). Idempotent: een docent die al een account heeft wordt
 * overgeslagen (o.a. Galal Ali). Waar het HR-dossier al bestaat, wordt het
 * meteen aan het nieuwe account gekoppeld (`medewerkers.user_id`) voor de
 * self-service; bestaat het dossier nog niet, dan legt HrDocentenSeeder die
 * koppeling later bij het aanmaken.
 */
class DocentLoginSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Docent::where('actief', true)->orderBy('id')->get() as $docent) {
            if (User::where('docent_id', $docent->id)->exists()) {
                continue; // heeft al een account
            }
            if ($docent->email === null || User::where('email', $docent->email)->exists()) {
                continue; // geen of botsend e-mailadres — overslaan
            }

            $user = User::create([
                'naam' => $docent->volledigeNaam(),
                'email' => $docent->email,
                'rol' => Rol::Docent,
                'docent_id' => $docent->id,
                'actief' => true,
            ]);

            // Koppel het bestaande HR-dossier aan dit account (self-service "Mijn HR").
            $medewerker = Medewerker::where('docent_id', $docent->id)->whereNull('user_id')->first();
            $medewerker?->update(['user_id' => $user->id]);
        }
    }
}
