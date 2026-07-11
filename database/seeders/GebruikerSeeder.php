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

        $aydin = Docent::where('code', 'DOC-001')->first();
        User::create(['naam' => 'dr. Yusuf Aydın', 'email' => 'y.aydin@iuasr.nl', 'rol' => Rol::Docent, 'docent_id' => $aydin?->id]);

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

        // Module Relatiebeheer & Stagebeheer (opleidingoverstijgend). Net als de
        // Directie zijn deze rollen opleidinggebonden: zij zien/beheren uitsluitend
        // de relaties van de eigen opleiding(en). Koppeling via dezelfde
        // opleiding-toewijzing (`opleidingen()` → directie_opleidingen).
        $relatiebeheerder = User::create(['naam' => 'drs. Laila Haddad', 'email' => 'l.haddad@iuasr.nl', 'rol' => Rol::Relatiebeheerder]);
        $stagecoordinator = User::create(['naam' => 'Tarik Ozan', 'email' => 't.ozan@iuasr.nl', 'rol' => Rol::Stagecoordinator]);

        // Relatiebeheerder verzorgt de PABO-stagescholen; de stagecoördinator de
        // werkveldstages van de Bachelor Theologie en de Master IGV.
        $relatiebeheerder->opleidingen()->sync($opl(['PABO']));
        $stagecoordinator->opleidingen()->sync($opl(['ISLTH', 'MGV']));
    }
}
