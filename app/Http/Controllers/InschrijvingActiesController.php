<?php

namespace App\Http\Controllers;

use App\Enums\InschrijvingStatus;
use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Periode;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lifecycle-acties op de inschrijving van een student: uitschrijven, schorsen
 * (met één klik), en herinschrijven voor een nieuwe periode. Elke actie wordt
 * gelogd. De interne studentsleutel en het studentnummer blijven altijd behouden.
 */
class InschrijvingActiesController extends Controller
{
    private function huidige(Student $student): ?Inschrijving
    {
        return $student->inschrijvingen()->latest('inschrijfdatum')->first();
    }

    // ---------------- Student kiezen (vanuit het menu) ----------------

    public function kiesHerinschrijven(Request $request): View
    {
        return $this->kies($request, 'herinschrijven', 'Herinschrijven', 'herinschrijven.form');
    }

    public function kiesUitschrijven(Request $request): View
    {
        return $this->kies($request, 'uitschrijven', 'Uitschrijven', 'uitschrijven.form');
    }

    private function kies(Request $request, string $sleutel, string $titel, string $doelRoute): View
    {
        $zoek = trim((string) $request->query('q', ''));
        $studenten = Student::query()
            ->with(['inschrijvingen' => fn ($q) => $q->latest('inschrijfdatum')->with('opleiding')])
            ->when($zoek !== '', fn ($q) => $q->where('studentnummer', 'like', $zoek.'%')
                ->orWhere('achternaam', 'like', '%'.$zoek.'%'))
            ->orderBy('studentnummer')
            ->paginate(15)
            ->withQueryString();

        return view('inschrijven.kies-student', compact('studenten', 'zoek', 'titel', 'doelRoute') + ['sleutel' => $sleutel]);
    }

    // ---------------- Uitschrijven ----------------

    public function uitschrijvenForm(Student $student): View
    {
        $huidige = $this->huidige($student);
        abort_if($huidige === null, 404, 'Geen inschrijving om uit te schrijven.');

        return view('inschrijven.uitschrijven', compact('student', 'huidige'));
    }

    public function uitschrijven(Request $request, Student $student): RedirectResponse
    {
        $huidige = $this->huidige($student);
        abort_if($huidige === null, 404);

        $data = $request->validate([
            'reden' => ['required', 'string', 'max:255'],
            'peildatum' => ['required', 'date'],
            'toelichting' => ['nullable', 'string', 'max:2000'],
        ]);

        // Wettelijke regel: uitschrijfdatum = einde van de lopende maand.
        $uitschrijfdatum = \Illuminate\Support\Carbon::parse($data['peildatum'])->endOfMonth()->toDateString();

        $toelichting = $data['toelichting'] ?? null;
        $huidige->update([
            'status' => InschrijvingStatus::Uitgeschreven,
            'uitschrijfdatum' => $uitschrijfdatum,
            'opmerkingen' => trim(($huidige->opmerkingen ? $huidige->opmerkingen."\n" : '')
                .'Uitgeschreven ('.$data['reden'].')'.($toelichting ? ': '.$toelichting : '')),
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'uitschrijving', context: [
            'reden' => $data['reden'],
            'uitschrijfdatum' => $uitschrijfdatum,
        ]);

        return redirect()->route('studenten.show', $student)
            ->with('status', 'Student uitgeschreven per '.\Illuminate\Support\Carbon::parse($uitschrijfdatum)->format('d-m-Y').'.');
    }

    // ---------------- Schorsen (één klik, omkeerbaar) ----------------

    public function schors(Student $student): RedirectResponse
    {
        $huidige = $this->huidige($student);
        abort_if($huidige === null, 404);

        // Alleen wisselen tussen actief en geschorst; overige statussen blijven.
        if ($huidige->status === InschrijvingStatus::Geschorst) {
            $nieuw = InschrijvingStatus::Actief;
            $melding = 'Schorsing opgeheven — student is weer actief.';
        } elseif ($huidige->status === InschrijvingStatus::Actief) {
            $nieuw = InschrijvingStatus::Geschorst;
            $melding = 'Student geschorst.';
        } else {
            return redirect()->route('studenten.show', $student)
                ->with('status', 'Schorsen kan alleen bij een actieve inschrijving.');
        }

        $huidige->update(['status' => $nieuw]);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'status', context: [
            'van' => $huidige->getOriginal('status'),
            'naar' => $nieuw->value,
        ]);

        return redirect()->route('studenten.show', $student)->with('status', $melding);
    }

    // ---------------- Herinschrijven ----------------

    public function herinschrijvenForm(Student $student): View
    {
        $huidige = $this->huidige($student);
        $perioden = Periode::orderByDesc('code')->get();
        $klassen = Klas::with('opleiding')->orderBy('code')->get();
        $financieel = \App\Support\Collegegeldstatus::voor($student);

        return view('inschrijven.herinschrijven', compact('student', 'huidige', 'perioden', 'klassen', 'financieel'));
    }

    public function herinschrijven(Request $request, Student $student): RedirectResponse
    {
        $huidige = $this->huidige($student);
        abort_if($huidige === null, 404, 'Geen bestaande inschrijving om op voort te bouwen.');

        // Blokkade studievoortgang bij betalingsachterstand.
        if (\App\Support\Collegegeldstatus::heeftAchterstand($student)) {
            return redirect()->route('studenten.show', $student)
                ->with('status', 'Herinschrijven geblokkeerd: de student heeft een openstaande betalingsachterstand.');
        }

        $data = $request->validate([
            'periode_id' => ['required', Rule::exists('perioden', 'id')],
            'klas_id' => ['nullable', Rule::exists('klassen', 'id')],
            'leerjaar' => ['nullable', 'integer', 'min:1', 'max:10'],
            'inschrijfdatum' => ['required', 'date'],
        ]);

        // Nieuwe inschrijving voor de nieuwe periode; opleiding en studentnummer
        // blijven gelijk (dezelfde interne student).
        $nieuw = Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => $huidige->opleiding_id,
            'klas_id' => $data['klas_id'] ?? null,
            'periode_id' => $data['periode_id'],
            'leerjaar' => $data['leerjaar'] ?? (($huidige->leerjaar ?? 1) + 1),
            'status' => InschrijvingStatus::Actief,
            'inschrijfdatum' => $data['inschrijfdatum'],
            'invoerdatum' => now()->toDateString(),
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $student, veld: 'herinschrijving', context: [
            'periode_id' => $data['periode_id'],
            'inschrijving_id' => $nieuw->id,
        ]);

        return redirect()->route('studenten.show', $student)
            ->with('status', 'Herinschrijving vastgelegd — studentnummer '.$student->studentnummer.' blijft gelijk.');
    }
}
