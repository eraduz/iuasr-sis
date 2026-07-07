<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    /**
     * Studentenlijst. Zoeken gebeurt op STUDENTNUMMER (niet op achternaam) —
     * bewuste les uit het oude systeem, want achternamen zijn niet uniek.
     */
    public function index(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));

        $studenten = Student::query()
            ->with(['inschrijvingen' => fn ($q) => $q->latest('inschrijfdatum')->with(['opleiding', 'klas', 'periode'])])
            ->when($zoek !== '', function ($q) use ($zoek) {
                $q->where('studentnummer', 'like', $zoek.'%')
                    ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                    ->orWhere('voornaam', 'like', '%'.$zoek.'%');
            })
            ->orderBy('studentnummer')
            ->paginate(15)
            ->withQueryString();

        return view('studenten.index', compact('studenten', 'zoek'));
    }

    /**
     * Studentdetail. Het cijfer-tabblad is server-side afgeschermd voor
     * Studentenzaken (rolscheiding); de view toont dan het "geen toegang"-paneel.
     */
    public function show(Student $student): View
    {
        $student->load(['inschrijvingen.opleiding', 'inschrijvingen.klas', 'inschrijvingen.periode', 'nationaliteit', 'land']);
        $huidige = $student->inschrijvingen->sortByDesc('inschrijfdatum')->first();

        $magCijfers = auth()->user()->magCijfersInzien();

        return view('studenten.show', compact('student', 'huidige', 'magCijfers'));
    }

    /** Muteren van persoonsgegevens (Studentenzaken/Beheerder). */
    public function edit(Student $student): View
    {
        return view('studenten.edit', compact('student'));
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'voornaam' => ['required', 'string', 'max:255'],
            'tussenvoegsel' => ['nullable', 'string', 'max:60'],
            'achternaam' => ['required', 'string', 'max:255'],
            'roepnaam' => ['nullable', 'string', 'max:255'],
            'geboortedatum' => ['nullable', 'date'],
            'geboorteplaats' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'email_prive' => ['nullable', 'email', 'max:255'],
            'telefoon' => ['nullable', 'string', 'max:40'],
            'adres' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'woonplaats' => ['nullable', 'string', 'max:255'],
        ]);

        $gewijzigd = array_keys(array_diff_assoc($data, $student->only(array_keys($data))));
        $student->update($data);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'persoonsgegevens', context: [
            'velden' => $gewijzigd,
        ]);

        return redirect()
            ->route('studenten.show', $student)
            ->with('status', 'Persoonsgegevens bijgewerkt.');
    }

    /**
     * Toont het BSN (ontsleuteld, gemaskeerd) en LOGT de inzage. Alleen voor
     * rollen met een rechtsgrond (Studentenzaken, Beheerder).
     */
    public function bsn(Student $student): array
    {
        abort_unless(auth()->user()->magBsnInzien(), 403, 'Geen recht op BSN-inzage.');

        AuditLogger::bsnInzage($student);

        $bsn = $student->bsn; // ontsleuteld via cast
        $gemaskeerd = $bsn ? str_repeat('•', max(0, strlen($bsn) - 4)).substr($bsn, -4) : null;

        return ['bsn' => $gemaskeerd ?? 'niet vastgelegd'];
    }
}
