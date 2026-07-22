<?php

namespace App\Http\Controllers;

use App\Enums\InschrijvingStatus;
use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Periode;
use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\Herinschrijfcontrole;
use App\Support\Overgangsbeoordeling;
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
        // Alleen een student met een LOPENDE inschrijving (actief of geschorst) is
        // uitschrijfbaar; zonder zo'n inschrijving geeft het formulier terecht een
        // 404 ("Geen inschrijving om uit te schrijven"). Filter die studenten dus
        // al uit de keuzelijst, zodat de knop nooit naar een 404 wijst.
        return $this->kies($request, 'uitschrijven', 'Uitschrijven', 'uitschrijven.form',
            fn ($q) => $q->whereHas('inschrijvingen', fn ($i) => $i->whereIn('status', [
                InschrijvingStatus::Actief->value, InschrijvingStatus::Geschorst->value,
            ])));
    }

    private function kies(Request $request, string $sleutel, string $titel, string $doelRoute, ?\Closure $filter = null): View
    {
        $zoek = trim((string) $request->query('q', ''));
        $studenten = Student::query()
            ->with(['inschrijvingen' => fn ($q) => $q->latest('inschrijfdatum')->with('opleiding')])
            ->when($filter !== null, $filter)
            // De zoekvoorwaarden worden gegroepeerd, zodat het OR nooit buiten een
            // eventueel statusfilter lekt (anders zou het filter worden omzeild).
            ->when($zoek !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('studentnummer', 'like', $zoek.'%')
                ->orWhere('achternaam', 'like', '%'.$zoek.'%')))
            ->orderBy('studentnummer')
            ->paginate(15)
            ->withQueryString();

        return view('inschrijven.kies-student', compact('studenten', 'zoek', 'titel', 'doelRoute') + ['sleutel' => $sleutel]);
    }

    // ---------------- Uitschrijven ----------------

    public function uitschrijvenForm(Student $student): View
    {
        $huidige = $this->huidige($student);
        // Alleen een lopende inschrijving (actief/geschorst) kan worden uitgeschreven;
        // een afgestudeerde of al uitgeschreven inschrijving is een eindstatus.
        abort_unless($huidige !== null && $huidige->isLopend(), 404, 'Geen lopende inschrijving om uit te schrijven.');

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
        abort_unless($huidige !== null && $huidige->isLopend(), 404);

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

    // ---------------- Afstuderen (terminale eindstatus → alumnus) ----------------

    public function afstuderenForm(Student $student): View
    {
        $kandidaten = $this->afstudeerbareInschrijvingen($student);
        // Afstuderen kan alleen in het laatste leerjaar; is er geen kandidaat, dan
        // hoort de knop hier niet en geven we 404.
        abort_if($kandidaten->isEmpty(), 404, 'Geen inschrijving in het laatste leerjaar om af te studeren.');

        return view('inschrijven.afstuderen', compact('student', 'kandidaten'));
    }

    public function afstuderen(Request $request, Student $student): RedirectResponse
    {
        $kandidaten = $this->afstudeerbareInschrijvingen($student);
        abort_if($kandidaten->isEmpty(), 404);

        $data = $request->validate([
            'inschrijving_id' => ['required', Rule::in($kandidaten->pluck('id')->all())],
            'afstudeerdatum' => ['required', 'date'],
        ]);

        $inschrijving = $kandidaten->firstWhere('id', (int) $data['inschrijving_id']);
        // Dubbele server-side controle: uitsluitend een lopende inschrijving in het
        // laatste leerjaar mag afstuderen (UI kan omzeild worden).
        abort_unless($inschrijving && $inschrijving->magAfstuderen(), 422);

        $inschrijving->update([
            'status' => InschrijvingStatus::Afgestudeerd,
            'afstudeerdatum' => $data['afstudeerdatum'],
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'afstuderen', context: [
            'inschrijving_id' => $inschrijving->id,
            'opleiding' => $inschrijving->opleiding?->code,
            'leerjaar' => $inschrijving->leerjaar,
            'afstudeerdatum' => $data['afstudeerdatum'],
        ]);

        return redirect()->route('studenten.show', $student)->with('status',
            'Student afgestudeerd voor '.($inschrijving->opleiding?->naam ?? 'de opleiding')
            .'. De student is nu alumnus; deze inschrijving is afgerond. Het studentnummer blijft behouden voor een eventuele nieuwe opleiding.');
    }

    /**
     * Inschrijvingen waarvoor afstuderen mogelijk is: lopend (actief/geschorst) én
     * in het laatste leerjaar van de opleiding (`opleidingen.nominale_jaren`).
     *
     * @return \Illuminate\Support\Collection<int, Inschrijving>
     */
    private function afstudeerbareInschrijvingen(Student $student): \Illuminate\Support\Collection
    {
        return $student->inschrijvingen()->with('opleiding', 'periode')->get()
            ->filter(fn (Inschrijving $i) => $i->magAfstuderen())
            ->values();
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

        // Overgangsadvies van de vorige inschrijving (informatief op het formulier);
        // de doorstroomtoets zelf gebeurt server-side bij het opslaan.
        $overgang = ($huidige && $modus !== 'tweede') ? Overgangsbeoordeling::voor($huidige) : null;
        $magOverride = auth()->user()->heeftRol(Rol::Beheerder);

        return view('inschrijven.herinschrijven', compact('student', 'huidige', 'perioden', 'klassen', 'opleidingen', 'financieel', 'modus', 'overgang', 'magOverride'));
    }

    public function herinschrijven(Request $request, Student $student): RedirectResponse
    {
        $huidige = $this->huidige($student);
        abort_if($huidige === null, 404, 'Geen bestaande inschrijving om op voort te bouwen.');

        // Blokkade studievoortgang bij betalingsachterstand. Een lopende
        // betalingsafspraak (vastgelegd door de Financiële Administratie) heft
        // die blokkade op; de schuld zelf blijft bestaan.
        if (\App\Support\Collegegeldstatus::isGeblokkeerd($student)) {
            return redirect()->route('studenten.show', $student)
                ->with('status', 'Herinschrijven geblokkeerd: de student heeft een openstaande betalingsachterstand en geen lopende betalingsafspraak.');
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

        // Dezelfde studie niet opnieuw: is de student al AFGESTUDEERD voor deze
        // opleiding, dan is die afgerond. Een ANDERE opleiding mag wel — een nieuwe
        // registratie met hetzelfde studentnummer.
        $alAfgestudeerd = Inschrijving::where('student_id', $student->id)
            ->where('opleiding_id', $data['opleiding_id'])
            ->where('status', InschrijvingStatus::Afgestudeerd->value)
            ->exists();
        if ($alAfgestudeerd) {
            return back()->withInput()->with('fout', 'De student is al afgestudeerd voor deze opleiding en kan zich er niet opnieuw voor inschrijven. Kies een andere opleiding.');
        }

        // Doorstroomtoets: naar een hóger leerjaar in dezelfde opleiding mag alleen
        // wie het vorige jaar heeft gehaald én van wie de EC nog geldig zijn (pauze
        // korter dan de geldigheidsduur). Zie Herinschrijfcontrole.
        $controle = Herinschrijfcontrole::beoordeel(
            $huidige, (int) $data['opleiding_id'], (int) $data['leerjaar'], $data['inschrijfdatum']
        );
        $overrideReden = null;
        if (! $controle['toegestaan']) {
            $magOverride = $controle['override_mogelijk'] && auth()->user()->heeftRol(Rol::Beheerder);
            $overrulen = $magOverride && $request->boolean('override') && filled($request->input('override_reden'));
            if (! $overrulen) {
                return back()->withInput()->with('fout', $controle['melding']);
            }
            $overrideReden = trim((string) $request->input('override_reden'));
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

        // Een door de Beheerder vrijgegeven doorstroomblokkade wordt apart gelogd
        // (wie, waarom, welke inschrijving) — het is een uitzondering op de OER-norm.
        if ($overrideReden !== null) {
            AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'herinschrijving_override', context: [
                'blokkade' => $controle['blokkade'],
                'reden' => $overrideReden,
                'inschrijving_id' => $nieuw->id,
            ]);
        }

        $melding = ($studiewissel ? 'Herinschrijving met studiewissel vastgelegd' : 'Herinschrijving vastgelegd')
            .' — studentnummer '.$student->studentnummer.' blijft gelijk.'
            .($overrideReden !== null ? ' Doorstroomblokkade vrijgegeven door de Beheerder (gelogd).' : '');

        $redirect = redirect()->route('studenten.show', $student)->with('status', $melding);
        if ($controle['waarschuwing']) {
            $redirect->with('waarschuwing', $controle['waarschuwing']);
        }

        return $redirect;
    }
}
