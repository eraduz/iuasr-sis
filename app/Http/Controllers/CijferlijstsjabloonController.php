<?php

namespace App\Http\Controllers;

use App\Models\Cijferlijstsjabloon;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Beheer van het standaard-e-mailsjabloon voor de cijferlijst-mail. Alleen de
 * examencommissie (opdrachtgever 2026-07-22): zij verstuurt de cijferlijsten en
 * beheert de begeleidende tekst. Variabelen: {{Naam}}, {{Periode}}, {{Opleiding}}.
 */
class CijferlijstsjabloonController extends Controller
{
    public function edit(): View
    {
        return view('cijfers.sjabloon', ['sjabloon' => Cijferlijstsjabloon::huidige()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'onderwerp' => ['required', 'string', 'max:255'],
            'inhoud' => ['required', 'string', 'max:5000'],
        ]);

        $sjabloon = Cijferlijstsjabloon::huidige();
        $sjabloon->update($data);

        AuditLogger::log(AuditLogger::WIJZIGING, $sjabloon, veld: 'cijferlijst-mailsjabloon');

        return redirect()->route('cijferlijst-sjabloon')->with('status', 'Het e-mailsjabloon is bijgewerkt.');
    }
}
