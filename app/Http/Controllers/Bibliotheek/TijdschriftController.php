<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Artikel;
use App\Models\Bibliotheek\Auteur;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Uitgave;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tijdschriften: de uitgaven (afleveringen) en de artikelen daarin.
 *
 * Het tijdschrift zelf is een publicatie (soort = tijdschrift). Daaronder hangen
 * de uitgaven, en onder elke uitgave de artikelen met hun auteurs, pagina's en
 * trefwoorden. Daarmee is de kernvraag uit de opdracht te beantwoorden: in welk
 * tijdschrift staat een bepaald artikel?
 */
class TijdschriftController extends Controller
{
    /** Zoeken in artikelen: op artikeltitel, auteur, trefwoord of tijdschriftnaam. */
    public function artikelen(Request $request): View
    {
        $artikelen = Artikel::query()
            ->with(['auteurs', 'uitgave.tijdschrift'])
            ->when($request->filled('q'), fn ($q) => $q->zoek((string) $request->query('q')))
            ->when($request->filled('tijdschrift'), fn ($q) => $q->whereHas('uitgave', fn ($u) => $u->where('publicatie_id', (int) $request->query('tijdschrift'))))
            ->orderBy('titel')
            ->paginate(25)
            ->withQueryString();

        return view('bibliotheek.tijdschriften.artikelen', [
            'artikelen' => $artikelen,
            'tijdschriften' => $this->tijdschriften(),
            'zoek' => (string) $request->query('q', ''),
            'tijdschriftFilter' => (int) $request->query('tijdschrift', 0),
        ]);
    }

    /** Uitgave aanmaken bij een tijdschrift. */
    public function uitgaveStore(Request $request, Publicatie $publicatie): RedirectResponse
    {
        abort_unless($publicatie->beheerbaarVoor($request->user()), 403, 'U mag de bibliotheek niet beheren.');
        abort_unless($publicatie->heeftUitgaven(), 422, 'Alleen een soort met uitgaven (zoals een tijdschrift) kent afleveringen.');

        $data = $request->validate([
            'uitgavenummer' => ['required', 'string', 'max:40'],
            'publicatiedatum' => ['nullable', 'date'],
            'jaar' => ['nullable', 'integer', 'min:1000', 'max:'.(date('Y') + 1)],
            'locatie' => ['nullable', 'string', 'max:255'],
            'opmerking' => ['nullable', 'string', 'max:2000'],
        ], [], ['uitgavenummer' => 'uitgavenummer']);

        // Het uitgavenummer is uniek binnen één tijdschrift, niet daarbuiten.
        if ($publicatie->uitgaven()->where('uitgavenummer', $data['uitgavenummer'])->exists()) {
            return back()->withInput()->with('fout', 'Dit uitgavenummer bestaat al voor dit tijdschrift.');
        }

        $uitgave = $publicatie->uitgaven()->create($data);

        AuditLogger::log(AuditLogger::AANMAAK, $uitgave, veld: 'tijdschriftuitgave', context: [
            'tijdschrift' => $publicatie->titel,
            'uitgavenummer' => $uitgave->uitgavenummer,
        ]);

        return redirect()->route('bibliotheek.uitgaven.show', $uitgave)
            ->with('status', 'Uitgave '.$uitgave->uitgavenummer.' toegevoegd.');
    }

    public function uitgaveShow(Request $request, Uitgave $uitgave): View
    {
        abort_unless($request->user()->magBibliotheekInzien(), 403, 'Geen toegang tot de bibliotheek.');

        $uitgave->load(['tijdschrift', 'artikelen.auteurs']);

        return view('bibliotheek.tijdschriften.uitgave', ['uitgave' => $uitgave]);
    }

    /**
     * Artikel toevoegen vanaf de TIJDSCHRIFTPAGINA. Een artikel hoort bij een
     * uitgave; hier kiest de medewerker een bestaande uitgave óf voert een nieuw
     * uitgavenummer in — dan wordt de uitgave meteen aangemaakt. Zo hoeft hij niet
     * eerst naar de uitgavepagina door te klikken.
     */
    public function artikelSnelStore(Request $request, Publicatie $publicatie): RedirectResponse
    {
        abort_unless($publicatie->beheerbaarVoor($request->user()), 403, 'U mag de bibliotheek niet beheren.');
        abort_unless($publicatie->heeftUitgaven(), 422, 'Dit soort kent geen uitgaven met artikelen.');

        $data = $request->validate([
            'uitgave_id' => ['nullable', 'integer', 'exists:bibliotheek_uitgaven,id'],
            'nieuw_uitgavenummer' => ['nullable', 'string', 'max:40'],
            'nieuw_jaar' => ['nullable', 'integer', 'min:1000', 'max:'.(date('Y') + 1)],
            'titel' => ['required', 'string', 'max:255'],
            'paginas' => ['nullable', 'string', 'max:30'],
            'trefwoorden' => ['nullable', 'string', 'max:255'],
            'beschrijving' => ['nullable', 'string', 'max:2000'],
            'auteurs' => ['nullable', 'array'],
            'auteurs.*' => ['nullable', 'string', 'max:255'],
        ], [], ['titel' => 'artikeltitel', 'paginas' => "pagina's", 'uitgave_id' => 'uitgave']);

        // Welke uitgave? Een gekozen bestaande, of een nieuw ingevoerd nummer.
        $uitgave = $this->kiesOfMaakUitgave($publicatie, $data);

        if ($uitgave === null) {
            return back()->withInput()->with('fout', 'Kies een bestaande uitgave of vul een nieuw uitgavenummer in.');
        }

        $this->maakArtikel($uitgave, $data);

        return redirect()->route('bibliotheek.publicaties.show', $publicatie)
            ->with('status', 'Artikel toegevoegd aan uitgave '.$uitgave->uitgavenummer.'.');
    }

