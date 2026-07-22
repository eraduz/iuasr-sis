<?php

namespace App\Http\Controllers;

use App\Enums\CijferlijstStatus;
use App\Enums\Rol;
use App\Models\Cijferlijst;
use App\Models\Periode;
use App\Models\Resultaat;
use App\Models\Vak;
use App\Support\AuditLogger;
use App\Support\Cijferberekening;
use App\Support\Documentondertekening;
use App\Support\Presentiebewaking;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cijferregistratie met vaststellingsworkflow (Fase 4).
 *
 * Concept (docent voert in) → Ingediend (bij examencommissie) → Vastgesteld
 * (definitief door examencommissie). Wie wanneer mag bewerken:
 *  - Docent: eigen vak, alleen zolang de lijst 'concept' is.
 *  - Examencommissie: bij 'ingediend' (vaststellen/terugsturen) en 'vastgesteld'
 *    (gelogde correctie).
 *  - Studentenzaken: geen enkele toegang tot cijfers.
 */
class CijferController extends Controller
{
    /** Docent: overzicht van de eigen vakken met cijfer-/vaststellingsstatus. */
    public function mijnVakken(): View
    {
        $docentId = auth()->user()->docent_id;
        $periode = Periode::where('actief', true)->first();

        $vakken = Vak::where('docent_id', $docentId)->where('actief', true)
            ->with(['opleiding', 'toetsonderdelen'])->orderBy('code')->get();

        $lijsten = $periode
            ? Cijferlijst::where('periode_id', $periode->id)->whereIn('vak_id', $vakken->pluck('id'))->get()->keyBy('vak_id')
            : collect();

        $rijen = $vakken->map(function (Vak $vak) use ($lijsten) {
            $deelnemers = $vak->deelnemers()->get();
            // Alleen resultaten van DIT vak tellen (via de eigen toetsonderdelen).
            $ingevoerd = Resultaat::whereIn('inschrijving_id', $deelnemers->pluck('id'))
                ->whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
                ->distinct()->pluck('inschrijving_id')->count();

            return [
                'vak' => $vak,
                'aantal' => $deelnemers->count(),
                'ingevoerd' => $ingevoerd,
                'onderdelen' => $vak->toetsonderdelen->count(),
                'status' => $lijsten[$vak->id]?->status ?? CijferlijstStatus::Concept,
                // Presentieregistratie is voor de docent verplicht; toon de stand.
                'presentie' => Presentiebewaking::voorVak($vak)['samenvatting'],
            ];
        });

        return view('cijfers.mijn-vakken', ['vakken' => $rijen]);
    }

