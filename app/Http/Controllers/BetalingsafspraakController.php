<?php

namespace App\Http\Controllers;

use App\Models\Betalingsafspraak;
use App\Models\Student;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Betalingsafspraak: de student heeft nog niet betaald, maar heeft toegezegd dat
 * vóór een bepaalde datum te doen. Zolang de afspraak loopt vervallen de
 * blokkades op verklaringen en herinschrijven; de SCHULD blijft bestaan en
 * blijft zichtbaar op het dossier.
 *
 * Vastleggen en intrekken doet uitsluitend de Financiële Administratie (of
 * Beheer). Studentenzaken kan haar eigen blokkade dus niet opheffen om een
 * verklaring te kunnen afgeven.
 *
 * Beide handelingen worden gelogd: het gaat om een uitzondering op een
 * financiële maatregel.
 */
class BetalingsafspraakController extends Controller
{
    public function vastleggen(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'geldig_tot' => ['required', 'date', 'after:today'],
            'reden' => ['required', 'string', 'max:200'],
        ], [
            'geldig_tot.after' => 'De einddatum moet in de toekomst liggen.',
            'reden.required' => 'Geef aan wat er is afgesproken.',
        ]);

        // Een tweede lopende afspraak zou de vraag oproepen welke geldt; de
        // bestaande wordt daarom eerst ingetrokken.
        $lopend = Betalingsafspraak::lopendVoor($student);
        $lopend?->update([
            'ingetrokken_op' => now(),
            'ingetrokken_door_id' => $request->user()->id,
        ]);

        $afspraak = Betalingsafspraak::create([
            'student_id' => $student->id,
            'geldig_tot' => $data['geldig_tot'],
            'reden' => trim($data['reden']),
            'vastgelegd_door_id' => $request->user()->id,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $student, veld: 'betalingsafspraak', context: [
            'afspraak_id' => $afspraak->id,
            'geldig_tot' => $afspraak->geldig_tot->toDateString(),
            'reden' => $afspraak->reden,
            'vervangt_afspraak_id' => $lopend?->id,
            'achterstallig' => \App\Support\Collegegeldstatus::voor($student->fresh())['achterstallig'],
        ]);

        return back()->with('status',
            'Betalingsafspraak vastgelegd tot '.$afspraak->geldig_tot->format('d-m-Y').'. De blokkades zijn opgeheven.');
    }

    public function intrekken(Request $request, Student $student, Betalingsafspraak $afspraak): RedirectResponse
    {
        abort_unless($afspraak->student_id === $student->id, 404);

        if ($afspraak->isIngetrokken()) {
            return back()->with('status', 'Deze afspraak was al ingetrokken.');
        }

        $afspraak->update([
            'ingetrokken_op' => now(),
            'ingetrokken_door_id' => $request->user()->id,
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'betalingsafspraak', context: [
            'afspraak_id' => $afspraak->id,
            'actie' => 'ingetrokken',
            'geldig_tot' => $afspraak->geldig_tot->toDateString(),
        ]);

        return back()->with('status', 'Betalingsafspraak ingetrokken. Bij een openstaande achterstand gelden de blokkades weer.');
    }
}