    /**
     * Kiest de opgegeven bestaande uitgave, of maakt er een aan op basis van het
     * nieuwe uitgavenummer. Geeft null als er niets bruikbaars is opgegeven.
     *
     * @param  array<string,mixed>  $data
     */
    private function kiesOfMaakUitgave(Publicatie $publicatie, array $data): ?Uitgave
    {
        if (! empty($data['uitgave_id'])) {
            // Alleen een uitgave van DIT tijdschrift; nooit die van een ander.
            return $publicatie->uitgaven()->find($data['uitgave_id']);
        }

        $nummer = trim((string) ($data['nieuw_uitgavenummer'] ?? ''));

        if ($nummer === '') {
            return null;
        }

        // Bestaat het nummer al bij dit tijdschrift, gebruik dan die uitgave.
        return $publicatie->uitgaven()->firstOrCreate(
            ['uitgavenummer' => mb_substr($nummer, 0, 40)],
            ['jaar' => $data['nieuw_jaar'] ?? null],
        );
    }

    /**
     * Maakt één artikel aan bij een uitgave en logt dat.
     *
     * @param  array<string,mixed>  $data
     */
    private function maakArtikel(Uitgave $uitgave, array $data): Artikel
    {
        $artikel = $uitgave->artikelen()->create([
            'titel' => $data['titel'],
            'paginas' => $data['paginas'] ?? null,
            'trefwoorden' => $data['trefwoorden'] ?? null,
            'beschrijving' => $data['beschrijving'] ?? null,
        ]);

        $artikel->auteurs()->sync(Auteur::idsVoorNamen(array_filter($data['auteurs'] ?? [])));

        AuditLogger::log(AuditLogger::AANMAAK, $artikel, veld: 'tijdschriftartikel', context: [
            'uitgave' => $uitgave->omschrijving(),
            'titel' => $artikel->titel,
        ]);

        return $artikel;
    }

    /** Artikel toevoegen aan een uitgave. */
    public function artikelStore(Request $request, Uitgave $uitgave): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'titel' => ['required', 'string', 'max:255'],
            'paginas' => ['nullable', 'string', 'max:30'],
            'trefwoorden' => ['nullable', 'string', 'max:255'],
            'beschrijving' => ['nullable', 'string', 'max:2000'],
            'auteurs' => ['nullable', 'array'],
            'auteurs.*' => ['nullable', 'string', 'max:255'],
        ], [], ['titel' => 'artikeltitel', 'paginas' => "pagina's"]);

        $this->maakArtikel($uitgave, $data);

        return back()->with('status', 'Artikel toegevoegd.');
    }

    public function artikelUpdate(Request $request, Artikel $artikel): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'titel' => ['required', 'string', 'max:255'],
            'paginas' => ['nullable', 'string', 'max:30'],
            'trefwoorden' => ['nullable', 'string', 'max:255'],
            'beschrijving' => ['nullable', 'string', 'max:2000'],
            'auteurs' => ['nullable', 'array'],
            'auteurs.*' => ['nullable', 'string', 'max:255'],
        ], [], ['titel' => 'artikeltitel']);

        $oud = ['titel' => $artikel->titel, 'auteurs' => $artikel->auteurs->pluck('naam')->implode(', ')];

        $artikel->update(collect($data)->except('auteurs')->all());
        $artikel->auteurs()->sync(Auteur::idsVoorNamen(array_filter($data['auteurs'] ?? [])));

        AuditLogger::log(AuditLogger::WIJZIGING, $artikel, veld: 'tijdschriftartikel', context: [
            'oud' => $oud,
            'nieuw' => ['titel' => $artikel->titel, 'auteurs' => $artikel->fresh('auteurs')->auteurs->pluck('naam')->implode(', ')],
        ]);

        return back()->with('status', 'Artikel bijgewerkt.');
    }

    /**
     * Artikel verwijderen. Anders dan bij een publicatie mág dit hier wél: een
     * artikel is een inhoudsopgave-regel, geen bezit — een tikfout of een dubbel
     * ingelezen regel moet je gewoon kunnen weghalen. De verwijdering wordt
     * gelogd, mét de titel, zodat naspeurbaar blijft wat er weg is.
     */
    public function artikelDestroy(Request $request, Artikel $artikel): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $uitgave = $artikel->uitgave;

        AuditLogger::log(AuditLogger::VERWIJDERING, $artikel, veld: 'tijdschriftartikel', context: [
            'titel' => $artikel->titel,
            'uitgave' => $uitgave?->omschrijving(),
        ]);

        $artikel->auteurs()->detach();
        $artikel->delete();

        return back()->with('status', 'Artikel verwijderd.');
    }

    private function tijdschriften()
    {
        // Alle titels van een soort dat uitgaven kent (nu: tijdschrift).
        return Publicatie::whereHas('soort', fn ($s) => $s->where('heeft_uitgaven', true))
            ->orderBy('titel')->get();
    }
}
