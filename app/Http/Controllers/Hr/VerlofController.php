<?php

namespace App\Http\Controllers\Hr;

use App\Enums\Verlofstatus;
use App\Enums\Verloftype;
use App\Http\Controllers\Controller;
use App\Models\Verlofaanvraag;
use App\Support\AuditLogger;
use App\Support\Verlofoverzicht;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Verlofaanvragen (module HR). Self-service aanvraag door de medewerker →
 * goedkeuring door de leidinggevende (HR als terugval) → registratie. HR ziet
 * alle aanvragen, een Manager uitsluitend die van het eigen team.
 */
class VerlofController extends Controller
{
    /** HR/Manager-overzicht van de aanvragen (gescoped). */
    public function index(Request $request): View
    {
        $aanvragen = Verlofaanvraag::query()
            ->whereHas('medewerker', fn ($q) => $q->zichtbaarVoor($request->user()))
            ->with(['medewerker', 'beoordelaar'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByRaw("status='aangevraagd' desc")->orderByDesc('van')
            ->paginate(25)->withQueryString();

        return view('hr.verlof-index', [
            'aanvragen' => $aanvragen,
            'statusFilter' => (string) $request->query('status', ''),
            'statussen' => Verlofstatus::cases(),
        ]);
    }

    /** Self-service: eigen aanvragen + saldo. */
    public function mijn(Request $request): View
    {
        $medewerker = $this->eigenMedewerker($request);

        return view('hr.verlof-mijn', [
            'medewerker' => $medewerker,
            'aanvragen' => $medewerker->verlofaanvragen()->with('beoordelaar')->get(),
            'saldo' => Verlofoverzicht::voor($medewerker),
            'jaar' => (int) date('Y'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->eigenMedewerker($request);

        return view('hr.verlof-form', ['types' => Verloftype::opties()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $medewerker = $this->eigenMedewerker($request);

        $data = $request->validate([
            'verloftype' => ['required', Rule::in(Verloftype::waarden())],
            'van' => ['required', 'date'],
            'tot' => ['required', 'date', 'after_or_equal:van'],
            'uren' => ['required', 'numeric', 'min:0.5', 'max:2000'],
            'reden' => ['nullable', 'string', 'max:1000'],
        ]);

        $medewerker->verlofaanvragen()->create($data + [
            'status' => Verlofstatus::Aangevraagd->value,
            'aangevraagd_door_id' => $request->user()->id,
        ]);

        return redirect()->route('verlof.mijn')->with('status', 'Verlofaanvraag ingediend.');
    }

    /** Self-service: eigen openstaande aanvraag intrekken. */
    public function intrekken(Request $request, Verlofaanvraag $aanvraag): RedirectResponse
    {
        $medewerker = $this->eigenMedewerker($request);
        abort_unless($aanvraag->medewerker_id === $medewerker->id, 403, 'Dit is niet uw aanvraag.');
        abort_unless($aanvraag->status === Verlofstatus::Aangevraagd, 422, 'Alleen een openstaande aanvraag kan worden ingetrokken.');

        $aanvraag->update(['status' => Verlofstatus::Ingetrokken->value]);

        return redirect()->route('verlof.mijn')->with('status', 'Aanvraag ingetrokken.');
    }

    /** Goedkeuren of afwijzen (leidinggevende / HR). */
    public function beoordelen(Request $request, Verlofaanvraag $aanvraag): RedirectResponse
    {
        abort_unless($aanvraag->beoordeelbaarVoor($request->user()), 403, 'U mag deze aanvraag niet beoordelen.');

        $data = $request->validate([
            'besluit' => ['required', Rule::in(['goedgekeurd', 'afgewezen'])],
            'opmerking_beoordelaar' => ['nullable', 'string', 'max:1000'],
        ]);

        $aanvraag->update([
            'status' => $data['besluit'],
            'beoordelaar_id' => $request->user()->id,
            'beoordeeld_op' => now(),
            'opmerking_beoordelaar' => $data['opmerking_beoordelaar'] ?? null,
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $aanvraag->medewerker, veld: 'verlof', context: [
            'aanvraag' => $aanvraag->id, 'besluit' => $data['besluit'],
        ]);

        return back()->with('status', 'Aanvraag '.$data['besluit'].'.');
    }

    /** Het eigen personeelsrecord, of 403 als de gebruiker er geen heeft. */
    private function eigenMedewerker(Request $request)
    {
        $medewerker = $request->user()->medewerker;
        abort_if($medewerker === null, 403, 'Aan uw account is geen personeelsdossier gekoppeld.');

        return $medewerker;
    }
}
