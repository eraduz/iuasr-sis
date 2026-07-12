<?php

namespace App\Http\Controllers\Hr;

use App\Enums\MedewerkerSoort;
use App\Enums\MedewerkerStatus;
use App\Http\Controllers\Controller;
use App\Models\Afdeling;
use App\Models\Functie;
use App\Models\Medewerker;
use App\Models\MedewerkerNotitie;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PersoneelsnummerGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Medewerkersregistratie (module HR). Inzage is gescoped (Manager = eigen team);
 * aanmaken/wijzigen is voorbehouden aan HR en Beheer. BSN is versleuteld en
 * gelogd, en alleen zichtbaar als het via config is ingeschakeld.
 */
class MedewerkerController extends Controller
{
    public function index(Request $request): View
    {
        $medewerkers = Medewerker::query()
            ->zichtbaarVoor($request->user())
            ->with(['afdeling', 'functie', 'dienstverbanden'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $zoek = trim((string) $request->query('q'));
                $q->where(fn ($s) => $s->where('achternaam', 'like', "%{$zoek}%")
                    ->orWhere('voornaam', 'like', "%{$zoek}%")
                    ->orWhere('personeelsnummer', 'like', "%{$zoek}%"));
            })
            ->when($request->filled('afdeling'), fn ($q) => $q->where('afdeling_id', (int) $request->query('afdeling')))
            ->when($request->filled('functie'), fn ($q) => $q->where('functie_id', (int) $request->query('functie')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('soort'), fn ($q) => $q->where('soort', $request->query('soort')))
            ->orderBy('achternaam')->orderBy('voornaam')
            ->paginate(25)->withQueryString();

        return view('hr.medewerkers-index', [
            'medewerkers' => $medewerkers,
            'zoek' => (string) $request->query('q', ''),
            'statusFilter' => (string) $request->query('status', ''),
            'soortFilter' => (string) $request->query('soort', ''),
            'afdelingFilter' => (int) $request->query('afdeling', 0),
            'functieFilter' => (int) $request->query('functie', 0),
            'afdelingen' => Afdeling::orderBy('naam')->get(),
            'functies' => Functie::orderBy('naam')->get(),
            'statussen' => MedewerkerStatus::cases(),
            'soorten' => MedewerkerSoort::cases(),
        ]);
    }

    public function create(): View
    {
        return view('hr.medewerker-form', $this->formData(new Medewerker(['status' => MedewerkerStatus::Actief->value])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);

        $medewerker = null;
        for ($poging = 0; $poging < 5 && $medewerker === null; $poging++) {
            try {
                $medewerker = Medewerker::create($data + ['personeelsnummer' => PersoneelsnummerGenerator::genereer()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $medewerker = null;
            }
        }
        abort_if($medewerker === null, 500, 'Kon geen uniek personeelsnummer bepalen.');

        $this->sluitLopendDienstverband($medewerker);
        AuditLogger::log(AuditLogger::AANMAAK, $medewerker, veld: 'medewerker', context: ['personeelsnummer' => $medewerker->personeelsnummer]);

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Medewerker toegevoegd.');
    }

    public function show(Request $request, Medewerker $medewerker): View
    {
        abort_unless($medewerker->zichtbaarVoor($request->user()), 403, 'Deze medewerker valt buiten uw toegang.');

        $medewerker->load(['afdeling', 'functie', 'manager', 'user',
            'dienstverbanden' => fn ($q) => $q->with(['functie', 'afdeling']),
            'documenten' => fn ($q) => $q->with('geuploadDoor'),
            'verlofaanvragen' => fn ($q) => $q->with('beoordelaar'),
            'ziekmeldingen' => fn ($q) => $q->with('gemeldDoor'),
            'gesprekken' => fn ($q) => $q->with('gespreksvoerder'),
            'checklisttaken' => fn ($q) => $q->with('verantwoordelijke'),
            'notities' => fn ($q) => $q->with('gebruiker')]);

        $jaar = (int) date('Y');

        return view('hr.medewerker-show', [
            'medewerker' => $medewerker,
            'bsnZichtbaar' => (bool) config('sis.hr.bsn_ingeschakeld', false) && $request->user()->rol->magBsnInzien(),
            'saldo' => \App\Support\Verlofoverzicht::voor($medewerker, $jaar),
            'jaar' => $jaar,
            'magBeoordelen' => $request->user()->magVerlofBeoordelen(),
        ]);
    }

    public function edit(Request $request, Medewerker $medewerker): View
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag deze medewerker niet wijzigen.');

        return view('hr.medewerker-form', $this->formData($medewerker));
    }

    public function update(Request $request, Medewerker $medewerker): RedirectResponse
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag deze medewerker niet wijzigen.');

        $medewerker->update($this->valideer($request, $medewerker->id));
        $this->sluitLopendDienstverband($medewerker);
        AuditLogger::log(AuditLogger::WIJZIGING, $medewerker, veld: 'medewerker', context: ['personeelsnummer' => $medewerker->personeelsnummer]);

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Medewerker bijgewerkt.');
    }

    /**
     * Medewerker VOLLEDIG en onherstelbaar verwijderen — voor foutieve records
     * (bijvoorbeeld een dubbel aangemaakte medewerker). Alle gekoppelde HR-gegevens
     * (dienstverbanden, verlof, verzuim, documenten, notities, gesprekken,
     * checklists) cascaden mee; een gekoppeld login-account blijft bestaan.
     * Dubbele beveiliging: bevestiging + exact personeelsnummer intypen.
     */
    public function destroy(Request $request, Medewerker $medewerker): RedirectResponse
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag deze medewerker niet verwijderen.');

        $request->validate(['bevestig_nummer' => ['required', 'string']]);
        if ($request->input('bevestig_nummer') !== $medewerker->personeelsnummer) {
            return back()->with('fout', 'Verwijderen afgebroken: het ingevoerde personeelsnummer komt niet overeen.');
        }

        // Fysieke documentbestanden van de private schijf verwijderen (DB-rijen cascaden).
        foreach ($medewerker->documenten as $document) {
            if ($document->pad) {
                Storage::disk('local')->delete($document->pad);
            }
        }

        $nummer = $medewerker->personeelsnummer;
        AuditLogger::log(AuditLogger::VERWIJDERING, 'Medewerker', $medewerker->id, veld: 'medewerker', context: [
            'personeelsnummer' => $nummer, 'naam' => $medewerker->volledigeNaam(),
        ]);

        $medewerker->delete();

        return redirect()->route('medewerkers')->with('status', "Medewerker {$nummer} is volledig verwijderd.");
    }

    /**
     * Interne notitie toevoegen bij een medewerker (HR/Beheer): een logboekregel
     * voor een contactmoment — e-mail, telefoongesprek of gespreksverslag.
     */
    public function notitieStore(Request $request, Medewerker $medewerker): RedirectResponse
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag hier geen notities toevoegen.');

        $data = $request->validate([
            'tekst' => ['required', 'string', 'max:5000'],
        ]);

        $medewerker->notities()->create([
            'gebruiker_id' => auth()->id(),
            'tekst' => $data['tekst'],
        ]);

        return redirect()
            ->to(route('medewerkers.show', $medewerker).'#notities')
            ->with('status', 'Notitie toegevoegd.');
    }

