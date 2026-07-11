<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Afdeling;
use App\Models\Dienstverband;
use App\Models\Functie;
use App\Models\Medewerker;
use App\Models\User;
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

        // Rolaccounts.
        $hr = User::firstOrCreate(['email' => 'n.aslan@iuasr.nl'], ['naam' => 'Nadia Aslan', 'rol' => Rol::Hrmedewerker]);
        $manager = User::firstOrCreate(['email' => 'r.smit@iuasr.nl'], ['naam' => 'Ruben Smit', 'rol' => Rol::Manager]);

        // Manager-medewerker eerst (referentiepunt voor het team).
        $rubenMed = $this->medewerker('P260001', 'Ruben', 'Smit', $afdeling['ONDW'], $functie['MGR'], null, $manager->id, 40, 'vast');
        Afdeling::where('id', $afdeling['ONDW'])->update(['manager_id' => $rubenMed->id]);

        $this->medewerker('P260002', 'Nadia', 'Aslan', $afdeling['HRB'], $functie['STAF'], null, $hr->id, 32, 'vast');

        // Teamleden onder Ruben (voor de team-scoping van de Manager).
        $this->medewerker('P260003', 'Sophie', 'Willemsen', $afdeling['ONDW'], $functie['DOC'], $rubenMed->id, null, 20, 'tijdelijk');
        $this->medewerker('P260004', 'Mehmet', 'Yilmaz', $afdeling['ONDW'], $functie['DOC'], $rubenMed->id, null, 38, 'vast');

        // Medewerkers buiten het team van Ruben.
        $this->medewerker('P260005', 'Fadwa', 'Ben Ali', $afdeling['ADM'], $functie['ADMIN'], null, null, 24, 'vast');
        $this->medewerker('P260006', 'Johan', 'Bakker', $afdeling['ADM'], $functie['ADMIN'], null, null, 36, 'tijdelijk');
    }

    private function medewerker(string $nummer, string $voornaam, string $achternaam, int $afdelingId, int $functieId, ?int $managerId, ?int $userId, float $uren, string $contract): Medewerker
    {
        $medewerker = Medewerker::firstOrCreate(
            ['personeelsnummer' => $nummer],
            [
                'voornaam' => $voornaam,
                'achternaam' => $achternaam,
                'afdeling_id' => $afdelingId,
                'functie_id' => $functieId,
                'manager_id' => $managerId,
                'user_id' => $userId,
                'email' => strtolower($voornaam.'.'.str_replace(' ', '', $achternaam)).'@iuasr.nl',
                'status' => 'actief',
                'actief' => true,
            ]
        );

        Dienstverband::firstOrCreate(
            ['medewerker_id' => $medewerker->id, 'startdatum' => '2024-09-01'],
            ['contracttype' => $contract, 'uren_per_week' => $uren, 'functie_id' => $functieId, 'afdeling_id' => $afdelingId]
        );

        return $medewerker;
    }
}
