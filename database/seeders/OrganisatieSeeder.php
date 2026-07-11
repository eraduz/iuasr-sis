<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Enums\Stagestatus;
use App\Models\Afspraak;
use App\Models\Contactmoment;
use App\Models\ContactmomentType;
use App\Models\Contactpersoon;
use App\Models\Opleiding;
use App\Models\Organisatie;
use App\Models\OrganisatieType;
use App\Models\RelatieNotitie;
use App\Models\Relatietaak;
use App\Models\Stage;
use App\Models\Stageplaats;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Synthetische organisaties/relaties en organisatietypes voor de module
 * Relatiebeheer & Stagebeheer. Uitsluitend SYNTHETISCHE data (AVG). De types
 * zijn per opleiding ingericht (PABO, Bachelor Islamitische Theologie, Master
 * IGV) plus een generiek type voor alle opleidingen.
 */
class OrganisatieSeeder extends Seeder
{
    public function run(): void
    {
        $opl = fn (string $code) => Opleiding::where('code', $code)->value('id');

        $pabo = $opl('PABO');
        $islth = $opl('ISLTH');
        $mgv = $opl('MGV');

        // Organisatietypes — per opleiding configureerbaar (opleiding_id = null = alle).
        $types = [];
        foreach ([
            ['SAMENWERKINGSPARTNER', 'Samenwerkingspartner', null],
            ['BASISSCHOOL', 'Basisschool', $pabo],
            ['SCHOOLBESTUUR', 'Schoolbestuur', $pabo],
            ['OPLEIDINGSSCHOOL', 'Opleidingsschool', $pabo],
            ['ZORGINSTELLING', 'Zorginstelling', $mgv],
            ['ZIEKENHUIS', 'Ziekenhuis', $mgv],
            ['JUSTITIE', 'Justitiële inrichting', $mgv],
            ['MOSKEE', 'Moskee', $islth],
            ['GEMEENSCHAP', 'Geloofsgemeenschap', $islth],
        ] as [$code, $naam, $opleidingId]) {
            $types[$code] = OrganisatieType::firstOrCreate(
                ['code' => $code],
                ['naam' => $naam, 'opleiding_id' => $opleidingId, 'actief' => true]
            )->id;
        }

        // Synthetische organisaties, elk gekoppeld aan de relevante opleiding(en).
        $organisaties = [
            ['R260001', 'Basisschool De Regenboog', 'BASISSCHOOL', 'Rotterdam', 'Zuid-Holland', '01AB', ['PABO']],
            ['R260002', 'Stichting Islamitisch Primair Onderwijs', 'SCHOOLBESTUUR', 'Den Haag', 'Zuid-Holland', null, ['PABO']],
            ['R260003', 'Zorggroep Rijnmond', 'ZORGINSTELLING', 'Rotterdam', 'Zuid-Holland', null, ['MGV']],
            ['R260004', 'Penitentiaire Inrichting Zuid', 'JUSTITIE', 'Dordrecht', 'Zuid-Holland', null, ['MGV']],
            ['R260005', 'Moskee An-Nasr', 'MOSKEE', 'Utrecht', 'Utrecht', null, ['ISLTH']],
            ['R260006', 'Stichting Dialoog & Samenleving', 'SAMENWERKINGSPARTNER', 'Amsterdam', 'Noord-Holland', null, ['ISLTH', 'MGV']],
        ];

        foreach ($organisaties as [$nummer, $naam, $typeCode, $plaats, $provincie, $brin, $opleidingCodes]) {
            $organisatie = Organisatie::firstOrCreate(
                ['relatienummer' => $nummer],
                [
                    'naam' => $naam,
                    'organisatie_type_id' => $types[$typeCode] ?? null,
                    'brin_nummer' => $brin,
                    'plaats' => $plaats,
                    'provincie' => $provincie,
                    'actief' => true,
                ]
            );

            $ids = collect($opleidingCodes)->map($opl)->filter()->all();
            $organisatie->opleidingen()->sync($ids);
        }

        // Synthetische contactpersonen per organisatie (Fase B). Idempotent op
        // organisatie + achternaam.
        $contactpersonen = [
            ['R260001', 'Miriam', 'Bakker', 'Directeur', 'm.bakker@deregenboog-po.nl', 'e-mail'],
            ['R260001', 'Youssef', 'El Amrani', 'Stagecoördinator', 'y.elamrani@deregenboog-po.nl', 'telefoon'],
            ['R260002', 'Peter', 'Van Dijk', 'Bestuurssecretaris', 'p.vandijk@sipo.nl', 'e-mail'],
            ['R260003', 'Fatima', 'Ouali', 'Manager zorg', 'f.ouali@zorggroeprijnmond.nl', 'teams'],
            ['R260004', 'Hendrik', 'De Vries', 'Hoofd geestelijke verzorging', 'h.devries@dji.nl', 'e-mail'],
            ['R260005', 'Abdullah', 'Yaman', 'Voorzitter', 'voorzitter@moskee-annasr.nl', 'telefoon'],
        ];

        foreach ($contactpersonen as [$nummer, $voornaam, $achternaam, $functie, $email, $voorkeur]) {
            $organisatie = Organisatie::where('relatienummer', $nummer)->first();
            if ($organisatie === null) {
                continue;
            }

            Contactpersoon::firstOrCreate(
                ['organisatie_id' => $organisatie->id, 'achternaam' => $achternaam],
                [
                    'voornaam' => $voornaam,
                    'functie' => $functie,
                    'email' => $email,
                    'voorkeur_communicatie' => $voorkeur,
                    'actief' => true,
                ]
            );
        }

        // Contactmoment-types (Fase C) — beheerd via Opzoektabellen.
        $typeIds = [];
        foreach ([
            ['TELEFOON', 'Telefoongesprek', 1],
            ['EMAIL', 'E-mail', 2],
            ['TEAMS', 'Teams', 3],
            ['BEZOEK', 'Bezoek', 4],
            ['STAGEBEZOEK', 'Stagebezoek', 5],
            ['OVERLEG', 'Overleg', 6],
            ['NETWERK', 'Netwerkbijeenkomst', 7],
            ['KLACHT', 'Klacht', 8],
            ['EVALUATIE', 'Evaluatie', 9],
        ] as [$code, $naam, $volg]) {
            $typeIds[$code] = ContactmomentType::firstOrCreate(
                ['code' => $code],
                ['naam' => $naam, 'volgorde' => $volg, 'actief' => true]
            )->id;
        }

        // Demo contactmomenten + notitie (Fase C) op de PABO-stageschool.
        $medewerkerId = User::where('email', 'l.haddad@iuasr.nl')->value('id');
        $regenboog = Organisatie::where('relatienummer', 'R260001')->first();
        if ($regenboog !== null) {
            Contactmoment::firstOrCreate(
                ['organisatie_id' => $regenboog->id, 'onderwerp' => 'Kennismakingsgesprek nieuwe stageperiode'],
                ['contactmoment_type_id' => $typeIds['BEZOEK'] ?? null, 'medewerker_id' => $medewerkerId, 'datum' => '2026-09-02', 'samenvatting' => 'Afspraken gemaakt over het aantal stageplaatsen en de begeleiding.', 'vervolgdatum' => '2026-10-01']
            );
            Contactmoment::firstOrCreate(
                ['organisatie_id' => $regenboog->id, 'onderwerp' => 'Telefonisch: beschikbaarheid werkplekbegeleiders'],
                ['contactmoment_type_id' => $typeIds['TELEFOON'] ?? null, 'medewerker_id' => $medewerkerId, 'datum' => '2026-09-15', 'samenvatting' => 'Twee werkplekbegeleiders beschikbaar voor het komende blok.']
            );
            RelatieNotitie::firstOrCreate(
                ['organisatie_id' => $regenboog->id, 'tekst' => 'Prettige samenwerking; directeur reageert snel op e-mail.'],
                ['auteur_id' => $medewerkerId, 'categorie' => 'Samenwerking', 'tags' => 'stage']
            );
        }

        // Stageplaatsen (Fase D) — aanbod/capaciteit per opleiding.
        $paboId = $opl('PABO');
        $mgvId = $opl('MGV');
        foreach ([
            ['R260001', $paboId, 1, 4, 4, 'ma, di, do'],
            ['R260001', $paboId, 2, 3, 3, 'ma, wo, vr'],
            ['R260003', $mgvId, null, 2, 2, 'in overleg'],
        ] as [$nummer, $oplId, $leerjaar, $aantal, $max, $werkdagen]) {
            $org = Organisatie::where('relatienummer', $nummer)->first();
            if ($org === null || $oplId === null) {
                continue;
            }
            Stageplaats::firstOrCreate(
                ['organisatie_id' => $org->id, 'opleiding_id' => $oplId, 'leerjaar' => $leerjaar],
                ['aantal_plaatsen' => $aantal, 'max_studenten' => $max, 'werkdagen' => $werkdagen, 'actief' => true]
            );
        }

        // Demo-stage (Fase D): een PABO-student geplaatst op Basisschool De Regenboog.
        $paboStudent = Student::where('studentnummer', '261003')->first();
        if ($paboStudent !== null && $regenboog !== null && $paboId !== null) {
            $begeleiderId = User::where('rol', Rol::Docent)->value('id');
            $werkplekbegeleiderId = Contactpersoon::where('organisatie_id', $regenboog->id)->value('id');
            $plaatsId = Stageplaats::where('organisatie_id', $regenboog->id)->where('opleiding_id', $paboId)->where('leerjaar', 1)->value('id');

            Stage::firstOrCreate(
                ['stagenummer' => 'S260001'],
                [
                    'student_id' => $paboStudent->id,
                    'organisatie_id' => $regenboog->id,
                    'stageplaats_id' => $plaatsId,
                    'opleiding_id' => $paboId,
                    'stagebegeleider_id' => $begeleiderId,
                    'werkplekbegeleider_id' => $werkplekbegeleiderId,
                    'startdatum' => '2026-09-01',
                    'einddatum' => '2027-01-31',
                    'status' => Stagestatus::Lopend->value,
                ]
            );
        }

        // Demo-taak en -afspraak (Fase E) op Basisschool De Regenboog.
        if ($regenboog !== null) {
            Relatietaak::firstOrCreate(
                ['organisatie_id' => $regenboog->id, 'titel' => 'Samenwerkingsovereenkomst verlengen'],
                [
                    'toegewezen_aan_id' => $medewerkerId,
                    'aangemaakt_door_id' => $medewerkerId,
                    'prioriteit' => 'hoog',
                    'status' => 'open',
                    'vervaldatum' => '2026-10-15',
                ]
            );
            Afspraak::firstOrCreate(
                ['organisatie_id' => $regenboog->id, 'type' => 'stagebezoek', 'datum' => '2026-10-08'],
                [
                    'medewerker_id' => $medewerkerId,
                    'tijd_van' => '10:00',
                    'tijd_tot' => '11:00',
                    'locatie' => 'Rotterdam',
                    'status' => 'gepland',
                    'omschrijving' => 'Eerste stagebezoek bij de student.',
                ]
            );
        }
    }
}
