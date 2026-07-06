<?php

namespace Database\Seeders;

use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Nationaliteit;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * SYNTHETISCHE studenten — verzonnen namen, geen echte personen. BSN wordt
 * NIET geseed (pas na akkoord FG). Studentnummer: de jaarprefix is bekend,
 * maar het aantal cijfers is een OPENSTAANDE PARAMETER; hier wordt een neutrale
 * synthetische reeks gebruikt, uitsluitend om het scherm te vullen.
 */
class SynthetischeStudentSeeder extends Seeder
{
    public function run(): void
    {
        $periode = Periode::where('actief', true)->first();
        $theologie = Opleiding::where('code', 'ISLTH')->first();
        $klasJaar1 = Klas::where('code', 'IT-1')->first();
        $nlId = Nationaliteit::where('naam', 'Nederlandse')->value('id');

        $synthetisch = [
            ['261001', 'Yasmin', 'Demir', 'V', '2004-03-14', 'Rotterdam'],
            ['261002', 'Mehmet', 'Akın', 'M', '2003-11-02', 'Den Haag'],
            ['261003', 'Aïsha', 'El Bouzidi', 'V', '2005-06-21', 'Utrecht'],
            ['261004', 'Rachid', 'Belhaj', 'M', '2002-09-30', 'Amsterdam'],
        ];

        foreach ($synthetisch as [$nr, $voornaam, $achternaam, $geslacht, $gebdatum, $gebplaats]) {
            $student = Student::create([
                'studentnummer' => $nr,
                'voornaam' => $voornaam,
                'roepnaam' => $voornaam,
                'achternaam' => $achternaam,
                'geslacht' => $geslacht,
                'geboortedatum' => $gebdatum,
                'geboorteplaats' => $gebplaats,
                'nationaliteit_id' => $nlId,
                'email' => strtolower(substr($voornaam, 0, 1)).'.'.strtolower(str_replace([' ', 'ï'], ['', 'i'], $achternaam)).'@student.iuasr.nl',
                // BSN bewust leeg — pas na akkoord FG.
            ]);

            Inschrijving::create([
                'student_id' => $student->id,
                'opleiding_id' => $theologie->id,
                'klas_id' => $klasJaar1->id,
                'periode_id' => $periode->id,
                'leerjaar' => 1,
                'status' => 'actief',
                'inschrijfdatum' => '2026-09-01',
                'invoerdatum' => '2026-08-20',
                'uitschrijfdatum' => '2027-08-31',
            ]);
        }
    }
}
