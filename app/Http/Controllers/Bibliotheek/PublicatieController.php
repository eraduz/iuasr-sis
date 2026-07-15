<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Enums\ExemplaarStatus;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Exemplaar;
use App\Models\Bibliotheek\Kast;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Reeks;
use App\Models\Bibliotheek\Taal;
use App\Models\Bibliotheek\Vakgebied;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * De catalogus: titels (boeken, tijdschriften, digitale documenten), hun
 * auteurs, talen en exemplaren, en de boekreeksen met hun delen.
 *
 * Titel en exemplaar zijn gescheiden (Koha-model): de titel staat één keer, de
 * fysieke boeken hangen eronder. De status hoort bij het exemplaar.
 *
 * Rolscheiding: de Bibliotheekmedewerker en de Beheerder muteren; het
 * Schoolbestuur kijkt mee (alleen-lezen). Server-side afgedwongen via de
 * route-middleware én de guards hieronder.
 */
class PublicatieController extends Controller
{
    public function index(Request $request): View
    {
        $publicaties = Publicatie::query()
            ->with(['auteurs', 'talen', 'vakgebied', 'reeks', 'exemplaren'])
            ->when($request->filled('q'), fn ($q) => $q->zoek((string) $request->query('q')))
            ->when($request->filled('soort'), fn ($q) => $q->where('soort_id', (int) $request->query('soort')))
            ->when($request->filled('vakgebied'), fn ($q) => $q->where('vakgebied_id', (int) $request->query('vakgebied')))
            ->when($request->filled('jaar'), fn ($q) => $q->where('uitgavejaar', (int) $request->query('jaar')))
            ->when($request->filled('taal'), fn ($q) => $q->whereHas('talen', fn ($t) => $t->where('bibliotheek_talen.id', (int) $request->query('taal'))))
            ->when($request->filled('status'), fn ($q) => $q->whereHas('exemplaren', fn ($e) => $e->where('status', (string) $request->query('status'))))
            ->when($request->filled('letter'), fn ($q) => $q->beginletter((string) $request->query('letter')))
            ->orderBy('titel')
            ->paginate(\App\Support\Paginakeuze::aantal($request))
            ->withQueryString();

        return view('bibliotheek.publicaties.index', [
            'publicaties' => $publicaties,
            'soorten' => Publicatiesoort::actief()->geordend()->get(),
            'vakgebieden' => Vakgebied::where('actief', true)->orderBy('volgorde')->get(),
            'talen' => Taal::where('actief', true)->orderBy('naam')->get(),
            'zoek' => (string) $request->query('q', ''),
            'soortFilter' => (int) $request->query('soort', 0),
            'vakgebiedFilter' => (int) $request->query('vakgebied', 0),
            'taalFilter' => (int) $request->query('taal', 0),
            'statusFilter' => (string) $request->query('status', ''),
            'jaarFilter' => (string) $request->query('jaar', ''),
            'letterFilter' => mb_strtoupper((string) $request->query('letter', '')),
            'perPagina' => \App\Support\Paginakeuze::aantal($request),
        ]);
    }

