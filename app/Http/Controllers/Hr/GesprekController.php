<?php

namespace App\Http\Controllers\Hr;

use App\Enums\Gespreksstatus;
use App\Enums\Gesprekstype;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Competentiescore;
use App\Models\Gesprek;
use App\Models\Gespreksdoel;
use App\Models\Medewerker;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * HR-gesprekken (beoordeling/functionering/exit) met doelen en competentiescores.
 * De HR-medewerker (tevens leidinggevende) ziet alle medewerkers. Mutaties gelogd
 * (personeelsdossier is gevoelig).
 */
class GesprekController extends Controller
{
    /** Module-overzicht van gesprekken (gescoped). */
    public function index(Request $request): View
    {
        $gesprekken = Gesprek::query()
            ->whereHas('medewerker', fn ($q) => $q->zichtbaarVoor($request->user()))
            ->with(['medewerker', 'gespreksvoerder'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRaw("status='gepland' desc")->orderByDesc('datum')
            ->paginate(25)->withQueryString();

        return view('hr.gesprekken-index', [
            'gesprekken' => $gesprekken,
            'statusFilter' => (string) $request->query('status', ''),
            'statussen' => Gespreksstatus::cases(),
        ]);
    }

    public function create(Request $request, Medewerker $medewerker): View
    {
        abort_unless($medewerker->zichtbaarVoor($request->user()), 403, 'Deze medewerker valt buiten uw team.');

        return view('hr.gesprek-form', [
            'medewerker' => $medewerker,
            'gesprek' => new Gesprek(['status' => Gespreksstatus::Gepland->value, 'datum' => now()->toDateString(), 'gespreksvoerder_id' => $request->user()->id]),
            'types' => Gesprekstype::opties(),
            'statussen' => Gespreksstatus::opties(),
            'gespreksvoerders' => $this->gespreksvoerders(),
        ]);
    }

    public function store(Request $request, Medewerker $medewerker): RedirectResponse
    {
        abort_unless($medewerker->zichtbaarVoor($request->user()), 403, 'Deze medewerker valt buiten uw team.');

        $gesprek = $medewerker->gesprekken()->create($this->valideer($request));
        AuditLogger::log(AuditLogger::AANMAAK, $medewerker, veld: 'gesprek', context: ['type' => $gesprek->type?->value]);

        return redirect()->route('gesprekken.show', $gesprek)->with('status', 'Gesprek gepland.');
    }

    /** Detail + bewerken (incl. doelen en competenties). */
    public function show(Request $request, Gesprek $gesprek): View
    {
        abort_unless($gesprek->beheerbaarVoor($request->user()), 403, 'Dit gesprek valt buiten uw team.');

        $gesprek->load(['medewerker', 'gespreksvoerder', 'doelen', 'competentiescores']);

        return view('hr.gesprek-show', [
            'gesprek' => $gesprek,
            'types' => Gesprekstype::opties(),
            'statussen' => Gespreksstatus::opties(),
            'gespreksvoerders' => $this->gespreksvoerders(),
            'doelStatussen' => Gespreksdoel::STATUSSEN,
            'scores' => Competentiescore::SCORES,
        ]);
    }

    public function update(Request $request, Gesprek $gesprek): RedirectResponse
    {
        abort_unless($gesprek->beheerbaarVoor($request->user()), 403, 'Dit gesprek valt buiten uw team.');

        $gesprek->update($this->valideer($request));
        AuditLogger::log(AuditLogger::WIJZIGING, $gesprek->medewerker, veld: 'gesprek', context: ['gesprek' => $gesprek->id]);

        return redirect()->route('gesprekken.show', $gesprek)->with('status', 'Gesprek bijgewerkt.');
    }

    public function destroy(Request $request, Gesprek $gesprek): RedirectResponse
    {
        abort_unless($gesprek->beheerbaarVoor($request->user()), 403, 'Dit gesprek valt buiten uw team.');

        $medewerker = $gesprek->medewerker;
        $gesprek->delete();
        AuditLogger::log(AuditLogger::VERWIJDERING, $medewerker, veld: 'gesprek');

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Gesprek verwijderd.');
    }

    public function doelStore(Request $request, Gesprek $gesprek): RedirectResponse
    {
        abort_unless($gesprek->beheerbaarVoor($request->user()), 403, 'Dit gesprek valt buiten uw team.');

        $gesprek->doelen()->create($request->validate([
            'omschrijving' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(Gespreksdoel::STATUSSEN))],
        ]));

        return back()->with('status', 'Doel toegevoegd.');
    }

    public function doelDestroy(Request $request, Gespreksdoel $doel): RedirectResponse
    {
        abort_unless($doel->gesprek->beheerbaarVoor($request->user()), 403, 'Dit gesprek valt buiten uw team.');

        $doel->delete();

        return back()->with('status', 'Doel verwijderd.');
    }

    public function competentieStore(Request $request, Gesprek $gesprek): RedirectResponse
    {
        abort_unless($gesprek->beheerbaarVoor($request->user()), 403, 'Dit gesprek valt buiten uw team.');

        $gesprek->competentiescores()->create($request->validate([
            'competentie' => ['required', 'string', 'max:255'],
            'score' => ['required', Rule::in(array_keys(Competentiescore::SCORES))],
            'toelichting' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('status', 'Competentie beoordeeld.');
    }

    public function competentieDestroy(Request $request, Competentiescore $score): RedirectResponse
    {
        abort_unless($score->gesprek->beheerbaarVoor($request->user()), 403, 'Dit gesprek valt buiten uw team.');

        $score->delete();

        return back()->with('status', 'Competentie verwijderd.');
    }

    /** @return \Illuminate\Support\Collection<int,User> */
    private function gespreksvoerders()
    {
        return User::whereIn('rol', [Rol::Hrmedewerker, Rol::Beheerder])->orderBy('naam')->get();
    }

    private function valideer(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(Gesprekstype::waarden())],
            'datum' => ['required', 'date'],
            'gespreksvoerder_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in(Gespreksstatus::waarden())],
            'samenvatting' => ['nullable', 'string', 'max:5000'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
