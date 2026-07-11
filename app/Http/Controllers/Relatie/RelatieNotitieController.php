<?php

namespace App\Http\Controllers\Relatie;

use App\Http\Controllers\Controller;
use App\Models\Organisatie;
use App\Models\RelatieNotitie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Vrije notities bij een organisatie (module Relatiebeheer & Stagebeheer). Geen
 * audit-logging: een notitie is werkinformatie, geen gevoelig persoonsgegeven —
 * consistent met de interne notities elders in het systeem. Autorisatie volgt de
 * organisatie (beheerbaarVoor).
 */
class RelatieNotitieController extends Controller
{
    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $data = $request->validate([
            'categorie' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'tekst' => ['required', 'string', 'max:5000'],
        ]);

        $organisatie->notities()->create($data + ['auteur_id' => $request->user()->id]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Notitie toegevoegd.');
    }

    public function destroy(Request $request, RelatieNotitie $notitie): RedirectResponse
    {
        $organisatie = $notitie->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $notitie->delete();

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Notitie verwijderd.');
    }
}
