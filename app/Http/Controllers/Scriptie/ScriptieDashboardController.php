<?php

namespace App\Http\Controllers\Scriptie;

use App\Enums\Scriptiestap;
use App\Http\Controllers\Controller;
use App\Models\Scriptie;
use Illuminate\Contracts\View\View;

/**
 * Startpagina van de module Scriptie Coördinatie: kerncijfers, de verdeling van de
 * lopende trajecten over de stappen, en de trajecten die op de ingelogde rol
 * wachten. Alles opleiding-/begeleidergebonden gescoped (Scriptie::zichtbaarVoor).
 */
class ScriptieDashboardController extends Controller
{
    public function dashboard(): View
    {
        $gebruiker = auth()->user();

        $trajecten = Scriptie::query()
            ->zichtbaarVoor($gebruiker)
            ->with(['student', 'opleiding', 'begeleider', 'stapstanden'])
            ->get();

        $lopend = $trajecten->where('status', Scriptie::LOPEND);

        $kpi = [
            'totaal' => $trajecten->count(),
            'lopend' => $lopend->count(),
            'afgerond' => $trajecten->where('status', Scriptie::AFGEROND)->count(),
            'afgebroken' => $trajecten->where('status', Scriptie::AFGEBROKEN)->count(),
        ];

        // Verdeling van de lopende trajecten over de huidige stap.
        $perStap = [];
        foreach (Scriptiestap::inVolgorde() as $stap) {
            $aantal = $lopend->filter(fn (Scriptie $s) => $s->huidigeStap() === $stap)->count();
            if ($aantal > 0) {
                $perStap[] = ['label' => $stap->label(), 'aantal' => $aantal];
            }
        }

        // Trajecten die nu op (een van) de rollen van de ingelogde gebruiker wachten.
        $rollen = $gebruiker->alleRollen();
        $wachtOpMij = $lopend
            ->filter(fn (Scriptie $s) => ($rol = $s->wachtOpRol()) !== null
                && $rollen->contains(fn ($r) => $r === $rol))
            ->sortBy('scriptienummer')
            ->values();

        $recent = $lopend->sortByDesc('gestart_op')->take(10)->values();

        return view('scriptie.dashboard', compact('kpi', 'perStap', 'wachtOpMij', 'recent'));
    }
}
