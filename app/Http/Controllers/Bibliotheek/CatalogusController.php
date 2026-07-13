<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Enums\ExemplaarStatus;
use App\Enums\PublicatieSoort;
use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Taal;
use App\Models\Bibliotheek\Vakgebied;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * "Bibliotheek IUASR" — de catalogus als ALLEEN-LEZEN raadpleegscherm voor
 * iedere ingelogde medewerker, uit welke module hij ook komt.
 *
 * Bewust gescheiden van de bibliotheekmodule zelf: hier kan een docent of
 * medewerker een boek OPZOEKEN (staat het er, in welke taal, in welke kast, is
 * er een exemplaar beschikbaar), maar er is geen enkele mutatieroute. Uitlenen,
 * innemen en het beheren van de catalogus blijven voorbehouden aan de rol
 * Bibliotheek — daarvoor is de module.
 *
 * Er is geen aparte rolcontrole nodig: de route zit in de `auth`-groep, en een
 * boekentitel is geen gevoelig gegeven. Wat hier NIET staat, is even belangrijk:
 * geen lenersgegevens, geen uitleenhistorie, geen knoppen.
 */
class CatalogusController extends Controller
{
    public function index(Request $request): View
    {
        $publicaties = Publicatie::query()
            ->with(['auteurs', 'talen', 'vakgebied', 'reeks', 'exemplaren.kast'])
            ->when($request->filled('q'), fn ($q) => $q->zoek((string) $request->query('q')))
            ->when($request->filled('soort'), fn ($q) => $q->where('soort', (string) $request->query('soort')))
            ->when($request->filled('vakgebied'), fn ($q) => $q->where('vakgebied_id', (int) $request->query('vakgebied')))
            ->when($request->filled('taal'), fn ($q) => $q->whereHas('talen', fn ($t) => $t->where('bibliotheek_talen.id', (int) $request->query('taal'))))
            ->when($request->query('beschikbaar') === '1',
                fn ($q) => $q->whereHas('exemplaren', fn ($e) => $e->where('status', ExemplaarStatus::Beschikbaar)))
            ->orderBy('titel')
            ->paginate(25)
            ->withQueryString();

        return view('catalogus.index', [
            'publicaties' => $publicaties,
            'vakgebieden' => Vakgebied::where('actief', true)->orderBy('volgorde')->get(),
            'talen' => Taal::where('actief', true)->orderBy('naam')->get(),
            'soorten' => PublicatieSoort::opties(),
            'zoek' => (string) $request->query('q', ''),
            'soortFilter' => (string) $request->query('soort', ''),
            'vakgebiedFilter' => (int) $request->query('vakgebied', 0),
            'taalFilter' => (int) $request->query('taal', 0),
            'alleenBeschikbaar' => $request->query('beschikbaar') === '1',
        ]);
    }

    /** De boekenkaart: waar staat het, en is er een exemplaar vrij? */
    public function show(Publicatie $publicatie): View
    {
        $publicatie->load(['auteurs', 'talen', 'vakgebied', 'reeks.delen', 'exemplaren.kast', 'uitgaven.artikelen.auteurs']);

        return view('catalogus.show', ['publicatie' => $publicatie]);
    }
}
