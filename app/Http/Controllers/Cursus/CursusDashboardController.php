<?php

namespace App\Http\Controllers\Cursus;

use App\Enums\CursusinschrijvingStatus;
use App\Http\Controllers\Controller;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\Cursusinschrijving;
use Illuminate\Contracts\View\View;

/**
 * Startscherm van de module Cursussen Administratie: kerncijfers en de cursussen
 * met hun aantal actieve inschrijvingen.
 */
class CursusDashboardController extends Controller
{
    public function index(): View
    {
        $cursussen = Cursus::withCount([
            'inschrijvingen as actieve_inschrijvingen' => fn ($q) => $q->where('status', CursusinschrijvingStatus::Actief->value),
        ])->orderBy('naam')->get();

        return view('cursussen.dashboard', [
            'cursussen' => $cursussen,
            'aantalCursussen' => $cursussen->where('actief', true)->count(),
            'aantalCursisten' => Cursist::count(),
            'aantalInschrijvingen' => Cursusinschrijving::where('status', CursusinschrijvingStatus::Actief->value)->count(),
        ]);
    }
}
