<?php

namespace App\Http\Controllers;

use App\Models\Docent;
use App\Models\Opleiding;
use App\Models\Vak;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheermodule vakstructuur (curriculum). De Studentenadministratie legt per
 * opleiding, per studiejaar (leerjaar) en per periode (blok) de vakken vast.
 * Deze structuur is de basis voor de automatische vaktoewijzing bij inschrijving
 * en blijft bewaard voor toekomstige studiejaren.
 */
class VakstructuurController extends Controller
{
    public function index(Request $request): View
    {
        $opleidingen = Opleiding::orderBy('naam')->get();
        $opleiding = $request->filled('opleiding')
            ? $opleidingen->firstWhere('id', (int) $request->query('opleiding'))
            : $opleidingen->first();

        $docenten = Docent::orderBy('achternaam')->get();

        $vakken = $opleiding
            ? Vak::where('opleiding_id', $opleiding->id)->with('docent')
                ->orderBy('leerjaar')->orderBy('blok')->orderBy('code')->get()
            : collect();

        // leerjaar => (blok => vakken)
        $structuur = $vakken->groupBy(fn ($v) => $v->leerjaar ?? 0)
            ->map(fn ($g) => $g->groupBy(fn ($v) => $v->blok ?? 0)->sortKeys())
            ->sortKeys();

        $maxLeerjaar = max($opleiding?->nominale_jaren ?? 1, ($structuur->keys()->max() ?: 1));

        return view('vakstructuur.index', compact('opleidingen', 'opleiding', 'docenten', 'structuur', 'maxLeerjaar'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);
        $vak = Vak::create($data + ['actief' => true]);

        // Standaard toetsopbouw zodat het vak direct beoordeelbaar is (later te verfijnen).
        $vak->toetsonderdelen()->create([
            'code' => 'TEN', 'naam' => 'Tentamen', 'type' => 'tentamen',
            'weging' => 1.00, 'telt_mee' => true, 'volgorde' => 1,
        ]);

        return redirect()->to(route('vakstructuur', ['opleiding' => $data['opleiding_id']]).'#lj'.$data['leerjaar'])
            ->with('status', 'Vak toegevoegd aan de vakstructuur.');
    }

    public function edit(Vak $vak): View
    {
        $opleidingen = Opleiding::orderBy('naam')->get();
        $docenten = Docent::orderBy('achternaam')->get();

        return view('vakstructuur.form', compact('vak', 'opleidingen', 'docenten'));
    }

    public function update(Request $request, Vak $vak): RedirectResponse
    {
        $vak->update($this->valideer($request, $vak->id));

        return redirect()->route('vakstructuur', ['opleiding' => $vak->opleiding_id])
            ->with('status', 'Vak bijgewerkt.');
    }

    public function destroy(Vak $vak): RedirectResponse
    {
        $opleidingId = $vak->opleiding_id;
        try {
            $vak->delete();
        } catch (QueryException) {
            return redirect()->route('vakstructuur', ['opleiding' => $opleidingId])
                ->with('status', 'Kan het vak niet verwijderen: het is al aan studenten toegewezen (historie blijft bewaard).');
        }

        return redirect()->route('vakstructuur', ['opleiding' => $opleidingId])
            ->with('status', 'Vak verwijderd.');
    }

    private function valideer(Request $request, ?int $negeerId = null): array
    {
        $data = $request->validate([
            'opleiding_id' => ['required', Rule::exists('opleidingen', 'id')],
            // De code is uniek BINNEN een opleiding: dezelfde code mag in twee
            // opleidingen voorkomen (bv. B-QR02 in de bachelor én de pre-master).
            'code' => ['required', 'string', 'max:40',
                Rule::unique('vakken', 'code')
                    ->where(fn ($q) => $q->where('opleiding_id', $request->input('opleiding_id')))
                    ->ignore($negeerId)],
            'naam' => ['required', 'string', 'max:255'],
            // Halve studiepunten komen voor (2,5 EC).
            'ec' => ['required', 'numeric', 'min:0', 'max:60'],
            'leerjaar' => ['required', 'integer', 'min:1', 'max:10'],
            // Leeg blok = het vak loopt het hele studiejaar (stage, scriptie).
            'blok' => ['nullable', 'integer', 'min:1', 'max:4'],
            'docent_id' => ['nullable', Rule::exists('docenten', 'id')],
        ]);

        // Keuzevakken worden niet automatisch aan een inschrijving toegewezen.
        $data['keuzevak'] = $request->boolean('keuzevak');

        return $data;
    }
}
