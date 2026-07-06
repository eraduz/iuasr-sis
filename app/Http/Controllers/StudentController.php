<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
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
