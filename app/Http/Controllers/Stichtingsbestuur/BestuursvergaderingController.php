<?php

namespace App\Http\Controllers\Stichtingsbestuur;

use App\Enums\Aanwezigheid;
use App\Enums\Bestuursorgaan;
use App\Http\Controllers\Controller;
use App\Models\Bestuurslid;
use App\Models\Bestuursvergadering;
use App\Models\BestuursvergaderingAanwezigheid;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van de bestuursvergaderingen: datum, orgaan (soort), onderwerpen,
 * besluiten en de aanwezigheid per lid (fysiek/online/niet bijgewoond). Muteren mag
 * alleen het Stichtingsbestuur en de Beheerder; mutaties worden gelogd.
 */
class BestuursvergaderingController extends Controller
{
    public function index(Request $request): View
    {
        $vergaderingen = Bestuursvergadering::query()
            ->with(['aanwezigheden', 'genotuleerdDoor'])
            ->when($request->filled('orgaan'), fn ($q) => $q->where('orgaan', (string) $request->query('orgaan')))
            ->chronologisch()
            ->paginate(20)
            ->withQueryString();

        return view('stichtingsbestuur.vergaderingen.index', [
            'vergaderingen' => $vergaderingen,
            'orgaanFilter' => $request->query('orgaan'),
        ]);
    }

    public function create(): View
    {
        return view('stichtingsbestuur.vergaderingen.form', [
            'vergadering' => new Bestuursvergadering(['datum' => now()->toDateString(), 'orgaan' => Bestuursorgaan::Stichtingsbestuur]),
            'leden' => Bestuurslid::actief()->geordend()->get(),
            'huidig' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);
        $data['genotuleerd_door_id'] = $request->user()->id;
        $vergadering = Bestuursvergadering::create($data);

        $this->syncAanwezigheid($request, $vergadering);

        AuditLogger::log(AuditLogger::AANMAAK, $vergadering, veld: 'bestuursvergadering', context: ['orgaan' => $vergadering->orgaan->value]);

        return redirect()->route('stichtingsbestuur.vergaderingen.show', $vergadering)
            ->with('status', 'Vergadering vastgelegd.');
    }

    public function show(Bestuursvergadering $vergadering): View
    {
        $vergadering->load(['aanwezigheden.bestuurslid', 'genotuleerdDoor']);

        return view('stichtingsbestuur.vergaderingen.show', ['vergadering' => $vergadering]);
    }

    public function edit(Bestuursvergadering $vergadering): View
    {
        $huidig = $vergadering->aanwezigheden()->pluck('aanwezigheid', 'bestuurslid_id')->all();

        return view('stichtingsbestuur.vergaderingen.form', [
            'vergadering' => $vergadering,
            'leden' => Bestuurslid::actief()->geordend()->get(),
            'huidig' => $huidig,
        ]);
    }

    public function update(Request $request, Bestuursvergadering $vergadering): RedirectResponse
    {
        $vergadering->update($this->valideer($request));
        $this->syncAanwezigheid($request, $vergadering);

        AuditLogger::log(AuditLogger::WIJZIGING, $vergadering, veld: 'bestuursvergadering');

        return redirect()->route('stichtingsbestuur.vergaderingen.show', $vergadering)
            ->with('status', 'Vergadering bijgewerkt.');
    }

    public function destroy(Bestuursvergadering $vergadering): RedirectResponse
    {
        $vergadering->delete();
        AuditLogger::log(AuditLogger::VERWIJDERING, 'Bestuursvergadering', $vergadering->id, veld: 'bestuursvergadering');

        return redirect()->route('stichtingsbestuur.vergaderingen')->with('status', 'Vergadering verwijderd.');
    }

    /** @return array<string, mixed> */
    private function valideer(Request $request): array
    {
        return $request->validate([
            'datum' => ['required', 'date'],
            'orgaan' => ['required', Rule::in(Bestuursorgaan::waarden())],
            'locatie' => ['nullable', 'string', 'max:255'],
            'onderwerpen' => ['nullable', 'string', 'max:10000'],
            'besluiten' => ['nullable', 'string', 'max:10000'],
            'opmerking' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * De aanwezigheid per lid opslaan. Een lege waarde = niet geregistreerd (de rij
     * wordt verwijderd). Alleen bestaande, actieve leden worden verwerkt.
     */
    private function syncAanwezigheid(Request $request, Bestuursvergadering $vergadering): void
    {
        $request->validate([
            'aanwezigheid' => ['array'],
            'aanwezigheid.*' => ['nullable', Rule::in(Aanwezigheid::waarden())],
        ]);

        $input = (array) $request->input('aanwezigheid', []);
        $geldigeIds = Bestuurslid::whereIn('id', array_keys($input))->pluck('id')->all();

        foreach ($geldigeIds as $lidId) {
            $waarde = $input[$lidId] ?? null;

            if ($waarde === null || $waarde === '') {
                BestuursvergaderingAanwezigheid::where('bestuursvergadering_id', $vergadering->id)
                    ->where('bestuurslid_id', $lidId)->delete();

                continue;
            }

            BestuursvergaderingAanwezigheid::updateOrCreate(
                ['bestuursvergadering_id' => $vergadering->id, 'bestuurslid_id' => $lidId],
                ['aanwezigheid' => $waarde],
            );
        }
    }
}
