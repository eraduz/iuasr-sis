<?php

namespace Database\Seeders;

use App\Models\Docent;
use App\Models\Faculteit;
use App\Models\Klas;
use App\Models\Land;
use App\Models\Nationaliteit;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Toetsonderdeel;
use App\Models\Vak;
use Illuminate\Database\Seeder;

/**
 * Referentiedata (opzoektabellen). Synthetisch en afgestemd op de leidende
 * designs. De normen (voldoende_grens, ec_overgang_drempel) blijven bewust
 * null: OPENSTAANDE PARAMETERS die de opdrachtgever nog moet bevestigen.
 */
class ReferentieSeeder extends Seeder
{
    public function run(): void
    {
        $fiw = Faculteit::create(['code' => 'FIW', 'naam' => 'Faculteit Islamitische Wetenschappen']);
        $foo = Faculteit::create(['code' => 'FOO', 'naam' => 'Faculteit Onderwijs & Opvoeding']);

        foreach ([
            ['NL', 'Nederland'], ['TR', 'Turkije'], ['MA', 'Marokko'], ['SY', 'Syrië'],
        ] as [$code, $naam]) {
            Land::create(['code' => $code, 'naam' => $naam]);
        }

        foreach (['Nederlandse', 'Turkse', 'Marokkaanse', 'Syrische'] as $n) {
            Nationaliteit::create(['naam' => $n]);
        }

        Periode::create([
            'code' => '2026-2027', 'naam' => 'Studiejaar 2026 / 2027',
            'startdatum' => '2026-09-01', 'einddatum' => '2027-08-31', 'actief' => true,
        ]);
        Periode::create([
            'code' => '2025-2026', 'naam' => 'Studiejaar 2025 / 2026',
            'startdatum' => '2025-09-01', 'einddatum' => '2026-08-31', 'actief' => false,
        ]);

        // Docenten (synthetisch — komen overeen met de mockups).
        $aydin = Docent::create(['code' => 'DOC-001', 'aanhef' => 'dr.', 'voornaam' => 'Yusuf', 'achternaam' => 'Aydın', 'email' => 'y.aydin@iuasr.nl']);
        Docent::create(['code' => 'DOC-002', 'aanhef' => 'dr.', 'voornaam' => 'Salima', 'achternaam' => 'Boujat', 'email' => 's.boujat@iuasr.nl']);

        // Opleidingen — normen (voldoende_grens/ec_overgang_drempel) TE BEVESTIGEN → null.
        $theologie = Opleiding::create([
            'faculteit_id' => $fiw->id, 'code' => 'ISLTH', 'naam' => 'Bachelor Islamitische Theologie',
            'soort' => 'bachelor', 'nominale_jaren' => 4, 'ec_totaal' => 240,
            'voldoende_grens' => null, 'ec_overgang_drempel' => null, 'actief' => true,
        ]);
        Opleiding::create([
            'faculteit_id' => $foo->id, 'code' => 'PABO', 'naam' => 'PABO — Leraar Basisonderwijs',
            'soort' => 'bachelor', 'nominale_jaren' => 4, 'ec_totaal' => 240,
            'voldoende_grens' => null, 'ec_overgang_drempel' => null, 'actief' => true,
        ]);
        Opleiding::create([
            'faculteit_id' => $fiw->id, 'code' => 'MGV', 'naam' => 'Master Isl. Geestelijke Verzorging',
            'soort' => 'master', 'nominale_jaren' => 2, 'ec_totaal' => 120,
            'voldoende_grens' => null, 'ec_overgang_drempel' => null, 'actief' => true,
        ]);
        Opleiding::create([
            'faculteit_id' => $fiw->id, 'code' => 'KRN', 'naam' => 'Cursus Koran & Hifz',
            'soort' => 'cursus', 'nominale_jaren' => 1, 'ec_totaal' => null,
            'voldoende_grens' => null, 'ec_overgang_drempel' => null, 'actief' => true,
        ]);

        // Klassen (opleiding + leerjaar).
        for ($jaar = 1; $jaar <= 4; $jaar++) {
            Klas::create([
                'opleiding_id' => $theologie->id, 'code' => "IT-{$jaar}",
                'naam' => "Islamitische Theologie jaar {$jaar}", 'leerjaar' => $jaar, 'groep' => 'dag',
            ]);
        }
        $pabo = Opleiding::where('code', 'PABO')->first();
        $mgv = Opleiding::where('code', 'MGV')->first();
        $krn = Opleiding::where('code', 'KRN')->first();
        Klas::create(['opleiding_id' => $pabo->id, 'code' => 'PB-1A', 'naam' => 'PABO jaar 1A', 'leerjaar' => 1, 'groep' => 'dag']);
        Klas::create(['opleiding_id' => $pabo->id, 'code' => 'PB-2B', 'naam' => 'PABO jaar 2B', 'leerjaar' => 2, 'groep' => 'dag']);
        Klas::create(['opleiding_id' => $mgv->id, 'code' => 'MGV-D', 'naam' => 'MGV deeltijd', 'leerjaar' => 1, 'groep' => 'deeltijd']);
        Klas::create(['opleiding_id' => $krn->id, 'code' => 'KH-2', 'naam' => 'Koran & Hifz jaar 2', 'leerjaar' => 2, 'groep' => 'deeltijd']);

        // Voorbeeldvak met genormaliseerde toetsstructuur (deelresultaten + weging).
        $vak = Vak::create([
            'opleiding_id' => $theologie->id, 'docent_id' => $aydin->id,
            'code' => 'ISLTH-ARA-201', 'naam' => 'Arabische grammatica II',
            'ec' => 6, 'leerjaar' => 2, 'blok' => 1, 'actief' => true,
        ]);
        Toetsonderdeel::create(['vak_id' => $vak->id, 'code' => 'TEN', 'naam' => 'Schriftelijk tentamen', 'type' => 'tentamen', 'weging' => 0.60, 'telt_mee' => true, 'volgorde' => 1]);
        Toetsonderdeel::create(['vak_id' => $vak->id, 'code' => 'WST', 'naam' => 'Werkstuk', 'type' => 'werkstuk', 'weging' => 0.25, 'telt_mee' => true, 'volgorde' => 2]);
        Toetsonderdeel::create(['vak_id' => $vak->id, 'code' => 'PRE', 'naam' => 'Presentatie', 'type' => 'presentatie', 'weging' => 0.15, 'telt_mee' => true, 'volgorde' => 3]);
    }
}
