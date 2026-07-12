<?php

namespace Database\Seeders;

use App\Enums\InschrijvingStatus;
use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Nationaliteit;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Support\Vaktoewijzer;
use Illuminate\Database\Seeder;

/**
 * SYNTHETISCHE studenten voor het testen met collega's (opdrachtgever 2026-07-12):
 * een ruimere populatie, verdeeld over alle leerjaren van de opleidingen. Verzonnen
 * namen, geen echte personen; BSN wordt NIET geseed.
 *
 * Studenten krijgen meteen de verplichte vakken van hun leerjaar toegewezen
 * (via {@see Vaktoewijzer}), zodat ze bruikbaar zijn in cijferinvoer,
 * aanwezigheidsregistratie en rapportages. Idempotent: bestaande studentnummers
 * worden overgeslagen. Studentnummer: cohort-jaarprefix (2) + volgnummer (4).
 */
class ExtraStudentenSeeder extends Seeder
{
    /** @var list<string> */
    private const VOORNAMEN_M = [
        'Ahmed', 'Bilal', 'Yusuf', 'Omar', 'Hamza', 'Ismail', 'Karim', 'Tarik', 'Younes',
        'Zakaria', 'Mohammed', 'Anas', 'Rayan', 'Idris', 'Soufyan', 'Adil', 'Nabil', 'Reda',
        'Emir', 'Kaan', 'Musa', 'Ilyas', 'Yasin', 'Hicham',
    ];

    /** @var list<string> */
    private const VOORNAMEN_V = [
        'Yasmin', 'Aïsha', 'Fatima', 'Nora', 'Maryam', 'Sara', 'Amal', 'Zaynab', 'Salma',
        'Lina', 'Hafsa', 'Rania', 'Imane', 'Nadia', 'Sumaya', 'Khadija', 'Esma', 'Rabia',
        'Hind', 'Dunya', 'Yara', 'Malak', 'Zahra', 'Latifa',
    ];

    /** @var list<string> */
    private const ACHTERNAMEN = [
        'Demir', 'Akın', 'El Bouzidi', 'Belhaj', 'El Idrissi', 'Ouahbi', 'Bennani', 'Yıldırım',
        'Haddadi', 'Ahmadi', 'Chakir', 'Amrani', 'Karadeniz', 'Sabri', 'Boutaib', 'Tahiri',
        'Ziani', 'Benali', 'Fassi', 'Loukili', 'Berrada', 'Çetin', 'Kaya', 'Arslan', 'Doğan',
        'Şahin', 'El Hamdaoui', 'Nassiri', 'Rifi', 'Bouchta',
    ];

    /** @var list<string> */
    private const PLAATSEN = [
        'Rotterdam', 'Den Haag', 'Utrecht', 'Amsterdam', 'Schiedam', 'Dordrecht', 'Delft',
        'Gouda', 'Zoetermeer', 'Leiden', 'Capelle a/d IJssel', 'Vlaardingen', 'Spijkenisse',
        'Almere', 'Tilburg', 'Breda',
    ];

    public function run(): void
    {
        $periode = Periode::where('actief', true)->first();
        if (! $periode) {
            return;
        }
        $nlId = Nationaliteit::where('naam', 'Nederlandse')->value('id');
        $start = substr((string) $periode->startdatum, 0, 10); // studiejaarstart, bv. 2026-09-01

        // [opleidingcode, leerjaar, jaarprefix, body-start (4 cijfers), aantal].
        // De jaarprefix weerspiegelt het cohort; de bodyranges zijn uniek zodat
        // er geen botsing is met bestaande nummers (261001..261014).
        $config = [
            ['ISLTH', 1, '26', 1050, 12],
            ['ISLTH', 2, '25', 2001, 12],
            ['ISLTH', 3, '24', 3001, 10],
            ['ISLTH', 4, '23', 4001, 8],
            ['MGV', 1, '26', 5001, 8],
            ['MGV', 2, '25', 5001, 6],
            ['PMGV', 1, '26', 6001, 6],
            ['PABO', 1, '26', 7001, 6],
            ['PABO', 2, '25', 7001, 4],
            ['PABO', 3, '24', 7001, 4],
            ['PABO', 4, '23', 7001, 4],
        ];

        $i = 0;      // doorlopende index voor naamvariatie
        $nieuw = 0;
        foreach ($config as [$oplCode, $leerjaar, $prefix, $bodyStart, $aantal]) {
            $opl = Opleiding::where('code', $oplCode)->first();
            if (! $opl) {
                continue;
            }
            $klas = Klas::where('opleiding_id', $opl->id)->where('leerjaar', $leerjaar)->first();

            for ($k = 0; $k < $aantal; $k++, $i++) {
                $nr = $prefix.str_pad((string) ($bodyStart + $k), 4, '0', STR_PAD_LEFT);

                $man = $i % 2 === 0;
                $voornaam = $man
                    ? self::VOORNAMEN_M[$i % count(self::VOORNAMEN_M)]
                    : self::VOORNAMEN_V[($i * 5 + 2) % count(self::VOORNAMEN_V)];
                $achternaam = self::ACHTERNAMEN[($i * 3 + 1) % count(self::ACHTERNAMEN)];
                $plaats = self::PLAATSEN[($i * 2) % count(self::PLAATSEN)];

                // Leeftijd bij het leerjaar: eerstejaars ~18, ouderejaars ouder.
                $geboortejaar = 2009 - $leerjaar - ($i % 3);
                $geb = sprintf('%04d-%02d-%02d', $geboortejaar, ($i % 12) + 1, ($i % 27) + 1);

                $student = Student::firstOrCreate(
                    ['studentnummer' => $nr],
                    [
                        'voornaam' => $voornaam,
                        'roepnaam' => $voornaam,
                        'achternaam' => $achternaam,
                        'geslacht' => $man ? 'M' : 'V',
                        'geboortedatum' => $geb,
                        'geboorteplaats' => $plaats,
                        'nationaliteit_id' => $nlId,
                        'email' => $this->email($voornaam, $achternaam),
                        'telefoon' => '06 '.implode(' ', str_split($nr, 2)),
                        'taal_nederlands' => 'voldoende',
                        'taal_arabisch' => ['goed', 'voldoende', 'onvoldoende'][$i % 3],
                        'nt2_examen_vereist' => false,
                    ],
                );

                if (! $student->wasRecentlyCreated) {
                    continue; // idempotent: bestaat al
                }
                $nieuw++;

                $inschrijving = Inschrijving::create([
                    'student_id' => $student->id,
                    'opleiding_id' => $opl->id,
                    'klas_id' => $klas?->id,
                    'periode_id' => $periode->id,
                    'leerjaar' => $leerjaar,
                    'status' => InschrijvingStatus::Actief,
                    'inschrijfdatum' => $start,
                    'invoerdatum' => $start,
                ]);

                // Verplichte vakken van het leerjaar toewijzen (voor cijfers/aanwezigheid).
                Vaktoewijzer::wijsToe($inschrijving);
            }
        }

        $this->command?->info("Extra studenten: {$nieuw} aangemaakt.");
    }

    private function email(string $voornaam, string $achternaam): string
    {
        $schoon = fn (string $s): string => preg_replace('/[^a-z0-9]/', '',
            strtr(mb_strtolower($s), ['ç' => 'c', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'ş' => 's',
                'ü' => 'u', 'ö' => 'o', 'ï' => 'i', 'ë' => 'e', 'é' => 'e', 'à' => 'a']));

        return $schoon(mb_substr($voornaam, 0, 1)).'.'.$schoon($achternaam).'@student.iuasr.nl';
    }
}
