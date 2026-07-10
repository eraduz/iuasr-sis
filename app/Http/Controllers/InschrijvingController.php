<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\StudentnummerGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Inschrijven van een nieuwe student (Studentenzaken/Beheerder). Genereert het
 * studentnummer bij opslaan en legt de aanmaak vast in de audit-log.
 */
class InschrijvingController extends Controller
{
    public function create(): View
    {
        $opleidingen = Opleiding::where('actief', true)->orderBy('naam')->get();
        $klassen = Klas::with('opleiding')->orderBy('code')->get();
        $perioden = Periode::orderByDesc('code')->get();
        $actievePeriode = $perioden->firstWhere('actief', true);

        return view('inschrijven.create', compact('opleidingen', 'klassen', 'perioden', 'actievePeriode'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'voornaam' => ['required', 'string', 'max:255'],
            'tussenvoegsel' => ['nullable', 'string', 'max:60'],
            'achternaam' => ['required', 'string', 'max:255'],
            'geboortedatum' => ['nullable', 'date'],
            'geboorteplaats' => ['nullable', 'string', 'max:255'],
            'email_prive' => ['nullable', 'email', 'max:255'],
            'adres' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'woonplaats' => ['nullable', 'string', 'max:255'],
            'telefoon' => ['nullable', 'string', 'max:40'],
            'rekeningnummer' => ['nullable', 'string', 'max:40'],
            'taal_nederlands' => ['nullable', Rule::enum(\App\Enums\TaalNiveau::class)],
            'taal_arabisch' => ['nullable', Rule::enum(\App\Enums\TaalNiveau::class)],
            'opleiding_id' => ['required', Rule::exists('opleidingen', 'id')],
            'klas_id' => ['nullable', Rule::exists('klassen', 'id')],
            'periode_id' => ['required', Rule::exists('perioden', 'id')],
            'leerjaar' => ['nullable', 'integer', 'min:1', 'max:10'],
            'inschrijfdatum' => ['required', 'date'],
            'betaalregeling' => ['nullable', new \Illuminate\Validation\Rules\Enum(\App\Enums\Betaalregeling::class)],
        ]);
        $data['nt2_examen_vereist'] = $request->boolean('nt2_examen_vereist');

        $student = DB::transaction(function () use ($data) {
            $jaar = (int) date('Y', strtotime($data['inschrijfdatum']));

            // Genereer een uniek studentnummer; herhaal bij een botsing op de
            // unieke index (concurrent insert).
            $student = null;
            for ($poging = 0; $poging < 5; $poging++) {
                $nummer = StudentnummerGenerator::genereer($jaar);
                try {
                    $student = Student::create([
                        'studentnummer' => $nummer,
                        'voornaam' => $data['voornaam'],
                        'tussenvoegsel' => $data['tussenvoegsel'] ?? null,
                        'achternaam' => $data['achternaam'],
                        'geboortedatum' => $data['geboortedatum'] ?? null,
                        'geboorteplaats' => $data['geboorteplaats'] ?? null,
                        'email_prive' => $data['email_prive'] ?? null,
                        'adres' => $data['adres'] ?? null,
                        'postcode' => $data['postcode'] ?? null,
                        'woonplaats' => $data['woonplaats'] ?? null,
                        'telefoon' => $data['telefoon'] ?? null,
                        'rekeningnummer' => ($data['rekeningnummer'] ?? '') ?: null,
                        'taal_nederlands' => $data['taal_nederlands'] ?? null,
                        'taal_arabisch' => $data['taal_arabisch'] ?? null,
                        'nt2_examen_vereist' => $data['nt2_examen_vereist'],
                        // BSN wordt hier bewust NIET vastgelegd (pas na akkoord FG).
                    ]);
                    break;
                } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                    continue; // volgend nummer proberen
                }
            }

            abort_if($student === null, 500, 'Kon geen uniek studentnummer toekennen.');

            $inschrijving = Inschrijving::create([
                'student_id' => $student->id,
                'opleiding_id' => $data['opleiding_id'],
                'klas_id' => $data['klas_id'] ?? null,
                'periode_id' => $data['periode_id'],
                'leerjaar' => $data['leerjaar'] ?? 1,
                'status' => 'actief',
                'inschrijfdatum' => $data['inschrijfdatum'],
                'invoerdatum' => now()->toDateString(),
                'betaalregeling' => $data['betaalregeling'] ?? \App\Enums\Betaalregeling::Termijnen->value,
            ]);

            // Vakken van dit studiejaar automatisch toewijzen.
            \App\Support\Vaktoewijzer::wijsToe($inschrijving);

            AuditLogger::log(AuditLogger::AANMAAK, $student, veld: 'inschrijving', context: [
                'studentnummer' => $student->studentnummer,
                'opleiding_id' => $data['opleiding_id'],
            ]);

            return $student;
        });

        return redirect()
            ->route('studenten.show', $student)
            ->with('status', "Student ingeschreven — studentnummer {$student->studentnummer} toegekend.");
    }
}