    /** Cijferinvoer-/inzagescherm voor één vak. */
    public function invoer(Vak $vak): View
    {
        $this->autoriseerInzage($vak);
        $vak->load(['opleiding', 'toetsonderdelen', 'docent']);

        $periode = $this->actievePeriode();
        $lijst = Cijferlijst::voor($vak, $periode);
        $magInvoeren = $this->magBewerken($vak, $lijst);

        if (! $magInvoeren && auth()->user()->magCijfersInzien()) {
            AuditLogger::log(AuditLogger::INZAGE, $vak, veld: 'cijfers', context: ['vak' => $vak->code]);
        }

        $deelnemers = $vak->deelnemers()->get();
        $resultaten = Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
            ->whereIn('inschrijving_id', $deelnemers->pluck('id'))->get();

        $grens = Cijferberekening::voldoendeGrens($vak);
        $vrijInschr = \App\Models\Vaktoewijzing::where('vak_id', $vak->id)->where('vrijgesteld', true)
            ->whereIn('inschrijving_id', $deelnemers->pluck('id'))->pluck('inschrijving_id')->flip();

        // Aanwezigheidssignalering: studiegids §2.3.3 — wie de norm niet haalt mag
        // formeel geen toets afleggen. Niet blokkerend; een waarschuwing per student
        // zodat docent én examencommissie het bij de beoordeling kunnen meewegen.
        $aanwezigheid = Presentiebewaking::voorVak($vak)['rijen']
            ->keyBy(fn ($r) => $r['inschrijving']->id);
        $ecModel = Cijferberekening::ecModel($vak);

        $rijen = $deelnemers->map(function ($insch) use ($vak, $resultaten, $vrijInschr, $aanwezigheid) {
            $eigen = $resultaten->where('inschrijving_id', $insch->id);
            $vrij = isset($vrijInschr[$insch->id]);
            $perOnderdeel = [];
            foreach ($vak->toetsonderdelen as $od) {
                $vanOd = $eigen->where('toetsonderdeel_id', $od->id);
                $perOnderdeel[$od->id] = [
                    'tentamen' => $vanOd->firstWhere('poging', 'tentamen'),
                    'herkansing' => $vanOd->firstWhere('poging', 'herkansing'),
                    'herkansing2' => $vanOd->firstWhere('poging', 'herkansing2'),
                ];
            }

            return [
                'inschrijving' => $insch,
                'student' => $insch->student,
                'resultaten' => $perOnderdeel,
                'vrijstelling' => (bool) $eigen->firstWhere('vrijstelling', true),
                'vak_vrijgesteld' => $vrij,
                'eind' => Cijferberekening::eindcijfer($vak, $eigen, $vrij),
                'ec' => Cijferberekening::ec($vak, $eigen, $vrij),
                'aanwezigheid' => $aanwezigheid[$insch->id]['status'] ?? null,
            ];
        });

        return view('cijfers.invoer', compact('vak', 'rijen', 'magInvoeren', 'grens', 'lijst', 'ecModel'));
    }

