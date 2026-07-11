<?php

namespace App\Http\Controllers\Relatie;

use App\Enums\AfspraakType;
use App\Http\Controllers\Controller;
use App\Models\Afspraak;
use App\Models\Organisatie;
use App\Models\Overeenkomst;
use App\Models\Relatietaak;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Agenda-afspraken bij een organisatie én de module-brede planning (aankomende
 * afspraken + open taken). Werkinformatie; geen audit-logging. Muteren volgt de
 * organisatie (beheerbaarVoor); de planning is zichtbaar voor alle module-rollen.
 */
class AfspraakController extends Controller
{
    /** Module-brede planning: aankomende afspraken en open taken (signalering). */
    public function index(Request $request): View
    {
        $gebruiker = $request->user();

        $afspraken = Afspraak::query()
            ->zichtbaarVoor($gebruiker)
            ->with(['organisatie', 'stage.student', 'medewerker'])
            ->where('status', 'gepland')
            ->whereDate('datum', '>=', now()->toDateString())
            ->orderBy('datum')->orderBy('tijd_van')
            ->limit(50)->get();

        $taken = Relatietaak::query()
            ->zichtbaarVoor($gebruiker)
            ->with(['organisatie', 'toegewezenAan'])
            ->where('status', '!=', 'afgerond')
            ->orderByRaw('vervaldatum is null, vervaldatum asc')
            ->limit(50)->get();

        // Signalering 'contracten die verlopen': aflopend binnen 60 dagen of al
        // verlopen, en niet opgezegd.
        $overeenkomsten = Overeenkomst::query()
            ->zichtbaarVoor($gebruiker)
            ->with('organisatie')
            ->whereNotNull('verloopdatum')
            ->where('status', '!=', 'opgezegd')
            ->whereDate('verloopdatum', '<=', now()->addDays(60)->toDateString())
            ->orderBy('verloopdatum')
            ->limit(50)->get();

        return view('relaties.agenda-index', compact('afspraken', 'taken', 'overeenkomsten'));
    }

    /**
     * iCal-export (.ics) van de aankomende afspraken binnen het bereik van de
     * gebruiker. Intranet-veilig: een standaard bestand-download (geen externe
     * koppeling), te importeren in Outlook, Google Agenda of Apple Agenda.
     */
    public function ical(Request $request)
    {
        $afspraken = Afspraak::query()
            ->zichtbaarVoor($request->user())
            ->with('organisatie')
            ->where('status', 'gepland')
            ->whereDate('datum', '>=', now()->subDay()->toDateString())
            ->orderBy('datum')->limit(200)->get();

        $regels = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//IUASR//Relatiebeheer//NL', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
        $stamp = now()->format('Ymd\THis');

        foreach ($afspraken as $af) {
            $datum = $af->datum->format('Ymd');
            $regels[] = 'BEGIN:VEVENT';
            $regels[] = 'UID:afspraak-'.$af->id.'@iuasr-sis';
            $regels[] = 'DTSTAMP:'.$stamp;
            if ($af->tijd_van) {
                $van = $datum.'T'.str_replace(':', '', substr((string) $af->tijd_van, 0, 5)).'00';
                $tot = $af->tijd_tot ? $datum.'T'.str_replace(':', '', substr((string) $af->tijd_tot, 0, 5)).'00' : $van;
                $regels[] = 'DTSTART:'.$van;
                $regels[] = 'DTEND:'.$tot;
            } else {
                $regels[] = 'DTSTART;VALUE=DATE:'.$datum;
            }
            $regels[] = 'SUMMARY:'.$this->icalEscape(($af->type?->label() ?? 'Afspraak').' — '.($af->organisatie?->naam ?? ''));
            if ($af->locatie) {
                $regels[] = 'LOCATION:'.$this->icalEscape($af->locatie);
            }
            if ($af->omschrijving) {
                $regels[] = 'DESCRIPTION:'.$this->icalEscape($af->omschrijving);
            }
            $regels[] = 'END:VEVENT';
        }
        $regels[] = 'END:VCALENDAR';

        return response(implode("\r\n", $regels)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="relatiebeheer-agenda.ics"',
        ]);
    }

    private function icalEscape(string $tekst): string
    {
        return str_replace(["\\", "\n", ',', ';'], ['\\\\', '\\n', '\\,', '\\;'], $tekst);
    }

    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $organisatie->afspraken()->create(
            $this->valideer($request, $organisatie) + ['medewerker_id' => $request->user()->id]
        );

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Afspraak gepland.');
    }

    public function edit(Request $request, Afspraak $afspraak): View
    {
        abort_unless($afspraak->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.afspraak-form', $this->formData($afspraak->organisatie, $afspraak));
    }

    public function create(Request $request, Organisatie $organisatie): View
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        return view('relaties.afspraak-form', $this->formData($organisatie, new Afspraak(['status' => 'gepland', 'datum' => now()->toDateString()])));
    }

    public function update(Request $request, Afspraak $afspraak): RedirectResponse
    {
        abort_unless($afspraak->organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $afspraak->update($this->valideer($request, $afspraak->organisatie));

        return redirect()->route('relaties.show', $afspraak->organisatie)->with('status', 'Afspraak bijgewerkt.');
    }

    public function destroy(Request $request, Afspraak $afspraak): RedirectResponse
    {
        $organisatie = $afspraak->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $afspraak->delete();

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Afspraak verwijderd.');
    }

    private function formData(Organisatie $organisatie, Afspraak $afspraak): array
    {
        return [
            'organisatie' => $organisatie,
            'afspraak' => $afspraak,
            'types' => AfspraakType::opties(),
            'stages' => $organisatie->stages()->with('student')->orderByDesc('id')->get(),
        ];
    }

    private function valideer(Request $request, Organisatie $organisatie): array
    {
        $stageIds = $organisatie->stages()->pluck('id')->all();

        return $request->validate([
            'type' => ['required', Rule::in(AfspraakType::waarden())],
            'stage_id' => ['nullable', 'integer', Rule::in($stageIds)],
            'datum' => ['required', 'date'],
            'tijd_van' => ['nullable', 'date_format:H:i'],
            'tijd_tot' => ['nullable', 'date_format:H:i'],
            'locatie' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['gepland', 'afgerond', 'geannuleerd'])],
            'omschrijving' => ['nullable', 'string', 'max:2000'],
        ], [], ['stage_id' => 'stage']);
    }
}
