<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Resultaat;
use App\Models\Vak;
use App\Support\AuditLogger;
use App\Support\Cijferberekening;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Cijferregistratie (Fase 4). Docenten voeren cijfers in voor hun EIGEN vak;
 * examencommissie en directie hebben inzage. Studentenzaken heeft geen toegang.
 * Elke invoer/wijziging en elke inzage wordt gelogd.
 */
class CijferController extends Controller
{
    /** Docent: overzicht van de eigen vakken met cijferstatus. */
    public function mijnVakken(): View
    {
        $docentId = auth()->user()->docent_id;

        $vakken = Vak::where('docent_id', $docentId)->where('actief', true)
            ->with(['opleiding', 'toetsonderdelen'])
            ->orderBy('code')
            ->get()
            ->map(function (Vak $vak) {
                $deelnemers = $vak->deelnemers()->get();
                $metResultaat = Resultaat::whereIn('inschrijving_id', $deelnemers->pluck('id'))
                    ->distinct()->pluck('inschrijving_id')->count();

                return [
                    'vak' => $vak,
                    'aantal' => $deelnemers->count(),
                    'ingevoerd' => $metResultaat,
                    'onderdelen' => $vak->toetsonderdelen->count(),
                ];
            });

        return view('cijfers.mijn-vakken', compact('vakken'));
    }

    /** Cijferinvoer-/inzagescherm voor één vak. */
    public function invoer(Vak $vak): View
    {
        $this->autoriseerInzage($vak);
        $vak->load(['opleiding', 'toetsonderdelen', 'docent']);

        $magInvoeren = Gate::allows('cijfers-invoeren', $vak);

        // Inzage door examencommissie/directie wordt gelogd.
        if (! $magInvoeren && auth()->user()->magCijfersInzien()) {
            AuditLogger::log(AuditLogger::INZAGE, $vak, veld: 'cijfers', context: ['vak' => $vak->code]);
        }

        $deelnemers = $vak->deelnemers()->get();
        $resultaten = Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
            ->whereIn('inschrijving_id', $deelnemers->pluck('id'))
            ->get();

        $grens = Cijferberekening::voldoendeGrens($vak);

        $rijen = $deelnemers->map(function ($insch) use ($vak, $resultaten) {
            $eigen = $resultaten->where('inschrijving_id', $insch->id);
            $perOnderdeel = [];
            foreach ($vak->toetsonderdelen as $od) {
                $perOnderdeel[$od->id] = Cijferberekening::beste($eigen, $od->id);
            }
            $eersteMetPoging = $eigen->first();

            return [
                'inschrijving' => $insch,
                'student' => $insch->student,
                'resultaten' => $perOnderdeel,
                'poging' => $eersteMetPoging?->poging ?? 'tentamen',
                'vrijstelling' => (bool) $eigen->firstWhere('vrijstelling', true),
                'eind' => Cijferberekening::eindcijfer($vak, $eigen),
                'ec' => Cijferberekening::ec($vak, $eigen),
            ];
        });

        return view('cijfers.invoer', compact('vak', 'rijen', 'magInvoeren', 'grens'));
    }

