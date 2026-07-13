<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Kast;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Taal;
use App\Models\Bibliotheek\Vakgebied;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * De opzoektabellen van de bibliotheek: SOORTEN, TALEN, VAKGEBIEDEN en KASTEN.
 * Hier voegt de bibliotheekmedewerker zelf waarden toe — een cd, een dvd, een
 * nieuwe taal, een nieuw vakgebied of een nieuwe kast — zonder programmeur.
 *
 * De SOORT is bijzonder: die draagt twee vlaggen die het gedrag van het systeem
 * bepalen, en dus geen cosmetiek zijn:
 *
 *   - heeft exemplaren : fysieke exemplaren die uitgeleend worden (boek, cd, dvd);
 *                        een digitaal document niet.
 *   - heeft uitgaven   : afleveringen met artikelen (tijdschrift).
 *
 * VERWIJDEREN kan alleen als er niets aan hangt. Een soort met titels, een taal
 * die aan een titel is gekoppeld, een kast met exemplaren: die blijven staan.
 * Wilt u zo'n waarde uit de keuzelijsten halen, zet hem dan op INACTIEF — dan
 * verdwijnt hij uit de formulieren maar blijft de historie kloppen.
 */
class OpzoektabelController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        return view('bibliotheek.opzoektabellen', [
            'soorten' => Publicatiesoort::geordend()->withCount('publicaties')->get(),
            'talen' => Taal::orderBy('naam')->get(),
            'vakgebieden' => Vakgebied::orderBy('volgorde')->orderBy('naam')->withCount('publicaties')->get(),
            'kasten' => Kast::orderBy('code')->withCount('exemplaren')->get(),
        ]);
    }

    /* --------------------------------------------------------------------
     | Soorten
     |------------------------------------------------------------------- */

    public function soortStore(Request $request): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('bibliotheek_soorten', 'code')],
            'naam' => ['required', 'string', 'max:255'],
            'volgorde' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['code' => 'code', 'naam' => 'naam']);

        $soort = Publicatiesoort::create([
            'code' => mb_strtolower($data['code']),
            'naam' => $data['naam'],
            'heeft_exemplaren' => $request->boolean('heeft_exemplaren'),
            'heeft_uitgaven' => $request->boolean('heeft_uitgaven'),
            'actief' => true,
            'volgorde' => $data['volgorde'] ?? 99,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $soort, veld: 'bibliotheek_soort', context: [
            'code' => $soort->code,
            'heeft_exemplaren' => $soort->heeft_exemplaren,
            'heeft_uitgaven' => $soort->heeft_uitgaven,
        ]);

        return back()->with('status', 'Soort "'.$soort->naam.'" toegevoegd.');
    }

    public function soortUpdate(Request $request, Publicatiesoort $soort): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'volgorde' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['naam' => 'naam']);

        $oud = $soort->only(['naam', 'heeft_exemplaren', 'heeft_uitgaven', 'actief']);

        $soort->update([
            'naam' => $data['naam'],
            'heeft_exemplaren' => $request->boolean('heeft_exemplaren'),
            'heeft_uitgaven' => $request->boolean('heeft_uitgaven'),
            'actief' => $request->boolean('actief'),
            'volgorde' => $data['volgorde'] ?? $soort->volgorde,
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $soort, veld: 'bibliotheek_soort', context: [
            'oud' => $oud,
            'nieuw' => $soort->only(['naam', 'heeft_exemplaren', 'heeft_uitgaven', 'actief']),
        ]);

        return back()->with('status', 'Soort "'.$soort->naam.'" bijgewerkt.');
    }

    public function soortDestroy(Request $request, Publicatiesoort $soort): RedirectResponse
    {
        return $this->verwijder($request, $soort, $soort->verwijderbaar(),
            'Er hangen nog titels aan de soort "'.$soort->naam.'". Zet hem op inactief in plaats van hem te verwijderen.',
            'bibliotheek_soort');
    }

    /* --------------------------------------------------------------------
     | Talen
     |------------------------------------------------------------------- */

    public function taalStore(Request $request): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:5', 'alpha', Rule::unique('bibliotheek_talen', 'code')],
            'naam' => ['required', 'string', 'max:255'],
        ], [], ['code' => 'taalcode', 'naam' => 'naam']);

        $taal = Taal::create([
            'code' => mb_strtolower($data['code']),
            'naam' => $data['naam'],
            'actief' => true,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $taal, veld: 'bibliotheek_taal', context: ['code' => $taal->code]);

        return back()->with('status', 'Taal "'.$taal->naam.'" toegevoegd.');
    }

    public function taalDestroy(Request $request, Taal $taal): RedirectResponse
    {
        $inGebruik = \Illuminate\Support\Facades\DB::table('bibliotheek_publicatie_taal')
            ->where('taal_id', $taal->id)->exists();

        return $this->verwijder($request, $taal, ! $inGebruik,
            'De taal "'.$taal->naam.'" is aan titels gekoppeld en kan niet worden verwijderd.',
            'bibliotheek_taal');
    }

    /* --------------------------------------------------------------------
     | Vakgebieden
     |------------------------------------------------------------------- */

    public function vakgebiedStore(Request $request): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string', 'max:255'],
            'rekletter' => ['nullable', 'string', 'size:1', 'alpha', Rule::unique('bibliotheek_vakgebieden', 'rekletter')],
            'volgorde' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['naam' => 'naam', 'rekletter' => 'rekletter']);

        $vakgebied = Vakgebied::create([
            'naam' => $data['naam'],
            'omschrijving' => $data['omschrijving'] ?? null,
            'rekletter' => isset($data['rekletter']) ? mb_strtoupper($data['rekletter']) : null,
            'actief' => true,
            'volgorde' => $data['volgorde'] ?? 99,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $vakgebied, veld: 'bibliotheek_vakgebied', context: ['naam' => $vakgebied->naam]);

        return back()->with('status', 'Vakgebied "'.$vakgebied->naam.'" toegevoegd.');
    }

    public function vakgebiedDestroy(Request $request, Vakgebied $vakgebied): RedirectResponse
    {
        return $this->verwijder($request, $vakgebied, $vakgebied->publicaties()->doesntExist(),
            'Er hangen nog titels aan het vakgebied "'.$vakgebied->naam.'".',
            'bibliotheek_vakgebied');
    }

    /* --------------------------------------------------------------------
     | Kasten
     |------------------------------------------------------------------- */

    public function kastStore(Request $request): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('bibliotheek_kasten', 'code')],
            'omschrijving' => ['nullable', 'string', 'max:255'],
        ], [], ['code' => 'kastcode']);

        $kast = Kast::create($data + ['actief' => true]);

        AuditLogger::log(AuditLogger::AANMAAK, $kast, veld: 'bibliotheek_kast', context: ['code' => $kast->code]);

        return back()->with('status', 'Kast "'.$kast->code.'" toegevoegd.');
    }

    public function kastDestroy(Request $request, Kast $kast): RedirectResponse
    {
        return $this->verwijder($request, $kast, $kast->exemplaren()->doesntExist(),
            'Er staan nog exemplaren in kast "'.$kast->code.'".',
            'bibliotheek_kast');
    }

    /**
     * Verwijderen met één regel: alleen als er niets aan hangt. Anders een nette
     * melding — nooit stilzwijgend gegevens weggooien of losmaken.
     */
    private function verwijder(Request $request, Model $rij, bool $mag, string $melding, string $veld): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        if (! $mag) {
            return back()->with('fout', $melding);
        }

        AuditLogger::log(AuditLogger::VERWIJDERING, $rij, veld: $veld);
        $rij->delete();

        return back()->with('status', 'Verwijderd.');
    }
}
