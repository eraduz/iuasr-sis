<?php

namespace App\Http\Controllers;

use App\Enums\VrijstellingGrondslag;
use App\Enums\VrijstellingsbesluitStatus;
use App\Models\Inschrijving;
use App\Models\Student;
use App\Models\Vrijstellingsbesluit;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Workflow vrijstelling: de examencommissie legt een besluit vast en stuurt het
 * (intern) naar Studentenzaken. Studentenzaken verwerkt het met één klik,
 * waarna de vrijstelling op de vak-toewijzing van de student wordt vastgelegd.
 * Alles blijft binnen het systeem en wordt gelogd.
 */
class VrijstellingsbesluitController extends Controller
{
    /** Examencommissie/Directie: besluit vastleggen en naar Studentenzaken sturen. */
    public function store(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'vak_id' => ['required', 'integer', 'exists:vakken,id'],
            'grondslag' => ['required', Rule::enum(VrijstellingGrondslag::class)],
            'besluit' => ['required', 'string', 'max:120'],
            'besluit_datum' => ['required', 'date'],
            'toelichting' => ['nullable', 'string', 'max:1000'],
        ], [], ['besluit' => 'besluit-referentie']);

        $bestaat = Vrijstellingsbesluit::where('student_id', $student->id)
            ->where('vak_id', $data['vak_id'])
            ->where('status', VrijstellingsbesluitStatus::Open)->exists();
        if ($bestaat) {
            return back()->with('fout', 'Er staat al een openstaand besluit voor dit vak.');
        }

        Vrijstellingsbesluit::create([
            'student_id' => $student->id,
            'vak_id' => $data['vak_id'],
            'grondslag' => $data['grondslag'],
            'besluit' => $data['besluit'],
            'besluit_datum' => $data['besluit_datum'],
            'toelichting' => $data['toelichting'] ?? null,
            'status' => VrijstellingsbesluitStatus::Open,
            'aangemaakt_door_id' => auth()->id(),
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $student, veld: 'vrijstellingsbesluit', context: [
            'vak_id' => $data['vak_id'], 'besluit' => $data['besluit'],
        ]);

        return back()->with('status', 'Vrijstellingsbesluit verstuurd naar Studentenzaken.');
    }

    /** Studentenzaken: besluit verwerken -> vrijstelling vastleggen op de vak-toewijzing. */
    public function verwerk(Vrijstellingsbesluit $besluit): RedirectResponse
    {
        abort_unless($besluit->status === VrijstellingsbesluitStatus::Open, 403, 'Dit besluit is al afgehandeld.');
        $besluit->load('vak', 'student');

        $inschrijving = Inschrijving::where('student_id', $besluit->student_id)->where('status', 'actief')
            ->latest('inschrijfdatum')->first()
            ?? Inschrijving::where('student_id', $besluit->student_id)->latest('inschrijfdatum')->first();

        $toewijzing = $inschrijving?->vaktoewijzingen()->where('vak_id', $besluit->vak_id)->first();

        if (! $toewijzing) {
            return back()->with('fout', 'Het vak is niet aan deze student toegewezen. Wijs het vak eerst toe en verwerk het besluit daarna.');
        }

        if (! $toewijzing->vrijgesteld) {
            $toewijzing->update([
                'vrijgesteld' => true,
                'vrijstelling_grondslag' => $besluit->grondslag,
                'vrijstelling_besluit' => $besluit->besluit,
                'vrijstelling_besluit_datum' => $besluit->besluit_datum,
                'vrijstelling_toelichting' => $besluit->toelichting,
                'vrijstelling_ec' => (int) $toewijzing->vak->ec,
                'vrijgesteld_door_id' => auth()->id(),
                'vrijgesteld_op' => now(),
            ]);
        }

        $besluit->update([
            'status' => VrijstellingsbesluitStatus::Verwerkt,
            'verwerkt_door_id' => auth()->id(),
            'verwerkt_op' => now(),
            'vaktoewijzing_id' => $toewijzing->id,
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $besluit->student, veld: 'vrijstelling', context: [
            'vak' => $besluit->vak?->code, 'bron' => 'examencommissie-besluit', 'besluit' => $besluit->besluit,
        ]);

        return back()->with('status', 'Vrijstelling voor '.$besluit->vak?->code.' vastgelegd bij '.$besluit->student->volledigeNaam().'.');
    }

    /** Examencommissie/Directie: een openstaand besluit annuleren. */
    public function annuleer(Vrijstellingsbesluit $besluit): RedirectResponse
    {
        abort_unless($besluit->status === VrijstellingsbesluitStatus::Open, 403, 'Alleen openstaande besluiten kunnen worden geannuleerd.');

        $besluit->update(['status' => VrijstellingsbesluitStatus::Geannuleerd]);
        AuditLogger::log(AuditLogger::WIJZIGING, $besluit->student, veld: 'vrijstellingsbesluit', context: ['actie' => 'geannuleerd']);

        return back()->with('status', 'Vrijstellingsbesluit geannuleerd.');
    }
}
