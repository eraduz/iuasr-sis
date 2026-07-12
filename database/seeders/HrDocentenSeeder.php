<?php

namespace Database\Seeders;

use App\Models\Afdeling;
use App\Models\Competentiescore;
use App\Models\Dienstverband;
use App\Models\Docent;
use App\Models\Functie;
use App\Models\Gesprek;
use App\Models\Gespreksdoel;
use App\Models\Medewerker;
use App\Models\User;
use App\Models\Verlofaanvraag;
use App\Models\Verlofsaldo;
use App\Models\Ziekmelding;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * HR-simulatie waarin de DOCENTEN de medewerkers zijn (opdrachtgever 2026-07-12).
 * Elke docent krijgt een personeelsdossier (Medewerker, gekoppeld via docent_id),
 * in de afdeling Onderwijs met functie Docent, plus een dienstverband en
 * verlofrecht. Enkele docenten krijgen realistische situaties zodat álle
 * HR-schermen (verlof, verzuim/Poortwachter, aflopende contracten, gesprekken,
 * rapportage) gevulde voorbeelden tonen.
 *
 * Uitsluitend SYNTHETISCHE data; geen BSN. Idempotent: een docent die al een
 * medewerkerdossier heeft wordt overgeslagen. Draait ná HrSeeder (afdelingen,
 * functies en de HR-/managementaccounts bestaan dan).
 */
class HrDocentenSeeder extends Seeder
{
    public function run(): void
    {
        $ondw = Afdeling::firstOrCreate(['code' => 'ONDW'], ['naam' => 'Onderwijs', 'actief' => true]);
        $docFunctie = Functie::firstOrCreate(['code' => 'DOC'], ['naam' => 'Docent', 'categorie' => 'docent', 'actief' => true]);
        $manager = Medewerker::where('personeelsnummer', 'P260001')->first(); // Ruben Smit (Onderwijs)
        $jaar = (int) date('Y');

        $urenReeks = [40, 32, 38, 20, 36, 24, 28, 16];
        $i = 0;
        foreach (Docent::where('actief', true)->orderBy('id')->get() as $docent) {
            if (Medewerker::where('docent_id', $docent->id)->exists()) {
                continue; // idempotent
            }

            $uren = $urenReeks[$i % count($urenReeks)];
            $tijdelijk = $i % 4 === 3;
            $startjaar = 2024 - ($i % 6); // 2019..2024
            $user = User::where('docent_id', $docent->id)->first();

            $medewerker = Medewerker::create([
                'personeelsnummer' => 'P26'.str_pad((string) (100 + $docent->id), 4, '0', STR_PAD_LEFT),
                'docent_id' => $docent->id,
                'user_id' => $user?->id,
                'manager_id' => $manager?->id,
                'afdeling_id' => $ondw->id,
                'functie_id' => $docFunctie->id,
                'aanhef' => $docent->aanhef,
                'voornaam' => $docent->voornaam,
                'achternaam' => $docent->achternaam,
                'email' => $docent->email,
                'status' => 'actief',
                'actief' => true,
            ]);

            // Eén aflopend tijdelijk contract (binnen de signaaltermijn van 60 dagen).
            $einddatum = null;
            if ($tijdelijk) {
                $einddatum = $i === 3
                    ? Carbon::now()->addDays(45)->toDateString()   // aflopend-contract signaal
                    : ($startjaar + 3).'-08-31';
            }
            Dienstverband::create([
                'medewerker_id' => $medewerker->id,
                'contracttype' => $tijdelijk ? 'tijdelijk' : 'vast',
                'startdatum' => $startjaar.'-09-01',
                'einddatum' => $einddatum,
                'uren_per_week' => $uren,
                'functie_id' => $docFunctie->id,
                'afdeling_id' => $ondw->id,
            ]);

            $fte = $uren / 40;
            Verlofsaldo::firstOrCreate(['medewerker_id' => $medewerker->id, 'jaar' => $jaar, 'verloftype' => 'vakantie'], ['recht_uren' => round($fte * 200, 1)]);
            Verlofsaldo::firstOrCreate(['medewerker_id' => $medewerker->id, 'jaar' => $jaar, 'verloftype' => 'bijzonder'], ['recht_uren' => 16]);

            $i++;
        }

        $this->situaties($jaar);
    }

