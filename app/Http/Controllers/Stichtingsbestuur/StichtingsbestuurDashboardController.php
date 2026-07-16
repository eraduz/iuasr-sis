<?php

namespace App\Http\Controllers\Stichtingsbestuur;

use App\Enums\Bestuursorgaan;
use App\Http\Controllers\Controller;
use App\Models\Bestuurslid;
use App\Models\Bestuursvergadering;
use Illuminate\Contracts\View\View;

/**
 * Startpagina van de module Stichtingsbestuur: de samenstelling van het bestuur en
 * de Raad van Toezicht, plus de recentste vergaderingen.
 */
class StichtingsbestuurDashboardController extends Controller
{
    public function dashboard(): View
    {
        $leden = Bestuurslid::actief()->geordend()->get();

        $bestuur = $leden->where('orgaan', Bestuursorgaan::Stichtingsbestuur)->values();
        $rvt = $leden->where('orgaan', Bestuursorgaan::RaadVanToezicht)->values();

        $kpi = [
            'bestuur' => $bestuur->count(),
            'rvt' => $rvt->count(),
            'vergaderingen' => Bestuursvergadering::count(),
        ];

        $vergaderingen = Bestuursvergadering::query()
            ->with(['aanwezigheden', 'genotuleerdDoor'])
            ->chronologisch()
            ->limit(8)
            ->get();

        return view('stichtingsbestuur.dashboard', compact('kpi', 'bestuur', 'rvt', 'vergaderingen'));
    }
}
