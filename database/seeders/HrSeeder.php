<?php

namespace Database\Seeders;

use App\Enums\ChecklistSoort;
use App\Enums\Rol;
use App\Models\Afdeling;
use App\Models\Competentiescore;
use App\Models\HrChecklisttaak;
use App\Models\Dienstverband;
use App\Models\Functie;
use App\Models\Gesprek;
use App\Models\Gespreksdoel;
use App\Models\Medewerker;
use App\Models\MedewerkerNotitie;
use App\Models\User;
use App\Models\Verlofaanvraag;
use App\Models\Verlofsaldo;
use App\Models\Ziekmelding;
use Illuminate\Database\Seeder;

/**
 * Synthetische HR-data (module HR / Personeelszaken): afdelingen, functies,
 * medewerkers met dienstverband, en de HR-rolaccounts. Uitsluitend SYNTHETISCHE
 * data (AVG); geen echte persoonsgegevens, geen BSN.
 */
class HrSeeder extends Seeder
{
    public function run(): void
    {
        // Functies.
        $functie = [];
        foreach ([
            ['DOC', 'Docent', 'docent'],
            ['STAF', 'Stafmedewerker', 'staf'],
            ['ADMIN', 'Administratief medewerker', 'staf'],
            ['MGR', 'Afdelingsmanager', 'management'],
            // Functies zijn de ROL (los van de soort personeel/vrijwilliger/zzp):
            // een vrijwilliger kan kok of schoonmaker zijn, een ZZP'er trainer.
            ['KOK', 'Kok', 'facilitair'],
            ['SCHM', 'Schoonmaker', 'facilitair'],
            ['GASTV', 'Gastvrouw/gastheer', 'facilitair'],
            ['TRAINER', 'Trainer', 'onderwijs'],
            ['ICT', 'ICT-consultant', 'staf'],
        ] as [$code, $naam, $cat]) {
            $functie[$code] = Functie::firstOrCreate(['code' => $code], ['naam' => $naam, 'categorie' => $cat, 'actief' => true])->id;
        }

        // Afdelingen.
        $afdeling = [];
        foreach ([
            ['ONDW', 'Onderwijs'],
            ['ADM', 'Administratie'],
            ['HRB', 'HR & Bestuur'],
        ] as [$code, $naam]) {
            $afdeling[$code] = Afdeling::firstOrCreate(['code' => $code], ['naam' => $naam, 'actief' => true])->id;
        }
        // Team (een afdeling met een bovenliggende afdeling) — toont de hiërarchie.
        $afdeling['ONDW-PABO'] = Afdeling::firstOrCreate(
            ['code' => 'ONDW-PABO'],
            ['naam' => 'PABO-team', 'bovenliggende_afdeling_id' => $afdeling['ONDW'], 'actief' => true]
        )->id;

        // Rolaccounts. HR-medewerker en Manager zijn bij IUASR één gecombineerde rol
        // (Rol::Hrmedewerker): Ruben is leidinggevende én doet de personeelszaken.
        $hr = User::firstOrCreate(['email' => 'n.aslan@iuasr.nl'], ['naam' => 'Nadia Aslan', 'rol' => Rol::Hrmedewerker]);
        $manager = User::firstOrCreate(['email' => 'r.smit@iuasr.nl'], ['naam' => 'Ruben Smit', 'rol' => Rol::Hrmedewerker]);

        // Leidinggevende-medewerker eerst (referentiepunt voor de organisatiestructuur).
        $rubenMed = $this->medewerker('P260001', 'Ruben', 'Smit', $afdeling['ONDW'], $functie['MGR'], null, $manager->id, 40, 'vast');
        Afdeling::whereIn('id', [$afdeling['ONDW'], $afdeling['ONDW-PABO']])->update(['manager_id' => $rubenMed->id]);

        $this->medewerker('P260002', 'Nadia', 'Aslan', $afdeling['HRB'], $functie['STAF'], null, $hr->id, 32, 'vast');

        // Teamleden onder Ruben (organisatiestructuur), in het PABO-team.
        $this->medewerker('P260003', 'Sophie', 'Willemsen', $afdeling['ONDW-PABO'], $functie['DOC'], $rubenMed->id, null, 20, 'tijdelijk');
        $this->medewerker('P260004', 'Mehmet', 'Yilmaz', $afdeling['ONDW-PABO'], $functie['DOC'], $rubenMed->id, null, 38, 'vast');

        // Medewerkers buiten het team van Ruben.
        $this->medewerker('P260005', 'Fadwa', 'Ben Ali', $afdeling['ADM'], $functie['ADMIN'], null, null, 24, 'vast');
        $this->medewerker('P260006', 'Johan', 'Bakker', $afdeling['ADM'], $functie['ADMIN'], null, null, 36, 'tijdelijk');

        // Vrijwilligers (stichting): een PROFIEL (soort), los van de functie. Zij
        // worden geregistreerd maar tellen NIET mee in de FTE. Hun functie is hun
        // echte rol (kok, schoonmaker, gastvrouw). Amina heeft afgesproken uren op een
        // dienstverband — die FTE telt bewust toch niet mee.
        $this->medewerker('P260007', 'Amina', 'El Idrissi', $afdeling['ONDW'], $functie['KOK'], $rubenMed->id, null, 8, 'tijdelijk', 'vrijwilliger');
        $this->medewerker('P260008', 'Karim', 'Ait Bella', $afdeling['ADM'], $functie['SCHM'], null, null, 0, 'tijdelijk', 'vrijwilliger', metDienstverband: false);
        $this->medewerker('P260009', 'Latifa', 'Ouhadi', $afdeling['HRB'], $functie['GASTV'], null, null, 0, 'tijdelijk', 'vrijwilliger', metDienstverband: false);

        // ZZP'ers / freelancers: eveneens een apart profiel, niet in de FTE. Zij
        // werken op opdrachtbasis (geen dienstverband), met hun eigen functie/rol.
        $this->medewerker('P260010', 'Driss', 'Haddad', $afdeling['ONDW'], $functie['TRAINER'], null, null, 0, 'tijdelijk', 'zzp', metDienstverband: false);
        $this->medewerker('P260011', 'Sanne', 'de Groot', $afdeling['ADM'], $functie['ICT'], null, null, 0, 'tijdelijk', 'zzp', metDienstverband: false);

        $this->verlofEnVerzuim();
        $this->gesprekken();
        $this->checklist();
        $this->notities();
    }

