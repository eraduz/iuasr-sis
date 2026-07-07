<?php

namespace App\Http\Controllers;

use App\Models\Betaling;
use App\Models\Student;
use App\Support\Collegegeldstatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Financiële Administratie: betalingen registreren en betalingsachterstanden
 * inzien. Het systeem leidt de achterstand automatisch af uit collegegeld en
 * geregistreerde betalingen.
 */
class BetalingController extends Controller
{
    public function index(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));

        $alle = Student::with(['inschrijvingen.periode', 'betalingen'])->get()
            ->map(fn (Student $s) => ['student' => $s, 'status' => Collegegeldstatus::voor($s)]);

        // Studenten met een openstaande schuld.
        $achterstanden = $alle
            ->filter(fn ($r) => $r['status']['openstaand'] > 0)
            ->sortByDesc(fn ($r) => $r['status']['openstaand'])
            ->values();

        // Studenten die teveel hebben betaald (terugbetaling).
        $terugbetalingen = $alle
            ->filter(fn ($r) => $r['status']['terugbetaling'] > 0)
            ->sortByDesc(fn ($r) => $r['status']['terugbetaling'])
            ->values();

        $resultaten = $zoek !== ''
            ? Student::where('studentnummer', 'like', $zoek.'%')
                ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                ->orWhere('voornaam', 'like', '%'.$zoek.'%')
                ->orderBy('studentnummer')->limit(20)->get()
            : collect();

        return view('financien.index', compact('achterstanden', 'terugbetalingen', 'resultaten', 'zoek'));
    }

    public function student(Student $student): View
    {
        $student->load(['inschrijvingen.opleiding', 'inschrijvingen.periode', 'betalingen.geregistreerdDoor']);
        $status = Collegegeldstatus::voor($student);

        // Per inschrijving het (pro rata) verschuldigde bedrag tonen.
        $regels = $student->inschrijvingen->sortByDesc('inschrijfdatum')->map(fn ($i) => [
            'inschrijving' => $i,
            'tarief' => Collegegeldstatus::tarief($i),
            'maanden' => Collegegeldstatus::maanden($i),
            'verschuldigd' => Collegegeldstatus::verschuldigd($i),
        ]);

        return view('financien.student', compact('student', 'status', 'regels'));
    }

    public function registreer(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'inschrijving_id' => ['required', Rule::exists('inschrijvingen', 'id')->where('student_id', $student->id)],
            'bedrag' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'datum' => ['required', 'date'],
            'betaalwijze' => ['nullable', 'string', 'max:40'],
            'opmerking' => ['nullable', 'string', 'max:500'],
        ]);

        $student->betalingen()->create([
            'inschrijving_id' => $data['inschrijving_id'],
            'bedrag' => $data['bedrag'],
            'datum' => $data['datum'],
            'betaalwijze' => $data['betaalwijze'] ?? null,
            'opmerking' => $data['opmerking'] ?? null,
            'geregistreerd_door_id' => auth()->id(),
        ]);

        return redirect()->route('financien.student', $student)
            ->with('status', 'Betaling geregistreerd.');
    }
}
