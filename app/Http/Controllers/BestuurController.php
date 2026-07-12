<?php

namespace App\Http\Controllers;

use App\Enums\CursusinschrijvingStatus;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\Medewerker;
use App\Models\Opleiding;
use App\Support\Cursusrapport;
use App\Support\HrRapport;
use App\Support\Relatierapport;
use App\Support\Statistiek;
use Illuminate\Contracts\View\View;

/**
 * Globale bestuurspagina: één instellingsbreed overzicht voor het Schoolbestuur
 * (en Beheer). Het bundelt de statistieken van álle modules en afdelingen —
 * Studentenzaken, Cursussen, Relatiebeheer & Stage en HR / Personeelszaken —
 * geordend in duidelijke rubrieken. Alles is alleen-lezen en overkoepelend
 * (scope = null, dus instellingsbreed). De cijfers komen uit dezelfde
 * aggregatieklassen als de losse moduledashboards.
 */
class BestuurController extends Controller
{
    public function index(): View
    {
        // Cursussen één keer inladen (met inschrijvingen) voor zowel de aantallen
        // als de cursusgelden.
        $cursussen = Cursus::with('inschrijvingen')->get();
        $actieveInschrijvingen = fn (Cursus $c) => $c->inschrijvingen
            ->where('status', CursusinschrijvingStatus::Actief->value)->count();

        // Collegegeld (opleidingen) en cursusgelden (cursussen) — twee gescheiden
        // geldstromen die samen het instellingsbrede financiële beeld vormen.
        $collegegeld = Statistiek::financieel();
        $cursusgeld = Cursusrapport::financieelTotaal($cursussen);
        $financieelTotaal = [
            'verschuldigd' => $collegegeld['verschuldigd'] + $cursusgeld['verschuldigd'],
            'betaald' => $collegegeld['betaald'] + $cursusgeld['betaald'],
            'openstaand' => $collegegeld['openstaand'] + $cursusgeld['openstaand'],
        ];
        $financieelTotaal['betaalgraad'] = $financieelTotaal['verschuldigd'] > 0
            ? (int) round($financieelTotaal['betaald'] / $financieelTotaal['verschuldigd'] * 100)
            : 0;

        return view('bestuur.index', [
            // Rubriek 1 — Studenten & onderwijs (module Studentenzaken).
            'kern' => Statistiek::kern(),
            'slaag' => Statistiek::slaagpercentage(),
            'presentie' => Statistiek::presentie(),
            'perOpleiding' => Statistiek::perOpleiding(),
            'instroom' => Statistiek::instroomPerStudiejaar(),
            'status' => Statistiek::statusVerdeling(),
            'presentiePerOpleiding' => Statistiek::presentiePerOpleiding(),
            'presentieVerdeling' => Statistiek::presentieVerdeling(),
            'aantalOpleidingen' => Opleiding::where('actief', true)->count(),

            // Rubriek 2 — Financiën. Twee geldstromen expliciet gescheiden plus totaal:
            // collegegeld komt van de opleidingen, cursusgeld van de cursussen.
            'collegegeld' => $collegegeld,
            'cursusgeld' => $cursusgeld,
            'financieelTotaal' => $financieelTotaal,

            // Rubriek 3 — Cursussen (module Cursussen).
            'aantalCursussen' => $cursussen->where('actief', true)->count(),
            'aantalCursisten' => Cursist::count(),
            'cursusInschrijvingen' => $cursussen->sum($actieveInschrijvingen),
            'cursusPerCursus' => $cursussen
                ->map(fn (Cursus $c) => ['label' => $c->naam, 'value' => $actieveInschrijvingen($c)])
                ->sortByDesc('value')->values()->all(),

            // Rubriek 4 — Relatiebeheer & Stage (module Relatiebeheer).
            'relatie' => Relatierapport::kerncijfers(),
            'stagesPerStatus' => Relatierapport::stagesPerStatus(),
            'organisatiesPerType' => Relatierapport::organisatiesPerType(),
            'stageEvaluatie' => Relatierapport::evaluatie(),

            // Rubriek 5 — HR / Personeelszaken (module HR).
            'hr' => HrRapport::kerncijfers(),
            'hrPerAfdeling' => HrRapport::perAfdeling(),
            'aantalAfdelingen' => Medewerker::query()->whereNotNull('afdeling_id')->distinct('afdeling_id')->count('afdeling_id'),

            // Onderwijsnieuws (lokaal opgeslagen; opgehaald door de scheduler).
            'nieuws' => \App\Models\Nieuwsbericht::with('bron')
                ->orderByDesc('gepubliceerd_op')->orderByDesc('id')
                ->limit((int) config('sis.nieuws.toon_aantal', 6))->get(),
        ]);
    }
}
