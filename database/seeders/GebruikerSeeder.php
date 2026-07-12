<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Cursus;
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

        // Docent-login: Galal Ali (echte docent). Zo kan de rol Docent met een
        // echt docentprofiel worden getest ("Mijn vakken" = zijn eigen vakken).
        $ali = Docent::where('achternaam', 'Ali')->first();
        User::create(['naam' => 'drs. Galal Ali', 'email' => 'amer@iuasr.nl', 'rol' => Rol::Docent, 'docent_id' => $ali?->id]);

        User::create(['naam' => 'prof. Karima Nassar', 'email' => 'k.nassar@iuasr.nl', 'rol' => Rol::Examencommissie]);

        // Directie is opleidinggebonden: elk directielid ziet en beheert uitsluitend
        // de eigen opleiding(en). Verdeling (opdrachtgever): één directeur voor de
        // Bachelor Islamitische Theologie + de Pre-Master GV, een eigen directeur
        // voor de Master GV en een eigen directeur voor de PABO. Een dubbel
        // ingeschreven student is voor beide betrokken directies zichtbaar.
        $theologieDir = User::create(['naam' => 'drs. Bram de Wit', 'email' => 'b.dewit@iuasr.nl', 'rol' => Rol::Directie]);
        $paboDir = User::create(['naam' => 'drs. Mariëlle Groen', 'email' => 'm.groen@iuasr.nl', 'rol' => Rol::Directie]);
        $gvDir = User::create(['naam' => 'dr. Yasin Demir', 'email' => 'y.demir@iuasr.nl', 'rol' => Rol::Directie]);

        $opl = fn (array $codes) => Opleiding::whereIn('code', $codes)->pluck('id')->all();
        // Bachelor Islamitische Theologie + Pre-Master Islamitische Geestelijke Verzorging.
        $theologieDir->opleidingen()->sync($opl(['ISLTH', 'PMGV']));
        // PABO — Leraar Basisonderwijs (Faculteit Onderwijs & Opvoeding).
        $paboDir->opleidingen()->sync($opl(['PABO']));
        // Master Islamitische Geestelijke Verzorging (eigen directeur).
        $gvDir->opleidingen()->sync($opl(['MGV']));

        User::create(['naam' => 'mr. Nadia Öztürk', 'email' => 'n.ozturk@iuasr.nl', 'rol' => Rol::Bestuur]);
        User::create(['naam' => 'Ismail Kaya', 'email' => 'i.kaya@iuasr.nl', 'rol' => Rol::Beheerder]);

        // Module Cursussen Administratie: de cursusadministratie is per cursus
        // afgeschermd. Arabische Taal is een aparte cursus met een eigen directeur;
        // Hifz en Ijaaza worden door dezelfde directeur beheerd. Elke directeur ziet
        // en beheert uitsluitend de eigen cursus(sen); Financiën (boekhouding),
        // Beheer en Bestuur zien alle cursussen.
        $hafsa = User::create(['naam' => 'Hafsa Bakkali', 'email' => 'h.bakkali@iuasr.nl', 'rol' => Rol::Cursusadministratie]);
        $omar = User::create(['naam' => 'Omar Faruk', 'email' => 'o.faruk@iuasr.nl', 'rol' => Rol::Cursusadministratie]);

        Cursus::where('code', 'ARAB-TAAL')->update(['directeur_id' => $hafsa->id]);
        Cursus::whereIn('code', ['HIFZ', 'IJAZA'])->update(['directeur_id' => $omar->id]);

        // Module Relatiebeheer & Stagebeheer (opleidingoverstijgend). Elke opleiding
        // wordt STRIKT GESCHEIDEN gehouden en houdt de eigen relaties én stages zelf
        // bij: per opleiding een eigen relatiebeheerder (relaties/contactpersonen) én
        // een eigen stagecoördinator (relaties + stageplaatsing). Elk account is aan
        // precies één opleiding gekoppeld (via `opleidingen()` → directie_opleidingen).
        $koppel = fn (User $user, string $code) => $user->opleidingen()->sync($opl([$code]));

        // PABO
        $koppel(User::create(['naam' => 'drs. Laila Haddad', 'email' => 'l.haddad@iuasr.nl', 'rol' => Rol::Relatiebeheerder]), 'PABO');
        $koppel(User::create(['naam' => 'Ilse Vermeer', 'email' => 'i.vermeer@iuasr.nl', 'rol' => Rol::Stagecoordinator]), 'PABO');
        // Bachelor Islamitische Theologie
        $koppel(User::create(['naam' => 'Karim Belkacem', 'email' => 'k.belkacem@iuasr.nl', 'rol' => Rol::Relatiebeheerder]), 'ISLTH');
        $koppel(User::create(['naam' => 'Tarik Ozan', 'email' => 't.ozan@iuasr.nl', 'rol' => Rol::Stagecoordinator]), 'ISLTH');
        // Master Islamitische Geestelijke Verzorging
        $koppel(User::create(['naam' => 'Amina Cherif', 'email' => 'a.cherif@iuasr.nl', 'rol' => Rol::Relatiebeheerder]), 'MGV');
        $koppel(User::create(['naam' => 'Joost Prins', 'email' => 'j.prins@iuasr.nl', 'rol' => Rol::Stagecoordinator]), 'MGV');
    }
}
