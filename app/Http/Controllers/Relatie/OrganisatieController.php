<?php

namespace App\Http\Controllers\Relatie;

use App\Http\Controllers\Controller;
use App\Models\Opleiding;
use App\Models\Organisatie;
use App\Models\OrganisatieType;
use App\Support\AuditLogger;
use App\Support\RelatienummerGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van de externe organisaties/relaties (module Relatiebeheer &
 * Stagebeheer). Opleidingoverstijgend en opleidinggebonden gescoped: een
 * relatiebeheerder/stagecoördinator/directielid ziet en beheert uitsluitend de
 * organisaties van de eigen opleiding(en); Bestuur en Beheer zien alles.
 *
 * Inzage staat open voor alle rollen met moduletoegang; aanmaken/wijzigen is
 * voorbehouden aan de relatiebeheerder, de stagecoördinator en de Beheerder
 * (server-side via de route-middleware én de guards hieronder).
 */
class OrganisatieController extends Controller
{
    public function index(Request $request): View
    {
        $gebruiker = $request->user();

        $organisaties = Organisatie::query()
            ->zichtbaarVoor($gebruiker)
            ->with(['type', 'opleidingen'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $zoek = trim((string) $request->query('q'));
                $q->where(function ($sub) use ($zoek) {
                    $sub->where('naam', 'like', "%{$zoek}%")
                        ->orWhere('plaats', 'like', "%{$zoek}%")
                        ->orWhere('relatienummer', 'like', "%{$zoek}%");
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->where('organisatie_type_id', (int) $request->query('type')))
            ->when($request->filled('opleiding'), fn ($q) => $q->whereHas('opleidingen', fn ($o) => $o->where('opleidingen.id', (int) $request->query('opleiding'))))
            ->when($request->query('status', 'actief') === 'actief', fn ($q) => $q->where('actief', true))
            ->when($request->query('status') === 'inactief', fn ($q) => $q->where('actief', false))
            ->orderBy('naam')
            ->paginate(25)
            ->withQueryString();

        return view('relaties.index', [
            'organisaties' => $organisaties,
            'zoek' => (string) $request->query('q', ''),
            'status' => (string) $request->query('status', 'actief'),
            'typeFilter' => (int) $request->query('type', 0),
            'opleidingFilter' => (int) $request->query('opleiding', 0),
            'types' => $this->beschikbareTypes($request),
            'opleidingen' => $this->beschikbareOpleidingen($request),
        ]);
    }

    public function create(Request $request): View
    {
        return view('relaties.form', [
            'organisatie' => new Organisatie(['actief' => true]),
            'gekozenOpleidingen' => [],
            'types' => $this->beschikbareTypes($request),
            'opleidingen' => $this->beschikbareOpleidingen($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);
        $opleidingIds = $this->valideerOpleidingen($request);

        $organisatie = null;
        for ($poging = 0; $poging < 5 && $organisatie === null; $poging++) {
            try {
                $organisatie = Organisatie::create($data + ['relatienummer' => RelatienummerGenerator::genereer()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $organisatie = null;
            }
        }
        abort_if($organisatie === null, 500, 'Kon geen uniek relatienummer bepalen.');

        $organisatie->opleidingen()->sync($opleidingIds);
        AuditLogger::log(AuditLogger::AANMAAK, $organisatie, veld: 'organisatie', context: ['relatienummer' => $organisatie->relatienummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Organisatie toegevoegd.');
    }

    public function show(Request $request, Organisatie $organisatie): View
    {
        abort_unless($organisatie->zichtbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw toegang.');

        $organisatie->load([
            'type',
            'opleidingen',
            'contactpersonen' => fn ($q) => $q->orderByDesc('actief')->orderBy('achternaam'),
            'contactmomenten' => fn ($q) => $q->with(['type', 'medewerker', 'contactpersoon'])->orderByDesc('datum')->orderByDesc('id'),
            'notities' => fn ($q) => $q->with('auteur')->orderByDesc('created_at'),
            'stageplaatsen' => fn ($q) => $q->with(['opleiding', 'periode', 'stages'])->orderByDesc('actief')->orderBy('id'),
            'stages' => fn ($q) => $q->with(['student', 'opleiding', 'stagebegeleider', 'werkplekbegeleider'])->orderByDesc('id'),
            'relatietaken' => fn ($q) => $q->with(['toegewezenAan'])->orderByRaw("status='afgerond'")->orderByRaw('vervaldatum is null, vervaldatum asc'),
            'afspraken' => fn ($q) => $q->with(['medewerker', 'stage.student'])->orderByDesc('datum')->orderByDesc('id'),
        ]);

        return view('relaties.show', [
            'organisatie' => $organisatie,
            'tijdlijn' => \App\Support\Relatietijdlijn::voor($organisatie),
            'taakMedewerkers' => \App\Models\User::whereIn('rol', [
                \App\Enums\Rol::Relatiebeheerder, \App\Enums\Rol::Stagecoordinator, \App\Enums\Rol::Beheerder,
            ])->orderBy('naam')->get(),
        ]);
    }

    public function edit(Request $request, Organisatie $organisatie): View
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.form', [
            'organisatie' => $organisatie,
            'gekozenOpleidingen' => $organisatie->opleidingen->pluck('id')->all(),
            'types' => $this->beschikbareTypes($request),
            'opleidingen' => $this->beschikbareOpleidingen($request),
        ]);
    }

    public function update(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $organisatie->update($this->valideer($request));
        $organisatie->opleidingen()->sync($this->valideerOpleidingen($request));
        AuditLogger::log(AuditLogger::WIJZIGING, $organisatie, veld: 'organisatie', context: ['relatienummer' => $organisatie->relatienummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Organisatie bijgewerkt.');
    }

    /** Zet een organisatie op actief/inactief (niet verwijderen — historie blijft). */
    public function status(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $organisatie->update(['actief' => ! $organisatie->actief]);
        AuditLogger::log(AuditLogger::WIJZIGING, $organisatie, veld: 'organisatie_status', context: ['actief' => $organisatie->actief]);

        return back()->with('status', $organisatie->actief ? 'Organisatie geactiveerd.' : 'Organisatie op inactief gezet.');
    }

    private function valideer(Request $request): array
    {
        $data = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'organisatie_type_id' => ['nullable', 'integer', 'exists:organisatie_types,id'],
            'kvk_nummer' => ['nullable', 'string', 'max:20'],
            'brin_nummer' => ['nullable', 'string', 'max:20'],
            'adres' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'plaats' => ['nullable', 'string', 'max:255'],
            'provincie' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'telefoon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'opmerkingen' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['actief'] = $request->boolean('actief');

        return $data;
    }

    /**
     * Gevalideerde opleidingkeuze. Een opleidinggebonden gebruiker mag alleen de
     * eigen opleiding(en) koppelen; het systeem dwingt dat server-side af.
     *
     * @return array<int,int>
     */
    private function valideerOpleidingen(Request $request): array
    {
        $toegestaan = $this->toegestaneOpleidingIds($request);

        $data = $request->validate([
            'opleidingen' => ['nullable', 'array'],
            'opleidingen.*' => ['integer', Rule::in($toegestaan)],
        ], [], ['opleidingen.*' => 'opleiding']);

        return array_map('intval', $data['opleidingen'] ?? []);
    }

    /** @return array<int,int> */
    private function toegestaneOpleidingIds(Request $request): array
    {
        return $this->beschikbareOpleidingen($request)->pluck('id')->all();
    }

    /** Opleidingen die deze gebruiker mag koppelen/filteren. */
    private function beschikbareOpleidingen(Request $request)
    {
        $gebruiker = $request->user();
        $query = Opleiding::query()->orderBy('naam');

        if ($gebruiker->isRelatieBeperkt()) {
            $query->whereIn('id', $gebruiker->opleidingIds());
        }

        return $query->get();
    }

    /** Organisatietypes die relevant zijn (alle opleidingen + de eigen opleiding[en]). */
    private function beschikbareTypes(Request $request)
    {
        $gebruiker = $request->user();
        $query = OrganisatieType::query()->actief()->orderBy('naam');

        if ($gebruiker->isRelatieBeperkt()) {
            $ids = $gebruiker->opleidingIds();
            $query->where(fn ($q) => $q->whereNull('opleiding_id')->orWhereIn('opleiding_id', $ids));
        }

        return $query->get();
    }
}
