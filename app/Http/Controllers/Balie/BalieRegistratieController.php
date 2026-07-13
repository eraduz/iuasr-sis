<?php

namespace App\Http\Controllers\Balie;

use App\Enums\BalieRichting;
use App\Enums\BalieSoort;
use App\Http\Controllers\Controller;
use App\Enums\MedewerkerStatus;
use App\Models\BalieRegistratie;
use App\Models\Medewerker;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Het balielogboek (module Balie/Receptie): telefoongesprekken, bezoekers en
 * in- en uitgaande post, chronologisch vastgelegd en doorzoekbaar.
 *
 * Rolscheiding, server-side afgedwongen via de route-middleware én de guards
 * hieronder: de Balie en de Beheerder registreren en wijzigen; Directie en
 * Bestuur zien het logboek uitsluitend in (alleen-lezen). Registraties worden
 * nooit verwijderd — het logboek is een verantwoordingsdocument; een fout wordt
 * gecorrigeerd met een wijziging, en die wijziging wordt gelogd.
 */
class BalieRegistratieController extends Controller
{
    public function index(Request $request): View
    {
        $registraties = BalieRegistratie::query()
            ->with(['medewerker', 'geregistreerdDoor'])
            ->when($request->filled('q'), fn ($q) => $q->zoek((string) $request->query('q')))
            ->when($request->filled('soort'), fn ($q) => $q->where('soort', (string) $request->query('soort')))
            ->when($request->filled('richting'), fn ($q) => $q->where('richting', (string) $request->query('richting')))
            ->when($request->filled('medewerker'), fn ($q) => $q->where('medewerker_id', (int) $request->query('medewerker')))
            ->when($request->filled('vanaf'), fn ($q) => $q->whereDate('datum_tijd', '>=', $request->date('vanaf')))
            ->when($request->filled('tot'), fn ($q) => $q->whereDate('datum_tijd', '<=', $request->date('tot')))
            ->when($request->query('aanwezig') === '1', fn ($q) => $q->nogAanwezig())
            ->chronologisch()
            ->paginate(25)
            ->withQueryString();

        return view('balie.index', [
            'registraties' => $registraties,
            'medewerkers' => $this->medewerkers(),
            'zoek' => (string) $request->query('q', ''),
            'soortFilter' => (string) $request->query('soort', ''),
            'richtingFilter' => (string) $request->query('richting', ''),
            'medewerkerFilter' => (int) $request->query('medewerker', 0),
            'vanaf' => (string) $request->query('vanaf', ''),
            'tot' => (string) $request->query('tot', ''),
            'alleenAanwezig' => $request->query('aanwezig') === '1',
        ]);
    }

