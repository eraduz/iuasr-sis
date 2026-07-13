<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Emailsjabloon;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Beheer van de vijf e-mailsjablonen (uitleenbevestiging, herinnering vooraf,
 * te laat student, te laat docent, retourbevestiging). Uitsluitend de Beheerder,
 * zoals de opdracht voorschrijft ("de Administrator moet e-mailtemplates kunnen
 * beheren").
 *
 * Nieuwe sjablonen aanmaken of verwijderen kan niet: de vijf soorten liggen vast
 * in de code (BibliotheekMailsoort) en worden door de verzendlogica opgezocht.
 * De beheerder past onderwerp en tekst aan.
 */
class EmailsjabloonController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->magBibliotheekSjablonenBeheren(), 403, 'Alleen de Beheerder beheert de e-mailsjablonen.');

        return view('bibliotheek.sjablonen.index', [
            'sjablonen' => Emailsjabloon::orderBy('id')->get(),
            'variabelen' => Emailsjabloon::VARIABELEN,
        ]);
    }

    public function update(Request $request, Emailsjabloon $sjabloon): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekSjablonenBeheren(), 403, 'Alleen de Beheerder beheert de e-mailsjablonen.');

        $data = $request->validate([
            'onderwerp' => ['required', 'string', 'max:255'],
            'inhoud' => ['required', 'string', 'max:5000'],
            'actief' => ['nullable', 'boolean'],
        ], [], ['onderwerp' => 'onderwerp', 'inhoud' => 'inhoud']);

        $oud = ['onderwerp' => $sjabloon->onderwerp, 'actief' => $sjabloon->actief];

        $sjabloon->update([
            'onderwerp' => $data['onderwerp'],
            'inhoud' => $data['inhoud'],
            'actief' => $request->boolean('actief'),
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $sjabloon, veld: 'emailsjabloon', context: [
            'soort' => $sjabloon->soort->value,
            'oud' => $oud,
            'nieuw' => ['onderwerp' => $sjabloon->onderwerp, 'actief' => $sjabloon->actief],
        ]);

        return back()->with('status', 'Sjabloon "'.$sjabloon->soort->label().'" bijgewerkt.');
    }
}
