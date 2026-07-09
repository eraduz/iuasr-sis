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

        // Financieel gevolg (pro rata) — voor live-berekening op het formulier.
        $jaarbedrag = \App\Support\Collegegeldstatus::tarief($huidige);
        $fin = [
            'jaarbedrag' => $jaarbedrag,
            'maandbedrag' => $jaarbedrag !== null ? round($jaarbedrag / 12, 2) : null,
            'startjaar' => $huidige->periode?->startdatum?->year
                ?? (int) substr((string) $huidige->periode?->code, 0, 4),
            'betaald' => (float) $student->betalingen()->sum('bedrag'),
        ];

        return view('inschrijven.uitschrijven', compact('student', 'huidige', 'fin'));
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
        $opleidingen = \App\Models\Opleiding::where('actief', true)->orderBy('naam')->get();
        $financieel = \App\Support\Collegegeldstatus::voor($student);
        // 'tweede' = een tweede (parallelle) opleiding toevoegen naast een lopende.
        $modus = request('modus') === 'tweede' ? 'tweede' : 'herinschrijven';

        return view('inschrijven.herinschrijven', compact('student', 'huidige', 'perioden', 'klassen', 'opleidingen', 'financieel', 'modus'));
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
            'opleiding_id' => ['required', Rule::exists('opleidingen', 'id')],
            'periode_id' => ['required', Rule::exists('perioden', 'id')],
            'klas_id' => ['nullable', Rule::exists('klassen', 'id')],
            'leerjaar' => ['required', 'integer', 'min:1', 'max:10'],
            'inschrijfdatum' => ['required', 'date'],
        ]);

        // Gekozen klas moet bij de gekozen opleiding horen (studiewissel-veilig).
        if (! empty($data['klas_id'])) {
            $klas = Klas::find($data['klas_id']);
            if ($klas && (int) $klas->opleiding_id !== (int) $data['opleiding_id']) {
                return back()->withInput()->with('fout', 'De gekozen klas hoort niet bij de gekozen opleiding.');
            }
        }

        // Dubbele inschrijving is toegestaan (twee opleidingen tegelijk), maar niet
        // twee keer dezelfde opleiding in hetzelfde studiejaar.
        $bestaatAl = Inschrijving::where('student_id', $student->id)
            ->where('periode_id', $data['periode_id'])
            ->where('opleiding_id', $data['opleiding_id'])->exists();
        if ($bestaatAl) {
            return back()->withInput()->with('fout', 'De student is voor dit studiejaar al ingeschreven voor deze opleiding.');
        }

        // Nieuwe inschrijving; studentnummer en persoonsgegevens blijven gelijk.
        // De opleiding kan wijzigen (studiewissel), bijvoorbeeld van een cursus
        // naar een bacheloropleiding.
        $nieuw = Inschrijving::create([
            'student_id' => $student->id,
            'opleiding_id' => $data['opleiding_id'],
            'klas_id' => $data['klas_id'] ?? null,
            'periode_id' => $data['periode_id'],
            'leerjaar' => $data['leerjaar'],
            'status' => InschrijvingStatus::Actief,
            'inschrijfdatum' => $data['inschrijfdatum'],
            'invoerdatum' => now()->toDateString(),
        ]);

        // Vakken van de (nieuwe) opleiding + leerjaar automatisch toewijzen.
        \App\Support\Vaktoewijzer::wijsToe($nieuw);

        $studiewissel = (int) $data['opleiding_id'] !== (int) $huidige->opleiding_id;
        AuditLogger::log(AuditLogger::AANMAAK, $student, veld: 'herinschrijving', context: [
            'periode_id' => $data['periode_id'],
            'opleiding_id' => $data['opleiding_id'],
            'studiewissel' => $studiewissel,
            'inschrijving_id' => $nieuw->id,
        ]);

        return redirect()->route('studenten.show', $student)->with('status',
            ($studiewissel ? 'Herinschrijving met studiewissel vastgelegd' : 'Herinschrijving vastgelegd')
            .' — studentnummer '.$student->studentnummer.' blijft gelijk.');
    }
}
