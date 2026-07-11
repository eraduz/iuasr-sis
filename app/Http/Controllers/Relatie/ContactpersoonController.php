<?php

namespace App\Http\Controllers\Relatie;

use App\Http\Controllers\Controller;
use App\Models\Contactpersoon;
use App\Models\Organisatie;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Beheer van de contactpersonen bij een organisatie (module Relatiebeheer &
 * Stagebeheer). De autorisatie volgt die van de organisatie: alleen wie de
 * organisatie mag beheren (relatiebeheerder/stagecoördinator van de eigen
 * opleiding, of Beheerder) kan haar contactpersonen muteren. Mutaties gelogd;
 * een contactpersoon wordt op inactief gezet, niet verwijderd (historie).
 */
class ContactpersoonController extends Controller
{
    public function create(Request $request, Organisatie $organisatie): View
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.contactpersoon-form', [
            'organisatie' => $organisatie,
            'contactpersoon' => new Contactpersoon(['actief' => true]),
        ]);
    }

    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $contactpersoon = $organisatie->contactpersonen()->create($this->valideer($request));
        AuditLogger::log(AuditLogger::AANMAAK, $contactpersoon, veld: 'contactpersoon', context: ['organisatie' => $organisatie->relatienummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Contactpersoon toegevoegd.');
    }

    public function edit(Request $request, Contactpersoon $contactpersoon): View
    {
        abort_unless($contactpersoon->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.contactpersoon-form', [
            'organisatie' => $contactpersoon->organisatie,
            'contactpersoon' => $contactpersoon,
        ]);
    }

    public function update(Request $request, Contactpersoon $contactpersoon): RedirectResponse
    {
        abort_unless($contactpersoon->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $contactpersoon->update($this->valideer($request));
        AuditLogger::log(AuditLogger::WIJZIGING, $contactpersoon, veld: 'contactpersoon', context: ['organisatie' => $contactpersoon->organisatie->relatienummer]);

        return redirect()->route('relaties.show', $contactpersoon->organisatie)->with('status', 'Contactpersoon bijgewerkt.');
    }

    public function status(Request $request, Contactpersoon $contactpersoon): RedirectResponse
    {
        abort_unless($contactpersoon->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $contactpersoon->update(['actief' => ! $contactpersoon->actief]);
        AuditLogger::log(AuditLogger::WIJZIGING, $contactpersoon, veld: 'contactpersoon_status', context: ['actief' => $contactpersoon->actief]);

        return back()->with('status', $contactpersoon->actief ? 'Contactpersoon geactiveerd.' : 'Contactpersoon op inactief gezet.');
    }

    private function valideer(Request $request): array
    {
        $data = $request->validate([
            'voornaam' => ['required', 'string', 'max:255'],
            'achternaam' => ['required', 'string', 'max:255'],
            'functie' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobiel' => ['nullable', 'string', 'max:30'],
            'telefoon' => ['nullable', 'string', 'max:30'],
            'afdeling' => ['nullable', 'string', 'max:255'],
            'voorkeur_communicatie' => ['nullable', 'in:e-mail,telefoon,teams'],
            'linkedin' => ['nullable', 'string', 'max:255'],
        ]);

        $data['actief'] = $request->boolean('actief');

        return $data;
    }
}
