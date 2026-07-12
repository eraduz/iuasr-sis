<?php

namespace Database\Seeders;

use App\Models\Afdeling;
use App\Models\Dienstverband;
use App\Models\Docent;
use App\Models\Functie;
use App\Models\Medewerker;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Werkt de docenten én de HR-personeelsadministratie bij op basis van de echte
 * personeelslijst `database/data/personeel.csv` (personeelsnummer; voornaam;
 * achternaam; functie).
 *
 * AVG: dit bestand bevat ECHTE namen en staat daarom NIET in Git (zie
 * .gitignore) — uitsluitend functie/naam/personeelsnummer, géén BSN/IBAN/salaris/
 * adres. Ontbreekt het bestand (bv. een verse clone), dan doet deze seeder niets.
 *
 * Per rij:
 *  - Functie 'Docent'  → koppelt aan het bestaande docentprofiel (of maakt een
 *    nieuwe docent + login) en zet het personeelsnummer/voornaam op het dossier.
 *  - Overige functies (Bestuur/Boekhouder/Medewerker) → maakt een medewerker-
 *    dossier met de juiste afdeling/functie en een standaard dienstverband.
 *
 * Idempotent. Bestaande synthetische HR-accounts (HrSeeder) blijven staan.
 */
class PersoneelSeeder extends Seeder
{
    public const BESTAND = 'database/data/personeel.csv';

    /** Spellingsverschillen lijst → bestaand docentprofiel (genormaliseerd). */
    private const ACHTERNAAM_ALIAS = [
        'abuhijaa' => 'abualhija',
        'bouchtoui' => 'bouchtaoui',
    ];

    public function run(): void
    {
        $pad = base_path(self::BESTAND);
        if (! is_readable($pad)) {
            $this->command?->warn('personeel.csv niet aanwezig; personeelsupdate overgeslagen.');

            return;
        }

        $functies = [
            'Docent' => Functie::firstOrCreate(['code' => 'DOC'], ['naam' => 'Docent', 'categorie' => 'docent', 'actief' => true])->id,
            'Bestuur' => Functie::firstOrCreate(['code' => 'BESTUURDER'], ['naam' => 'Bestuurder', 'categorie' => 'management', 'actief' => true])->id,
            'Boekhouder' => Functie::firstOrCreate(['code' => 'BOEKH'], ['naam' => 'Boekhouder', 'categorie' => 'staf', 'actief' => true])->id,
            'Medewerker' => Functie::firstOrCreate(['code' => 'MEDEW'], ['naam' => 'Medewerker', 'categorie' => 'staf', 'actief' => true])->id,
        ];
        $afdelingen = [
            'Docent' => Afdeling::firstOrCreate(['code' => 'ONDW'], ['naam' => 'Onderwijs', 'actief' => true])->id,
            'Bestuur' => Afdeling::firstOrCreate(['code' => 'BESTUUR'], ['naam' => 'Bestuur', 'actief' => true])->id,
            'Boekhouder' => Afdeling::firstOrCreate(['code' => 'ADM'], ['naam' => 'Administratie', 'actief' => true])->id,
            'Medewerker' => Afdeling::firstOrCreate(['code' => 'ADM'], ['naam' => 'Administratie', 'actief' => true])->id,
        ];

        $docenten = 0;
        $medewerkers = 0;
        foreach ($this->regels($pad) as [$nummer, $voornaam, $achternaam, $functie]) {
            $functie = trim($functie);
            if (! isset($functies[$functie])) {
                $functie = 'Medewerker'; // onbekende functie → algemene medewerker
            }

            if ($functie === 'Docent') {
                $this->docent($nummer, $voornaam, $achternaam, $functies['Docent'], $afdelingen['Docent']);
                $docenten++;
            } else {
                $this->overigeMedewerker($nummer, $voornaam, $achternaam, $functies[$functie], $afdelingen[$functie]);
                $medewerkers++;
            }
        }

        // Zorg dat nieuwe docenten (bv. Coskun) ook een inlogaccount krijgen.
        (new DocentLoginSeeder)->run();

        $this->command?->info("Personeel: {$docenten} docenten, {$medewerkers} overige medewerkers bijgewerkt.");
    }

