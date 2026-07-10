<?php

namespace App\Http\Controllers\Cursus;

use App\Enums\Betaalmethode;
use App\Enums\Cursusbetaalstatus;
use App\Http\Controllers\Controller;
use App\Models\Cursus;
use App\Models\Cursusbetaling;
use App\Models\Cursusinschrijving;
use App\Support\AuditLogger;
use App\Support\Cursusgeldstatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Boekhouding van de module Cursussen: cursusgelden volgen, betalingen
 * registreren en corrigeren, openstaande bedragen en betalingshistorie inzien.
 * Toegang: Financiële Administratie (boekhouding) en Beheer.
 */
class CursusbetalingController extends Controller
{
    public function overzicht(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));
        $filterStatus = (string) $request->query('status', 'alle');
        $cursusId = $request->query('cursus');

        $inschrijvingen = Cursusinschrijving::query()
            ->with(['cursist', 'cursus', 'betalingen.geregistreerdDoor'])
            ->whereHas('cursist')
            ->when($cursusId, fn ($q) => $q->where('cursus_id', $cursusId))
            ->when($zoek !== '', fn ($q) => $q->whereHas('cursist',
                fn ($c) => $c->where('achternaam', 'like', '%'.$zoek.'%')
                    ->orWhere('voornaam', 'like', '%'.$zoek.'%')
                    ->orWhere('cursistnummer', 'like', $zoek.'%')))
            ->get()
            ->map(fn (Cursusinschrijving $i) => ['inschrijving' => $i, 'geld' => Cursusgeldstatus::voor($i)])
            ->when($filterStatus !== 'alle', fn ($c) => $c->filter(fn ($r) => $r['geld']['status'] === $filterStatus))
            ->sortBy(fn ($r) => $r['inschrijving']->cursist->achternaam)
            ->values();

        return view('cursussen.betalingen', [
            'rijen' => $inschrijvingen,
            'zoek' => $zoek,
            'filterStatus' => $filterStatus,
            'cursusId' => $cursusId,
            'cursussen' => Cursus::orderBy('naam')->get(),
            'methoden' => Betaalmethode::opties(),
            'statussen' => Cursusbetaalstatus::opties(),
            'totaalOpenstaand' => $inschrijvingen->sum(fn ($r) => $r['geld']['openstaand']),
            'aantalOpen' => $inschrijvingen->filter(fn ($r) => $r['geld']['status'] !== Cursusgeldstatus::VOLDAAN)->count(),
        ]);
    }

    public function registreer(Request $request, Cursusinschrijving $inschrijving): RedirectResponse
    {
        $data = $this->valideer($request);

        $inschrijving->betalingen()->create($data + ['geregistreerd_door_id' => $request->user()->id]);

        AuditLogger::log(AuditLogger::AANMAAK, $inschrijving->cursist, veld: 'cursusbetaling', context: [
            'cursusinschrijving_id' => $inschrijving->id,
            'bedrag' => (float) $data['bedrag'],
            'methode' => $data['betaalmethode'],
            'status' => $data['betalingsstatus'],
        ]);

        return back()->with('status', 'Betaling geregistreerd.');
    }

    public function bijwerken(Request $request, Cursusbetaling $betaling): RedirectResponse
    {
        $data = $this->valideer($request);
        $oud = $betaling->only(['bedrag', 'betaalmethode', 'betaaldatum', 'betalingsstatus', 'referentienummer']);

        $betaling->update($data);

        AuditLogger::log(AuditLogger::WIJZIGING, $betaling->inschrijving?->cursist, veld: 'cursusbetaling', context: [
            'betaling_id' => $betaling->id, 'oud' => $oud,
            'nieuw' => $betaling->only(['bedrag', 'betaalmethode', 'betaaldatum', 'betalingsstatus', 'referentienummer']),
        ]);

        return back()->with('status', 'Betaling gewijzigd en gelogd.');
    }

    public function verwijderen(Cursusbetaling $betaling): RedirectResponse
    {
        $cursist = $betaling->inschrijving?->cursist;
        AuditLogger::log(AuditLogger::VERWIJDERING, $cursist, veld: 'cursusbetaling', context: [
            'betaling_id' => $betaling->id, 'bedrag' => (float) $betaling->bedrag,
        ]);

        $betaling->delete();

        return back()->with('status', 'Betaling verwijderd en gelogd.');
    }

    private function valideer(Request $request): array
    {
        return $request->validate([
            'betaalmethode' => ['required', new Enum(Betaalmethode::class)],
            'bedrag' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'betaaldatum' => ['required', 'date'],
            'betalingsstatus' => ['required', new Enum(Cursusbetaalstatus::class)],
            'referentienummer' => ['nullable', 'string', 'max:100'],
            'opmerking' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
