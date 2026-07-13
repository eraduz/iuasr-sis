<?php

namespace App\Http\Controllers;

use App\Enums\Betaalregeling;
use App\Models\Inschrijving;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Betaalregeling per inschrijving: vijf termijnen of één factuur voor het
 * volledige jaarbedrag. Studentenzaken maakt deze afspraak met de student bij
 * de inschrijving; de Financiële Administratie boekt daarna per termijn.
 *
 * De regeling hangt aan de INSCHRIJVING en geldt dus per studiejaar: bij
 * herinschrijving wordt zij opnieuw vastgesteld. De wijziging wordt gelogd.
 */
class BetaalregelingController extends Controller
{
    public function bijwerken(Request $request, Inschrijving $inschrijving): RedirectResponse
    {
        abort_unless($request->user()->magCollegegeldBeheren(), 403);

        // Een afgestudeerde inschrijving is afgerond en bevroren: geen betaalregeling meer.
        if ($inschrijving->isAfgestudeerd()) {
            return back()->with('status', 'De opleiding is afgerond (afgestudeerd); de betaalregeling kan niet meer worden gewijzigd.');
        }

        $data = $request->validate([
            'betaalregeling' => ['required', new Enum(Betaalregeling::class)],
        ]);

        $nieuw = Betaalregeling::from($data['betaalregeling']);

        if ($inschrijving->betaalregeling !== $nieuw) {
            $oud = $inschrijving->betaalregeling;
            $inschrijving->update(['betaalregeling' => $nieuw]);

            AuditLogger::log(AuditLogger::WIJZIGING, $inschrijving->student, veld: 'betaalregeling', context: [
                'inschrijving_id' => $inschrijving->id,
                'studiejaar' => $inschrijving->periode?->code,
                'van' => $oud?->value,
                'naar' => $nieuw->value,
            ]);
        }

        return back()->with('status', 'Betaalregeling gewijzigd naar: '.$nieuw->label().'.');
    }
}
