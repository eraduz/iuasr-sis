<?php

namespace Database\Seeders;

use App\Models\Contactpersoon;
use App\Models\Opleiding;
use App\Models\Organisatie;
use App\Models\OrganisatieType;
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
    }
}