    public function create(Request $request): View
    {
        // Vooraf gekozen soort (via de knoppen op het dashboard); anders 'boek'.
        $soort = Publicatiesoort::metCode((string) $request->query('soort', '')) ?? Publicatiesoort::metCode('boek');

        return view('bibliotheek.publicaties.form', $this->formulierData(new Publicatie([
            'soort_id' => $soort?->id,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);

        $publicatie = Publicatie::create($data['publicatie']);
        $publicatie->auteurs()->sync(Auteur::idsVoorNamen($data['auteurs']));
        $publicatie->talen()->sync($data['talen']);

        // Exemplaren die bij het aanmaken al zijn ingevoerd (serienummers).
        $this->exemplarenBijwerken($publicatie, $data['exemplaren'], $data['kast_id']);

        AuditLogger::log(AuditLogger::AANMAAK, $publicatie, veld: 'publicatie', context: [
            'titel' => $publicatie->titel,
            'soort' => $publicatie->soort?->code,
        ]);

        // Bij een tijdschrift mogen bij het aanmaken meteen een eerste uitgave en
        // haar artikelen worden opgegeven — dan hoeft de medewerker niet eerst op
        // te slaan en terug te komen.
        $aantalArtikelen = $publicatie->heeftUitgaven()
            ? $this->eersteUitgaveMetArtikelen($request, $publicatie)
            : 0;

        $melding = 'Publicatie toegevoegd.';
        if ($aantalArtikelen > 0) {
            $melding .= ' '.$aantalArtikelen.' '.($aantalArtikelen === 1 ? 'artikel' : 'artikelen').' toegevoegd.';
        }

        return redirect()->route('bibliotheek.publicaties.show', $publicatie)->with('status', $melding);
    }

    public function show(Request $request, Publicatie $publicatie): View
    {
        abort_unless($publicatie->zichtbaarVoor($request->user()), 403, 'Deze publicatie valt buiten uw toegang.');

        $publicatie->load([
            'auteurs', 'talen', 'vakgebied', 'reeks.delen',
            'exemplaren.kast',
            'exemplaren.uitleningen' => fn ($q) => $q->with(['student', 'medewerker'])->limit(5),
            'uitgaven.artikelen.auteurs',
        ]);

        return view('bibliotheek.publicaties.show', [
            'publicatie' => $publicatie,
            'kasten' => Kast::where('actief', true)->orderBy('code')->get(),
        ]);
    }

    public function edit(Request $request, Publicatie $publicatie): View
    {
        abort_unless($publicatie->beheerbaarVoor($request->user()), 403, 'U mag deze publicatie niet wijzigen.');

        $publicatie->load(['auteurs', 'talen']);

        return view('bibliotheek.publicaties.form', $this->formulierData($publicatie));
    }

    /**
     * Bijwerken. De opdracht noemt titel, auteurs, druknummer en notitie als
     * wijzigbaar; de audit-log legt per veld de oude en de nieuwe waarde vast.
     */
    public function update(Request $request, Publicatie $publicatie): RedirectResponse
    {
        abort_unless($publicatie->beheerbaarVoor($request->user()), 403, 'U mag deze publicatie niet wijzigen.');

        $data = $this->valideer($request, $publicatie);

        $oud = [
            'titel' => $publicatie->titel,
            'auteurs' => $publicatie->auteurs->pluck('naam')->implode(', '),
            'druknummer' => $publicatie->druknummer,
            'opmerking' => $publicatie->opmerking,
        ];

        $publicatie->update($data['publicatie']);
        $publicatie->auteurs()->sync(Auteur::idsVoorNamen($data['auteurs']));
        $publicatie->talen()->sync($data['talen']);

        $publicatie->load('auteurs');
        $nieuw = [
            'titel' => $publicatie->titel,
            'auteurs' => $publicatie->auteurs->pluck('naam')->implode(', '),
            'druknummer' => $publicatie->druknummer,
            'opmerking' => $publicatie->opmerking,
        ];

        // Alleen de daadwerkelijk gewijzigde velden loggen, met oude én nieuwe waarde.
        $wijzigingen = collect($nieuw)
            ->filter(fn ($waarde, $veld) => (string) $waarde !== (string) $oud[$veld])
            ->map(fn ($waarde, $veld) => ['oud' => $oud[$veld], 'nieuw' => $waarde])
            ->all();

        if ($wijzigingen !== []) {
            AuditLogger::log(AuditLogger::WIJZIGING, $publicatie, veld: 'publicatie', context: $wijzigingen);
        }

        return redirect()->route('bibliotheek.publicaties.show', $publicatie)
            ->with('status', 'Publicatie bijgewerkt.');
    }

    /**
     * Bij het aanmaken van een tijdschrift: een eerste uitgave met haar artikelen,
     * als die zijn ingevoerd. Geeft het aantal aangemaakte artikelen terug.
     * Zonder uitgavenummer gebeurt er niets — de uitgave is optioneel.
     */
    private function eersteUitgaveMetArtikelen(Request $request, Publicatie $publicatie): int
    {
        $data = $request->validate([
            'eerste_uitgavenummer' => ['nullable', 'string', 'max:40'],
            'eerste_jaar' => ['nullable', 'integer', 'min:1000', 'max:'.(date('Y') + 1)],
            'artikelen' => ['nullable', 'array'],
            'artikelen.*.titel' => ['nullable', 'string', 'max:255'],
            'artikelen.*.auteur' => ['nullable', 'string', 'max:255'],
            'artikelen.*.paginas' => ['nullable', 'string', 'max:30'],
        ]);

        $nummer = trim((string) ($data['eerste_uitgavenummer'] ?? ''));

        if ($nummer === '') {
            return 0;
        }

        $uitgave = $publicatie->uitgaven()->create([
            'uitgavenummer' => $nummer,
            'jaar' => $data['eerste_jaar'] ?? null,
        ]);

        $aantal = 0;

        foreach ($data['artikelen'] ?? [] as $rij) {
            $titel = trim((string) ($rij['titel'] ?? ''));

            if ($titel === '') {
                continue; // lege regel: overslaan
            }

            $artikel = $uitgave->artikelen()->create([
                'titel' => mb_substr($titel, 0, 255),
                'paginas' => $rij['paginas'] ?? null,
            ]);

            $auteur = trim((string) ($rij['auteur'] ?? ''));
            if ($auteur !== '') {
                $artikel->auteurs()->sync(Auteur::idsVoorNamen([$auteur]));
            }

            $aantal++;
        }

        AuditLogger::log(AuditLogger::AANMAAK, $uitgave, veld: 'tijdschriftuitgave', context: [
            'tijdschrift' => $publicatie->titel,
            'uitgavenummer' => $uitgave->uitgavenummer,
            'artikelen' => $aantal,
        ]);

        return $aantal;
    }

    /** Voeg één exemplaar toe aan een bestaande titel. */
    public function exemplaarToevoegen(Request $request, Publicatie $publicatie): RedirectResponse
    {
        abort_unless($publicatie->beheerbaarVoor($request->user()), 403, 'U mag deze publicatie niet wijzigen.');
        abort_unless($publicatie->heeftExemplaren(), 422, 'Dit soort kent geen fysieke exemplaren (bijvoorbeeld een digitaal document).');

        $data = $request->validate([
            'serienummer' => ['required', 'string', 'max:40', 'unique:bibliotheek_exemplaren,serienummer'],
            'kast_id' => ['nullable', 'integer', 'exists:bibliotheek_kasten,id'],
            'opmerking' => ['nullable', 'string', 'max:500'],
        ], [], ['serienummer' => 'serienummer', 'kast_id' => 'kast']);

        $exemplaar = $publicatie->exemplaren()->create($data + ['status' => ExemplaarStatus::Beschikbaar]);

        AuditLogger::log(AuditLogger::AANMAAK, $exemplaar, veld: 'exemplaar', context: [
            'serienummer' => $exemplaar->serienummer,
            'publicatie' => $publicatie->titel,
        ]);

        return back()->with('status', 'Exemplaar '.$exemplaar->serienummer.' toegevoegd.');
    }

    /**
     * Wijzig de status van een exemplaar (bijv. als verloren of beschadigd
     * markeren). 'Uitgeleend' kan hier niet worden gekozen: die volgt uit de
     * uitleenadministratie en zou anders uit de pas gaan lopen.
     */
    public function exemplaarStatus(Request $request, Exemplaar $exemplaar): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'status' => ['required', Rule::in([
                ExemplaarStatus::Beschikbaar->value,
                ExemplaarStatus::Gereserveerd->value,
                ExemplaarStatus::Verloren->value,
                ExemplaarStatus::Beschadigd->value,
            ])],
        ]);

        if ($exemplaar->lopendeUitlening() !== null) {
            return back()->with('fout', 'Dit exemplaar is uitgeleend; neem het eerst in.');
        }

        $exemplaar->update(['status' => $data['status']]);

        AuditLogger::log(AuditLogger::WIJZIGING, $exemplaar, veld: 'exemplaar_status', context: [
            'serienummer' => $exemplaar->serienummer,
            'nieuw' => $data['status'],
        ]);

        return back()->with('status', 'Status van exemplaar '.$exemplaar->serienummer.' bijgewerkt.');
    }

