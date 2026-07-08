<?php

namespace App\Http\Controllers;

use App\Models\Kennistoetsresultaat;
use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\Kennistoetsbewaking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Registratie van landelijke kennistoetsen (PABO) door Studentenzaken:
 * vastleggen of een student een toets heeft behaald (met datum) of dit weer
 * wissen. Bewaking (deadline) gebeurt via Kennistoetsbewaking.
 */
class KennistoetsController extends Controller
{
    public function bijwerken(Request $request, Student $student): RedirectResponse
    {
        abort_unless(auth()->user()->magInschrijvingBeheren(), 403);

        $data = $request->validate([
            'kennistoets_id' => ['required', 'integer', 'exists:kennistoetsen,id'],
            'behaald_op' => ['nullable', 'date'],
        ]);

        // De toets moet horen bij een verplichtende opleiding van deze student.
        abort_unless(Kennistoetsbewaking::toetsenVoor($student)->contains('id', $data['kennistoets_id']), 403);

        if (empty($data['behaald_op'])) {
            Kennistoetsresultaat::where('student_id', $student->id)
                ->where('kennistoets_id', $data['kennistoets_id'])->delete();
            $actie = 'gewist';
        } else {
            Kennistoetsresultaat::updateOrCreate(
                ['student_id' => $student->id, 'kennistoets_id' => $data['kennistoets_id']],
                ['behaald_op' => $data['behaald_op'], 'geregistreerd_door_id' => auth()->id()],
            );
            $actie = 'behaald';
        }

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'kennistoets', context: [
            'kennistoets_id' => $data['kennistoets_id'], 'actie' => $actie,
        ]);

        return back()->with('status', 'Kennistoets bijgewerkt.');
    }
}
