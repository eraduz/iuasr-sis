<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Enums\TaakPrioriteit;
use App\Enums\TaakStatus;
use App\Models\Student;
use App\Models\Taak;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Gedeelde takenlijst van Studentenzaken, naar het model van Outlook Taken:
 * onderwerp, begindatum, vervaldatum, status en prioriteit. Een taak mag aan
 * een medewerker en optioneel aan een studentdossier worden gekoppeld.
 *
 * Toegang: uitsluitend Studentenzaken en Beheer. Geen audit-logging: een taak
 * is werkverdeling, geen gevoelig persoonsgegeven. Wie de taak aanmaakte en aan
 * wie zij is toegewezen blijft wel zichtbaar op de taak zelf.
 */
class TaakController extends Controller
{
    public function index(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'openstaand');
        $vanMij = $request->boolean('mijn');

        $taken = Taak::query()
            ->with(['student', 'toegewezenAan', 'aangemaaktDoor'])
            // 'openstaand' = alles behalve afgerond; 'alle' = geen filter.
            ->when($status === 'openstaand', fn ($q) => $q->openstaand())
            ->when(array_key_exists($status, TaakStatus::opties()), fn ($q) => $q->where('status', $status))
            ->when($vanMij, fn ($q) => $q->where('toegewezen_aan_id', $request->user()->id))
            ->when($zoek !== '', function ($q) use ($zoek) {
                $q->where(function ($sub) use ($zoek) {
                    $sub->where('titel', 'like', '%'.$zoek.'%')
                        ->orWhere('omschrijving', 'like', '%'.$zoek.'%')
                        ->orWhereHas('student', fn ($s) => $s->where('studentnummer', 'like', $zoek.'%'));
                });
            })
            ->opUrgentie()
            ->paginate(25)
            ->withQueryString();

        return view('taken.index', [
            'taken' => $taken,
            'zoek' => $zoek,
            'status' => $status,
            'vanMij' => $vanMij,
            'medewerkers' => $this->medewerkers(),
            'studenten' => Student::orderBy('studentnummer')->get(['id', 'studentnummer', 'voornaam', 'tussenvoegsel', 'achternaam']),
            'statussen' => TaakStatus::opties(),
            'prioriteiten' => TaakPrioriteit::opties(),
            'teLaat' => Taak::openstaand()->whereNotNull('vervaldatum')
                ->whereDate('vervaldatum', '<', now()->toDateString())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);

        // Een nieuwe taak begint altijd op 'open', ongeacht wat er wordt meegestuurd.
        $data['status'] = TaakStatus::Open->value;
        $data['afgerond_op'] = null;
        $data['aangemaakt_door_id'] = $request->user()->id;

        Taak::create($data);

        return back()->with('status', 'Taak toegevoegd.');
    }

    public function update(Request $request, Taak $taak): RedirectResponse
    {
        $data = $this->valideer($request);
        $data['status'] = $request->input('status', $taak->status->value);

        // Het afrondmoment volgt de status; nooit een afgeronde taak zonder datum.
        $data['afgerond_op'] = $data['status'] === TaakStatus::Afgerond->value
            ? ($taak->afgerond_op ?? now())
            : null;

        $taak->update($data);

        return redirect()->route('taken')->with('status', 'Taak bijgewerkt.');
    }

    /** Afvinken of weer openzetten (één klik vanuit de lijst). */
    public function afronden(Request $request, Taak $taak): RedirectResponse
    {
        $afronden = ! $taak->isAfgerond();

        $taak->update([
            'status' => $afronden ? TaakStatus::Afgerond : TaakStatus::Open,
            'afgerond_op' => $afronden ? now() : null,
        ]);

        return back()->with('status', $afronden ? 'Taak afgerond.' : 'Taak heropend.');
    }

    public function destroy(Taak $taak): RedirectResponse
    {
        $taak->delete();

        return back()->with('status', 'Taak verwijderd.');
    }

    /** Medewerkers aan wie een taak kan worden toegewezen: Studentenzaken en Beheer. */
    private function medewerkers()
    {
        return User::whereIn('rol', [Rol::Studentenzaken->value, Rol::Beheerder->value])
            ->orderBy('naam')->get(['id', 'naam']);
    }

    private function valideer(Request $request): array
    {
        // De vergelijking met de begindatum geldt alleen als die is ingevuld;
        // anders zou Laravel 'startdatum' als letterlijke datum proberen te lezen.
        $vervaldatum = ['nullable', 'date'];
        if ($request->filled('startdatum')) {
            $vervaldatum[] = 'after_or_equal:startdatum';
        }

        $data = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'omschrijving' => ['nullable', 'string', 'max:2000'],
            'student_id' => ['nullable', Rule::exists('studenten', 'id')],
            'toegewezen_aan_id' => ['nullable', Rule::exists('users', 'id')],
            'startdatum' => ['nullable', 'date'],
            'vervaldatum' => $vervaldatum,
            'prioriteit' => ['required', new Enum(TaakPrioriteit::class)],
            'status' => ['nullable', new Enum(TaakStatus::class)],
        ], [
            'vervaldatum.after_or_equal' => 'De vervaldatum kan niet vóór de begindatum liggen.',
        ]);

        // Lege selectvelden komen als '' binnen; die horen null te zijn.
        foreach (['student_id', 'toegewezen_aan_id', 'startdatum', 'vervaldatum', 'omschrijving'] as $veld) {
            if (($data[$veld] ?? '') === '') {
                $data[$veld] = null;
            }
        }

        return $data;
    }
}