    /** Interne notitie verwijderen (HR/Beheer). */
    public function notitieDestroy(Request $request, Medewerker $medewerker, MedewerkerNotitie $notitie): RedirectResponse
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag deze notitie niet verwijderen.');
        abort_unless($notitie->medewerker_id === $medewerker->id, 404);

        $notitie->delete();

        return redirect()
            ->to(route('medewerkers.show', $medewerker).'#notities')
            ->with('status', 'Notitie verwijderd.');
    }

    private function formData(Medewerker $medewerker): array
    {
        // Gebruikers die (nog) aan geen andere medewerker gekoppeld zijn — voor self-service.
        $gekoppeld = Medewerker::whereNotNull('user_id')->where('id', '!=', $medewerker->id ?? 0)->pluck('user_id');

        return [
            'medewerker' => $medewerker,
            'afdelingen' => Afdeling::orderBy('naam')->get(),
            'functies' => Functie::orderBy('naam')->get(),
            'managers' => Medewerker::where('id', '!=', $medewerker->id ?? 0)->orderBy('achternaam')->get(),
            'gebruikers' => User::whereNotIn('id', $gekoppeld)->orderBy('naam')->get(),
            'statussen' => MedewerkerStatus::cases(),
            'soorten' => MedewerkerSoort::cases(),
            'bsnInschakelen' => (bool) config('sis.hr.bsn_ingeschakeld', false),
        ];
    }

    private function valideer(Request $request, ?int $negeerId = null): array
    {
        $regels = [
            'aanhef' => ['nullable', 'string', 'max:20'],
            'voornaam' => ['required', 'string', 'max:255'],
            'tussenvoegsel' => ['nullable', 'string', 'max:255'],
            'achternaam' => ['required', 'string', 'max:255'],
            'geboortedatum' => ['nullable', 'date'],
            'afdeling_id' => ['nullable', 'integer', 'exists:afdelingen,id'],
            'functie_id' => ['nullable', 'integer', 'exists:functies,id'],
            'manager_id' => ['nullable', 'integer', Rule::exists('medewerkers', 'id')->where(fn ($q) => $negeerId ? $q->where('id', '!=', $negeerId) : $q)],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('medewerkers', 'user_id')->ignore($negeerId)],
            'adres' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'woonplaats' => ['nullable', 'string', 'max:255'],
            'telefoon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'email_prive' => ['nullable', 'email', 'max:255'],
            'soort' => ['nullable', Rule::in(MedewerkerSoort::waarden())],
            'status' => ['required', Rule::in(MedewerkerStatus::waarden())],
            // Offboarding: de uit-dienstdatum is verplicht zodra de status 'uit dienst' is.
            'uit_dienst_datum' => ['nullable', 'date', 'required_if:status,'.MedewerkerStatus::UitDienst->value],
            'uit_dienst_reden' => ['nullable', 'string', 'max:255'],
            'opmerkingen' => ['nullable', 'string', 'max:2000'],
        ];

        if (config('sis.hr.bsn_ingeschakeld', false)) {
            $regels['bsn'] = ['nullable', 'digits:9'];
        }

        $data = $request->validate($regels);
        $data['actief'] = $request->boolean('actief', true);

        // Soort alleen wegschrijven als die is meegegeven: bij aanmaken geldt anders
        // de databasestandaard 'personeel', bij wijzigen blijft de bestaande soort staan.
        if (empty($data['soort'])) {
            unset($data['soort']);
        }

        // Offboarding-logica: uit-dienstdatum/-reden horen alleen bij status 'uit dienst',
        // en wie uit dienst is, is per definitie niet meer actief.
        if (($data['status'] ?? null) === MedewerkerStatus::UitDienst->value) {
            $data['actief'] = false;
        } else {
            $data['uit_dienst_datum'] = null;
            $data['uit_dienst_reden'] = null;
        }

        // BSN alleen verwerken als het is ingeschakeld; hash voor zoeken/uniciteit.
        if (config('sis.hr.bsn_ingeschakeld', false) && ! empty($data['bsn'])) {
            $data['bsn_hash'] = hash('sha256', $data['bsn']);
        } else {
            unset($data['bsn']);
        }

        return $data;
    }

    /**
     * Sluit het lopende dienstverband op de uit-dienstdatum. Cruciaal bij een VAST
     * contract: dat heeft geen eigen einddatum, dus zonder deze stap zou het
     * dienstverband "lopend" blijven terwijl de medewerker uit dienst is. Bestaande
     * einddata worden niet overschreven.
     */
    private function sluitLopendDienstverband(Medewerker $medewerker): void
    {
        if ($medewerker->status !== MedewerkerStatus::UitDienst || $medewerker->uit_dienst_datum === null) {
            return;
        }

        $medewerker->load('dienstverbanden');
        $lopend = $medewerker->huidigDienstverband();
        if ($lopend && $lopend->einddatum === null) {
            $lopend->update(['einddatum' => $medewerker->uit_dienst_datum]);
        }
    }
}