    /** Cijfers opslaan (docent eigen vak, of examencommissie). */
    public function opslaan(Request $request, Vak $vak): RedirectResponse
    {
        abort_unless(Gate::allows('cijfers-invoeren', $vak), 403, 'Geen recht om cijfers voor dit vak in te voeren.');

        // Nederlandse komma-decimalen normaliseren naar punt vóór validatie.
        $cijfers = $request->input('cijfer', []);
        array_walk_recursive($cijfers, function (&$v) {
            if (is_string($v)) {
                $v = str_replace(',', '.', trim($v));
            }
        });
        $request->merge(['cijfer' => $cijfers]);

        $request->validate([
            'cijfer.*.*' => ['nullable', 'numeric', 'between:1,10'],
            'poging.*' => ['nullable', 'in:tentamen,herkansing'],
        ]);

        $vak->load('toetsonderdelen');
        $grens = Cijferberekening::voldoendeGrens($vak);
        $deelnemers = $vak->deelnemers()->get();

        $bestaand = Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
            ->whereIn('inschrijving_id', $deelnemers->pluck('id'))
            ->get()
            ->keyBy(fn ($r) => $r->inschrijving_id.'-'.$r->toetsonderdeel_id);

        foreach ($deelnemers as $insch) {
            $vrij = (bool) data_get($request->input('vrijstelling'), $insch->id);
            $poging = data_get($request->input('poging'), $insch->id, 'tentamen');
            $gewijzigd = false;

            foreach ($vak->toetsonderdelen as $od) {
                $raw = data_get($request->input('cijfer'), $insch->id.'.'.$od->id);
                $cijfer = ($raw === null || $raw === '') ? null : (float) str_replace(',', '.', (string) $raw);
                if ($vrij) {
                    $cijfer = null;
                }

                $res = $bestaand->get($insch->id.'-'.$od->id);
                if ($res === null && ! $vrij && $cijfer === null) {
                    continue; // niets in te voeren voor deze cel
                }

                $res ??= new Resultaat([
                    'inschrijving_id' => $insch->id,
                    'student_id' => $insch->student_id,
                    'toetsonderdeel_id' => $od->id,
                ]);
                $res->fill([
                    'poging' => $poging,
                    'poging_nr' => 1,
                    'vrijstelling' => $vrij,
                    'cijfer' => $cijfer,
                    'voldoende' => $vrij ? true : ($cijfer !== null ? $cijfer >= $grens : null),
                    'ingevoerd_door_id' => auth()->id(),
                    'toetsdatum' => $res->toetsdatum ?? now()->toDateString(),
                ]);

                if ($res->isDirty()) {
                    $res->save();
                    $gewijzigd = true;
                }
            }

            if ($gewijzigd) {
                AuditLogger::log(AuditLogger::WIJZIGING, $insch->student, veld: 'cijfer', context: ['vak' => $vak->code]);
            }
        }

        return redirect()->route('vakken.cijfers', $vak)->with('status', 'Cijfers opgeslagen.');
    }

    /** Examencommissie/Directie: overzicht van alle vakken met gemiddelde en geslaagd. */
    public function overzicht(): View
    {
        $vakken = Vak::where('actief', true)->with(['opleiding', 'docent', 'toetsonderdelen'])
            ->orderBy('code')->get()
            ->map(function (Vak $vak) {
                $deelnemers = $vak->deelnemers()->get();
                $resultaten = Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
                    ->whereIn('inschrijving_id', $deelnemers->pluck('id'))->get();

                $cijfers = [];
                $geslaagd = 0;
                foreach ($deelnemers as $insch) {
                    $eigen = $resultaten->where('inschrijving_id', $insch->id);
                    $eind = Cijferberekening::eindcijfer($vak, $eigen);
                    if ($eind['status'] === 'cijfer') {
                        $cijfers[] = $eind['cijfer'];
                    }
                    if ((Cijferberekening::ec($vak, $eigen) ?? 0) > 0) {
                        $geslaagd++;
                    }
                }

                return [
                    'vak' => $vak,
                    'aantal' => $deelnemers->count(),
                    'gemiddeld' => $cijfers ? round(array_sum($cijfers) / count($cijfers), 1) : null,
                    'geslaagd' => $geslaagd,
                ];
            });

        return view('cijfers.overzicht', compact('vakken'));
    }

    private function autoriseerInzage(Vak $vak): void
    {
        $user = auth()->user();

        if ($user->rol === Rol::Docent) {
            abort_unless($user->docent_id !== null && $vak->docent_id === $user->docent_id, 403,
                'U kunt alleen uw eigen vak inzien.');

            return;
        }

        abort_unless(in_array($user->rol, [Rol::Examencommissie, Rol::Directie], true), 403);
    }
}
