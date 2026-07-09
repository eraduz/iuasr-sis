<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Periode;
use App\Models\Presentie;
use App\Models\Vak;
use App\Support\AuditLogger;
use App\Support\Presentiebewaking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Presentieregistratie per college (Fase 6).
 *
 * De docent is VERPLICHT per onderwijsweek de aanwezigheid vast te leggen:
 * 1 = aanwezig, 0 = afwezig, leeg = nog niet geregistreerd. Vrijgestelde
 * studenten volgen het vak niet en krijgen geen registratie.
 *
 * Rolscheiding: registreren doet uitsluitend de docent van het eigen vak.
 * Inzage hebben examencommissie, directie (eigen opleiding) en schoolbestuur.
 * Studentenzaken en Financiën hebben geen toegang tot presentiegegevens.
 */
class PresentieController extends Controller
{
    /** Presentielijst van één vak (registratie voor de docent, anders alleen-lezen). */
    public function lijst(Vak $vak): View
    {
        $this->autoriseerInzage($vak);
        $vak->load(['opleiding', 'docent']);

        $magRegistreren = auth()->user()->can('presentie-registreren', $vak);

        if (! $magRegistreren) {
            AuditLogger::log(AuditLogger::INZAGE, $vak, veld: 'presentie', context: ['vak' => $vak->code]);
        }

        ['rijen' => $rijen, 'samenvatting' => $samenvatting] = Presentiebewaking::voorVak($vak);

        return view('presentie.lijst', [
            'vak' => $vak,
            'rijen' => $rijen,
            'samenvatting' => $samenvatting,
            'weken' => Presentiebewaking::weken(),
            'magRegistreren' => $magRegistreren,
            'periode' => Periode::where('actief', true)->first(),
        ]);
    }

    /** Presentie opslaan. Alleen de docent van het eigen vak. */
    public function opslaan(Request $request, Vak $vak): RedirectResponse
    {
        abort_unless($request->user()->can('presentie-registreren', $vak), 403,
            'U kunt alleen de aanwezigheid van uw eigen vak registreren.');

        $request->validate([
            'presentie.*.*' => ['nullable', 'in:0,1'],
        ]);

        $weken = Presentiebewaking::weken();
        $deelnemers = $vak->deelnemers()->get();
        $ids = $deelnemers->pluck('id');
        $vrijgesteld = Presentiebewaking::vrijgesteldeInschrijvingen($vak, $ids)->flip();

        $bestaand = Presentie::where('vak_id', $vak->id)->whereIn('inschrijving_id', $ids)
            ->get()->keyBy(fn (Presentie $p) => $p->inschrijving_id.'-'.$p->week);

        $ingevoerd = $request->input('presentie', []);
        $gewijzigd = 0;

        foreach ($deelnemers as $insch) {
            // Vrijgestelde studenten: geen registratie, ook niet als de POST er een bevat.
            if (isset($vrijgesteld[$insch->id])) {
                continue;
            }

            foreach ($weken as $week) {
                $waarde = data_get($ingevoerd, $insch->id.'.'.$week);
                $regel = $bestaand->get($insch->id.'-'.$week);

                // Leeg = 'nog niet geregistreerd'. Een bestaande regel wordt dan
                // teruggenomen (de docent kan een foutieve invoer wissen).
                if ($waarde === null || $waarde === '') {
                    if ($regel) {
                        $regel->delete();
                        $gewijzigd++;
                    }

                    continue;
                }

                $aanwezig = $waarde === '1';
                $regel ??= new Presentie([
                    'inschrijving_id' => $insch->id,
                    'vak_id' => $vak->id,
                    'week' => $week,
                ]);
                $regel->fill(['aanwezig' => $aanwezig, 'geregistreerd_door_id' => auth()->id()]);

                if ($regel->isDirty(['aanwezig']) || ! $regel->exists) {
                    $regel->save();
                    $gewijzigd++;
                }
            }
        }

        if ($gewijzigd > 0) {
            AuditLogger::log(AuditLogger::WIJZIGING, $vak, veld: 'presentie',
                context: ['vak' => $vak->code, 'regels' => $gewijzigd]);
        }

        return redirect()->route('vakken.presentie', $vak)
            ->with('status', 'Aanwezigheid opgeslagen.');
    }

    /**
     * Presentieoverzicht over alle vakken — kwaliteitsbeeld voor examencommissie,
     * directie (eigen opleiding) en schoolbestuur. Toont per vak of de docent de
     * registratie bijhoudt en hoe hoog de gemiddelde aanwezigheid is.
     */
    public function overzicht(Request $request): View
    {
        $gebruiker = $request->user();
        abort_unless($gebruiker->magPresentieInzien(), 403);

        $vakken = Vak::where('actief', true)
            ->when($gebruiker->isOpleidingBeperkt(),
                fn ($q) => $q->whereIn('opleiding_id', $gebruiker->opleidingIds()))
            ->when($gebruiker->rol === Rol::Docent,
                fn ($q) => $q->where('docent_id', $gebruiker->docent_id))
            ->with(['opleiding', 'docent'])->orderBy('code')->get();

        $rijen = $vakken->map(fn (Vak $vak) => [
            'vak' => $vak,
            'samenvatting' => Presentiebewaking::voorVak($vak)['samenvatting'],
        ]);

        AuditLogger::log(AuditLogger::INZAGE, 'Presentie', veld: 'presentieoverzicht',
            context: ['vakken' => $rijen->count()]);

        return view('presentie.overzicht', [
            'rijen' => $rijen,
            'weken' => count(Presentiebewaking::weken()),
            'onvolledig' => $rijen->filter(fn ($r) => ! $r['samenvatting']['volledig'])->count(),
        ]);
    }

    /** Docent: eigen vak. Directie: eigen opleiding. Examencie/Bestuur: alles. */
    private function autoriseerInzage(Vak $vak): void
    {
        $user = auth()->user();
        abort_unless($user->magPresentieInzien(), 403);

        if ($user->rol === Rol::Docent) {
            abort_unless($user->docent_id !== null && $vak->docent_id === $user->docent_id, 403,
                'U kunt alleen de presentielijst van uw eigen vak inzien.');

            return;
        }

        if ($user->isOpleidingBeperkt()) {
            abort_unless($user->opleidingIds()->contains($vak->opleiding_id), 403,
                'Dit vak valt buiten uw opleiding(en).');
        }
    }
}
