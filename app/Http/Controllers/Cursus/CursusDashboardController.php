<?php

namespace App\Http\Controllers\Cursus;

use App\Enums\CursusinschrijvingStatus;
use App\Http\Controllers\Controller;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\Cursusinschrijving;
use App\Support\Cursusrapport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Startscherm van de module Cursussen Administratie: kerncijfers en de cursussen
 * met hun aantal actieve inschrijvingen. Voor een cursusdirecteur is alles beperkt
 * tot de eigen cursus(sen); Financiën, Beheer en Bestuur zien alle cursussen.
 */
class CursusDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $gebruiker = $request->user();

        $cursussen = Cursus::query()->zichtbaarVoor($gebruiker)->withCount([
            'inschrijvingen as actieve_inschrijvingen' => fn ($q) => $q->where('status', CursusinschrijvingStatus::Actief->value),
        ])->orderBy('naam')->get();

        $cursusIds = $cursussen->pluck('id');

        $financieel = Cursusrapport::financieelTotaal(
            Cursus::query()->zichtbaarVoor($gebruiker)->with('inschrijvingen.betalingen')->get()
        );

        return view('cursussen.dashboard', [
            'cursussen' => $cursussen,
            'aantalCursussen' => $cursussen->where('actief', true)->count(),
            'aantalCursisten' => Cursist::query()->zichtbaarVoor($gebruiker)->count(),
            'aantalInschrijvingen' => Cursusinschrijving::whereIn('cursus_id', $cursusIds)
                ->where('status', CursusinschrijvingStatus::Actief->value)->count(),
            'financieel' => $financieel,
        ]);
    }
}
