<?php

namespace App\Http\Controllers\Scriptie;

use App\Enums\InschrijvingStatus;
use App\Http\Controllers\Controller;
use App\Models\Inschrijving;
use App\Models\Opleiding;
use App\Support\AuditLogger;
use App\Support\Scriptietoelating;
use App\Support\Scriptietraject;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Scriptie Kandidaten: de studenten die aan de toelatingseisen voldoen (minimaal
 * de EC-norm behaald én Methoden en Technieken I en II afgerond) en nog geen
 * scriptietraject hebben. Vanaf hier start de coördinator het traject.
 *
 * Alleen opleidingen met een scriptie (config) worden getoond, opleidinggebonden
 * gescoped voor de coördinator/Directie.
 */
class ScriptieKandidaatController extends Controller
{
    public function index(Request $request): View
    {
        $gebruiker = $request->user();

        // Opleidingen met een scriptie, binnen de scope van de gebruiker.
        $codes = Scriptietoelating::ondersteundeOpleidingcodes();
        $opleidingen = Opleiding::whereIn('code', $codes)
            ->when($gebruiker->isScriptieBeperkt(),
                fn ($q) => $q->whereIn('id', $gebruiker->opleidingIds()))
            ->orderBy('naam')->get();

        $gekozenOpleiding = $request->integer('opleiding') ?: null;
        $toonAlles = $request->boolean('alles'); // ook wie (nog) niet voldoet

        $inschrijvingen = Inschrijving::query()
            ->where('status', InschrijvingStatus::Actief->value)
            ->whereIn('opleiding_id', $opleidingen->pluck('id'))
            ->when($gekozenOpleiding, fn ($q) => $q->where('opleiding_id', $gekozenOpleiding))
            ->whereDoesntHave('scriptie')
            ->with(['student', 'opleiding'])
            ->get();

        $kandidaten = $inschrijvingen
            ->map(function (Inschrijving $inschrijving) {
                return [
                    'inschrijving' => $inschrijving,
                    'student' => $inschrijving->student,
                    'toelating' => Scriptietoelating::voor($inschrijving->student, $inschrijving),
                ];
            })
            ->filter(fn ($r) => $r['student'] !== null)
            ->when(! $toonAlles, fn ($c) => $c->filter(fn ($r) => $r['toelating']['voldoet']))
            ->sortBy(fn ($r) => $r['student']->achternaam)
            ->values();

        return view('scriptie.kandidaten', [
            'kandidaten' => $kandidaten,
            'opleidingen' => $opleidingen,
            'gekozenOpleiding' => $gekozenOpleiding,
            'toonAlles' => $toonAlles,
        ]);
    }

    /** Start een scriptietraject voor een inschrijving. */
    public function start(Request $request, Inschrijving $inschrijving): RedirectResponse
    {
        $gebruiker = $request->user();
        abort_unless($gebruiker->magScriptieBeheren(), 403, 'Alleen de scriptiecoördinator start een traject.');

        $inschrijving->loadMissing(['student', 'opleiding']);

        // Opleidinggebonden: de coördinator/Directie mag alleen de eigen opleiding.
        if ($gebruiker->isScriptieBeperkt()
            && ! $gebruiker->opleidingIds()->contains($inschrijving->opleiding_id)) {
            abort(403, 'Deze opleiding valt buiten uw bereik.');
        }

        // Eén traject per inschrijving.
        if ($inschrijving->scriptie()->exists()) {
            return redirect()->route('scriptie.show', $inschrijving->scriptie)
                ->with('status', 'Er loopt al een scriptietraject voor deze inschrijving.');
        }

        // De opleiding moet een scriptie kennen (geconfigureerd).
        abort_unless(
            in_array($inschrijving->opleiding?->code, Scriptietoelating::ondersteundeOpleidingcodes(), true),
            422,
            'Deze opleiding heeft geen scriptietraject.'
        );

        $scriptie = Scriptietraject::start($inschrijving, $gebruiker);

        AuditLogger::log(AuditLogger::AANMAAK, $scriptie, veld: 'scriptietraject', context: [
            'student' => $scriptie->student?->studentnummer,
            'opleiding' => $inschrijving->opleiding?->code,
        ]);

        return redirect()->route('scriptie.show', $scriptie)
            ->with('status', 'Scriptietraject '.$scriptie->scriptienummer.' gestart.');
    }
}
