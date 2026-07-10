<?php

namespace App\Http\Controllers;

use App\Models\Cursus;
use App\Models\Module;
use Illuminate\Contracts\View\View;

/**
 * Keuzescherm dat na de login verschijnt: de gebruiker kiest een module
 * (Studentenzaken, Cursussen, en later Stage, Scriptie, HR). Welke modules
 * bruikbaar zijn, volgt uit de rol; nog niet gebouwde modules worden als
 * 'binnenkort' getoond.
 */
class ModuleController extends Controller
{
    public function index(): View
    {
        $gebruiker = auth()->user();
        $modules = Module::voorKeuzescherm($gebruiker);

        // Directe knoppen per cursus op het welkomstscherm. De gebruiker ziet
        // uitsluitend de cursussen die hij mag openen (cursusdirecteur = eigen
        // cursus[sen]; Financiën, Beheer en Bestuur = alle actieve cursussen).
        $cursussen = collect();
        $cursusModule = Module::where('sleutel', 'cursussen')->first();
        if ($cursusModule && $cursusModule->bruikbaarVoor($gebruiker)) {
            $cursussen = Cursus::query()->zichtbaarVoor($gebruiker)
                ->where('actief', true)->orderBy('naam')->get();
        }

        return view('modules.index', [
            'modules' => $modules->map(fn (Module $m) => [
                'module' => $m,
                'bruikbaar' => $m->bruikbaarVoor($gebruiker),
                'toegankelijk' => $m->toegankelijkVoor($gebruiker),
                'route' => $m->startRoute(),
            ]),
            'cursussen' => $cursussen,
        ]);
    }
}
