<?php

namespace App\Http\Controllers;

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

        return view('modules.index', [
            'modules' => $modules->map(fn (Module $m) => [
                'module' => $m,
                'bruikbaar' => $m->bruikbaarVoor($gebruiker),
                'toegankelijk' => $m->toegankelijkVoor($gebruiker),
                'route' => $m->startRoute(),
            ]),
        ]);
    }
}
