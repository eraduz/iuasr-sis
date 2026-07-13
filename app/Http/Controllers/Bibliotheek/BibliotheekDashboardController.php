<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Http\Controllers\Controller;
use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Publicatiesoort;
use App\Models\Bibliotheek\Uitlening;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard en rapportages van de bibliotheek.
 *
 * Alle waarschuwingen zijn afleidingen uit de datums (te laat, binnenkort
 * retour); er wordt niets als vlag opgeslagen. De rapporten volgen de opdracht:
 * algemene lijst, per vakgebied, per auteur, per uitgavejaar, en meest
 * uitgeleend (met aantal uitleningen en laatste uitleendatum).
 */
class BibliotheekDashboardController extends Controller
{
    public function dashboard(Request $request): View
    {
        $venster = (int) config('sis.bibliotheek.herinnering_dagen_vooraf', 3);

        return view('bibliotheek.dashboard', [
            // Per SOORT geteld, uit de opzoektabel — zo verschijnt een nieuwe soort
            // (cd, dvd, ...) vanzelf op het dashboard, zonder codewijziging.
            'perSoort' => Publicatiesoort::actief()->geordend()->withCount('publicaties')->get(),
            'kpi' => [
                'uitgeleend' => Uitlening::lopend()->count(),
                'telaat' => Uitlening::teLaat()->count(),
                'vandaag_uit' => Uitlening::whereDate('uitgeleend_op', Carbon::today())->count(),
                'vandaag_retour' => Uitlening::whereDate('retour_op', Carbon::today())->count(),
            ],
            // Actieve waarschuwingen: te laat, en wat binnen het venster terug moet.
            'telaat' => Uitlening::teLaat()->with(['exemplaar.publicatie', 'student', 'medewerker'])
                ->orderBy('verwachte_retour_op')->get(),
            'binnenkort' => Uitlening::binnenkortRetour($venster)->with(['exemplaar.publicatie', 'student', 'medewerker'])
                ->orderBy('verwachte_retour_op')->get(),
            'venster' => $venster,
            'perMaand' => $this->uitleningenPerMaand(),
            'populair' => $this->populaireTitels(5),
            'perVakgebied' => $this->uitleningenPerVakgebied(),
            'perTaal' => $this->uitleningenPerTaal(),
        ]);
    }

    /** Rapportagescherm met alle overzichten uit de opdracht. */
    public function rapport(Request $request): View
    {
        return view('bibliotheek.rapport', [
            'perVakgebied' => $this->aantalPerVakgebied(),
            'perJaar' => $this->aantalPerJaar(),
            'perAuteur' => $this->aantalPerAuteur(),
            'populair' => $this->populaireTitels(25),
        ]);
    }

