<?php

namespace App\Http\Controllers;

use App\Enums\Quotesoort;
use App\Models\Quote;
use App\Support\AuditLogger;
use App\Support\Quoteroulatie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zijbalk-quotes: de 99 Schone Namen van Allah en eigen spreuken.
 *
 * Beheer is voorbehouden aan de Beheerder; lezen mag iedereen die is ingelogd —
 * de quote staat immers in ieders zijbalk. Er wordt NIET ge-audit-logd: dit is
 * geen persoonsgegeven en geen gevoelige handeling, net zoals de notities en
 * taken dat niet zijn. De enige uitzondering is verwijderen, omdat een per
 * ongeluk gewiste eigen spreuk anders spoorloos is.
 */
class QuoteController extends Controller
{
    // --- Zijbalk (iedere ingelogde gebruiker) ---

    /**
     * De quote van het huidige tijdvak. De browser vraagt dit exact op de
     * wisselgrens op (zie partials/quote), zodat wie op één pagina blijft staan
     * de reeks toch ziet doorlopen — zonder te pollen.
     */
    public function huidig(): JsonResponse
    {
        $quote = Quoteroulatie::huidige();

        return response()->json([
            'slot' => Quoteroulatie::slot(),
            'volgende_over' => Quoteroulatie::secondenTotVolgende(),
            'quote' => $quote ? [
                'kop' => $quote->kop(),
                'arabisch' => $quote->arabisch,
                'betekenis' => $quote->betekenis,
                'bron' => $quote->bron,
                'afbeelding' => $quote->heeftAfbeelding() ? route('quotes.afbeelding', $quote) : null,
            ] : null,
        ]);
    }

    /**
     * Serveert de afbeelding van de private schijf. Bewust geen storage:link:
     * dan zou er user-upload in de webroot staan, en symlinks zijn op Plesk en
     * in OneDrive-mappen een bekende bron van ellende. De inhoud verandert nooit
     * (een nieuwe afbeelding krijgt een nieuwe bestandsnaam), dus mag de browser
     * hem lang bewaren.
     */
    public function afbeelding(Quote $quote): Response
    {
        abort_unless($quote->heeftAfbeelding(), 404);
        abort_unless(Storage::disk('local')->exists($quote->afbeelding_pad), 404);

        return response(Storage::disk('local')->get($quote->afbeelding_pad), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($quote->afbeelding_pad),
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    // --- Beheer (Beheerder) ---

    public function index(Request $request): View
    {
        $soort = $request->query('soort');

        $quotes = Quote::query()
            ->when($soort, fn ($q) => $q->where('soort', $soort))
            ->geordend()
            ->paginate(25)
            ->withQueryString();

        return view('quotes.index', [
            'quotes' => $quotes,
            'soort' => $soort,
            'aantalActief' => Quote::query()->actief()->count(),
            'huidige' => Quoteroulatie::huidige(),
            'intervalMinuten' => (int) config('sis.quote.interval_minuten'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);

        $quote = new Quote($this->velden($data));
        $quote->volgorde = $data['volgorde'] ?? ((int) Quote::max('volgorde') + 1);
        $quote->afbeelding_pad = $this->bewaarAfbeelding($request);
        $quote->save();

        return redirect()->route('quotes')->with('status', 'Quote toegevoegd.');
    }

    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $data = $this->valideer($request, $quote);

        $quote->fill($this->velden($data));
        $quote->volgorde = $data['volgorde'] ?? $quote->volgorde;

        if ($nieuw = $this->bewaarAfbeelding($request)) {
            $this->verwijderBestand($quote->afbeelding_pad);
            $quote->afbeelding_pad = $nieuw;
        } elseif ($request->boolean('afbeelding_verwijderen')) {
            $this->verwijderBestand($quote->afbeelding_pad);
            $quote->afbeelding_pad = null;
        }

        $quote->save();

        return redirect()->route('quotes')->with('status', 'Quote bijgewerkt.');
    }

    /** Aan/uit zetten zonder het formulier te openen — de meest gebruikte actie. */
    public function toggle(Quote $quote): RedirectResponse
    {
        $quote->update(['actief' => ! $quote->actief]);

        return back()->with('status', "\"{$this->omschrijf($quote)}\" staat nu ".($quote->actief ? 'aan' : 'uit').'.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        $omschrijving = $this->omschrijf($quote);

        // Wel loggen: een eigen spreuk is met de hand ingevoerd werk en zou
        // anders spoorloos verdwijnen. De 99 Namen staan in de seeder.
        AuditLogger::log(AuditLogger::VERWIJDERING, 'Quote', $quote->id, veld: 'quote', context: [
            'soort' => $quote->soort->value,
            'omschrijving' => $omschrijving,
        ]);

        $this->verwijderBestand($quote->afbeelding_pad);
        $quote->delete();

        return redirect()->route('quotes')->with('status', "\"{$omschrijving}\" is verwijderd.");
    }

    // --- Intern ---

    /** @return array<string, mixed> */
    private function valideer(Request $request, ?Quote $quote = null): array
    {
        return $request->validate([
            'soort' => ['required', new Enum(Quotesoort::class)],
            'titel' => ['nullable', 'string', 'max:255'],
            'arabisch' => ['nullable', 'string', 'max:255'],
            'betekenis' => ['required', 'string', 'max:2000'],
            'bron' => ['nullable', 'string', 'max:255'],
            'volgorde' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'actief' => ['sometimes', 'boolean'],
            // Alleen echte afbeeldingen: 'image' controleert de INHOUD, niet de
            // extensie. Geen svg — dat kan script bevatten en wordt hier inline
            // in de zijbalk van iedere medewerker getoond.
            'afbeelding' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.(int) config('sis.quote.max_upload_kb')],
        ]);
    }

    /** @return array<string, mixed> */
    private function velden(array $data): array
    {
        return [
            'soort' => $data['soort'],
            'titel' => $data['titel'] ?? null,
            'arabisch' => $data['arabisch'] ?? null,
            'betekenis' => $data['betekenis'],
            'bron' => $data['bron'] ?? null,
            'actief' => (bool) ($data['actief'] ?? false),
        ];
    }

    private function bewaarAfbeelding(Request $request): ?string
    {
        if (! $request->hasFile('afbeelding')) {
            return null;
        }

        // Laravel genereert zelf een willekeurige bestandsnaam met de echte
        // extensie; de originele naam van de gebruiker komt er niet in voor.
        return $request->file('afbeelding')->store('quotes', 'local');
    }

    private function verwijderBestand(?string $pad): void
    {
        if ($pad && Storage::disk('local')->exists($pad)) {
            Storage::disk('local')->delete($pad);
        }
    }

    private function omschrijf(Quote $quote): string
    {
        return $quote->titel ?: mb_strimwidth($quote->betekenis, 0, 40, '…');
    }
}
