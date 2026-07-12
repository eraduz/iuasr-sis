<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * De EXAMENCOMMISSIE geeft — bij hoge uitzondering — vervroegd afstuderen vrij:
 * een student met genoeg vrijstellingen/eerder behaalde EC mag dan afstuderen
 * vóór het laatste leerjaar. Studentenzaken voert het afstuderen daarna
 * administratief uit (Acties → Afgestudeerd markeren). De vrijgave of intrekking
 * wordt gelogd; wie het besluit nam, staat in de audit-log.
 */
class VervroegdAfstuderenController extends Controller
{
    public function bijwerken(Request $request, Inschrijving $inschrijving): RedirectResponse
    {
        abort_unless($request->user()->magVervroegdAfstuderenVrijgeven(), 403);

        $data = $request->validate([
            'reden' => ['nullable', 'string', 'max:255'],
        ]);
        $vrijgeven = $request->boolean('vervroegd_afstuderen');

        // Vrijgeven heeft alleen zin bij een lopende inschrijving; een reeds
        // afgestudeerde/uitgeschreven inschrijving is een eindstatus.
        if ($vrijgeven && ! $inschrijving->isLopend()) {
            return back()->with('status', 'Vrijgeven kan alleen bij een lopende inschrijving.');
        }

        if ((bool) $inschrijving->vervroegd_afstuderen !== $vrijgeven) {
            $inschrijving->update(['vervroegd_afstuderen' => $vrijgeven]);

            AuditLogger::log(AuditLogger::WIJZIGING, $inschrijving->student, veld: 'vervroegd_afstuderen', context: [
                'inschrijving_id' => $inschrijving->id,
                'opleiding' => $inschrijving->opleiding?->code,
                'leerjaar' => $inschrijving->leerjaar,
                'vrijgegeven' => $vrijgeven,
                'reden' => $data['reden'] ?? null,
            ]);
        }

        return back()->with('status', $vrijgeven
            ? 'Vervroegd afstuderen vrijgegeven door de examencommissie. Studentenzaken kan de student nu afstuderen.'
            : 'Vrijgave voor vervroegd afstuderen ingetrokken.');
    }
}
