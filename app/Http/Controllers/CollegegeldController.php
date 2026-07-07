<?php

namespace App\Http\Controllers;

use App\Models\CollegegeldTarief;
use App\Models\Opleiding;
use App\Models\Periode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Collegegeldmodule (Studentenadministratie). Hier wordt het jaarlijks
 * wijzigende collegegeld per studiejaar ingesteld en bijgewerkt. Toekomstbestendig:
 * een standaardtarief per jaar plus optioneel een afwijkend tarief per opleiding.
 */
class CollegegeldController extends Controller
{
    public function index(): View
    {
        $tarieven = CollegegeldTarief::with(['periode', 'opleiding', 'ingesteldDoor'])
            ->get()
            ->sortByDesc(fn ($t) => $t->periode?->code)
            ->values();

        $perioden = Periode::orderByDesc('code')->get();
        $opleidingen = Opleiding::orderBy('naam')->get();
        $actievePeriode = $perioden->firstWhere('actief', true);

        return view('collegegeld.index', compact('tarieven', 'perioden', 'opleidingen', 'actievePeriode'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periode_id' => ['required', Rule::exists('perioden', 'id')],
            'opleiding_id' => ['nullable', Rule::exists('opleidingen', 'id')],
            'bedrag' => ['required', 'numeric', 'min:0', 'max:100000'],
            'aantal_termijnen' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        CollegegeldTarief::updateOrCreate(
            ['periode_id' => $data['periode_id'], 'opleiding_id' => $data['opleiding_id'] ?: null],
            [
                'bedrag' => $data['bedrag'],
                'aantal_termijnen' => $data['aantal_termijnen'],
                'ingesteld_door_id' => auth()->id(),
            ]
        );

        return redirect()->route('collegegeld')->with('status', 'Collegegeldtarief opgeslagen.');
    }

    public function destroy(CollegegeldTarief $tarief): RedirectResponse
    {
        $tarief->delete();

        return redirect()->route('collegegeld')->with('status', 'Tarief verwijderd.');
    }
}