    /** Cijfers opslaan (docent bij concept, of examencommissie bij ingediend/vastgesteld). */
    public function opslaan(Request $request, Vak $vak): RedirectResponse
    {
        $periode = $this->actievePeriode();
        $lijst = Cijferlijst::voor($vak, $periode);
        abort_unless($this->magBewerken($vak, $lijst), 403, 'Deze cijferlijst mag u nu niet bewerken.');

        // Kommadecimalen normaliseren voor alle invoervelden (1e poging + 1e/2e herkansing).
        foreach (['cijfer', 'herkansing', 'herkansing2'] as $veld) {
            $waarden = $request->input($veld, []);
            array_walk_recursive($waarden, function (&$v) {
                if (is_string($v)) {
                    $v = str_replace(',', '.', trim($v));
                }
            });
            $request->merge([$veld => $waarden]);
        }

        // Invoergrenzen volgen de cijferschaal uit config (1–10, env-overschrijfbaar).
        $min = (float) config('sis.cijfers.schaal_min', 1);
        $max = (float) config('sis.cijfers.schaal_max', 10);
        $request->validate([
            'cijfer.*.*' => ['nullable', 'numeric', "between:$min,$max"],
            'herkansing.*.*' => ['nullable', 'numeric', "between:$min,$max"],
            'herkansing2.*.*' => ['nullable', 'numeric', "between:$min,$max"],
        ]);

        $vak->load('toetsonderdelen');
        $grens = Cijferberekening::voldoendeGrens($vak);
        $deelnemers = $vak->deelnemers()->get();
        $correctie = auth()->user()->heeftRol(Rol::Examencommissie) && $lijst->status === CijferlijstStatus::Vastgesteld;

        // Bestaande resultaten per (inschrijving, onderdeel, poging_nr): meerdere
        // pogingen per onderdeel zijn toegestaan (tentamen = 1, herkansing = 2).
        $bestaand = Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
            ->whereIn('inschrijving_id', $deelnemers->pluck('id'))->get()
            ->keyBy(fn ($r) => $r->inschrijving_id.'-'.$r->toetsonderdeel_id.'-'.$r->poging_nr);

        // Poging 1 = reguliere toets, 2 = herkansing, 3 = 2e herkansing (elk een aparte regel).
        $pogingen = [
            ['naam' => 'tentamen', 'nr' => 1, 'veld' => 'cijfer'],
            ['naam' => 'herkansing', 'nr' => 2, 'veld' => 'herkansing'],
            ['naam' => 'herkansing2', 'nr' => 3, 'veld' => 'herkansing2'],
        ];

        foreach ($deelnemers as $insch) {
            $vrij = (bool) data_get($request->input('vrijstelling'), $insch->id);
            $gewijzigd = false;

            foreach ($vak->toetsonderdelen as $od) {
                foreach ($pogingen as $p) {
                    $raw = data_get($request->input($p['veld']), $insch->id.'.'.$od->id);
                    $cijfer = ($raw === null || $raw === '') ? null : (float) $raw;

                    // Vrijstelling geldt voor de reguliere poging; een herkansing vervalt dan.
                    $isVrij = $vrij && $p['naam'] === 'tentamen';
                    if ($vrij) {
                        $cijfer = null;
                    }

                    $res = $bestaand->get($insch->id.'-'.$od->id.'-'.$p['nr']);
                    if ($res === null && ! $isVrij && $cijfer === null) {
                        continue; // niets in te voeren voor deze poging
                    }

                    $res ??= new Resultaat([
                        'inschrijving_id' => $insch->id,
                        'student_id' => $insch->student_id,
                        'toetsonderdeel_id' => $od->id,
                    ]);
                    $res->fill([
                        'poging' => $p['naam'],
                        'poging_nr' => $p['nr'],
                        'vrijstelling' => $isVrij,
                        'cijfer' => $cijfer,
                        'voldoende' => $isVrij ? true : ($cijfer !== null ? $cijfer >= $grens : null),
                        'ingevoerd_door_id' => auth()->id(),
                        'toetsdatum' => $res->toetsdatum ?? now()->toDateString(),
                        'definitief' => $lijst->status === CijferlijstStatus::Vastgesteld,
                    ]);

                    if ($res->isDirty()) {
                        $res->save();
                        $gewijzigd = true;
                    }
                }
            }

            if ($gewijzigd) {
                AuditLogger::log(AuditLogger::WIJZIGING, $insch->student, veld: 'cijfer',
                    context: ['vak' => $vak->code, 'correctie' => $correctie]);
            }
        }

        // Vervolgactie in dezelfde handeling: de docent kan direct indienen en de
        // examencommissie direct vaststellen. Zo gaan zojuist ingevoerde cijfers
        // nooit verloren doordat men het opslaan vergeet (de knoppen delen nu één
        // formulier met het raster).
        $na = $request->input('na_opslaan');
        $u = auth()->user();

        if ($na === 'indienen' && $u->heeftRol(Rol::Docent) && $u->docent_id === $vak->docent_id
            && $lijst->status === CijferlijstStatus::Concept) {
            $this->voerIndienenUit($lijst, $vak, $u->id);

            return redirect()->route('vakken.cijfers', $vak)
                ->with('status', 'Cijfers opgeslagen en ingediend bij de examencommissie.');
        }

        if ($na === 'vaststellen' && $u->heeftRol(Rol::Examencommissie)
            && $lijst->status === CijferlijstStatus::Ingediend) {
            $this->voerVaststellenUit($lijst, $vak);

            return redirect()->route('vakken.cijfers', $vak)
                ->with('status', 'Cijfers opgeslagen en cijferlijst vastgesteld.');
        }

        return redirect()->route('vakken.cijfers', $vak)
            ->with('status', $correctie ? 'Correctie opgeslagen en gelogd.' : 'Cijfers opgeslagen.');
    }

    /** Docent dient de cijferlijst in bij de examencommissie. */
    public function indienen(Vak $vak): RedirectResponse
    {
        $lijst = Cijferlijst::voor($vak, $this->actievePeriode());
        $u = auth()->user();
        abort_unless($u->heeftRol(Rol::Docent) && $u->docent_id === $vak->docent_id
            && $lijst->status === CijferlijstStatus::Concept, 403);

        $this->voerIndienenUit($lijst, $vak, $u->id);

        return redirect()->route('vakken.cijfers', $vak)->with('status', 'Cijferlijst ingediend bij de examencommissie.');
    }

