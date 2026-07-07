<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentNotitie;
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
        // Standaard tonen we alleen ACTIEVE studenten (geen uitgeschrevenen).
        $status = (string) $request->query('status', 'actief');
        $opleidingId = $request->query('opleiding');

        // Correlated subquery: filter op de MEEST RECENTE inschrijving van de student,
        // zodat de huidige status/opleiding telt (niet een oude inschrijving).
        $laatste = fn ($iq) => $iq->whereRaw(
            'inschrijvingen.inschrijfdatum = (select max(i2.inschrijfdatum) from inschrijvingen i2 where i2.student_id = inschrijvingen.student_id)'
        );

        $studenten = Student::query()
            ->with(['inschrijvingen' => fn ($q) => $q->latest('inschrijfdatum')->with(['opleiding', 'klas', 'periode'])])
            ->when($zoek !== '', function ($q) use ($zoek) {
                $q->where(function ($sub) use ($zoek) {
                    $sub->where('studentnummer', 'like', $zoek.'%')
                        ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                        ->orWhere('voornaam', 'like', '%'.$zoek.'%');
                });
            })
            ->when($status !== 'alle', fn ($q) => $q->whereHas('inschrijvingen',
                fn ($iq) => $laatste($iq)->where('status', $status)))
            ->when($opleidingId, fn ($q) => $q->whereHas('inschrijvingen',
                fn ($iq) => $laatste($iq)->where('opleiding_id', $opleidingId)))
            ->orderBy('studentnummer')
            ->paginate(15)
            ->withQueryString();

        $opleidingen = \App\Models\Opleiding::orderBy('naam')->get(['id', 'naam']);
        $statussen = \App\Enums\InschrijvingStatus::cases();

        return view('studenten.index', compact('studenten', 'zoek', 'status', 'opleidingId', 'opleidingen', 'statussen'));
    }

    /**
     * Studentdetail. Het cijfer-tabblad is server-side afgeschermd voor
     * Studentenzaken (rolscheiding); de view toont dan het "geen toegang"-paneel.
     */
    public function show(Student $student): View
    {
        $student->load([
            'inschrijvingen.opleiding', 'inschrijvingen.klas', 'inschrijvingen.periode',
            'nationaliteit', 'land', 'notities.gebruiker',
        ]);
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

    /** Interne notitie toevoegen bij een student (Studentenzaken/Beheerder). */
    public function notitieStore(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'tekst' => ['required', 'string', 'max:2000'],
        ]);

        $student->notities()->create([
            'gebruiker_id' => auth()->id(),
            'tekst' => $data['tekst'],
        ]);

        return redirect()
            ->to(route('studenten.show', $student).'#notities')
            ->with('status', 'Notitie toegevoegd.');
    }

    /** Interne notitie verwijderen. */
    public function notitieDestroy(Student $student, StudentNotitie $notitie): RedirectResponse
    {
        abort_unless($notitie->student_id === $student->id, 404);
        $notitie->delete();

        return redirect()
            ->to(route('studenten.show', $student).'#notities')
            ->with('status', 'Notitie verwijderd.');
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
