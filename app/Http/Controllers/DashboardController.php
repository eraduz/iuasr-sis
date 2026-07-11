<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\AuditLog;
use App\Models\Cijferlijst;
use App\Models\Periode;
use App\Models\Student;
use App\Support\Statistiek;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Rolbewust dashboard met statistieken en grafieken. Per rol worden alleen
     * de relevante (en niet te zware) aggregaties berekend; cijfergebonden
     * statistieken zijn voorbehouden aan rollen met cijferinzage.
     */
    public function index(): View|\Illuminate\Http\RedirectResponse
    {
        $rol = auth()->user()->rol;

        // Het Studentenzaken-dashboard is niet voor rollen buiten die module
        // (bijv. Cursusadministratie, Relatiebeheerder); stuur hen naar hun
        // eigen module — of naar het keuzescherm als dat er meerdere zijn.
        if (! $rol->magModule('studentenzaken')) {
            if ($rol->magModule('cursussen')) {
                return redirect()->route('cursussen.dashboard');
            }
            if ($rol->magModule('relatiebeheer')) {
                return redirect()->route('relaties');
            }

            return redirect()->route('modules.kiezen');
        }
        $actievePeriodeId = Periode::where('actief', true)->value('id');
        $kern = Statistiek::kern();

        // Directie is opleidinggebonden: alle aggregaties worden beperkt tot de
        // eigen opleiding(en). null = geen beperking (overige rollen).
        $eigenOpleidingIds = $rol === Rol::Directie ? auth()->user()->opleidingIds()->all() : null;

        $kpi = [
            'studenten' => $kern['studenten'],
            'inschrijvingen' => $kern['actief'],
            'afgestudeerd' => $kern['afgestudeerd'],
            'uitgeschreven' => $kern['uitgeschreven'],
            'vakken' => $kern['vakken'],
            'gebruikers' => $kern['gebruikers'],
            'audit' => AuditLog::count(),
            'ter_vaststelling' => Cijferlijst::where('status', 'ingediend')->where('periode_id', $actievePeriodeId)->count(),
        ];

        // Directie: KPI-tegels tonen uitsluitend cijfers van de eigen opleiding(en).
        if ($eigenOpleidingIds !== null) {
            $statussen = \App\Models\Inschrijving::whereIn('opleiding_id', $eigenOpleidingIds)
                ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
            $kpi['studenten'] = Student::whereHas('inschrijvingen',
                fn ($q) => $q->whereIn('opleiding_id', $eigenOpleidingIds))->count();
            $kpi['inschrijvingen'] = (int) ($statussen['actief'] ?? 0);
            $kpi['afgestudeerd'] = (int) ($statussen['afgestudeerd'] ?? 0);
            $kpi['uitgeschreven'] = (int) ($statussen['uitgeschreven'] ?? 0);
        }

        // Docent: presentiestatistiek uitsluitend over de eigen vakken.
        $docentId = $rol === Rol::Docent ? auth()->user()->docent_id : null;

        $stat = match ($rol) {
            Rol::Bestuur => [
                'perOpleiding' => Statistiek::perOpleiding(),
                'instroom' => Statistiek::instroomPerStudiejaar(),
                'status' => Statistiek::statusVerdeling(),
                'overgang' => Statistiek::overgangVerdeling(),
                'slaag' => Statistiek::slaagpercentage(),
                'financieel' => Statistiek::financieel(),
                'presentie' => Statistiek::presentie(),
                'presentiePerOpleiding' => Statistiek::presentiePerOpleiding(),
                'presentieVerdeling' => Statistiek::presentieVerdeling(),
            ],
            Rol::Directie => [
                'perOpleiding' => Statistiek::perOpleiding($eigenOpleidingIds),
                'instroom' => Statistiek::instroomPerStudiejaar($eigenOpleidingIds),
                'status' => Statistiek::statusVerdeling($eigenOpleidingIds),
                'overgang' => Statistiek::overgangVerdeling($eigenOpleidingIds),
                'slaag' => Statistiek::slaagpercentage($eigenOpleidingIds),
                'financieel' => Statistiek::financieel($eigenOpleidingIds),
                'presentie' => Statistiek::presentie($eigenOpleidingIds),
                'presentiePerVak' => Statistiek::presentiePerVak($eigenOpleidingIds),
                'presentieVerdeling' => Statistiek::presentieVerdeling($eigenOpleidingIds),
            ],
            Rol::Docent => [
                'presentie' => Statistiek::presentie(null, $docentId),
                'presentiePerVak' => Statistiek::presentiePerVak(null, $docentId),
                'presentieVerdeling' => Statistiek::presentieVerdeling(null, $docentId),
            ],
            Rol::Examencommissie => [
                'slaag' => Statistiek::slaagpercentage(),
                'cijferverdeling' => Statistiek::cijferverdeling(),
                'overgang' => Statistiek::overgangVerdeling(),
                'cijferlijstStatus' => Statistiek::cijferlijstStatus(),
                'herkansingen' => Statistiek::herkansingen(),
                'vrijstellingen' => Statistiek::vrijstellingen(),
                'besluitenOpen' => \App\Models\Vrijstellingsbesluit::where('status', 'open')->count(),
                'perOpleiding' => Statistiek::perOpleiding(),
                'presentie' => Statistiek::presentie(),
            ],
            Rol::Financien => [
                'financieel' => Statistiek::financieel(),
            ],
            Rol::Studentenzaken => [
                'perOpleiding' => Statistiek::perOpleiding(),
                'perLeerjaar' => Statistiek::perLeerjaar(),
                'instroom' => Statistiek::instroomPerStudiejaar(),
                'status' => Statistiek::statusVerdeling(),
                'nt2' => Statistiek::nt2Verdeling(),
                'vrijstellingen' => Statistiek::vrijstellingen(),
            ],
            Rol::Beheerder => [
                'gebruikersPerRol' => Statistiek::gebruikersPerRol(),
            ],
            default => [],
        };

        // Lijst 'studenten met vrijstelling' — voor iedereen behalve Beheerder
        // en Financiële Administratie (die hebben er geen belang bij).
        $vrijstellingLijst = collect();
        if (! in_array($rol, [Rol::Beheerder, Rol::Financien], true)) {
            $vrijstellingLijst = Statistiek::vrijstellingStudenten();
        }

        // Studenten met een dubbele inschrijving (twee opleidingen tegelijk) —
        // relevant voor Studentenzaken, Directie en Financiële Administratie.
        // Directie ziet alleen studenten binnen de eigen opleiding(en).
        $dubbeleInschrijving = collect();
        if (in_array($rol, [Rol::Studentenzaken, Rol::Directie, Rol::Financien], true)) {
            $dubbeleInschrijving = Student::whereHas('inschrijvingen', fn ($q) => $q->where('status', 'actief'))
                ->with(['inschrijvingen' => fn ($q) => $q->where('status', 'actief')->with('opleiding')])
                ->get()
                ->filter(fn (Student $s) => $s->heeftDubbeleInschrijving());

            if ($rol === Rol::Directie) {
                $eigen = auth()->user()->opleidingIds();
                $dubbeleInschrijving = $dubbeleInschrijving->filter(
                    fn (Student $s) => $s->actieveInschrijvingen()->pluck('opleiding_id')->intersect($eigen)->isNotEmpty()
                );
            }

            $dubbeleInschrijving = $dubbeleInschrijving->sortBy('studentnummer')->values();
        }

        // Venster 'Studenten met 50%-aanwezigheidsregeling'. Zichtbaar voor elke
        // rol die de regeling mag kennen; Directie ziet alleen de eigen
        // opleiding(en), de Docent alleen de studenten uit de eigen vakken.
        $regelingLijst = collect();
        if (auth()->user()->magAanwezigheidsregelingZien()) {
            $regelingLijst = $rol === Rol::Docent
                ? $this->regelingInEigenVakken($docentId)
                : Statistiek::aanwezigheidsregelingStudenten($eigenOpleidingIds);
        }

        // Venster 'Presentieregistratie'. De registratie is voor de docent
        // verplicht; directie en bestuur zien welke vakken achterlopen.
        $presentieAchterstand = collect();
        if (in_array($rol, [Rol::Docent, Rol::Directie, Rol::Bestuur], true)) {
            $vakken = \App\Models\Vak::where('actief', true)
                ->when($docentId !== null, fn ($q) => $q->where('docent_id', $docentId))
                ->when($eigenOpleidingIds !== null, fn ($q) => $q->whereIn('opleiding_id', $eigenOpleidingIds))
                ->with(['opleiding', 'docent'])->orderBy('code')->get();

            $presentieAchterstand = $vakken
                ->map(fn ($vak) => ['vak' => $vak, 'samenvatting' => \App\Support\Presentiebewaking::voorVak($vak)['samenvatting']])
                ->filter(fn ($r) => ! $r['samenvatting']['volledig'] && $r['samenvatting']['deelnemers'] > 0)
                ->values();
        }

        // Lopende betalingsafspraken: studenten met een schuld waarvan de blokkades
        // tijdelijk zijn opgeheven. Voor de Financiële Administratie en Beheer.
        $afspraken = collect();
        if (in_array($rol, [Rol::Financien, Rol::Beheerder], true)) {
            $afspraken = \App\Models\Betalingsafspraak::lopend()
                ->with(['student', 'vastgelegdDoor'])
                ->orderBy('geldig_tot')->get()
                ->filter(fn ($a) => $a->student !== null)
                ->values();
        }

        // Venster 'Mijn taken': eigen taken plus de taken die aan niemand zijn
        // toegewezen, op urgentie. Taken zonder vervaldatum blijven buiten beeld.
        $mijnTaken = collect();
        if (auth()->user()->magTakenBeheren()) {
            $mijnTaken = \App\Models\Taak::openstaand()
                ->voorGebruiker(auth()->user())
                ->whereNotNull('vervaldatum')
                ->whereDate('vervaldatum', '<=', now()->addDays(7)->toDateString())
                ->with(['student', 'toegewezenAan'])
                ->opUrgentie()->limit(10)->get();
        }

        // Signaleringen voor Studentenzaken (lijsten onder de statistieken).
        $nt2 = collect();
        $docLater = collect();
        $openBesluiten = collect();
        $kennistoetsBewaking = collect();
        if ($rol === Rol::Studentenzaken) {
            $openBesluiten = \App\Models\Vrijstellingsbesluit::where('status', 'open')
                ->with(['student', 'vak', 'aangemaaktDoor'])->latest()->get();

            // Landelijke kennistoetsen (PABO): studenten die nog niet alles behaald hebben.
            $opleidingMetToets = \App\Models\Kennistoets::where('actief', true)->pluck('opleiding_id')->unique();
            if ($opleidingMetToets->isNotEmpty()) {
                $kennistoetsBewaking = Student::whereHas('inschrijvingen', fn ($q) => $q->where('status', 'actief')->whereIn('opleiding_id', $opleidingMetToets))
                    ->with('inschrijvingen')->get()
                    ->map(fn ($s) => ['student' => $s, 'kt' => \App\Support\Kennistoetsbewaking::voor($s)])
                    ->filter(fn ($r) => $r['kt']['vereist'] && $r['kt']['status'] !== 'afgerond')
                    ->sortBy(fn ($r) => $r['kt']['dagen'] ?? PHP_INT_MAX)
                    ->values();
            }

            $nt2 = Student::where('nt2_examen_vereist', true)
                ->whereNull('nt2_behaald_op')
                ->with('inschrijvingen')
                ->get()
                ->map(fn (Student $s) => [
                    'student' => $s,
                    'deadline' => $s->nt2Deadline(),
                    'dagen' => $s->nt2DagenResterend(),
                    'status' => $s->nt2Status(),
                ])
                ->sortBy(fn ($r) => $r['dagen'] ?? PHP_INT_MAX)
                ->values();

            $docLater = Student::where('documenten_later', true)->orderBy('achternaam')->get();
        }

        return view('dashboard.index', compact('kpi', 'nt2', 'docLater', 'stat', 'openBesluiten',
            'vrijstellingLijst', 'kennistoetsBewaking', 'dubbeleInschrijving',
            'regelingLijst', 'presentieAchterstand', 'mijnTaken', 'afspraken'));
    }

    /**
     * Studenten met de 50%-regeling die daadwerkelijk deelnemen aan een vak van
     * deze docent. De docent heeft geen toegang tot het studentdossier, maar
     * moet de regeling wel kennen om de aanwezigheid juist te beoordelen.
     *
     * @return \Illuminate\Support\Collection<int, array{student: Student, inschrijving: \App\Models\Inschrijving}>
     */
    private function regelingInEigenVakken(?int $docentId): \Illuminate\Support\Collection
    {
        if ($docentId === null) {
            return collect();
        }

        return \App\Models\Vak::where('docent_id', $docentId)->where('actief', true)->get()
            ->flatMap(fn ($vak) => $vak->deelnemers()
                ->where('inschrijvingen.aanwezigheidsregeling_50', true)
                ->with(['student', 'opleiding', 'periode'])->get())
            ->unique('id')
            ->filter(fn ($i) => $i->student !== null)
            ->map(fn ($i) => ['student' => $i->student, 'inschrijving' => $i])
            ->sortBy(fn ($r) => $r['student']->achternaam)
            ->values();
    }
}
