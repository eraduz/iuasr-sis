<?php

namespace Database\Seeders;

use App\Enums\BalieRichting;
use App\Enums\BalieSoort;
use App\Models\BalieRegistratie;
use App\Models\Medewerker;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Synthetische balieregistraties (AVG: nooit echte namen of telefoonnummers).
 * Geeft een gevulde week zodat de filters, het "nu in het pand"-overzicht en de
 * export meteen iets laten zien.
 *
 * Idempotent: draait de seeder een tweede keer, dan gebeurt er niets. Het
 * balie-account zelf wordt door de migratie aangemaakt, niet hier.
 */
class BalieSeeder extends Seeder
{
    public function run(): void
    {
        if (BalieRegistratie::query()->exists()) {
            return;
        }

        $balie = User::where('email', 'balie@iuasr.nl')->first();
        $medewerkers = Medewerker::query()->orderBy('id')->limit(6)->get();

        if ($medewerkers->isEmpty()) {
            return; // Zonder medewerkers valt er niets te koppelen; HR-seeders draaien eerst.
        }

        $voor = fn (int $i) => $medewerkers[$i % $medewerkers->count()]->id;

        $registraties = [
            // Inkomende telefoongesprekken.
            [BalieSoort::Telefoon, BalieRichting::Inkomend, '-3 days 09:15', 'Vraag over inschrijving', 'Yusuf Demir', null, '010-1234567', $voor(0), null, 'Wil weten of zijn inschrijving is verwerkt. Teruggebeld verzocht.'],
            [BalieSoort::Telefoon, BalieRichting::Inkomend, '-2 days 11:40', 'Ziekmelding docent', 'Amina el Fassi', null, '06-11223344', $voor(1), null, 'Meldt zich ziek voor het college van vanmiddag.'],
            [BalieSoort::Telefoon, BalieRichting::Inkomend, '-1 day 14:05', 'Vraag over collegegeld', 'Nour Haddad', null, '06-99887766', null, 'Financiële Administratie', 'Doorverbonden; termijn stond nog open.'],
            [BalieSoort::Telefoon, BalieRichting::Inkomend, 'today 09:05', 'Aanvraag verklaring', 'Sami Bouzid', null, '06-55443322', null, 'Studentenzaken', 'Vraagt een bewijs van inschrijving aan.'],

            // Uitgaande telefoongesprekken.
            [BalieSoort::Telefoon, BalieRichting::Uitgaand, '-2 days 15:20', 'Afspraak bevestigen', 'Stichting Al-Noor', 'Stichting Al-Noor', '010-7654321', $voor(2), null, 'Afspraak van volgende week bevestigd.'],
            [BalieSoort::Telefoon, BalieRichting::Uitgaand, 'today 10:30', 'Terugbelverzoek afgehandeld', 'Yusuf Demir', null, '010-1234567', $voor(0), null, 'Teruggebeld over de inschrijving; afgehandeld.'],

            // Bezoekers — de laatste twee zijn nog niet afgemeld (staan "in het pand").
            [BalieSoort::Bezoek, BalieRichting::Inkomend, '-1 day 10:00', 'Kennismakingsgesprek', 'Fatima Bakkali', 'Gemeente Rotterdam', null, $voor(3), null, 'Kennismaking over samenwerking.', '-1 day 11:15'],
            [BalieSoort::Bezoek, BalieRichting::Inkomend, 'today 09:30', 'Sollicitatiegesprek', 'Hakan Yilmaz', null, null, $voor(4), null, 'Sollicitant voor de vacature docent Arabisch.'],
            [BalieSoort::Bezoek, BalieRichting::Inkomend, 'today 11:00', 'Levering kantoorartikelen', 'Koeriersdienst De Maas', 'De Maas Koeriers', null, null, 'Facilitair', 'Pakket afgegeven bij de balie.'],

            // Inkomende post.
            [BalieSoort::Post, BalieRichting::Inkomend, '-3 days 08:45', null, 'DUO', 'DUO', null, $voor(1), null, 'Aangetekende brief; doorgegeven aan de directie.'],
            [BalieSoort::Post, BalieRichting::Inkomend, '-1 day 08:50', null, 'Belastingdienst', 'Belastingdienst', null, null, 'Financiële Administratie', 'Blauwe envelop, doorgegeven.'],

            // Uitgaande post.
            [BalieSoort::Post, BalieRichting::Uitgaand, '-2 days 16:10', null, 'Inspectie van het Onderwijs', 'Inspectie van het Onderwijs', null, $voor(2), null, 'Jaarverslag aangetekend verzonden.'],
            [BalieSoort::Post, BalieRichting::Uitgaand, 'today 15:45', null, 'Stichting Al-Noor', 'Stichting Al-Noor', null, $voor(3), null, 'Ondertekende samenwerkingsovereenkomst verzonden.'],
        ];

        foreach ($registraties as $r) {
            BalieRegistratie::create([
                'soort' => $r[0],
                'richting' => $r[1],
                'datum_tijd' => Carbon::parse($r[2]),
                'onderwerp' => $r[3],
                'contact_naam' => $r[4],
                'contact_organisatie' => $r[5],
                'contact_telefoon' => $r[6],
                'medewerker_id' => $r[7],
                'afdeling' => $r[8],
                'toelichting' => $r[9],
                'vertrokken_op' => isset($r[10]) ? Carbon::parse($r[10]) : null,
                'geregistreerd_door_user_id' => $balie?->id,
            ]);
        }
    }
}
