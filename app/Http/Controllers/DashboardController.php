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
    public function index(): View
    {
        $rol = auth()->user()->rol;
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

        $stat = match ($rol) {
            Rol::Bestuur => [
                'perOpleiding' => Statistiek::perOpleiding(),
                'instroom' => Statistiek::instroomPerStudiejaar(),
                'status' => Statistiek::statusVerdeling(),
                'overgang' => Statistiek::overgangVerdeling(),
                'slaag' => Statistiek::slaagpercentage(),
                'financieel' => Statistiek::financieel(),
            ],
            Rol::Directie => [
                'perOpleiding' => Statistiek::perOpleiding($eigenOpleidingIds),
                'instroom' => Statistiek::instroomPerStudiejaar($eigenOpleidingIds),
                'status' => Statistiek::statusVerdeling($eigenOpleidingIds),
                'overgang' => Statistiek::overgangVerdeling($eigenOpleidingIds),
                'slaag' => Statistiek::slaagpercentage($eigenOpleidingIds),
                'financieel' => Statistiek::financieel($eigenOpleidingIds),
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

        return view('dashboard.index', compact('kpi', 'nt2', 'docLater', 'stat', 'openBesluiten', 'vrijstellingLijst', 'kennistoetsBewaking', 'dubbeleInschrijving'));
    }
}
