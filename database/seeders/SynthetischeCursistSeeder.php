<?php

namespace Database\Seeders;

use App\Enums\CursusinschrijvingStatus;
use App\Models\Cursist;
use App\Models\Cursus;
use Illuminate\Database\Seeder;

/**
 * Synthetische cursisten voor testdoeleinden: een handvol per cursus, met
 * wisselende betaalstatussen (volledig betaald, deels betaald, openstaand), zodat
 * het dashboard, de cursusgelden en de rapportage meteen gevulde cijfers tonen.
 *
 * Uitsluitend synthetische data (geen echte persoonsgegevens). Idempotent: elke
 * cursist heeft een vast cursistnummer, dus opnieuw draaien maakt geen dubbelen.
 */
class SynthetischeCursistSeeder extends Seeder
{
    public function run(): void
    {
        // [voornaam, tussenvoegsel, achternaam, betaalfractie]  (fractie van het cursusgeld dat is betaald)
        $perCursus = [
            'ARAB-TAAL' => [
                ['Yusuf', null, 'Demir', 1.0],
                ['Amina', 'el', 'Fassi', 1.0],
                ['Bilal', null, 'Kaya', 0.5],
                ['Noor', null, 'Haddad', 0.0],
            ],
            'HIFZ' => [
                ['Ibrahim', null, 'Yılmaz', 1.0],
                ['Khadija', null, 'Mansour', 0.5],
                ['Omar', null, 'Benali', 0.0],
                ['Salma', 'van der', 'Berg', 1.0],
            ],
            'IJAZA' => [
                ['Zakaria', null, 'Aydın', 1.0],
                ['Layla', null, 'Nasser', 0.5],
                ['Tariq', null, 'El Amrani', 0.0],
                ['Fatima', null, 'Cetin', 1.0],
            ],
        ];

        $volgnr = 9001; // synthetische reeks, ver van de generator-reeks (0001...)

        foreach ($perCursus as $code => $cursisten) {
            $cursus = Cursus::where('code', $code)->first();
            if (! $cursus) {
                continue;
            }

            foreach ($cursisten as [$voornaam, $tussenvoegsel, $achternaam, $fractie]) {
                $nummer = 'C26'.$volgnr++;

                $cursist = Cursist::firstOrCreate(
                    ['cursistnummer' => $nummer],
                    [
                        'voornaam' => $voornaam,
                        'tussenvoegsel' => $tussenvoegsel,
                        'achternaam' => $achternaam,
                        'email' => strtolower($voornaam.'.'.str_replace(' ', '', $achternaam)).'@voorbeeld.test',
                        'status' => 'actief',
                    ]
                );

                $inschrijving = $cursist->inschrijvingen()->firstOrCreate(
                    ['cursus_id' => $cursus->id],
                    [
                        'inschrijfdatum' => now()->subDays(30),
                        'status' => CursusinschrijvingStatus::Actief,
                        'totaalbedrag' => $cursus->cursusgeld,
                    ]
                );

                $bedrag = round((float) $cursus->cursusgeld * $fractie, 2);
                if ($bedrag > 0 && $inschrijving->betalingen()->count() === 0) {
                    $inschrijving->betalingen()->create([
                        'betaalmethode' => 'ideal',
                        'bedrag' => $bedrag,
                        'betaaldatum' => now()->subDays(20),
                        'betalingsstatus' => 'betaald',
                        'opmerking' => 'Synthetische testbetaling',
                    ]);
                }
            }
        }
    }
}
