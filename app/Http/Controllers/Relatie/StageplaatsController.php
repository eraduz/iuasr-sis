<?php

namespace App\Http\Controllers\Relatie;

use App\Http\Controllers\Controller;
use App\Models\Organisatie;
use App\Models\Periode;
use App\Models\Stageplaats;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van de stageplaatsen (aanbod/capaciteit) bij een organisatie. Alleen de
 * stagecoördinator (eigen opleiding) en de Beheerder muteren; de opleiding moet
 * er een van de organisatie zijn (server-side afgedwongen).
 */
class StageplaatsController extends Controller
{
    public function create(Request $request, Organisatie $organisatie): View
    {
        abort_unless($organisatie->stagesBeheerbaarVoor($request->user()), 403, 'U mag de stageplaatsen van deze organisatie niet beheren.');

        return view('relaties.stageplaats-form', $this->formData($request, $organisatie, new Stageplaats(['actief' => true, 'aantal_plaatsen' => 1])));
    }

    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->stagesBeheerbaarVoor($request->user()), 403, 'U mag de stageplaatsen van deze organisatie niet beheren.');

        $stageplaats = $organisatie->stageplaatsen()->create($this->valideer($request, $organisatie));
        AuditLogger::log(AuditLogger::AANMAAK, $stageplaats, veld: 'stageplaats', context: ['organisatie' => $organisatie->relatienummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Stageplaats toegevoegd.');
    }

    public function edit(Request $request, Stageplaats $stageplaats): View
    {
        $organisatie = $stageplaats->organisatie;
        abort_unless($organisatie->stagesBeheerbaarVoor($request->user()), 403, 'U mag de stageplaatsen van deze organisatie niet beheren.');

        return view('relaties.stageplaats-form', $this->formData($request, $organisatie, $stageplaats));
    }

    public function update(Request $request, Stageplaats $stageplaats): RedirectResponse
    {
        $organisatie = $stageplaats->organisatie;
        abort_unless($organisatie->stagesBeheerbaarVoor($request->user()), 403, 'U mag de stageplaatsen van deze organisatie niet beheren.');

        $stageplaats->update($this->valideer($request, $organisatie));
        AuditLogger::log(AuditLogger::WIJZIGING, $stageplaats, veld: 'stageplaats', context: ['organisatie' => $organisatie->relatienummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Stageplaats bijgewerkt.');
    }

    public function status(Request $request, Stageplaats $stageplaats): RedirectResponse
    {
        $organisatie = $stageplaats->organisatie;
        abort_unless($organisatie->stagesBeheerbaarVoor($request->user()), 403, 'U mag de stageplaatsen van deze organisatie niet beheren.');

        $stageplaats->update(['actief' => ! $stageplaats->actief]);

        return back()->with('status', $stageplaats->actief ? 'Stageplaats geactiveerd.' : 'Stageplaats op inactief gezet.');
    }

    private function formData(Request $request, Organisatie $organisatie, Stageplaats $stageplaats): array
    {
        return [
            'organisatie' => $organisatie,
            'stageplaats' => $stageplaats,
            'opleidingen' => $this->opleidingen($request, $organisatie),
            'perioden' => Periode::orderByDesc('startdatum')->get(),
        ];
    }

    /** De opleidingen van de organisatie die deze gebruiker mag beheren. */
    private function opleidingen(Request $request, Organisatie $organisatie)
    {
        $opleidingen = $organisatie->opleidingen()->orderBy('naam')->get();
        if ($request->user()->isRelatieBeperkt()) {
            $eigen = $request->user()->opleidingIds();
            $opleidingen = $opleidingen->whereIn('id', $eigen)->values();
        }

        return $opleidingen;
    }

    private function valideer(Request $request, Organisatie $organisatie): array
    {
        $opleidingIds = $this->opleidingen($request, $organisatie)->pluck('id')->all();

        $data = $request->validate([
            'opleiding_id' => ['required', 'integer', Rule::in($opleidingIds)],
            'periode_id' => ['nullable', 'integer', 'exists:perioden,id'],
            'leerjaar' => ['nullable', 'integer', 'min:1', 'max:10'],
            'aantal_plaatsen' => ['required', 'integer', 'min:0', 'max:999'],
            'max_studenten' => ['nullable', 'integer', 'min:0', 'max:999'],
            'eisen' => ['nullable', 'string', 'max:2000'],
            'specialisaties' => ['nullable', 'string', 'max:255'],
            'werkdagen' => ['nullable', 'string', 'max:255'],
        ], [], ['opleiding_id' => 'opleiding']);

        $data['actief'] = $request->boolean('actief');

        return $data;
    }
}