    /** Examencommissie stelt de cijferlijst definitief vast. */
    public function vaststellen(Vak $vak): RedirectResponse
    {
        abort_unless(auth()->user()->heeftRol(Rol::Examencommissie), 403);
        $lijst = Cijferlijst::voor($vak, $this->actievePeriode());
        abort_unless($lijst->status === CijferlijstStatus::Ingediend, 403, 'Alleen een ingediende lijst kan worden vastgesteld.');

        $this->voerVaststellenUit($lijst, $vak);

        return redirect()->route('vakken.cijfers', $vak)->with('status', 'Cijferlijst vastgesteld.');
    }

    /** Zet een conceptlijst op 'ingediend' (gedeeld door de losse route en de opslaan+indienen-knop). */
    private function voerIndienenUit(Cijferlijst $lijst, Vak $vak, int $docentUserId): void
    {
        $lijst->update([
            'status' => CijferlijstStatus::Ingediend,
            'ingediend_op' => now(),
            'ingediend_door_id' => $docentUserId,
            'opmerking' => null,
        ]);
        AuditLogger::log(AuditLogger::WIJZIGING, $vak, veld: 'cijferlijst', context: ['status' => 'ingediend']);
    }

    /** Stelt een ingediende lijst vast en markeert de resultaten definitief (gedeeld). */
    private function voerVaststellenUit(Cijferlijst $lijst, Vak $vak): void
    {
        $lijst->update([
            'status' => CijferlijstStatus::Vastgesteld,
            'vastgesteld_op' => now(),
            'vastgesteld_door_id' => auth()->id(),
        ]);

        $vak->load('toetsonderdelen');
        $deelnemers = $vak->deelnemers()->get();
        Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
            ->whereIn('inschrijving_id', $deelnemers->pluck('id'))
            ->update(['definitief' => true]);

        AuditLogger::log(AuditLogger::WIJZIGING, $vak, veld: 'cijferlijst', context: ['status' => 'vastgesteld']);
    }

    /** Examencommissie stuurt een ingediende lijst terug naar de docent. */
    public function terugsturen(Request $request, Vak $vak): RedirectResponse
    {
        abort_unless(auth()->user()->heeftRol(Rol::Examencommissie), 403);
        $lijst = Cijferlijst::voor($vak, $this->actievePeriode());
        abort_unless($lijst->status === CijferlijstStatus::Ingediend, 403);

        $data = $request->validate(['opmerking' => ['nullable', 'string', 'max:500']]);
        $lijst->update([
            'status' => CijferlijstStatus::Concept,
            'ingediend_op' => null,
            'ingediend_door_id' => null,
            'opmerking' => $data['opmerking'] ?? null,
        ]);
        AuditLogger::log(AuditLogger::WIJZIGING, $vak, veld: 'cijferlijst', context: ['status' => 'teruggestuurd']);

        return redirect()->route('vakken.cijfers', $vak)->with('status', 'Cijferlijst teruggestuurd naar de docent.');
    }

    /** Read-only tentamenlijst (deelnemers + resultaten) van een vak. */
    public function tentamenlijst(Vak $vak): View
    {
        $this->autoriseerInzage($vak);
        $vak->load(['opleiding', 'toetsonderdelen', 'docent']);
        $periode = $this->actievePeriode();
        $lijst = Cijferlijst::voor($vak, $periode);

        [$rijen, $grens, $samenvatting] = $this->tentamenlijstData($vak);

        AuditLogger::log(AuditLogger::INZAGE, $vak, veld: 'tentamenlijst', context: ['vak' => $vak->code]);

        return view('cijfers.tentamenlijst', compact('vak', 'rijen', 'grens', 'samenvatting', 'lijst', 'periode'));
    }

