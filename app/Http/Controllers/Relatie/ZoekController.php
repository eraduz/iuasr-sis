<?php

namespace App\Http\Controllers\Relatie;

use App\Http\Controllers\Controller;
use App\Models\Contactpersoon;
use App\Models\Organisatie;
use App\Models\Stage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Globaal zoeken binnen de module Relatiebeheer & Stagebeheer: over organisaties,
 * contactpersonen en stages tegelijk. Opleidinggebonden gescoped — men vindt
 * uitsluitend wat binnen de eigen opleiding(en) valt.
 */
class ZoekController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $gebruiker = $request->user();

        $organisaties = collect();
        $contactpersonen = collect();
        $stages = collect();

        if (mb_strlen($q) >= 2) {
            $organisaties = Organisatie::query()->zichtbaarVoor($gebruiker)
                ->where(fn ($x) => $x->where('naam', 'like', "%{$q}%")
                    ->orWhere('plaats', 'like', "%{$q}%")
                    ->orWhere('relatienummer', 'like', "%{$q}%"))
                ->orderBy('naam')->limit(25)->get();

            $contactpersonen = Contactpersoon::query()
                ->whereHas('organisatie', fn ($o) => $o->zichtbaarVoor($gebruiker))
                ->where(fn ($x) => $x->where('voornaam', 'like', "%{$q}%")
                    ->orWhere('achternaam', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('functie', 'like', "%{$q}%"))
                ->with('organisatie')->orderBy('achternaam')->limit(25)->get();

            $stages = Stage::query()->zichtbaarVoor($gebruiker)
                ->where(fn ($x) => $x->where('stagenummer', 'like', "%{$q}%")
                    ->orWhereHas('student', fn ($s) => $s->where('achternaam', 'like', "%{$q}%")->orWhere('studentnummer', 'like', "%{$q}%")))
                ->with(['student', 'organisatie'])->orderByDesc('id')->limit(25)->get();
        }

        return view('relaties.zoeken', compact('q', 'organisaties', 'contactpersonen', 'stages'));
    }
}
