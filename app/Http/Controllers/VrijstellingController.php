<?php

namespace App\Http\Controllers;

use App\Enums\VrijstellingGrondslag;
use App\Models\Student;
use App\Models\Vaktoewijzing;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registratie van vrijstellingen door Studentenzaken (rolscheiding: dit is een
 * administratieve status, GEEN cijfer). Een vrijstelling wordt formeel verleend
 * door de examencommissie; SZ legt het besluit vast op de vak-toewijzing met
 * verplichte besluit-referentie en -datum. Een vrijstelling kent de volledige
 * vak-EC toe zonder numeriek eindcijfer (vermelding "VR" op de cijferlijst).
 */
class VrijstellingController extends Controller
{
    public function store(Request $request, Student $student): RedirectResponse
    {
        abort_unless(auth()->user()->magInschrijvingBeheren(), 403);

        $data = $request->validate([
            'vaktoewijzing_id' => ['required', 'integer'],
            'grondslag' => ['required', Rule::enum(VrijstellingGrondslag::class)],
            'besluit' => ['required', 'string', 'max:120'],
            'besluit_datum' => ['required', 'date'],
            'toelichting' => ['nullable', 'string', 'max:1000'],
        ], [], ['besluit' => 'besluit-referentie']);

        $vt = Vaktoewijzing::with(['vak', 'inschrijving'])->findOrFail($data['vaktoewijzing_id']);
        abort_unless($vt->inschrijving->student_id === $student->id, 403);

        $vt->update([
            'vrijgesteld' => true,
            'vrijstelling_grondslag' => $data['grondslag'],
            'vrijstelling_besluit' => $data['besluit'],
            'vrijstelling_besluit_datum' => $data['besluit_datum'],
            'vrijstelling_toelichting' => $data['toelichting'] ?? null,
            'vrijstelling_ec' => (float) $vt->vak->ec,
            'vrijgesteld_door_id' => auth()->id(),
            'vrijgesteld_op' => now(),
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'vrijstelling', context: [
            'vak' => $vt->vak->code, 'besluit' => $data['besluit'], 'grondslag' => $data['grondslag'],
        ]);

        return back()->with('status', 'Vrijstelling vastgelegd voor '.$vt->vak->code.'.');
    }

    public function destroy(Student $student, Vaktoewijzing $vaktoewijzing): RedirectResponse
    {
        abort_unless(auth()->user()->magInschrijvingBeheren(), 403);
        abort_unless($vaktoewijzing->inschrijving->student_id === $student->id, 403);

        $vakcode = $vaktoewijzing->vak?->code;
        $vaktoewijzing->update([
            'vrijgesteld' => false,
            'vrijstelling_grondslag' => null,
            'vrijstelling_besluit' => null,
            'vrijstelling_besluit_datum' => null,
            'vrijstelling_toelichting' => null,
            'vrijstelling_ec' => null,
            'vrijgesteld_door_id' => null,
            'vrijgesteld_op' => null,
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'vrijstelling', context: [
            'vak' => $vakcode, 'actie' => 'ingetrokken',
        ]);

        return back()->with('status', 'Vrijstelling ingetrokken'.($vakcode ? ' voor '.$vakcode : '').'.');
    }
}
