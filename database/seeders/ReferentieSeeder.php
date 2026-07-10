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

        // Bestaande (niet-EU) landen + alle 27 EU-lidstaten. Nederland staat maar
        // één keer in de lijst. Codes zijn ISO 3166-1 alpha-2.
        foreach ([
            ['NL', 'Nederland'], ['TR', 'Turkije'], ['MA', 'Marokko'], ['SY', 'Syrië'],
            // EU-lidstaten
            ['BE', 'België'], ['BG', 'Bulgarije'], ['CY', 'Cyprus'], ['DK', 'Denemarken'],
            ['DE', 'Duitsland'], ['EE', 'Estland'], ['FI', 'Finland'], ['FR', 'Frankrijk'],
            ['GR', 'Griekenland'], ['HU', 'Hongarije'], ['IE', 'Ierland'], ['IT', 'Italië'],
            ['HR', 'Kroatië'], ['LV', 'Letland'], ['LT', 'Litouwen'], ['LU', 'Luxemburg'],
            ['MT', 'Malta'], ['AT', 'Oostenrijk'], ['PL', 'Polen'], ['PT', 'Portugal'],
            ['RO', 'Roemenië'], ['SI', 'Slovenië'], ['SK', 'Slowakije'], ['ES', 'Spanje'],
            ['CZ', 'Tsjechië'], ['SE', 'Zweden'],
        ] as [$code, $naam]) {
            Land::firstOrCreate(['code' => $code], ['naam' => $naam]);
        }

        // Bestaande nationaliteiten + de EU-nationaliteiten (Nederlandse één keer).
        foreach ([
            'Nederlandse', 'Turkse', 'Marokkaanse', 'Syrische',
            'Belgische', 'Bulgaarse', 'Cypriotische', 'Deense', 'Duitse', 'Estse',
            'Finse', 'Franse', 'Griekse', 'Hongaarse', 'Ierse', 'Italiaanse',
            'Kroatische', 'Letse', 'Litouwse', 'Luxemburgse', 'Maltese', 'Oostenrijkse',
            'Poolse', 'Portugese', 'Roemeense', 'Sloveense', 'Slowaakse', 'Spaanse',
            'Tsjechische', 'Zweedse',
        ] as $n) {
            Nationaliteit::firstOrCreate(['naam' => $n]);
        }

        // Studiejaar loopt van 1 september t/m 31 juli. De seeder legt 2025-2026 als
        // actief vast (referentie-/testbasislijn met 2026-2027 als "komend jaar").
        // De daadwerkelijke jaarovergang naar 2026-2027 gebeurt op de draaiende
        // database via de datamigratie 'activeer_studiejaar_2026_2027' én is daarna
        // door Beheer te sturen via Opzoektabellen → Studiejaren (vinkje 'Huidig
        // studiejaar'), dat automatisch het vorige jaar deactiveert.
        Periode::create([
            'code' => '2025-2026', 'naam' => 'Studiejaar 2025 / 2026',
            'startdatum' => '2025-09-01', 'einddatum' => '2026-07-31', 'actief' => true,
        ]);
        Periode::create([
            'code' => '2026-2027', 'naam' => 'Studiejaar 2026 / 2027',
            'startdatum' => '2026-09-01', 'einddatum' => '2027-07-31', 'actief' => false,
        ]);
        Periode::create([
            'code' => '2024-2025', 'naam' => 'Studiejaar 2024 / 2025',
            'startdatum' => '2024-09-01', 'einddatum' => '2025-07-31', 'actief' => false,
        ]);

        // Extra toekomstige studiejaren, klaar voor productie (inactief; slechts
        // één studiejaar is tegelijk actief).
        foreach (range(2027, 2031) as $jaar) {
            Periode::firstOrCreate(
                ['code' => $jaar.'-'.($jaar + 1)],
                [
                    'naam' => 'Studiejaar '.$jaar.' / '.($jaar + 1),
                    'startdatum' => $jaar.'-09-01', 'einddatum' => ($jaar + 1).'-07-31', 'actief' => false,
                ],
            );
        }

        // Docenten (synthetisch — komen overeen met de mockups).
        $aydin = Docent::create(['code' => 'DOC-001', 'aanhef' => 'dr.', 'voornaam' => 'Yusuf', 'achternaam' => 'Aydın', 'email' => 'y.aydin@iuasr.nl']);
        Docent::create(['code' => 'DOC-002', 'aanhef' => 'dr.', 'voornaam' => 'Salima', 'achternaam' => 'Boujat', 'email' => 's.boujat@iuasr.nl']);

        // Opleidingen. voldoende_grens = 5,5. EC-overgangsdrempel voor bachelors:
        // 30 EC (landelijke BSA-norm vanaf studiejaar 2026-2027) als vertrekpunt;
        // per opleiding aan te passen conform de OER (Beheer → Opzoektabellen).
        $theologie = Opleiding::create([
            'faculteit_id' => $fiw->id, 'code' => 'ISLTH', 'naam' => 'Bachelor Islamitische Theologie',
            'soort' => 'bachelor', 'nominale_jaren' => 4, 'ec_totaal' => 240,
            'voldoende_grens' => 5.5, 'ec_overgang_drempel' => 30, 'actief' => true,
        ]);
        Opleiding::create([
            'faculteit_id' => $foo->id, 'code' => 'PABO', 'naam' => 'PABO — Leraar Basisonderwijs',
            'soort' => 'bachelor', 'nominale_jaren' => 4, 'ec_totaal' => 240,
            'voldoende_grens' => 5.5, 'ec_overgang_drempel' => 30, 'actief' => true,
        ]);
        Opleiding::create([
            'faculteit_id' => $fiw->id, 'code' => 'PMGV', 'naam' => 'Pre-Master Islamitische Geestelijke Verzorging',
            // BEVESTIGD (opdrachtgever, 2026-07-10): de pre-master telt 50 EC,
            // conform het curriculum (12 vakken, samen 50 EC) — geen 60.
            'soort' => 'premaster', 'nominale_jaren' => 1, 'ec_totaal' => 50,
            'actief' => true,
        ]);
        Opleiding::create([
            'faculteit_id' => $fiw->id, 'code' => 'MGV', 'naam' => 'Master Islamitische Geestelijke Verzorging',
            'soort' => 'master', 'nominale_jaren' => 2, 'ec_totaal' => 120,
            'voldoende_grens' => 5.5, 'ec_overgang_drempel' => null, 'actief' => true,
        ]);
        Opleiding::create([
            'faculteit_id' => $fiw->id, 'code' => 'KRN', 'naam' => 'Cursus Koran & Hifz',
            'soort' => 'cursus', 'nominale_jaren' => 1, 'ec_totaal' => null,
            'voldoende_grens' => 5.5, 'ec_overgang_drempel' => null, 'actief' => true,
        ]);
        Opleiding::create([
            'faculteit_id' => $fiw->id, 'code' => 'ARAB', 'naam' => 'Cursus Arabisch',
            'soort' => 'cursus', 'nominale_jaren' => 1, 'ec_totaal' => null,
            'voldoende_grens' => 5.5, 'ec_overgang_drempel' => null, 'actief' => true,
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

        // Vastgestelde collegegeldtarieven 2026-2027 (opdrachtgever 2026-07-10).
        // Ook via een migratie gezet voor reeds bestaande databases; hier voor een
        // verse migrate:fresh --seed, waar de migratie op een lege DB draaide.
        $sj2627 = Periode::where('code', '2026-2027')->first();
        if ($sj2627) {
            foreach (['ISLTH' => 3500.00, 'PMGV' => 3500.00, 'MGV' => 4000.00, 'PABO' => 3500.00] as $code => $bedrag) {
                $opleiding = Opleiding::where('code', $code)->first();
                if ($opleiding) {
                    \App\Models\CollegegeldTarief::firstOrCreate(
                        ['periode_id' => $sj2627->id, 'opleiding_id' => $opleiding->id],
                        ['bedrag' => $bedrag, 'aantal_termijnen' => 5],
                    );
                }
            }
        }

        // Het ECHTE curriculum staat in CurriculumSeeder (database/data/curriculum.csv).
        // De synthetische voorbeeldvakken (ISLTH-*) zijn verhuisd naar
        // SynthetischVakSeeder: die is alleen een testfixture en mag niet naast
        // het echte curriculum actief staan, omdat hij anders meetelt in de
        // EC-totalen en automatisch aan studenten wordt toegewezen.
    }
}
