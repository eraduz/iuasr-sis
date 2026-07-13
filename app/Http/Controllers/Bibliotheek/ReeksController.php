<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Enums\ExemplaarStatus;
use App\Enums\PublicatieSoort;
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

/**
 * Boekreeksen (bv. Tafsir Ibn Kathir deel 1 t/m 4).
 *
 * Werkwijze naar het model van Koha: de reeks is een eigen record met een
 * hoofdtitel; de delen zijn gewone publicaties die naar de reeks verwijzen en
 * een deelnummer dragen. Bij het aanmaken voert de medewerker de gedeelde
 * gegevens (auteurs, talen, vakgebied, kast) ÉÉN keer in en geeft daarnaast per
 * deel alleen het deelnummer, de ondertitel en het serienummer op — precies wat
 * de opdracht vraagt: "in één scherm meerdere delen toevoegen".
 */
class ReeksController extends Controller
{
    public function index(Request $request): View
    {
        $reeksen = Reeks::query()
            ->withCount('delen')
            ->when($request->filled('q'), fn ($q) => $q->where('titel', 'like', '%'.trim((string) $request->query('q')).'%'))
            ->orderBy('titel')
            ->paginate(25)
            ->withQueryString();

        return view('bibliotheek.reeksen.index', [
            'reeksen' => $reeksen,
            'zoek' => (string) $request->query('q', ''),
        ]);
    }

    public function create(): View
    {
        return view('bibliotheek.reeksen.form', $this->formulierData());
    }

    /**
     * Maakt de reeks én in één keer de opgegeven delen aan. Per deel wordt een
     * publicatie gemaakt met de gedeelde gegevens; de auteurs en talen worden
     * op elk deel gekoppeld, zodat de delen los vindbaar blijven.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'titel' => ['required', 'string', 'max:255'],
            'opmerking' => ['nullable', 'string', 'max:2000'],
            'vakgebied_id' => ['nullable', 'integer', 'exists:bibliotheek_vakgebieden,id'],
            'kast_id' => ['nullable', 'integer', 'exists:bibliotheek_kasten,id'],
            'uitgavejaar' => ['nullable', 'integer', 'min:1000', 'max:'.(date('Y') + 1)],
            'auteurs' => ['nullable', 'array'],
            'auteurs.*' => ['nullable', 'string', 'max:255'],
            'talen' => ['nullable', 'array'],
            'talen.*' => ['integer', 'exists:bibliotheek_talen,id'],
            'delen' => ['required', 'array', 'min:1'],
            'delen.*.deelnummer' => ['required', 'integer', 'min:1', 'max:999'],
            'delen.*.titel' => ['nullable', 'string', 'max:255'],
            'delen.*.serienummer' => ['nullable', 'string', 'max:40'],
        ], [], [
            'titel' => 'reekstitel',
            'delen' => 'delen',
            'delen.*.deelnummer' => 'deelnummer',
        ]);

        $reeks = Reeks::create([
            'titel' => $data['titel'],
            'opmerking' => $data['opmerking'] ?? null,
        ]);

        $auteurIds = Auteur::idsVoorNamen(array_filter($data['auteurs'] ?? []));
        $taalIds = array_map('intval', $data['talen'] ?? []);
        $aantal = 0;

        foreach ($data['delen'] as $deel) {
            $publicatie = Publicatie::create([
                'soort' => PublicatieSoort::Boek,
                // Zonder eigen ondertitel krijgt het deel de reekstitel; de
                // deelaanduiding komt uit volledigeTitel().
                'titel' => ($deel['titel'] ?? '') ?: $data['titel'],
                'uitgavejaar' => $data['uitgavejaar'] ?? null,
                'vakgebied_id' => $data['vakgebied_id'] ?? null,
                'reeks_id' => $reeks->id,
                'deelnummer' => (int) $deel['deelnummer'],
            ]);

            $publicatie->auteurs()->sync($auteurIds);
            $publicatie->talen()->sync($taalIds);

            $serienummer = trim((string) ($deel['serienummer'] ?? ''));
            if ($serienummer !== '' && ! Exemplaar::where('serienummer', $serienummer)->exists()) {
                $publicatie->exemplaren()->create([
                    'serienummer' => $serienummer,
                    'kast_id' => $data['kast_id'] ?? null,
                    'status' => ExemplaarStatus::Beschikbaar,
                ]);
            }

            $aantal++;
        }

        AuditLogger::log(AuditLogger::AANMAAK, $reeks, veld: 'boekreeks', context: [
            'titel' => $reeks->titel,
            'aantal_delen' => $aantal,
        ]);

        return redirect()->route('bibliotheek.reeksen.show', $reeks)
            ->with('status', 'Boekreeks aangemaakt met '.$aantal.' '.($aantal === 1 ? 'deel' : 'delen').'.');
    }

    public function show(Request $request, Reeks $reeks): View
    {
        abort_unless($request->user()->magBibliotheekInzien(), 403, 'Geen toegang tot de bibliotheek.');

        $reeks->load(['delen.exemplaren', 'delen.auteurs', 'delen.talen']);

        return view('bibliotheek.reeksen.show', ['reeks' => $reeks]);
    }

    /** Voeg later nog een deel toe aan een bestaande reeks. */
    public function deelToevoegen(Request $request, Reeks $reeks): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'deelnummer' => ['required', 'integer', 'min:1', 'max:999'],
            'titel' => ['nullable', 'string', 'max:255'],
            'serienummer' => ['nullable', 'string', 'max:40', 'unique:bibliotheek_exemplaren,serienummer'],
        ], [], ['deelnummer' => 'deelnummer', 'serienummer' => 'serienummer']);

        // Neem de gedeelde gegevens over van het eerste deel, zodat een nieuw deel
        // niet opnieuw ingevoerd hoeft te worden.
        $voorbeeld = $reeks->delen()->with(['auteurs', 'talen'])->first();

        $publicatie = Publicatie::create([
            'soort' => PublicatieSoort::Boek,
            'titel' => ($data['titel'] ?? '') ?: $reeks->titel,
            'uitgavejaar' => $voorbeeld?->uitgavejaar,
            'vakgebied_id' => $voorbeeld?->vakgebied_id,
            'reeks_id' => $reeks->id,
            'deelnummer' => (int) $data['deelnummer'],
        ]);

        if ($voorbeeld !== null) {
            $publicatie->auteurs()->sync($voorbeeld->auteurs->pluck('id')->all());
            $publicatie->talen()->sync($voorbeeld->talen->pluck('id')->all());
        }

        $serienummer = trim((string) ($data['serienummer'] ?? ''));
        if ($serienummer !== '') {
            $publicatie->exemplaren()->create([
                'serienummer' => $serienummer,
                'status' => ExemplaarStatus::Beschikbaar,
            ]);
        }

        AuditLogger::log(AuditLogger::AANMAAK, $publicatie, veld: 'boekreeks_deel', context: [
            'reeks' => $reeks->titel,
            'deelnummer' => $publicatie->deelnummer,
        ]);

        return back()->with('status', 'Deel '.$publicatie->deelnummer.' toegevoegd.');
    }

    /** @return array<string,mixed> */
    private function formulierData(): array
    {
        return [
            'vakgebieden' => Vakgebied::where('actief', true)->orderBy('volgorde')->get(),
            'talen' => Taal::where('actief', true)->orderBy('naam')->get(),
            'kasten' => Kast::where('actief', true)->orderBy('code')->get(),
        ];
    }
}
