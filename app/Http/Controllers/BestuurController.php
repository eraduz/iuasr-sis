<?php

namespace App\Http\Controllers;

use App\Enums\CursusinschrijvingStatus;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\Cursusinschrijving;
use App\Models\Opleiding;
use App\Support\Statistiek;
use Illuminate\Contracts\View\View;

/**
 * Globale bestuurspagina: een instellingsbreed overzicht voor het Schoolbestuur
 * (en Beheer). Alles is alleen-lezen en overkoepelend — studenten, onderwijs,
 * aanwezigheid, financiën én de cursussen op één plek. De onderliggende cijfers
 * komen uit dezelfde `Statistiek`-aggregaties als het dashboard.
 */
class BestuurController extends Controller
{
    public function index(): View
    {
        $kern = Statistiek::kern();
        $slaag = Statistiek::slaagpercentage();
        $presentie = Statistiek::presentie();
        $financieel = Statistiek::financieel();

        // Cursussen (aparte module) — instellingsbreed meegenomen in het overzicht.
        $cursusInschrijvingen = Cursusinschrijving::where('status', CursusinschrijvingStatus::Actief->value)->count();
        $cursusPerCursus = Cursus::withCount([
            'inschrijvingen as actieve_inschrijvingen' => fn ($q) => $q->where('status', CursusinschrijvingStatus::Actief->value),
        ])->orderByDesc('actieve_inschrijvingen')->get()
            ->map(fn (Cursus $c) => ['label' => $c->naam, 'value' => (int) $c->actieve_inschrijvingen])
            ->all();

        return view('bestuur.index', [
            'kern' => $kern,
            'slaag' => $slaag,
            'presentie' => $presentie,
            'financieel' => $financieel,
            'perOpleiding' => Statistiek::perOpleiding(),
            'instroom' => Statistiek::instroomPerStudiejaar(),
            'status' => Statistiek::statusVerdeling(),
            'presentiePerOpleiding' => Statistiek::presentiePerOpleiding(),
            'presentieVerdeling' => Statistiek::presentieVerdeling(),
            'aantalOpleidingen' => Opleiding::where('actief', true)->count(),
            'aantalCursussen' => Cursus::where('actief', true)->count(),
            'aantalCursisten' => Cursist::count(),
            'cursusInschrijvingen' => $cursusInschrijvingen,
            'cursusPerCursus' => $cursusPerCursus,
        ]);
    }
}