    /** Officiële tentamenlijst als ondertekende PDF (op briefpapier). */
    public function tentamenlijstPdf(Request $request, Vak $vak): StreamedResponse
    {
        $this->autoriseerInzage($vak);
        $data = $request->validate(['ontvanger' => ['required', 'string', 'max:255']]);
        $vak->load(['opleiding', 'toetsonderdelen', 'docent']);
        $periode = $this->actievePeriode();

        [$rijen, $grens, $samenvatting] = $this->tentamenlijstData($vak);

        $html = view('pdf.tentamenlijst', [
            'vak' => $vak, 'rijen' => $rijen, 'grens' => $grens, 'samenvatting' => $samenvatting,
            'periode' => $periode, 'ondertekenaar' => auth()->user()->naam,
        ])->render();

        $doc = Documentondertekening::ondertekenHtml($html, [
            'type' => 'tentamenlijst',
            'titel' => 'Tentamenlijst '.$vak->code,
            'ontvanger' => $data['ontvanger'],
            'uitgegeven_door_id' => auth()->id(),
        ]);

        AuditLogger::log(AuditLogger::UITGIFTE, $vak, veld: 'tentamenlijst', context: [
            'code' => $doc->code, 'ontvanger' => $data['ontvanger'], 'vak' => $vak->code,
        ]);

        return response()->streamDownload(
            fn () => print(Documentondertekening::pdfBytes($doc)),
            $doc->bestandsnaam,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: float|null, 2: array} */
    private function tentamenlijstData(Vak $vak): array
    {
        $deelnemers = $vak->deelnemers()->get();
        $resultaten = Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
            ->whereIn('inschrijving_id', $deelnemers->pluck('id'))->get();
        $grens = Cijferberekening::voldoendeGrens($vak);
        $vrijInschr = \App\Models\Vaktoewijzing::where('vak_id', $vak->id)->where('vrijgesteld', true)
            ->whereIn('inschrijving_id', $deelnemers->pluck('id'))->pluck('inschrijving_id')->flip();

        $rijen = $deelnemers->map(function ($insch) use ($vak, $resultaten, $vrijInschr) {
            $eigen = $resultaten->where('inschrijving_id', $insch->id);
            $vrij = isset($vrijInschr[$insch->id]);
            $perOnderdeel = [];
            foreach ($vak->toetsonderdelen as $od) {
                $perOnderdeel[$od->id] = Cijferberekening::beste($eigen, $od->id);
            }

            return [
                'student' => $insch->student,
                'perOnderdeel' => $perOnderdeel,
                'vak_vrijgesteld' => $vrij,
                'eind' => Cijferberekening::eindcijfer($vak, $eigen, $vrij),
                'ec' => Cijferberekening::ec($vak, $eigen, $vrij),
            ];
        })->sortBy(fn ($r) => $r['student']->achternaam)->values();

        $cijfers = $rijen->map(fn ($r) => $r['eind'])->filter(fn ($e) => $e['status'] === 'cijfer')->map(fn ($e) => $e['cijfer']);
        $samenvatting = [
            'aantal' => $rijen->count(),
            'geslaagd' => $rijen->filter(fn ($r) => ($r['ec'] ?? 0) > 0)->count(),
            'gemiddeld' => $cijfers->count() ? round($cijfers->avg(), 1) : null,
        ];

        return [$rijen, $grens, $samenvatting];
    }

    /** Examencommissie/Directie: overzicht van alle vakken met status en gemiddelde. */
    public function overzicht(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));
        $periode = Periode::where('actief', true)->first();
        $gebruiker = $request->user();
        $vakken = Vak::where('actief', true)
            ->when($gebruiker->isOpleidingBeperkt(),
                fn ($q) => $q->whereIn('opleiding_id', $gebruiker->opleidingIds()))
            ->with(['opleiding', 'docent', 'toetsonderdelen'])
            ->when($zoek !== '', function ($q) use ($zoek) {
                $q->where(function ($w) use ($zoek) {
                    $w->where('code', 'like', '%'.$zoek.'%')
                        ->orWhere('naam', 'like', '%'.$zoek.'%')
                        ->orWhereHas('docent', fn ($d) => $d->where('achternaam', 'like', '%'.$zoek.'%'));
                });
            })
            ->orderBy('code')->get();
        $lijsten = $periode
            ? Cijferlijst::where('periode_id', $periode->id)->whereIn('vak_id', $vakken->pluck('id'))->get()->keyBy('vak_id')
            : collect();

