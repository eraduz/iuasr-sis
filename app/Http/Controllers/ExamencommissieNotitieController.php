<?php

namespace App\Http\Controllers;

use App\Models\ExamencommissieNotitie;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Notities van de examencommissie per student — hun eigen werkaantekeningen, los
 * van de interne notities van Studentenzaken en uitsluitend voor de examencommissie
 * (en de Beheerder als systeembeheer). Elke notitie krijgt datum + auteur.
 */
class ExamencommissieNotitieController extends Controller
{
    public function store(Request $request, Student $student): RedirectResponse
    {
        abort_unless($request->user()->magExamencommissieNotities(), 403);

        $data = $request->validate(['tekst' => ['required', 'string', 'max:2000']]);

        $student->examencommissieNotities()->create([
            'gebruiker_id' => $request->user()->id,
            'tekst' => $data['tekst'],
        ]);

        return redirect()->to(route('studenten.show', $student).'#ec-notities')
            ->with('status', 'Notitie toegevoegd.');
    }

    public function destroy(Request $request, Student $student, ExamencommissieNotitie $notitie): RedirectResponse
    {
        abort_unless($request->user()->magExamencommissieNotities(), 403);
        abort_unless($notitie->student_id === $student->id, 404);

        $notitie->delete();

        return redirect()->to(route('studenten.show', $student).'#ec-notities')
            ->with('status', 'Notitie verwijderd.');
    }
}