    /**
     * Validatie. Een tijdschrift en een digitaal document horen niet in een
     * boekreeks; dat wordt hier afgedwongen, niet alleen in de UI.
     *
     * @return array{publicatie: array<string,mixed>, auteurs: array<int,string>, talen: array<int,int>, exemplaren: array<int,string>, kast_id: ?int}
     */
    private function valideer(Request $request, ?Publicatie $bestaand = null): array
    {
        $soort = Publicatiesoort::find((int) $request->input('soort_id'));

        $data = $request->validate([
            'soort_id' => ['required', 'integer', 'exists:bibliotheek_soorten,id'],
            'titel' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            // De rekplaats ("F. 1070"): waar het boek fysiek ligt.
            'bron_rekcode' => ['nullable', 'string', 'max:40'],
            'uitgavejaar' => ['nullable', 'integer', 'min:1000', 'max:'.(date('Y') + 1)],
            'druknummer' => ['nullable', 'string', 'max:30'],
            'vakgebied_id' => ['nullable', 'integer', 'exists:bibliotheek_vakgebieden,id'],
            'reeks_id' => ['nullable', 'integer', 'exists:bibliotheek_reeksen,id'],
            'deelnummer' => ['nullable', 'integer', 'min:1', 'max:999'],
            'opmerking' => ['nullable', 'string', 'max:2000'],
            'auteurs' => ['nullable', 'array'],
            'auteurs.*' => ['nullable', 'string', 'max:255'],
            'talen' => ['nullable', 'array'],
            'talen.*' => ['integer', 'exists:bibliotheek_talen,id'],
            'exemplaren' => ['nullable', 'array'],
            'exemplaren.*' => ['nullable', 'string', 'max:40'],
            'kast_id' => ['nullable', 'integer', 'exists:bibliotheek_kasten,id'],
        ], [], [
            'titel' => 'titel',
            'uitgavejaar' => 'uitgavejaar',
            'vakgebied_id' => 'vakgebied',
            'reeks_id' => 'boekreeks',
            'kast_id' => 'kast',
            'bron_rekcode' => 'rek / plaats',
            'soort_id' => 'soort',
        ]);

        // Alleen een boek kan deel zijn van een boekreeks.
        if ($soort?->code !== 'boek') {
            $data['reeks_id'] = null;
            $data['deelnummer'] = null;
        }

        return [
            'publicatie' => collect($data)->only([
                'soort_id', 'titel', 'isbn', 'uitgavejaar', 'druknummer',
                'vakgebied_id', 'reeks_id', 'deelnummer', 'opmerking', 'bron_rekcode',
            ])->all(),
            'auteurs' => array_filter($data['auteurs'] ?? []),
            'talen' => array_map('intval', $data['talen'] ?? []),
            'exemplaren' => array_filter($data['exemplaren'] ?? []),
            'kast_id' => isset($data['kast_id']) ? (int) $data['kast_id'] : null,
        ];
    }

