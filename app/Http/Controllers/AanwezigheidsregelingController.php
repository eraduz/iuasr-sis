<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 50%-aanwezigheidsregeling: de student hoeft minimaal de helft van de
 * colleges, practica en werkgroepen bij te wonen in plaats van de reguliere
 * norm. De regeling wordt door de directie toegestaan en door Studentenzaken
 * op de inschrijving vastgelegd — per opleiding én per studiejaar, zodat zij
 * bij herinschrijving bewust opnieuw wordt toegekend.
 *
 * De mutatie wordt gelogd (wie, wanneer, welke inschrijving).
 */
class AanwezigheidsregelingController extends Controller
{
    public function bijwerken(Request $request, Inschrijving $inschrijving): RedirectResponse
    {
        abort_unless($request->user()->can('aanwezigheidsregeling-beheren'), 403);

        $toegekend = $request->boolean('aanwezigheidsregeling_50');

        if ($inschrijving->aanwezigheidsregeling_50 !== $toegekend) {
            $inschrijving->update(['aanwezigheidsregeling_50' => $toegekend]);

            AuditLogger::log(AuditLogger::WIJZIGING, $inschrijving->student,
                veld: 'aanwezigheidsregeling_50',
                context: [
                    'inschrijving_id' => $inschrijving->id,
                    'opleiding' => $inschrijving->opleiding?->code,
                    'studiejaar' => $inschrijving->periode?->code,
                    'toegekend' => $toegekend,
                ]);
        }

        return back()->with('status', $toegekend
            ? '50%-aanwezigheidsregeling toegekend.'
            : '50%-aanwezigheidsregeling ingetrokken.');
    }
}
