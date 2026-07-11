<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Afdeling;
use App\Models\Medewerker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Globaal zoeken binnen de module HR / Personeelszaken (Fase G): over medewerkers
 * en afdelingen tegelijk. Team-gescoped — een team-beperkte gebruiker vindt
 * uitsluitend de eigen teamleden en geen instellingsbrede afdelingen.
 */
class ZoekController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $gebruiker = $request->user();

        $medewerkers = collect();
        $afdelingen = collect();

        if (mb_strlen($q) >= 2) {
            $medewerkers = Medewerker::query()->zichtbaarVoor($gebruiker)
                ->where(fn ($x) => $x->where('voornaam', 'like', "%{$q}%")
                    ->orWhere('achternaam', 'like', "%{$q}%")
                    ->orWhere('personeelsnummer', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%"))
                ->with(['afdeling', 'functie'])
                ->orderBy('achternaam')->orderBy('voornaam')->limit(25)->get();

            // Afdelingen zijn instellingsbreed; alleen tonen aan wie niet team-beperkt is.
            if (! $gebruiker->isHrTeamBeperkt()) {
                $afdelingen = Afdeling::query()
                    ->where(fn ($x) => $x->where('naam', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"))
                    ->with('manager')
                    ->withCount(['medewerkers as medewerkers_actief' => fn ($m) => $m->where('actief', true)])
                    ->orderBy('naam')->limit(25)->get();
            }
        }

        return view('hr.zoeken', compact('q', 'medewerkers', 'afdelingen'));
    }
}
