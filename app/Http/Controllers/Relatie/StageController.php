<?php

namespace App\Http\Controllers\Relatie;

use App\Enums\Rol;
use App\Enums\Stagestatus;
use App\Http\Controllers\Controller;
use App\Models\Opleiding;
use App\Models\Organisatie;
use App\Models\Stage;
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
            ->with(['student', 'organisatie', 'opleiding', 'stagebegeleider', 'werkplekbegeleider'])
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

        return view('relaties.stages-index', [
            'stages' => $stages,
            'zoek' => (string) $request->query('q', ''),
            'statusFilter' => (string) $request->query('status', ''),
            'statussen' => Stagestatus::cases(),
            'opleidingen' => $this->zichtbareOpleidingen($request),
            'opleidingFilter' => (int) $request->query('opleiding', 0),
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

    private function valideer(Request $request, Organisatie $organisatie, bool $metBeoordeling): array
    {
        $opleidingIds = $this->opleidingenVoorOrganisatie($request, $organisatie)->pluck('id')->all();
        $studentIds = $this->studenten($opleidingIds)->pluck('id')->all();
        $stageplaatsIds = $organisatie->stageplaatsen()->pluck('id')->all();
        $contactpersoonIds = $organisatie->contactpersonen()->pluck('id')->all();

        $regels = [
            'student_id' => ['required', 'integer', Rule::in($studentIds)],
            'opleiding_id' => ['required', 'integer', Rule::in($opleidingIds)],
            'stageplaats_id' => ['nullable', 'integer', Rule::in($stageplaatsIds)],
            'stagebegeleider_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('rol', Rol::Docent->value)],
            'werkplekbegeleider_id' => ['nullable', 'integer', Rule::in($contactpersoonIds)],
            'startdatum' => ['nullable', 'date'],
            'einddatum' => ['nullable', 'date', 'after_or_equal:startdatum'],
            'status' => ['required', Rule::in(Stagestatus::waarden())],
        ];

        if ($metBeoordeling) {
            $regels['beoordeling'] = ['nullable', 'in:voldoende,onvoldoende'];
            $regels['beoordeling_toelichting'] = ['nullable', 'string', 'max:2000'];
        }

        return $request->validate($regels, [], [
            'student_id' => 'student',
            'opleiding_id' => 'opleiding',
            'stageplaats_id' => 'stageplaats',
            'werkplekbegeleider_id' => 'werkplekbegeleider',
        ]);
    }
}
