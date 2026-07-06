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
        $kpi = [
            'studenten' => Student::count(),
            'inschrijvingen' => Inschrijving::where('status', 'actief')->count(),
            'vakken' => Vak::count(),
            'gebruikers' => User::count(),
            'audit' => AuditLog::count(),
        ];

        return view('dashboard.index', compact('kpi'));
    }
}
