<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Enums\ExemplaarStatus;
use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Taal;
use App\Models\Bibliotheek\Vakgebied;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * De publieke zoekpagina van de bibliotheek — bedoeld voor de PC in de
 * bibliotheek, zodat een student zelf kan opzoeken of een boek er is en waar het
 * ligt. GEEN login.
 *
 * Wat hier bewust NIET staat, is even belangrijk als wat er wel staat:
 *   - geen leners, geen uitleenhistorie, geen namen van medewerkers;
 *   - geen enkele mutatieroute (alleen GET);
 *   - geen interne opmerkingen bij een boek (die kunnen aantekeningen van de
 *     bibliothecaris bevatten) — alleen titel, auteur, taal, vakgebied, jaar,
 *     ISBN, de rekplaats en of er een exemplaar vrij is.
 *
 * Er staan dus uitsluitend bibliografische gegevens op: geen persoonsgegevens.
 * De pagina is verder afgeschermd door de netwerkbeperking van de applicatie
 * (`SIS_TOEGESTANE_IPS`) en een verzoeklimiet op de route.
 */
class PubliekeCatalogusController extends Controller
{
    public function index(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));

        $publicaties = Publicatie::query()
            ->with(['auteurs', 'talen', 'vakgebied', 'reeks', 'exemplaren'])
            ->when($zoek !== '', fn ($q) => $q->zoek($zoek))
            ->when($request->filled('taal'), fn ($q) => $q->whereHas('talen', fn ($t) => $t->where('bibliotheek_talen.id', (int) $request->query('taal'))))
            ->when($request->filled('vakgebied'), fn ($q) => $q->where('vakgebied_id', (int) $request->query('vakgebied')))
            ->when($request->query('beschikbaar') === '1',
                fn ($q) => $q->whereHas('exemplaren', fn ($e) => $e->where('status', ExemplaarStatus::Beschikbaar)))
            ->when($request->filled('letter'), fn ($q) => $q->beginletter((string) $request->query('letter')))
            ->orderBy('titel')
            ->paginate(\App\Support\Paginakeuze::aantal($request))
            ->withQueryString();

        return view('catalogus.publiek', [
            'publicaties' => $publicaties,
            'talen' => Taal::where('actief', true)->orderBy('naam')->get(),
            'vakgebieden' => Vakgebied::where('actief', true)->orderBy('volgorde')->get(),
            'zoek' => $zoek,
            'taalFilter' => (int) $request->query('taal', 0),
            'vakgebiedFilter' => (int) $request->query('vakgebied', 0),
            'alleenBeschikbaar' => $request->query('beschikbaar') === '1',
            'letterFilter' => mb_strtoupper((string) $request->query('letter', '')),
            'perPagina' => \App\Support\Paginakeuze::aantal($request),
        ]);
    }
}