    /** Synthetische contactmoment-notities (logboek per medewerker). */
    private function notities(): void
    {
        $auteur = User::where('email', 'n.aslan@iuasr.nl')->value('id');

        $sophie = Medewerker::where('personeelsnummer', 'P260003')->first();
        if ($sophie !== null) {
            MedewerkerNotitie::firstOrCreate(
                ['medewerker_id' => $sophie->id, 'tekst' => 'Telefonisch contact over de contractverlenging; medewerker wil graag uitbreiden naar 0,6 fte. Teruggekoppeld dat de leidinggevende dit in het functioneringsgesprek bespreekt.'],
                ['gebruiker_id' => $auteur]
            );
            MedewerkerNotitie::firstOrCreate(
                ['medewerker_id' => $sophie->id, 'tekst' => 'E-mail ontvangen met vraag over het verlofsaldo; per e-mail beantwoord met het actuele saldo.'],
                ['gebruiker_id' => $auteur]
            );
        }
    }

    /** Demo-onboarding voor een recente medewerker (Fase E). */
    private function checklist(): void
    {
        $johan = Medewerker::where('personeelsnummer', 'P260006')->first();
        if ($johan === null) {
            return;
        }

        foreach (ChecklistSoort::Onboarding->sjabloon() as $volgorde => $titel) {
            HrChecklisttaak::firstOrCreate(
                ['medewerker_id' => $johan->id, 'soort' => 'onboarding', 'titel' => $titel],
                ['volgorde' => $volgorde, 'gereed' => $volgorde < 2, 'gereed_op' => $volgorde < 2 ? now() : null]
            );
        }
    }

