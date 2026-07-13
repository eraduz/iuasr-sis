<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Korting op het collegegeld van één inschrijving, doorgaans voor een tweede
 * opleiding. Studentenzaken legt het percentage vast; het systeem leidt nooit
 * zelf af welke opleiding 'de tweede' is.
 *
 * Een korting boven 0% vereist een reden: er wordt minder gefactureerd dan het
 * tarief, en dat moet achteraf navolgbaar zijn. De wijziging wordt gelogd.
 */
class KortingController extends Controller
{
    public function bijwerken(Request $request, Inschrijving $inschrijving): RedirectResponse
    {
        abort_unless($request->user()->magCollegegeldBeheren(), 403);

        // Een afgestudeerde inschrijving is afgerond en bevroren: geen korting meer.
        if ($inschrijving->isAfgestudeerd()) {
            return back()->with('status', 'De opleiding is afgerond (afgestudeerd); de korting kan niet meer worden gewijzigd.');
        }

        $data = $request->validate([
            'korting_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'korting_reden' => ['nullable', 'string', 'max:120', 'required_unless:korting_percentage,0'],
        ], [
            'korting_reden.required_unless' => 'Geef een reden op wanneer u korting verleent.',
        ]);

        $percentage = round((float) $data['korting_percentage'], 2);
        $reden = $percentage > 0 ? trim((string) $data['korting_reden']) : null;

        $oudPercentage = (float) $inschrijving->korting_percentage;
        $oudeReden = $inschrijving->korting_reden;

        if ($oudPercentage === $percentage && $oudeReden === $reden) {
            return back()->with('status', 'Korting ongewijzigd.');
        }

        $inschrijving->update(['korting_percentage' => $percentage, 'korting_reden' => $reden]);

        AuditLogger::log(AuditLogger::WIJZIGING, $inschrijving->student, veld: 'korting_collegegeld', context: [
            'inschrijving_id' => $inschrijving->id,
            'opleiding' => $inschrijving->opleiding?->code,
            'studiejaar' => $inschrijving->periode?->code,
            'van' => ['percentage' => $oudPercentage, 'reden' => $oudeReden],
            'naar' => ['percentage' => $percentage, 'reden' => $reden],
        ]);

        return back()->with('status', $percentage > 0
            ? 'Korting van '.rtrim(rtrim(number_format($percentage, 2, ',', ''), '0'), ',').'% vastgelegd.'
            : 'Korting ingetrokken; het volledige tarief geldt.');
    }
}
