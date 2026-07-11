<?php

namespace App\Http\Controllers\Hr;

use App\Enums\ChecklistSoort;
use App\Http\Controllers\Controller;
use App\Models\HrChecklisttaak;
use App\Models\Medewerker;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Onboarding-/offboarding-checklists per medewerker (module HR). HR ziet iedereen,
 * een Manager uitsluitend het eigen team (via de zichtbaarheid van de medewerker).
 */
class ChecklistController extends Controller
{
    /** Start een checklist door de sjabloontaken aan te maken (indien nog leeg). */
    public function start(Request $request, Medewerker $medewerker): RedirectResponse
    {
        $this->guard($request, $medewerker);

        $soort = ChecklistSoort::from($request->validate(['soort' => ['required', Rule::in(ChecklistSoort::waarden())]])['soort']);

        if ($medewerker->checklisttaken()->where('soort', $soort->value)->exists()) {
            return back()->with('status', $soort->label().' is al gestart.');
        }

        foreach ($soort->sjabloon() as $volgorde => $titel) {
            $medewerker->checklisttaken()->create([
                'soort' => $soort->value,
                'titel' => $titel,
                'volgorde' => $volgorde,
            ]);
        }
        AuditLogger::log(AuditLogger::AANMAAK, $medewerker, veld: 'checklist', context: ['soort' => $soort->value]);

        return redirect()->route('medewerkers.show', $medewerker)->with('status', $soort->label().' gestart.');
    }

    /** Een losse taak toevoegen aan een checklist. */
    public function store(Request $request, Medewerker $medewerker): RedirectResponse
    {
        $this->guard($request, $medewerker);

        $data = $request->validate([
            'soort' => ['required', Rule::in(ChecklistSoort::waarden())],
            'titel' => ['required', 'string', 'max:255'],
            'verantwoordelijke_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $medewerker->checklisttaken()->create($data + ['volgorde' => 99]);

        return back()->with('status', 'Taak toegevoegd.');
    }

    /** Afvinken of heropenen. */
    public function toggle(Request $request, HrChecklisttaak $taak): RedirectResponse
    {
        $this->guard($request, $taak->medewerker);

        $gereed = ! $taak->gereed;
        $taak->update([
            'gereed' => $gereed,
            'gereed_op' => $gereed ? now() : null,
            'gereed_door_id' => $gereed ? $request->user()->id : null,
        ]);

        return back()->with('status', $gereed ? 'Taak afgevinkt.' : 'Taak heropend.');
    }

    public function destroy(Request $request, HrChecklisttaak $taak): RedirectResponse
    {
        $medewerker = $taak->medewerker;
        $this->guard($request, $medewerker);

        $taak->delete();

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Taak verwijderd.');
    }

    private function guard(Request $request, Medewerker $medewerker): void
    {
        abort_unless($request->user()->magHrInzien() && $medewerker->zichtbaarVoor($request->user()), 403, 'Deze medewerker valt buiten uw team.');
    }
}
