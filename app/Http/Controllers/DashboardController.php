<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Inschrijving;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Rolbewust dashboard. De view toont per rol een andere weergave; de shell
     * (design system) verzorgt header/sidebar op basis van dezelfde rolsleutel.
     */
    public function index(): View
    {
        $actievePeriodeId = \App\Models\Periode::where('actief', true)->value('id');

        $kpi = [
            'studenten' => Student::count(),
            'inschrijvingen' => Inschrijving::where('status', 'actief')->count(),
            'vakken' => Vak::count(),
            'gebruikers' => User::count(),
            'audit' => AuditLog::count(),
            'ter_vaststelling' => \App\Models\Cijferlijst::where('status', 'ingediend')
                ->where('periode_id', $actievePeriodeId)->count(),
        ];

        // Signaleringen voor Studentenzaken.
        $nt2 = collect();
        $docLater = collect();
        if (auth()->user()->rol === \App\Enums\Rol::Studentenzaken) {
            // NT2-bewaking: op deadline (1 jaar vanaf inschrijfdatum) gesorteerd.
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

            // Documenten die later worden aangeleverd (diploma/cijferlijst e.d.).
            $docLater = Student::where('documenten_later', true)->orderBy('achternaam')->get();
        }

        return view('dashboard.index', compact('kpi', 'nt2', 'docLater'));
    }
}