    /** Synthetische HR-gesprekken met doelen en competenties (Fase C). */
    private function gesprekken(): void
    {
        $jaar = (int) date('Y');
        $ruben = User::where('email', 'r.smit@iuasr.nl')->value('id');

        $sophie = Medewerker::where('personeelsnummer', 'P260003')->first();
        if ($sophie !== null) {
            Gesprek::firstOrCreate(
                ['medewerker_id' => $sophie->id, 'type' => 'functionering', 'datum' => $jaar.'-11-15'],
                ['gespreksvoerder_id' => $ruben, 'status' => 'gepland']
            );
        }

        $mehmet = Medewerker::where('personeelsnummer', 'P260004')->first();
        if ($mehmet !== null) {
            $gesprek = Gesprek::firstOrCreate(
                ['medewerker_id' => $mehmet->id, 'type' => 'beoordeling', 'datum' => $jaar.'-02-10'],
                ['gespreksvoerder_id' => $ruben, 'status' => 'afgerond', 'samenvatting' => 'Sterk jaar; doelen behaald.', 'feedback' => 'Blijf de nieuwe lesmethode doorontwikkelen.']
            );
            Gespreksdoel::firstOrCreate(['gesprek_id' => $gesprek->id, 'omschrijving' => 'Nieuwe lesmethode invoeren'], ['status' => 'behaald']);
            Competentiescore::firstOrCreate(['gesprek_id' => $gesprek->id, 'competentie' => 'Samenwerking'], ['score' => 'goed']);
        }
    }

