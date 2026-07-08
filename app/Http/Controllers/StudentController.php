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

        // Markeer welke getoonde studenten een betalingsachterstand hebben.
        $schuldIds = $studenten->getCollection()
            ->filter(fn ($s) => \App\Support\Collegegeldstatus::heeftAchterstand($s))
            ->pluck('id')->all();

        return view('studenten.index', compact('studenten', 'zoek', 'status', 'opleidingId', 'opleidingen', 'statussen', 'schuldIds'));
    }

    /**
     * Studentdetail. Het cijfer-tabblad is server-side afgeschermd voor
     * Studentenzaken (rolscheiding); de view toont dan het "geen toegang"-paneel.
     */
    public function show(Student $student): View
    {
        $student->load([
            'inschrijvingen.opleiding', 'inschrijvingen.klas', 'inschrijvingen.periode',
            'inschrijvingen.vaktoewijzingen.vak',
            'nationaliteit', 'land', 'notities.gebruiker',
        ]);
        $huidige = $student->inschrijvingen->sortByDesc('inschrijfdatum')->first();

        // Vakhistorie: per studiejaar (inschrijving) de toegewezen vakken, gegroepeerd
        // per periode (blok). Blijft ook jaren later volledig raadpleegbaar.
        $vakHistorie = $student->inschrijvingen->sortBy('inschrijfdatum')->values()->map(fn ($insch) => [
            'inschrijving' => $insch,
            'perBlok' => $insch->vaktoewijzingen->map->vak->filter()
                ->sortBy('code')->groupBy(fn ($v) => $v->blok ?? 0)->sortKeys(),
        ]);

        $magCijfers = auth()->user()->magCijfersInzien();

        // Cijfers per vak — alleen voor rollen met inzage; inzage wordt gelogd.
        $cijferVakken = collect();
        if ($magCijfers) {
            $student->load('resultaten.toetsonderdeel.vak.toetsonderdelen', 'resultaten.toetsonderdeel.vak.opleiding');
            foreach ($student->resultaten->groupBy(fn ($r) => $r->toetsonderdeel->vak_id) as $rs) {
                $vak = $rs->first()->toetsonderdeel->vak;
                $cijferVakken->push([
                    'vak' => $vak,
                    'eind' => \App\Support\Cijferberekening::eindcijfer($vak, $rs),
                    'ec' => \App\Support\Cijferberekening::ec($vak, $rs),
                ]);
            }
            if ($cijferVakken->isNotEmpty()
                && in_array(auth()->user()->rol, [\App\Enums\Rol::Examencommissie, \App\Enums\Rol::Directie], true)) {
                AuditLogger::log(AuditLogger::INZAGE, $student, veld: 'cijfers');
            }
        }

        // Financiële status (betalingsachterstand) — stuurt de waarschuwing en blokkades.
        $financieel = \App\Support\Collegegeldstatus::voor($student);

        return view('studenten.show', compact('student', 'huidige', 'magCijfers', 'cijferVakken', 'financieel', 'vakHistorie'));
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
            'huisnummer' => ['nullable', 'string', 'max:20'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'woonplaats' => ['nullable', 'string', 'max:255'],
            'provincie' => ['nullable', 'string', 'max:100'],
            'land_id' => ['nullable', 'exists:landen,id'],
            'rekeningnummer' => ['nullable', 'string', 'max:40'],
            'vooropleiding' => ['nullable', 'string', 'max:255'],
            'diploma' => ['nullable', 'string', 'max:255'],
            'vorige_instelling' => ['nullable', 'string', 'max:255'],
            'afstudeerjaar' => ['nullable', 'string', 'max:9'],
            'taal_nederlands' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\TaalNiveau::class)],
            'taal_arabisch' => ['nullable', \Illuminate\Validation\Rule::enum(\App\Enums\TaalNiveau::class)],
            'nt2_behaald_op' => ['nullable', 'date'],
        ]);
        $data['nt2_examen_vereist'] = $request->boolean('nt2_examen_vereist');

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
