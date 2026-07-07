<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Opleiding;
use App\Models\Periode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Rapporten — de web-opvolger van de oude Access-rapporten. In deze fase is de
 * klassenlijst functioneel (geen cijfers). Cijferrapporten/tentamenlijsten
 * volgen in Fase 5, na de cijferregistratie (Fase 4).
 */
class RapportController extends Controller
{
    public function index(): View
    {
        $opleidingen = Opleiding::orderBy('naam')->get();
        $perioden = Periode::orderByDesc('code')->get();
        $klassen = Klas::with('opleiding')->orderBy('code')->get();

        return view('rapporten.index', compact('opleidingen', 'perioden', 'klassen'));
    }

    /** Klassenlijst: alle studenten per opleiding/periode/klas — geen cijfers. */
    public function klassenlijst(Request $request): View
    {
        $data = $request->validate([
            'opleiding_id' => ['nullable', 'exists:opleidingen,id'],
            'periode_id' => ['nullable', 'exists:perioden,id'],
            'klas_id' => ['nullable', 'exists:klassen,id'],
            'alleen_actief' => ['sometimes', 'boolean'],
        ]);

        $q = Inschrijving::query()
            ->with(['student', 'opleiding', 'klas', 'periode'])
            ->when($data['opleiding_id'] ?? null, fn ($q, $v) => $q->where('opleiding_id', $v))
            ->when($data['periode_id'] ?? null, fn ($q, $v) => $q->where('periode_id', $v))
            ->when($data['klas_id'] ?? null, fn ($q, $v) => $q->where('klas_id', $v))
            ->when($request->boolean('alleen_actief'), fn ($q) => $q->where('status', 'actief'));

        $inschrijvingen = $q->get()->sortBy(fn ($i) => $i->student->studentnummer)->values();

        $opleiding = ($data['opleiding_id'] ?? null) ? Opleiding::find($data['opleiding_id']) : null;
        $periode = ($data['periode_id'] ?? null) ? Periode::find($data['periode_id']) : null;
        $klas = ($data['klas_id'] ?? null) ? Klas::find($data['klas_id']) : null;

        return view('rapporten.klassenlijst', compact('inschrijvingen', 'opleiding', 'periode', 'klas'));
    }
}
