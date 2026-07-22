<?php

namespace App\Http\Controllers\Relatie;

use App\Enums\Rol;
use App\Enums\Stagestatus;
use App\Http\Controllers\Controller;
use App\Models\Opleiding;
use App\Models\Organisatie;
use App\Models\Stage;
use App\Models\Stageperiode;
use App\Models\Student;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\StagenummerGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van de stages (plaatsingen). Inzage volgt de module (opleidinggebonden
 * gescoped); muteren is voorbehouden aan de stagecoördinator (eigen opleiding)
 * en de Beheerder. De beoordeling (voldoende/onvoldoende) is een gevoelig,
 * gelogd gegeven over de student.
 */
class StageController extends Controller
{
    public function index(Request $request): View
    {
        $stages = Stage::query()
            ->zichtbaarVoor($request->user())
            ->with(['student', 'organisatie', 'opleiding', 'stageperiode', 'stagebegeleider', 'werkplekbegeleider'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('opleiding'), fn ($q) => $q->where('opleiding_id', (int) $request->query('opleiding')))
            ->when($request->filled('organisatie'), fn ($q) => $q->where('organisatie_id', (int) $request->query('organisatie')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $zoek = trim((string) $request->query('q'));
                $q->where(fn ($s) => $s->where('stagenummer', 'like', "%{$zoek}%")
                    ->orWhereHas('student', fn ($st) => $st->where('achternaam', 'like', "%{$zoek}%")->orWhere('studentnummer', 'like', "%{$zoek}%")));
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Organisaties waar deze gebruiker een student mag plaatsen (voor de knop
        // 'Student plaatsen' bovenaan). Alleen als hij stages mag beheren.
        $organisatiesVoorPlaatsing = $request->user()->magStagebeheer()
            ? Organisatie::query()->zichtbaarVoor($request->user())->where('actief', true)->orderBy('naam')->get()
            : collect();

        return view('relaties.stages-index', [
            'stages' => $stages,
            'zoek' => (string) $request->query('q', ''),
            'statusFilter' => (string) $request->query('status', ''),
            'statussen' => Stagestatus::cases(),
            'opleidingen' => $this->zichtbareOpleidingen($request),
            'opleidingFilter' => (int) $request->query('opleiding', 0),
            'organisatiesVoorPlaatsing' => $organisatiesVoorPlaatsing,
        ]);
    }

    public function create(Request $request, Organisatie $organisatie): View
    {
        abort_unless($organisatie->stagesBeheerbaarVoor($request->user()), 403, 'U mag geen stages plaatsen bij deze organisatie.');

        return view('relaties.stage-form', $this->formData($request, $organisatie, new Stage(['status' => Stagestatus::Aangevraagd->value])));
    }

    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->stagesBeheerbaarVoor($request->user()), 403, 'U mag geen stages plaatsen bij deze organisatie.');

        $data = $this->valideer($request, $organisatie, metBeoordeling: false);

        $stage = null;
        for ($poging = 0; $poging < 5 && $stage === null; $poging++) {
            try {
                $stage = $organisatie->stages()->create($data + ['stagenummer' => StagenummerGenerator::genereer()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $stage = null;
            }
        }
        abort_if($stage === null, 500, 'Kon geen uniek stagenummer bepalen.');

        AuditLogger::log(AuditLogger::AANMAAK, $stage, veld: 'stage', context: ['stagenummer' => $stage->stagenummer]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Stage geplaatst ('.$stage->stagenummer.').');
    }

    public function edit(Request $request, Stage $stage): View
    {
        abort_unless($stage->beheerbaarVoor($request->user()), 403, 'Deze stage valt buiten uw beheer.');

        return view('relaties.stage-form', $this->formData($request, $stage->organisatie, $stage));
    }

    public function update(Request $request, Stage $stage): RedirectResponse
    {
        abort_unless($stage->beheerbaarVoor($request->user()), 403, 'Deze stage valt buiten uw beheer.');

        $data = $this->valideer($request, $stage->organisatie, metBeoordeling: true);
        $beoordelingGewijzigd = ($stage->beoordeling !== ($data['beoordeling'] ?? null));

        $stage->update($data);
        AuditLogger::log(AuditLogger::WIJZIGING, $stage, veld: $beoordelingGewijzigd ? 'stage_beoordeling' : 'stage', context: [
            'stagenummer' => $stage->stagenummer,
            'status' => $stage->status?->value,
        ] + ($beoordelingGewijzigd ? ['beoordeling' => $stage->beoordeling] : []));

        return redirect()->route('relaties.show', $stage->organisatie)->with('status', 'Stage bijgewerkt.');
    }

    private function formData(Request $request, Organisatie $organisatie, Stage $stage): array
    {
        $opleidingen = $this->opleidingenVoorOrganisatie($request, $organisatie);
        $opleidingIds = $opleidingen->pluck('id')->all();

        return [
            'organisatie' => $organisatie,
            'stage' => $stage,
            'opleidingen' => $opleidingen,
            'studenten' => $this->studenten($opleidingIds),
            'stageplaatsen' => $organisatie->stageplaatsen()->where('actief', true)->with('opleiding')->get(),
            // Stageperioden van de beheerbare opleidingen; de view filtert ze per
            // gekozen opleiding (data-attribuut) en vult de urennorm voor.
            'stageperioden' => Stageperiode::whereIn('opleiding_id', $opleidingIds)->actief()->geordend()->get(),
            'begeleiders' => User::where('rol', Rol::Docent)->orderBy('naam')->get(),
            'werkplekbegeleiders' => $organisatie->contactpersonen()->where('actief', true)->orderBy('achternaam')->get(),
            'statussen' => Stagestatus::cases(),
        ];
    }

    /** Opleidingen van de organisatie die deze gebruiker mag beheren. */
    private function opleidingenVoorOrganisatie(Request $request, Organisatie $organisatie)
    {
        $opleidingen = $organisatie->opleidingen()->orderBy('naam')->get();
        if ($request->user()->isRelatieBeperkt()) {
            $opleidingen = $opleidingen->whereIn('id', $request->user()->opleidingIds())->values();
        }

        return $opleidingen;
    }

    /** Opleidingen die deze gebruiker mag zien (voor de filterbalk). */
    private function zichtbareOpleidingen(Request $request)
    {
        $query = Opleiding::query()->orderBy('naam');
        if ($request->user()->isRelatieBeperkt()) {
            $query->whereIn('id', $request->user()->opleidingIds());
        }

        return $query->get();
    }

    /** Studenten met een actieve inschrijving in een van de opgegeven opleidingen. */
    private function studenten(array $opleidingIds)
    {
        if (empty($opleidingIds)) {
            return collect();
        }

        return Student::whereHas('inschrijvingen', fn ($q) => $q->where('status', 'actief')->whereIn('opleiding_id', $opleidingIds))
            ->orderBy('achternaam')->orderBy('voornaam')->get();
    }

    /**
     * Zet het zoekveld `student_zoek` om naar `student_id`. Geaccepteerd worden
     * "261234 — Naam" (zoals de keuzelijst aanbiedt) en een kaal studentnummer,
     * zodat wie het nummer uit het hoofd kent niets hoeft te kiezen. Wordt er
     * niets gevonden, dan blijft `student_id` leeg en meldt de gewone validatie
     * dat de student verplicht is — geen stille mislukking.
     */
    private function vertaalStudentZoek(Request $request, array $opleidingIds): void
    {
        if ($request->filled('student_id') || ! $request->filled('student_zoek')) {
            return;
        }

        $ingevoerd = trim((string) $request->input('student_zoek'));
        // Alles vóór de eerste spatie of het gedachtestreepje is het studentnummer.
        $nummer = trim(preg_split('/\s|—/u', $ingevoerd, 2)[0] ?? '');

        $student = $this->studenten($opleidingIds)->firstWhere('studentnummer', $nummer);

        $request->merge(['student_id' => $student?->id]);
    }

    private function valideer(Request $request, Organisatie $organisatie, bool $metBeoordeling): array
    {
        $opleidingIds = $this->opleidingenVoorOrganisatie($request, $organisatie)->pluck('id')->all();
        $studentIds = $this->studenten($opleidingIds)->pluck('id')->all();
        $stageplaatsIds = $organisatie->stageplaatsen()->pluck('id')->all();
        $contactpersoonIds = $organisatie->contactpersonen()->pluck('id')->all();

        // De student wordt met een zoekveld gekozen ("261234 — Ahmed Yilmaz") in
        // plaats van een keuzelijst: met honderden studenten is scrollen
        // onwerkbaar. Hier wordt het studentnummer er weer afgepeld en terug naar
        // een id vertaald; de controle hieronder (Rule::in) blijft ongewijzigd, dus
        // een student buiten de eigen opleiding blijft geweigerd.
        $this->vertaalStudentZoek($request, $opleidingIds);

        // Stageperiode: gebonden aan de GEKOZEN opleiding. Heeft die opleiding
        // stageperioden, dan is een keuze verplicht (opdrachtgever 2026-07-22);
        // opleidingen zonder perioden (bv. cursussen, PABO zolang leeg) blijven vrij.
        $gekozenOpleiding = (int) $request->input('opleiding_id');
        $periodeIds = Stageperiode::where('opleiding_id', $gekozenOpleiding)->actief()->pluck('id')->all();

        $regels = [
            'student_id' => ['required', 'integer', Rule::in($studentIds)],
            'opleiding_id' => ['required', 'integer', Rule::in($opleidingIds)],
            'stageperiode_id' => [empty($periodeIds) ? 'nullable' : 'required', 'integer', Rule::in($periodeIds)],
            'stageplaats_id' => ['nullable', 'integer', Rule::in($stageplaatsIds)],
            'stagebegeleider_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('rol', Rol::Docent->value)],
            'werkplekbegeleider_id' => ['nullable', 'integer', Rule::in($contactpersoonIds)],
            'startdatum' => ['nullable', 'date'],
            'einddatum' => ['nullable', 'date', 'after_or_equal:startdatum'],
            'uren' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'status' => ['required', Rule::in(Stagestatus::waarden())],
        ];

        if ($metBeoordeling) {
            $regels['beoordeling'] = ['nullable', 'in:voldoende,onvoldoende'];
            $regels['beoordeling_toelichting'] = ['nullable', 'string', 'max:2000'];
        }

        $data = $request->validate($regels, [], [
            'student_id' => 'student',
            'opleiding_id' => 'opleiding',
            'stageperiode_id' => 'stageperiode',
            'stageplaats_id' => 'stageplaats',
            'werkplekbegeleider_id' => 'werkplekbegeleider',
        ]);

        // Uren niet ingevuld maar wel een stageperiode gekozen? Neem de urennorm
        // als startwaarde over (de coördinator kan die later bijstellen).
        if (($data['stageperiode_id'] ?? null) && ($data['uren'] ?? null) === null) {
            $data['uren'] = Stageperiode::find($data['stageperiode_id'])?->verplichte_uren;
        }

        return $data;
    }
}
