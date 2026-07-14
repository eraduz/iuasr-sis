<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Support\AuditLogger;
use App\Support\Titelvergelijker;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dubbele tijdschriften opsporen en samenvoegen.
 *
 * HOE HET DUBBEL IS GEKOMEN. Dezelfde tijdschriftnaam komt uit twee bronnen:
 *   - de BOEKENLIJST (werkblad "C. Tijdschriften"): elke regel is een los stuk op
 *     de plank. Die titels hebben exemplaren en een rekcode, maar geen uitgaven.
 *   - de TIJDSCHRIFTINHOUD (het aparte bestand): daar staan de echte tijdschriften
 *     met hun uitgaven en artikelen, maar zonder exemplaren.
 * Ze horen bij elkaar, maar het systeem kan dat niet zelf weten: de schrijfwijzen
 * verschillen ("The Moslim world" versus "The Muslim World").
 *
 * DAAROM EEN VOORSTEL, GEEN AUTOMATISME. Dit scherm zoekt kandidaten op
 * naamgelijkenis en legt ze naast elkaar. Samenvoegen gebeurt pas als een MENS het
 * aanvinkt. Bij het samenvoegen gaat er niets verloren: de exemplaren, de rekcode,
 * de auteurs, de talen en de opmerkingen van de plankregel verhuizen mee naar het
 * tijdschrift met de uitgaven.
 */
class TijdschriftSamenvoegController extends Controller
{
    /** Vanaf deze gelijkenis tonen we een paar als voorstel. */
    private const DREMPEL = 0.80;

    public function index(Request $request): View
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $drempel = max(0.5, min(1.0, (float) $request->query('drempel', self::DREMPEL)));

        return view('bibliotheek.samenvoegen', [
            'voorstellen' => $this->voorstellen($drempel),
            'drempel' => $drempel,
            'aantalZonderUitgaven' => $this->zonderUitgaven()->count(),
            'aantalMetUitgaven' => $this->metUitgaven()->count(),
        ]);
    }

    /**
     * Voert de aangevinkte samenvoegingen uit. Elk paar: de PLANKREGEL (zonder
     * uitgaven) gaat op in het TIJDSCHRIFT (met uitgaven).
     */
    public function samenvoegen(Request $request): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'paren' => ['required', 'array', 'min:1'],
            'paren.*' => ['string'],   // "bronId:doelId"
        ], [], ['paren' => 'voorstellen']);

        $samengevoegd = 0;
        $overgeslagen = 0;

        foreach ($data['paren'] as $paar) {
            [$bronId, $doelId] = array_pad(explode(':', $paar, 2), 2, null);

            $bron = Publicatie::with(['exemplaren', 'auteurs', 'talen'])->find((int) $bronId);
            $doel = Publicatie::with(['auteurs', 'talen'])->find((int) $doelId);

            if ($bron === null || $doel === null || $bron->id === $doel->id) {
                $overgeslagen++;

                continue;
            }

            // Veiligheidsslot: een titel MET uitgaven wordt nooit opgeslokt — daar
            // zouden artikelen bij verdwijnen.
            if ($bron->uitgaven()->exists()) {
                $overgeslagen++;

                continue;
            }

            DB::transaction(function () use ($bron, $doel, &$samengevoegd) {
                $oud = [
                    'bron_id' => $bron->id,
                    'bron_titel' => $bron->titel,
                    'bron_rekcode' => $bron->bron_rekcode,
                    'exemplaren' => $bron->exemplaren->count(),
                ];

                // De fysieke exemplaren verhuizen mee: die staan op de plank.
                $bron->exemplaren()->update(['publicatie_id' => $doel->id]);

                // Wat het doel nog niet heeft, neemt het over van de plankregel.
                if ($doel->bron_rekcode === null && $bron->bron_rekcode !== null) {
                    $doel->bron_rekcode = $bron->bron_rekcode;
                }

                if ($doel->vakgebied_id === null && $bron->vakgebied_id !== null) {
                    $doel->vakgebied_id = $bron->vakgebied_id;
                }

                if ($doel->uitgavejaar === null && $bron->uitgavejaar !== null) {
                    $doel->uitgavejaar = $bron->uitgavejaar;
                }

                if ($doel->isbn === null && $bron->isbn !== null) {
                    $doel->isbn = $bron->isbn;
                }

                // De opmerking van de plankregel gaat niet verloren.
                if ($bron->opmerking) {
                    $doel->opmerking = trim(($doel->opmerking ? $doel->opmerking.' | ' : '')
                        .'Samengevoegd met "'.$bron->titel.'": '.$bron->opmerking);
                }

                $doel->save();

                $doel->auteurs()->syncWithoutDetaching($bron->auteurs->pluck('id')->all());
                $doel->talen()->syncWithoutDetaching($bron->talen->pluck('id')->all());

                AuditLogger::log(AuditLogger::WIJZIGING, $doel, veld: 'tijdschrift_samengevoegd', context: [
                    'opgenomen' => $oud,
                    'doel_titel' => $doel->titel,
                ]);

                // De plankregel zelf verdwijnt; alles wat eraan hing is verhuisd.
                $bron->auteurs()->detach();
                $bron->talen()->detach();
                $bron->delete();

                $samengevoegd++;
            });
        }

        $melding = $samengevoegd.' '.($samengevoegd === 1 ? 'tijdschrift' : 'tijdschriften').' samengevoegd.';

        if ($overgeslagen > 0) {
            $melding .= ' '.$overgeslagen.' overgeslagen (die hadden zelf uitgaven).';
        }

        return back()->with('status', $melding);
    }

    /**
     * De voorstellen: elke plankregel (zonder uitgaven) naast het tijdschrift (met
     * uitgaven) dat er het meest op lijkt, mits de gelijkenis boven de drempel ligt.
     *
     * @return array<int,array<string,mixed>>
     */
    private function voorstellen(float $drempel): array
    {
        $metUitgaven = $this->metUitgaven()->get();
        $voorstellen = [];

        foreach ($this->zonderUitgaven()->with('exemplaren')->get() as $plank) {
            $beste = null;
            $besteScore = 0.0;

            foreach ($metUitgaven as $tijdschrift) {
                $score = Titelvergelijker::gelijkenis($plank->titel, $tijdschrift->titel);

                if ($score > $besteScore) {
                    $besteScore = $score;
                    $beste = $tijdschrift;
                }
            }

            if ($beste === null || $besteScore < $drempel) {
                continue;
            }

            $voorstellen[] = [
                'bron' => $plank,
                'doel' => $beste,
                'score' => $besteScore,
                'exemplaren' => $plank->exemplaren->count(),
                'uitgaven' => $beste->uitgaven_count,
            ];
        }

        usort($voorstellen, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $voorstellen;
    }

    /** Tijdschriften MET uitgaven: die hebben de artikelen (uit de inhoudsbestanden). */
    private function metUitgaven()
    {
        return Publicatie::where('soort_id', $this->tijdschriftSoortId())
            ->has('uitgaven')
            ->withCount('uitgaven')
            ->orderBy('titel');
    }

    /** Tijdschriften ZONDER uitgaven: de plankregels uit de boekenlijst. */
    private function zonderUitgaven()
    {
        return Publicatie::where('soort_id', $this->tijdschriftSoortId())
            ->doesntHave('uitgaven')
            ->orderBy('titel');
    }

    private function tijdschriftSoortId(): ?int
    {
        return Publicatiesoort::metCode('tijdschrift')?->id;
    }
}