    /** Synthetisch verlofrecht, enkele aanvragen en een ziekmelding (Fase B). */
    private function verlofEnVerzuim(): void
    {
        $jaar = (int) date('Y');

        foreach (Medewerker::all() as $medewerker) {
            // Vrijwilligers en ZZP'ers bouwen in deze demo geen betaald verlofrecht op.
            if (! $medewerker->teltVoorFte()) {
                continue;
            }
            $fte = $medewerker->fte() ?? 1.0;
            Verlofsaldo::firstOrCreate(
                ['medewerker_id' => $medewerker->id, 'jaar' => $jaar, 'verloftype' => 'vakantie'],
                ['recht_uren' => round($fte * 200, 1)]
            );
            Verlofsaldo::firstOrCreate(
                ['medewerker_id' => $medewerker->id, 'jaar' => $jaar, 'verloftype' => 'bijzonder'],
                ['recht_uren' => 16]
            );
        }

        // Teamlid Sophie (onder Ruben): één openstaande en één goedgekeurde aanvraag.
        $sophie = Medewerker::where('personeelsnummer', 'P260003')->first();
        if ($sophie !== null) {
            Verlofaanvraag::firstOrCreate(
                ['medewerker_id' => $sophie->id, 'van' => $jaar.'-08-05'],
                ['verloftype' => 'vakantie', 'tot' => $jaar.'-08-09', 'uren' => 40, 'status' => 'aangevraagd', 'aangevraagd_door_id' => $sophie->user_id, 'reden' => 'Zomervakantie']
            );
            Verlofaanvraag::firstOrCreate(
                ['medewerker_id' => $sophie->id, 'van' => $jaar.'-05-01'],
                ['verloftype' => 'vakantie', 'tot' => $jaar.'-05-03', 'uren' => 24, 'status' => 'goedgekeurd', 'aangevraagd_door_id' => $sophie->user_id]
            );
        }

        // Wettelijk verlof (WAZO) als voorbeeld: zwangerschaps-/bevallingsverlof voor
        // Nadia (berekend uit de uitgerekende datum) en geboorteverlof voor Mehmet.
        $nadia = Medewerker::where('personeelsnummer', 'P260002')->first();
        if ($nadia !== null) {
            $wazo = \App\Support\Wettelijkverlof::zwangerschapEnBevalling(\Illuminate\Support\Carbon::create($jaar, 10, 1));
            Verlofaanvraag::firstOrCreate(
                ['medewerker_id' => $nadia->id, 'verloftype' => 'zwangerschap', 'van' => $wazo['van']->toDateString()],
                ['tot' => $wazo['tot']->toDateString(), 'uren' => round(($nadia->huidigDienstverband()?->uren_per_week ?? 32) * $wazo['weken'], 1), 'status' => 'goedgekeurd', 'aangevraagd_door_id' => $nadia->user_id, 'reden' => 'Zwangerschaps- en bevallingsverlof']
            );
        }

        $mehmet = Medewerker::where('personeelsnummer', 'P260004')->first();
        if ($mehmet !== null) {
            Verlofaanvraag::firstOrCreate(
                ['medewerker_id' => $mehmet->id, 'verloftype' => 'geboorte', 'van' => $jaar.'-04-08'],
                ['tot' => $jaar.'-04-12', 'uren' => \App\Support\Wettelijkverlof::geboorteverlofUren($mehmet->huidigDienstverband()?->uren_per_week ?? 38), 'status' => 'goedgekeurd', 'aangevraagd_door_id' => $mehmet->user_id, 'reden' => 'Geboorteverlof partner']
            );
        }

        // Ouderschapsverlof (gespreid): Johan neemt de 9 betaalde weken op.
        $johan = Medewerker::where('personeelsnummer', 'P260006')->first();
        if ($johan !== null) {
            $ouder = \App\Support\Wettelijkverlof::ouderschapsverlofUren($johan->huidigDienstverband()?->uren_per_week ?? 36);
            Verlofaanvraag::firstOrCreate(
                ['medewerker_id' => $johan->id, 'verloftype' => 'ouderschap', 'van' => $jaar.'-09-01'],
                ['tot' => $jaar.'-11-03', 'uren' => $ouder['betaald'], 'status' => 'goedgekeurd', 'aangevraagd_door_id' => $johan->user_id, 'reden' => 'Betaald ouderschapsverlof (9 weken)']
            );
        }

        // Een open ziekmelding (langdurig verzuim — Poortwachter-traject, Fase G).
        $fadwa = Medewerker::where('personeelsnummer', 'P260005')->first();
        if ($fadwa !== null) {
            Ziekmelding::firstOrCreate(['medewerker_id' => $fadwa->id, 'ziek_van' => $jaar.'-06-03'], ['percentage' => 100]);
            $fadwa->update(['status' => 'ziek']);
        }

        // Frequent verzuim (Fase G): drie korte, herstelde ziekmeldingen binnen het
        // jaar — een signaal voor een verzuimgesprek. Herstelde meldingen laten de
        // medewerkerstatus op 'actief'.
        $mehmet = Medewerker::where('personeelsnummer', 'P260004')->first();
        if ($mehmet !== null) {
            foreach ([['-01-13', '-01-15'], ['-03-04', '-03-05'], ['-05-19', '-05-21']] as [$van, $tot]) {
                Ziekmelding::firstOrCreate(
                    ['medewerker_id' => $mehmet->id, 'ziek_van' => $jaar.$van],
                    ['hersteld_op' => $jaar.$tot, 'percentage' => 100]
                );
            }
        }
    }

    private function medewerker(string $nummer, string $voornaam, string $achternaam, int $afdelingId, int $functieId, ?int $managerId, ?int $userId, float $uren, string $contract, string $soort = 'personeel', bool $metDienstverband = true): Medewerker
    {
        $medewerker = Medewerker::firstOrCreate(
            ['personeelsnummer' => $nummer],
            [
                'voornaam' => $voornaam,
                'achternaam' => $achternaam,
                'soort' => $soort,
                'afdeling_id' => $afdelingId,
                'functie_id' => $functieId,
                'manager_id' => $managerId,
                'user_id' => $userId,
                'email' => strtolower($voornaam.'.'.str_replace(' ', '', $achternaam)).'@iuasr.nl',
                'status' => 'actief',
                'actief' => true,
            ]
        );

        if ($metDienstverband) {
            Dienstverband::firstOrCreate(
                ['medewerker_id' => $medewerker->id, 'startdatum' => '2024-09-01'],
                ['contracttype' => $contract, 'uren_per_week' => $uren, 'functie_id' => $functieId, 'afdeling_id' => $afdelingId]
            );
        }

        return $medewerker;
    }
}