    /** De algemene bibliotheeklijst als CSV (respecteert de filters van de catalogus). */
    public function export(Request $request): StreamedResponse
    {
        $rijen = Publicatie::query()
            ->with(['auteurs', 'talen', 'vakgebied', 'exemplaren'])
            ->when($request->filled('q'), fn ($q) => $q->zoek((string) $request->query('q')))
            ->when($request->filled('soort'), fn ($q) => $q->where('soort_id', (int) $request->query('soort')))
            ->when($request->filled('vakgebied'), fn ($q) => $q->where('vakgebied_id', (int) $request->query('vakgebied')))
            ->when($request->filled('jaar'), fn ($q) => $q->where('uitgavejaar', (int) $request->query('jaar')))
            ->when($request->filled('taal'), fn ($q) => $q->whereHas('talen', fn ($t) => $t->where('bibliotheek_talen.id', (int) $request->query('taal'))))
            ->orderBy('titel')
            ->get();

        AuditLogger::log(AuditLogger::INZAGE, 'Bibliotheeklijst', veld: 'bibliotheek_export', context: ['aantal' => $rijen->count()]);

        $kolommen = ['Rek', 'Soort', 'ISBN', 'Titel', 'Auteur(s)', 'Talen', 'Uitgavejaar', 'Druk', 'Vakgebied', 'Exemplaren', 'Beschikbaar'];

        return response()->streamDownload(function () use ($rijen, $kolommen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $kolommen, ';');

            foreach ($rijen as $p) {
                fputcsv($out, [
                    $p->rekplaats() ?? '',
                    $p->soort->label(),
                    $p->isbn ?? '',
                    $p->volledigeTitel(),
                    $p->auteursTekst(),
                    $p->talenTekst(),
                    $p->uitgavejaar ?? '',
                    $p->druknummer ?? '',
                    $p->vakgebied?->naam ?? '',
                    $p->exemplaren->count(),
                    $p->aantalBeschikbaar(),
                ], ';');
            }

            fclose($out);
        }, 'bibliotheeklijst.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* --------------------------------------------------------------------
     | Statistiek
     |------------------------------------------------------------------- */

    /** @return array<int,array{label:string,value:int}> */
    private function uitleningenPerMaand(): array
    {
        $vanaf = Carbon::today()->subMonths(11)->startOfMonth();

        $rijen = Uitlening::query()
            ->where('uitgeleend_op', '>=', $vanaf)
            ->selectRaw("DATE_FORMAT(uitgeleend_op, '%Y-%m') as maand, count(*) as aantal")
            ->groupBy('maand')
            ->pluck('aantal', 'maand');

        $reeks = [];
        for ($i = 0; $i < 12; $i++) {
            $maand = $vanaf->copy()->addMonths($i);
            $reeks[] = [
                'label' => $maand->translatedFormat('M y'),
                'value' => (int) ($rijen[$maand->format('Y-m')] ?? 0),
            ];
        }

        return $reeks;
    }

    /**
     * Meest uitgeleende titels: aantal uitleningen én de laatste uitleendatum,
     * gerangschikt op populariteit (opdracht §4).
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    private function populaireTitels(int $limiet)
    {
        return DB::table('bibliotheek_uitleningen as u')
            ->join('bibliotheek_exemplaren as e', 'e.id', '=', 'u.exemplaar_id')
            ->join('bibliotheek_publicaties as p', 'p.id', '=', 'e.publicatie_id')
            ->groupBy('p.id', 'p.titel')
            ->select('p.id', 'p.titel', DB::raw('count(*) as aantal'), DB::raw('max(u.uitgeleend_op) as laatste'))
            ->orderByDesc('aantal')
            ->orderBy('p.titel')
            ->limit($limiet)
            ->get();
    }

    /** @return array<int,array{label:string,value:int}> */
    private function uitleningenPerVakgebied(): array
    {
        return DB::table('bibliotheek_uitleningen as u')
            ->join('bibliotheek_exemplaren as e', 'e.id', '=', 'u.exemplaar_id')
            ->join('bibliotheek_publicaties as p', 'p.id', '=', 'e.publicatie_id')
            ->leftJoin('bibliotheek_vakgebieden as v', 'v.id', '=', 'p.vakgebied_id')
            ->groupBy('v.naam')
            ->select(DB::raw("coalesce(v.naam, 'Onbekend') as label"), DB::raw('count(*) as value'))
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])
            ->all();
    }

    /** @return array<int,array{label:string,value:int}> */
    private function uitleningenPerTaal(): array
    {
        return DB::table('bibliotheek_uitleningen as u')
            ->join('bibliotheek_exemplaren as e', 'e.id', '=', 'u.exemplaar_id')
            ->join('bibliotheek_publicatie_taal as pt', 'pt.publicatie_id', '=', 'e.publicatie_id')
            ->join('bibliotheek_talen as t', 't.id', '=', 'pt.taal_id')
            ->groupBy('t.naam')
            ->select('t.naam as label', DB::raw('count(*) as value'))
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])
            ->all();
    }

    /** @return array<int,array{label:string,value:int}> */
    private function aantalPerVakgebied(): array
    {
        return DB::table('bibliotheek_publicaties as p')
            ->leftJoin('bibliotheek_vakgebieden as v', 'v.id', '=', 'p.vakgebied_id')
            ->groupBy('v.naam')
            ->select(DB::raw("coalesce(v.naam, 'Onbekend') as label"), DB::raw('count(*) as value'))
            ->orderByDesc('value')
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])
            ->all();
    }

    /** @return array<int,array{label:string,value:int}> */
    private function aantalPerJaar(): array
    {
        return DB::table('bibliotheek_publicaties')
            ->whereNotNull('uitgavejaar')
            ->groupBy('uitgavejaar')
            ->select('uitgavejaar as label', DB::raw('count(*) as value'))
            ->orderByDesc('uitgavejaar')
            ->limit(20)
            ->get()
            ->map(fn ($r) => ['label' => (string) $r->label, 'value' => (int) $r->value])
            ->all();
    }

    /** @return array<int,array{label:string,value:int}> */
    private function aantalPerAuteur(): array
    {
        return DB::table('bibliotheek_publicatie_auteur as pa')
            ->join('bibliotheek_auteurs as a', 'a.id', '=', 'pa.auteur_id')
            ->groupBy('a.naam')
            ->select('a.naam as label', DB::raw('count(*) as value'))
            ->orderByDesc('value')
            ->orderBy('a.naam')
            ->limit(25)
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])
            ->all();
    }
}
