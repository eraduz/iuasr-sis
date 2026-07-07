<?php

namespace Database\Seeders;

use App\Enums\InschrijvingStatus;
use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Nationaliteit;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use Illuminate\Database\Seeder;

/**
 * SYNTHETISCHE studenten — verzonnen namen, geen echte personen. BSN wordt
 * NIET geseed (pas na akkoord FG). Studentnummer: jaarprefix (2) + volgnummer,
 * totaal 6 tekens (bevestigd formaat, voorbeeld 261234).
 */
class SynthetischeStudentSeeder extends Seeder
{
    public function run(): void
    {
        $periode = Periode::where('actief', true)->first();
        $nlId = Nationaliteit::where('naam', 'Nederlandse')->value('id');

        $opl = fn (string $code) => Opleiding::where('code', $code)->first();
        $klas = fn (string $code) => Klas::where('code', $code)->first();

        // [studentnummer, voornaam, achternaam, geslacht, geb.datum, plaats, opleidingcode, klascode, leerjaar, status]
        $set = [
            ['261001', 'Yasmin', 'Demir', 'V', '2004-03-14', 'Rotterdam', 'ISLTH', 'IT-1', 1, InschrijvingStatus::Actief],
            ['261002', 'Mehmet', 'Akın', 'M', '2003-11-02', 'Den Haag', 'MGV', 'MGV-D', 1, InschrijvingStatus::Actief],
            ['261003', 'Aïsha', 'El Bouzidi', 'V', '2005-06-21', 'Utrecht', 'PABO', 'PB-1A', 1, InschrijvingStatus::Actief],
            ['261004', 'Rachid', 'Belhaj', 'M', '2002-09-30', 'Amsterdam', 'ISLTH', null, 3, InschrijvingStatus::Uitgeschreven],
            ['261005', 'Sara', 'El Idrissi', 'V', '2004-01-18', 'Rotterdam', 'PABO', 'PB-2B', 2, InschrijvingStatus::Actief],
            ['261006', 'Khalid', 'Ouahbi', 'M', '2001-12-05', 'Schiedam', 'KRN', 'KH-2', 2, InschrijvingStatus::Geschorst],
            ['261007', 'Fatima', 'Bennani', 'V', '2000-07-22', 'Rotterdam', 'ISLTH', 'IT-4', 4, InschrijvingStatus::Afgestudeerd],
            ['261008', 'Bilal', 'Yıldırım', 'M', '2003-04-11', 'Dordrecht', 'MGV', 'MGV-D', 1, InschrijvingStatus::Actief],
            ['261009', 'Nora', 'Haddadi', 'V', '2004-10-08', 'Rotterdam', 'PABO', 'PB-1A', 1, InschrijvingStatus::Actief],
            ['261010', 'Amal', 'Ahmadi', 'V', '2005-02-27', 'Delft', 'KRN', 'KH-2', 1, InschrijvingStatus::Aangemeld],
            // Extra ISLTH jaar-1 studenten (deelnemers van ARA-101, voor cijferinvoer).
            ['261011', 'Ibrahim', 'Chakir', 'M', '2005-05-19', 'Rotterdam', 'ISLTH', 'IT-1', 1, InschrijvingStatus::Actief],
            ['261012', 'Zaynab', 'Amrani', 'V', '2005-08-03', 'Gouda', 'ISLTH', 'IT-1', 1, InschrijvingStatus::Actief],
            ['261013', 'Yusuf', 'Karadeniz', 'M', '2004-12-14', 'Rotterdam', 'ISLTH', 'IT-1', 1, InschrijvingStatus::Actief],
            ['261014', 'Maryam', 'Sabri', 'V', '2005-03-28', 'Capelle a/d IJssel', 'ISLTH', 'IT-1', 1, InschrijvingStatus::Actief],
        ];

        foreach ($set as [$nr, $voornaam, $achternaam, $geslacht, $geb, $plaats, $oplCode, $klasCode, $leerjaar, $status]) {
            $student = Student::create([
                'studentnummer' => $nr,
                'voornaam' => $voornaam,
                'roepnaam' => $voornaam,
                'achternaam' => $achternaam,
                'geslacht' => $geslacht,
                'geboortedatum' => $geb,
                'geboorteplaats' => $plaats,
                'nationaliteit_id' => $nlId,
                'email' => strtolower(substr($voornaam, 0, 1)).'.'.strtolower(str_replace([' ', 'ï', 'İ'], ['', 'i', 'i'], $achternaam)).'@student.iuasr.nl',
                'telefoon' => '06 '.implode(' ', str_split(substr($nr, -6), 2)), // synthetisch
                'taal_nederlands' => $status === InschrijvingStatus::Aangemeld ? 'onvoldoende' : 'voldoende',
                'taal_arabisch' => 'goed',
                'nt2_examen_vereist' => $status === InschrijvingStatus::Aangemeld,
            ]);

            Inschrijving::create([
                'student_id' => $student->id,
                'opleiding_id' => $opl($oplCode)->id,
                'klas_id' => $klasCode ? $klas($klasCode)?->id : null,
                'periode_id' => $periode->id,
                'leerjaar' => $leerjaar,
                'status' => $status,
                'inschrijfdatum' => '2025-09-01',
                'invoerdatum' => '2025-08-20',
                // Uitgeschreven per einde uitschrijfmaand (december) -> 4 maanden ingeschreven.
                'uitschrijfdatum' => $status === InschrijvingStatus::Uitgeschreven ? '2025-12-31' : null,
            ]);
        }
    }
}