    /** Realistische situaties op enkele docent-medewerkers, voor de HR-signaleringen. */
    private function situaties(int $jaar): void
    {
        $hrUser = User::where('email', 'n.aslan@iuasr.nl')->value('id');
        $managerUser = User::where('email', 'r.smit@iuasr.nl')->value('id');
        $med = fn (string $achternaam) => Medewerker::whereNotNull('docent_id')->where('achternaam', $achternaam)->first();

        // Galal Ali (het docent-login): eigen verlofaanvraag + gepland gesprek.
        if ($ali = $med('Ali')) {
            Verlofaanvraag::firstOrCreate(
                ['medewerker_id' => $ali->id, 'van' => $jaar.'-10-20'],
                ['verloftype' => 'vakantie', 'tot' => $jaar.'-10-24', 'uren' => 32, 'status' => 'aangevraagd',
                    'aangevraagd_door_id' => $ali->user_id, 'reden' => 'Herfstvakantie']
            );
            Gesprek::firstOrCreate(
                ['medewerker_id' => $ali->id, 'type' => 'functionering', 'datum' => $jaar.'-11-12'],
                ['gespreksvoerder_id' => $managerUser, 'status' => 'gepland']
            );
        }

        // Langdurig verzuim (Poortwachter): open ziekmelding ~7 weken geleden.
        if ($ziek = $med('Bicer-Uslu')) {
            Ziekmelding::firstOrCreate(
                ['medewerker_id' => $ziek->id, 'ziek_van' => Carbon::now()->subWeeks(7)->toDateString()],
                ['percentage' => 100]
            );
            $ziek->update(['status' => 'ziek']);
        }

        // Frequent verzuim: drie korte, herstelde ziekmeldingen dit jaar.
        if ($freq = $med('Aarab')) {
            foreach ([['-01-13', '-01-15'], ['-03-04', '-03-05'], ['-05-19', '-05-21']] as [$van, $tot]) {
                Ziekmelding::firstOrCreate(
                    ['medewerker_id' => $freq->id, 'ziek_van' => $jaar.$van],
                    ['hersteld_op' => $jaar.$tot, 'percentage' => 100]
                );
            }
        }

        // Afgerond beoordelingsgesprek met doel + competentie.
        if ($beoord = $med('Yalçınkaya')) {
            $gesprek = Gesprek::firstOrCreate(
                ['medewerker_id' => $beoord->id, 'type' => 'beoordeling', 'datum' => $jaar.'-02-14'],
                ['gespreksvoerder_id' => $managerUser, 'status' => 'afgerond',
                    'samenvatting' => 'Sterk jaar; hoge studenttevredenheid.', 'feedback' => 'Neem een rol in de curriculumcommissie.']
            );
            Gespreksdoel::firstOrCreate(['gesprek_id' => $gesprek->id, 'omschrijving' => 'Toetsopbouw moderniseren'], ['status' => 'behaald']);
            Competentiescore::firstOrCreate(['gesprek_id' => $gesprek->id, 'competentie' => 'Vakinhoud'], ['score' => 'goed']);
        }

        // Nog een goedgekeurde verlofaanvraag (voor het verlofoverzicht).
        if ($verlof = $med('Vlug')) {
            Verlofaanvraag::firstOrCreate(
                ['medewerker_id' => $verlof->id, 'van' => $jaar.'-07-28'],
                ['verloftype' => 'vakantie', 'tot' => $jaar.'-08-08', 'uren' => 64, 'status' => 'goedgekeurd',
                    'aangevraagd_door_id' => $verlof->user_id, 'beoordelaar_id' => $hrUser, 'beoordeeld_op' => Carbon::now()->subDays(20)]
            );
        }
    }
}
