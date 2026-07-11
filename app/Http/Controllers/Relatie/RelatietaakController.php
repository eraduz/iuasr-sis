<?php

namespace App\Http\Controllers\Relatie;

use App\Enums\Rol;
use App\Enums\TaakPrioriteit;
use App\Enums\TaakStatus;
use App\Http\Controllers\Controller;
use App\Models\Organisatie;
use App\Models\Relatietaak;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Taken bij een organisatie (module Relatiebeheer & Stagebeheer). Werkverdeling,
 * geen gevoelig persoonsgegeven — daarom geen audit-logging (net als de takenlijst
 * van Studentenzaken). Autorisatie volgt de organisatie (beheerbaarVoor).
 */
class RelatietaakController extends Controller
{
    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $organisatie->relatietaken()->create(
            $this->valideer($request, $organisatie) + ['aangemaakt_door_id' => $request->user()->id]
        );

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Taak toegevoegd.');
    }

    public function edit(Request $request, Relatietaak $taak): View
    {
        abort_unless($taak->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.relatietaak-form', $this->formData($taak->organisatie, $taak));
    }

    public function update(Request $request, Relatietaak $taak): RedirectResponse
    {
        abort_unless($taak->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $data = $this->valideer($request, $taak->organisatie, metStatus: true);
        $this->syncAfronding($taak, $data['status'] ?? $taak->status->value, $request->user());
        $taak->update($data);

        return redirect()->route('relaties.show', $taak->organisatie)->with('status', 'Taak bijgewerkt.');
    }

    /** Afvinken of heropenen met één klik. */
    public function afronden(Request $request, Relatietaak $taak): RedirectResponse
    {
        abort_unless($taak->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $nieuw = $taak->status === TaakStatus::Afgerond ? TaakStatus::Open : TaakStatus::Afgerond;
        $this->syncAfronding($taak, $nieuw->value, $request->user());
        $taak->update(['status' => $nieuw->value]);

        return back()->with('status', $nieuw === TaakStatus::Afgerond ? 'Taak afgerond.' : 'Taak heropend.');
    }

    public function destroy(Request $request, Relatietaak $taak): RedirectResponse
    {
        $organisatie = $taak->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $taak->delete();

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Taak verwijderd.');
    }

    /** Zet of wis de afrondingsvelden op basis van de nieuwe status. */
    private function syncAfronding(Relatietaak $taak, string $nieuweStatus, User $gebruiker): void
    {
        if ($nieuweStatus === TaakStatus::Afgerond->value && $taak->status !== TaakStatus::Afgerond) {
            $taak->afgerond_op = now();
            $taak->afgerond_door_id = $gebruiker->id;
        } elseif ($nieuweStatus !== TaakStatus::Afgerond->value && $taak->status === TaakStatus::Afgerond) {
            $taak->afgerond_op = null;
            $taak->afgerond_door_id = null;
        }
    }

    private function formData(Organisatie $organisatie, Relatietaak $taak): array
    {
        return [
            'organisatie' => $organisatie,
            'taak' => $taak,
            'medewerkers' => $this->medewerkers(),
            'stages' => $organisatie->stages()->with('student')->orderByDesc('id')->get(),
            'prioriteiten' => TaakPrioriteit::opties(),
            'statussen' => TaakStatus::opties(),
        ];
    }

    /** @return \Illuminate\Support\Collection<int,User> */
    private function medewerkers()
    {
        return User::whereIn('rol', [Rol::Relatiebeheerder, Rol::Stagecoordinator, Rol::Beheerder])
            ->orderBy('naam')->get();
    }

    private function valideer(Request $request, Organisatie $organisatie, bool $metStatus = false): array
    {
        $medewerkerIds = $this->medewerkers()->pluck('id')->all();
        $stageIds = $organisatie->stages()->pluck('id')->all();

        $regels = [
            'titel' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string', 'max:2000'],
            'toegewezen_aan_id' => ['nullable', 'integer', Rule::in($medewerkerIds)],
            'stage_id' => ['nullable', 'integer', Rule::in($stageIds)],
            'prioriteit' => ['required', Rule::in(array_keys(TaakPrioriteit::opties()))],
            'startdatum' => ['nullable', 'date'],
            'vervaldatum' => ['nullable', 'date'],
        ];
        if ($metStatus) {
            $regels['status'] = ['required', Rule::in(array_keys(TaakStatus::opties()))];
        }

        return $request->validate($regels, [], ['toegewezen_aan_id' => 'toegewezene', 'stage_id' => 'stage']);
    }
}