    /** Docent: koppel/maak het docentprofiel en werk het HR-dossier bij. */
    private function docent(string $nummer, string $voornaam, string $achternaam, int $functieId, int $afdelingId): void
    {
        $sleutel = self::ACHTERNAAM_ALIAS[$this->sleutel($achternaam)] ?? $this->sleutel($achternaam);

        $docent = Docent::all()->first(fn (Docent $d) => $this->sleutel($d->achternaam) === $sleutel);
        if ($docent === null) {
            $docent = Docent::create([
                'code' => $this->volgendeDocentcode(),
                'voornaam' => $voornaam,
                'achternaam' => $achternaam,
                'email' => DocentSeeder::emailVoor($achternaam),
                'actief' => true,
            ]);
        } else {
            $docent->update(['voornaam' => $voornaam]); // echte initialen; achternaam behouden (koppelingen)
        }

        $user = User::where('docent_id', $docent->id)->first();
        $medewerker = Medewerker::where('docent_id', $docent->id)->first()
            ?? Medewerker::where('personeelsnummer', $nummer)->first();

        if ($medewerker === null) {
            $medewerker = Medewerker::create([
                'personeelsnummer' => $nummer,
                'docent_id' => $docent->id,
                'user_id' => $user?->id,
                'afdeling_id' => $afdelingId,
                'functie_id' => $functieId,
                'voornaam' => $voornaam,
                'achternaam' => $achternaam,
                'email' => $docent->email,
                'status' => 'actief',
                'actief' => true,
            ]);
            $this->standaardDienstverband($medewerker, $functieId, $afdelingId);
        } else {
            $medewerker->update([
                'personeelsnummer' => $nummer,
                'docent_id' => $docent->id,
                'user_id' => $medewerker->user_id ?? $user?->id,
                'voornaam' => $voornaam,
                'achternaam' => $achternaam,
                'afdeling_id' => $afdelingId,
                'functie_id' => $functieId,
                'email' => $docent->email,
            ]);
        }
    }

    /** Bestuur/Boekhouder/Medewerker: maak/werk een HR-dossier bij. */
    private function overigeMedewerker(string $nummer, string $voornaam, string $achternaam, int $functieId, int $afdelingId): void
    {
        $medewerker = Medewerker::firstOrNew(['personeelsnummer' => $nummer]);
        $medewerker->fill([
            'voornaam' => $voornaam,
            'achternaam' => $achternaam,
            'afdeling_id' => $afdelingId,
            'functie_id' => $functieId,
            'email' => $medewerker->email ?? $this->uniekEmail($voornaam, $achternaam),
            'status' => 'actief',
            'actief' => true,
        ]);
        $nieuw = ! $medewerker->exists;
        $medewerker->save();

        if ($nieuw) {
            $this->standaardDienstverband($medewerker, $functieId, $afdelingId);
        }
    }

    private function standaardDienstverband(Medewerker $medewerker, int $functieId, int $afdelingId): void
    {
        Dienstverband::firstOrCreate(
            ['medewerker_id' => $medewerker->id, 'startdatum' => '2024-09-01'],
            ['contracttype' => 'vast', 'uren_per_week' => 36, 'functie_id' => $functieId, 'afdeling_id' => $afdelingId]
        );
    }

    /** Uniek e-mailadres volgens {achternaam}@iuasr.nl, met initiaal-prefix bij botsing. */
    private function uniekEmail(string $voornaam, string $achternaam): string
    {
        $basis = $this->sleutel($achternaam);
        $adres = $basis.'@iuasr.nl';
        if (! $this->emailInGebruik($adres)) {
            return $adres;
        }
        $adres = $this->sleutel($voornaam).$basis.'@iuasr.nl';

        return $this->emailInGebruik($adres) ? $this->sleutel($voornaam).'.'.$basis.'.'.uniqid().'@iuasr.nl' : $adres;
    }

    private function emailInGebruik(string $adres): bool
    {
        return Medewerker::where('email', $adres)->exists() || User::where('email', $adres)->exists();
    }

    private function volgendeDocentcode(): string
    {
        $max = Docent::where('code', 'like', 'DOC-%')->get()
            ->map(fn ($d) => (int) substr((string) $d->code, 4))->max() ?? 0;

        return 'DOC-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private function sleutel(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($s), [
            'ç' => 'c', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'ş' => 's', 'ü' => 'u', 'ö' => 'o',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ï' => 'i', 'à' => 'a', 'â' => 'a',
        ]));
    }

    /** @return list<array{0:string,1:string,2:string,3:string}> */
    private function regels(string $pad): array
    {
        $handle = fopen($pad, 'r');
        fgetcsv($handle, 0, ';'); // koprij overslaan

        $regels = [];
        while (($k = fgetcsv($handle, 0, ';')) !== false) {
            if (count($k) < 4 || trim((string) $k[0]) === '') {
                continue;
            }
            $regels[] = [trim((string) $k[0]), trim((string) $k[1]), trim((string) $k[2]), trim((string) $k[3])];
        }
        fclose($handle);

        return $regels;
    }
}
