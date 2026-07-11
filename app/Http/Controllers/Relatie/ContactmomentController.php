<?php

namespace App\Http\Controllers\Relatie;

use App\Http\Controllers\Controller;
use App\Models\Contactmoment;
use App\Models\ContactmomentType;
use App\Models\Organisatie;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registratie van contactmomenten bij een organisatie (module Relatiebeheer &
 * Stagebeheer). Autorisatie volgt de organisatie (beheerbaarVoor). Contactmomenten
 * zijn historische records: ze worden niet verwijderd, wel corrigeerbaar. Mutaties
 * worden gelogd.
 */
class ContactmomentController extends Controller
{
    public function create(Request $request, Organisatie $organisatie): View
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.contactmoment-form', [
            'organisatie' => $organisatie,
            'contactmoment' => new Contactmoment(['datum' => now()->toDateString()]),
            'types' => ContactmomentType::query()->actief()->orderBy('volgorde')->orderBy('naam')->get(),
            'contactpersonen' => $organisatie->contactpersonen()->orderBy('achternaam')->get(),
        ]);
    }

    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $contactmoment = $organisatie->contactmomenten()->create(
            $this->valideer($request, $organisatie) + ['medewerker_id' => $request->user()->id]
        );
        AuditLogger::log(AuditLogger::AANMAAK, $contactmoment, veld: 'contactmoment', context: ['organisatie' => $organisatie->relatienummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Contactmoment vastgelegd.');
    }

    public function edit(Request $request, Contactmoment $contactmoment): View
    {
        $organisatie = $contactmoment->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.contactmoment-form', [
            'organisatie' => $organisatie,
            'contactmoment' => $contactmoment,
            'types' => ContactmomentType::query()->actief()->orderBy('volgorde')->orderBy('naam')->get(),
            'contactpersonen' => $organisatie->contactpersonen()->orderBy('achternaam')->get(),
        ]);
    }

    public function update(Request $request, Contactmoment $contactmoment): RedirectResponse
    {
        $organisatie = $contactmoment->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $contactmoment->update($this->valideer($request, $organisatie));
        AuditLogger::log(AuditLogger::WIJZIGING, $contactmoment, veld: 'contactmoment', context: ['organisatie' => $organisatie->relatienummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Contactmoment bijgewerkt.');
    }

    /**
     * Maak een opvolgtaak van een contactmoment met een vervolgdatum. De taak
     * krijgt de vervaldatum van het contactmoment en verwijst naar dezelfde
     * organisatie (actiepunt → taak).
     */
    public function maakTaak(Request $request, Contactmoment $contactmoment): RedirectResponse
    {
        $organisatie = $contactmoment->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');
        abort_unless($contactmoment->vervolgdatum !== null, 422, 'Dit contactmoment heeft geen vervolgdatum.');

        $organisatie->relatietaken()->create([
            'titel' => 'Opvolging: '.$contactmoment->onderwerp,
            'omschrijving' => $contactmoment->samenvatting,
            'vervaldatum' => $contactmoment->vervolgdatum,
            'prioriteit' => 'normaal',
            'status' => 'open',
            'toegewezen_aan_id' => $request->user()->id,
            'aangemaakt_door_id' => $request->user()->id,
        ]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Opvolgtaak aangemaakt.');
    }

    private function valideer(Request $request, Organisatie $organisatie): array
    {
        $contactpersoonIds = $organisatie->contactpersonen()->pluck('id')->all();

        return $request->validate([
            'contactmoment_type_id' => ['nullable', 'integer', 'exists:contactmoment_types,id'],
            'contactpersoon_id' => ['nullable', 'integer', Rule::in($contactpersoonIds)],
            'datum' => ['required', 'date'],
            'tijd' => ['nullable', 'date_format:H:i'],
            'onderwerp' => ['required', 'string', 'max:255'],
            'samenvatting' => ['nullable', 'string', 'max:5000'],
            'vervolgdatum' => ['nullable', 'date'],
        ], [], ['contactpersoon_id' => 'contactpersoon']);
    }
}
