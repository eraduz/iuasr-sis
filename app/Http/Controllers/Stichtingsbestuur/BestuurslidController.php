<?php

namespace App\Http\Controllers\Stichtingsbestuur;

use App\Enums\Bestuursorgaan;
use App\Enums\Bestuurstitel;
use App\Http\Controllers\Controller;
use App\Models\Bestuurslid;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van de bestuursleden (Stichtingsbestuur) en commissarissen (Raad van
 * Toezicht). Muteren mag alleen het Stichtingsbestuur en de Beheerder; alle
 * mutaties worden gelogd (persoonsgegevens).
 */
class BestuurslidController extends Controller
{
    public function index(Request $request): View
    {
        $leden = Bestuurslid::query()
            ->when($request->filled('orgaan'), fn ($q) => $q->voorOrgaan((string) $request->query('orgaan')))
            ->when($request->query('actief', '1') !== 'alle', fn ($q) => $q->where('actief', true))
            ->geordend()
            ->get();

        return view('stichtingsbestuur.leden.index', [
            'leden' => $leden,
            'orgaanFilter' => $request->query('orgaan'),
            'toonInactief' => $request->query('actief') === 'alle',
        ]);
    }

    public function create(): View
    {
        return view('stichtingsbestuur.leden.form', ['lid' => new Bestuurslid(['actief' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);
        $lid = Bestuurslid::create($data);

        AuditLogger::log(AuditLogger::AANMAAK, $lid, veld: 'bestuurslid', context: ['orgaan' => $lid->orgaan->value]);

        return redirect()->route('stichtingsbestuur.leden')->with('status', $lid->volledigeNaam().' toegevoegd.');
    }

    public function edit(Bestuurslid $lid): View
    {
        return view('stichtingsbestuur.leden.form', ['lid' => $lid]);
    }

    public function update(Request $request, Bestuurslid $lid): RedirectResponse
    {
        $lid->update($this->valideer($request));
        AuditLogger::log(AuditLogger::WIJZIGING, $lid, veld: 'bestuurslid');

        return redirect()->route('stichtingsbestuur.leden')->with('status', $lid->volledigeNaam().' bijgewerkt.');
    }

    public function destroy(Bestuurslid $lid): RedirectResponse
    {
        $naam = $lid->volledigeNaam();
        $lid->delete();
        AuditLogger::log(AuditLogger::VERWIJDERING, 'Bestuurslid', $lid->id, veld: 'bestuurslid', context: ['naam' => $naam]);

        return redirect()->route('stichtingsbestuur.leden')->with('status', $naam.' verwijderd.');
    }

    /** @return array<string, mixed> */
    private function valideer(Request $request): array
    {
        $data = $request->validate([
            'orgaan' => ['required', Rule::in(Bestuursorgaan::waarden())],
            'titel' => ['required', Rule::in(Bestuurstitel::waarden())],
            'voornaam' => ['required', 'string', 'max:255'],
            'achternaam' => ['required', 'string', 'max:255'],
            'geboortedatum' => ['nullable', 'date'],
            'adres' => ['nullable', 'string', 'max:255'],
            'telefoon' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'datum_in_functie' => ['nullable', 'date'],
            'datum_uit_functie' => ['nullable', 'date', 'after_or_equal:datum_in_functie'],
            'bevoegdheid' => ['nullable', 'string', 'max:255'],
            'actief' => ['nullable', 'boolean'],
        ]);

        // De Raad van Toezicht (commissarissen) heeft geen bevoegdheid-veld.
        if ($data['orgaan'] === Bestuursorgaan::RaadVanToezicht->value) {
            $data['bevoegdheid'] = null;
        }
        $data['actief'] = $request->boolean('actief');

        return $data;
    }
}