    public function create(Request $request): View
    {
        // De baliemedewerker kiest het soort vooraf (of komt via een knop op het
        // dashboard binnen); het formulier toont dan alleen de relevante velden.
        $soort = BalieSoort::tryFrom((string) $request->query('soort', '')) ?? BalieSoort::Telefoon;

        $registratie = new BalieRegistratie([
            'soort' => $soort,
            'richting' => $soort === BalieSoort::Bezoek ? BalieRichting::Inkomend : BalieRichting::Inkomend,
            'datum_tijd' => now(),
        ]);

        return view('balie.form', [
            'registratie' => $registratie,
            'medewerkers' => $this->medewerkers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);
        $data['geregistreerd_door_user_id'] = $request->user()->id;

        $registratie = BalieRegistratie::create($data);

        AuditLogger::log(AuditLogger::AANMAAK, $registratie, veld: 'balie_registratie', context: [
            'soort' => $registratie->soort->value,
            'richting' => $registratie->richting->value,
        ]);

        return redirect()->route('balie')->with('status', $registratie->soortLabel().' geregistreerd.');
    }

    public function edit(Request $request, BalieRegistratie $registratie): View
    {
        abort_unless($registratie->beheerbaarVoor($request->user()), 403, 'U mag deze registratie niet wijzigen.');

        return view('balie.form', [
            'registratie' => $registratie,
            'medewerkers' => $this->medewerkers(),
        ]);
    }

    public function update(Request $request, BalieRegistratie $registratie): RedirectResponse
    {
        abort_unless($registratie->beheerbaarVoor($request->user()), 403, 'U mag deze registratie niet wijzigen.');

        $registratie->update($this->valideer($request));

        AuditLogger::log(AuditLogger::WIJZIGING, $registratie, veld: 'balie_registratie', context: [
            'soort' => $registratie->soort->value,
        ]);

        return redirect()->route('balie')->with('status', 'Registratie bijgewerkt.');
    }

    /**
     * Meld een bezoeker af: leg het vertrekmoment vast. Eén klik vanaf het
     * dashboard, want dat is hoe het aan de balie gebruikt wordt.
     */
    public function vertrek(Request $request, BalieRegistratie $registratie): RedirectResponse
    {
        abort_unless($registratie->beheerbaarVoor($request->user()), 403, 'U mag deze registratie niet wijzigen.');
        abort_unless($registratie->soort === BalieSoort::Bezoek, 404);

        if ($registratie->vertrokken_op !== null) {
            return back()->with('fout', 'Deze bezoeker is al afgemeld.');
        }

        $registratie->update(['vertrokken_op' => now()]);

        AuditLogger::log(AuditLogger::WIJZIGING, $registratie, veld: 'balie_vertrek', context: [
            'vertrokken_op' => $registratie->vertrokken_op->toDateTimeString(),
        ]);

        return back()->with('status', $registratie->contact_naam.' is afgemeld.');
    }

    /**
     * Validatie. Welke velden verplicht zijn, hangt af van het soort: een bezoek
     * is altijd inkomend en kent een vertrekmoment; bij post wordt geen onderwerp
     * vastgelegd. Dit wordt server-side afgedwongen, niet alleen in de UI.
     */
    private function valideer(Request $request): array
    {
        $soort = BalieSoort::tryFrom((string) $request->input('soort'));

        $data = $request->validate([
            'soort' => ['required', Rule::in(BalieSoort::waarden())],
            'richting' => ['required', Rule::in(BalieRichting::waarden())],
            'datum_tijd' => ['required', 'date'],
            'vertrokken_op' => ['nullable', 'date', 'after_or_equal:datum_tijd'],
            'onderwerp' => [$soort === BalieSoort::Post ? 'nullable' : 'required', 'string', 'max:255'],
            'contact_naam' => ['required', 'string', 'max:255'],
            'contact_organisatie' => ['nullable', 'string', 'max:255'],
            'contact_telefoon' => ['nullable', 'string', 'max:30'],
            'medewerker_id' => ['nullable', 'integer', 'exists:medewerkers,id'],
            'afdeling' => ['nullable', 'string', 'max:255'],
            'toelichting' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'datum_tijd' => 'datum en tijd',
            'contact_naam' => 'naam',
            'medewerker_id' => 'medewerker',
            'vertrokken_op' => 'vertrektijd',
        ]);

        // Een bezoek is per definitie inkomend; een uitgaand bezoek bestaat niet.
        if ($soort === BalieSoort::Bezoek) {
            $data['richting'] = BalieRichting::Inkomend->value;
        } else {
            // Alleen een bezoek kent een vertrekmoment.
            $data['vertrokken_op'] = null;
        }

        // Bij post wordt geen onderwerp vastgelegd (bron: opdrachtomschrijving).
        if ($soort === BalieSoort::Post) {
            $data['onderwerp'] = null;
        }

        return $data;
    }

    /**
     * De medewerkers waaraan een registratie gekoppeld kan worden: iedereen die
     * niet uit dienst is. De balie hoeft geen HR-rechten te hebben om een naam te
     * kunnen kiezen — dit is alleen de namenlijst, geen personeelsdossier.
     */
    private function medewerkers()
    {
        return Medewerker::query()
            ->where('status', '!=', MedewerkerStatus::UitDienst)
            ->orderBy('achternaam')
            ->orderBy('voornaam')
            ->get();
    }
}