    /**
     * Maakt de bij de registratie ingevoerde exemplaren aan. Een serienummer dat
     * al bestaat wordt overgeslagen (uniek veld), zodat een dubbele invoer geen
     * foutscherm oplevert maar gewoon niets doet.
     *
     * @param  array<int,string>  $serienummers
     */
    private function exemplarenBijwerken(Publicatie $publicatie, array $serienummers, ?int $kastId): void
    {
        if (! $publicatie->heeftExemplaren()) {
            return; // Bijv. een digitaal document: geen fysieke exemplaren.
        }

        foreach ($serienummers as $serienummer) {
            $serienummer = trim((string) $serienummer);

            if ($serienummer === '' || Exemplaar::where('serienummer', $serienummer)->exists()) {
                continue;
            }

            $publicatie->exemplaren()->create([
                'serienummer' => $serienummer,
                'kast_id' => $kastId,
                'status' => ExemplaarStatus::Beschikbaar,
            ]);
        }
    }

    /** @return array<string,mixed> */
    private function formulierData(Publicatie $publicatie): array
    {
        return [
            'publicatie' => $publicatie,
            'soorten' => Publicatiesoort::actief()->geordend()->get(),
            'vakgebieden' => Vakgebied::where('actief', true)->orderBy('volgorde')->get(),
            'talen' => Taal::where('actief', true)->orderBy('naam')->get(),
            'kasten' => Kast::where('actief', true)->orderBy('code')->get(),
            'reeksen' => Reeks::orderBy('titel')->get(),
            'gekozenTalen' => $publicatie->exists ? $publicatie->talen->pluck('id')->all() : [],
            'gekozenAuteurs' => $publicatie->exists ? $publicatie->auteurs->pluck('naam')->all() : [],
        ];
    }
}
