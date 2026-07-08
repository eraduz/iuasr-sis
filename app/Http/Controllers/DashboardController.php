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

        $stat = match ($rol) {
            Rol::Directie, Rol::Bestuur => [
                'perOpleiding' => Statistiek::perOpleiding(),
                'instroom' => Statistiek::instroomPerStudiejaar(),
                'status' => Statistiek::statusVerdeling(),
                'overgang' => Statistiek::overgangVerdeling(),
                'slaag' => Statistiek::slaagpercentage(),
                'financieel' => Statistiek::financieel(),
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

        // Signaleringen voor Studentenzaken (lijsten onder de statistieken).
        $nt2 = collect();
        $docLater = collect();
        $openBesluiten = collect();
        if ($rol === Rol::Studentenzaken) {
            $openBesluiten = \App\Models\Vrijstellingsbesluit::where('status', 'open')
                ->with(['student', 'vak', 'aangemaaktDoor'])->latest()->get();

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

        return view('dashboard.index', compact('kpi', 'nt2', 'docLater', 'stat', 'openBesluiten', 'vrijstellingLijst'));
    }
}