        $rijen = $vakken->map(function (Vak $vak) use ($lijsten) {
            $deelnemers = $vak->deelnemers()->get();
            $resultaten = Resultaat::whereIn('toetsonderdeel_id', $vak->toetsonderdelen->pluck('id'))
                ->whereIn('inschrijving_id', $deelnemers->pluck('id'))->get();
            $vrijInschr = \App\Models\Vaktoewijzing::where('vak_id', $vak->id)->where('vrijgesteld', true)
                ->whereIn('inschrijving_id', $deelnemers->pluck('id'))->pluck('inschrijving_id')->flip();

            $cijfers = [];
            $geslaagd = 0;
            foreach ($deelnemers as $insch) {
                $eigen = $resultaten->where('inschrijving_id', $insch->id);
                $vrij = isset($vrijInschr[$insch->id]);
                $eind = Cijferberekening::eindcijfer($vak, $eigen, $vrij);
                if ($eind['status'] === 'cijfer') {
                    $cijfers[] = $eind['cijfer'];
                }
                if ((Cijferberekening::ec($vak, $eigen, $vrij) ?? 0) > 0) {
                    $geslaagd++;
                }
            }

            return [
                'vak' => $vak,
                'aantal' => $deelnemers->count(),
                'gemiddeld' => $cijfers ? round(array_sum($cijfers) / count($cijfers), 1) : null,
                'geslaagd' => $geslaagd,
                'status' => $lijsten[$vak->id]?->status ?? CijferlijstStatus::Concept,
            ];
        });

        $terVaststelling = $rijen->filter(fn ($r) => $r['status'] === CijferlijstStatus::Ingediend)->count();

        return view('cijfers.overzicht', ['vakken' => $rijen, 'terVaststelling' => $terVaststelling, 'zoek' => $zoek]);
    }

    private function actievePeriode(): Periode
    {
        return Periode::where('actief', true)->firstOrFail();
    }

    private function magBewerken(Vak $vak, Cijferlijst $lijst): bool
    {
        $u = auth()->user();

        // Docent van het eigen vak mag bewerken zolang de lijst 'concept' is.
        if ($u->heeftRol(Rol::Docent) && $u->docent_id !== null && $vak->docent_id === $u->docent_id
            && $lijst->status === CijferlijstStatus::Concept) {
            return true;
        }

        // Examencommissie mag bij een ingediende (vaststellen) of vastgestelde (correctie) lijst.
        if ($u->heeftRol(Rol::Examencommissie)) {
            return in_array($lijst->status, [CijferlijstStatus::Ingediend, CijferlijstStatus::Vastgesteld], true);
        }

        return false;
    }

    private function autoriseerInzage(Vak $vak): void
    {
        $user = auth()->user();

        // Docent van het eigen vak: altijd inzage in dat vak.
        if ($user->heeftRol(Rol::Docent) && $user->docent_id !== null && $vak->docent_id === $user->docent_id) {
            return;
        }

        abort_unless($user->heeftRol(Rol::Examencommissie) || $user->heeftRol(Rol::Directie), 403,
            'U kunt alleen uw eigen vak inzien.');

        // Directie: alleen vakken van de eigen opleiding(en).
        if ($user->isOpleidingBeperkt()) {
            abort_unless($user->opleidingIds()->contains($vak->opleiding_id), 403,
                'Dit vak valt buiten uw opleiding(en).');
        }
    }
}
