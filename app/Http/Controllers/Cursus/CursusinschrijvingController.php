<?php

namespace App\Http\Controllers\Cursus;

use App\Enums\CursusinschrijvingStatus;
use App\Http\Controllers\Controller;
use App\Models\Cursist;
use App\Models\Cursus;
use App\Models\Cursusinschrijving;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Inschrijving van een cursist op een cursus, en het bijwerken van de status
 * (bijv. afronden of annuleren). Het cursusgeld wordt bij inschrijving als
 * momentopname vastgelegd, zodat een latere tariefwijziging niets verandert.
 */
class CursusinschrijvingController extends Controller
{
    public function store(Request $request, Cursist $cursist): RedirectResponse
    {
        $data = $request->validate([
            'cursus_id' => ['required', Rule::exists('cursussen', 'id')],
            'inschrijfdatum' => ['nullable', 'date'],
        ]);

        $cursus = Cursus::findOrFail($data['cursus_id']);

        // Al een lopende inschrijving op deze cursus? Dan niet dubbel inschrijven.
        $bestaat = $cursist->inschrijvingen()
            ->where('cursus_id', $cursus->id)
            ->whereIn('status', [CursusinschrijvingStatus::Aangemeld->value, CursusinschrijvingStatus::Actief->value])
            ->exists();
        if ($bestaat) {
            return back()->with('status', 'Deze cursist is al ingeschreven op '.$cursus->naam.'.');
        }

        $cursist->inschrijvingen()->create([
            'cursus_id' => $cursus->id,
            'inschrijfdatum' => $data['inschrijfdatum'] ?? now()->toDateString(),
            'status' => CursusinschrijvingStatus::Actief,
            'totaalbedrag' => $cursus->cursusgeld,
            'ingeschreven_door_id' => $request->user()->id,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $cursist, veld: 'cursusinschrijving',
            context: ['cursus' => $cursus->code, 'bedrag' => (float) $cursus->cursusgeld]);

        return back()->with('status', 'Ingeschreven op '.$cursus->naam.'.');
    }

    public function update(Request $request, Cursist $cursist, Cursusinschrijving $inschrijving): RedirectResponse
    {
        abort_unless($inschrijving->cursist_id === $cursist->id, 404);

        $data = $request->validate([
            'status' => ['required', new Enum(CursusinschrijvingStatus::class)],
        ]);

        $inschrijving->update(['status' => $data['status']]);

        AuditLogger::log(AuditLogger::WIJZIGING, $cursist, veld: 'cursusinschrijving',
            context: ['cursus' => $inschrijving->cursus?->code, 'status' => $data['status']]);

        return back()->with('status', 'Inschrijvingsstatus bijgewerkt.');
    }
}
