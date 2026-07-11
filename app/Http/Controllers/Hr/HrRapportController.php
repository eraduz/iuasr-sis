<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Afdeling;
use App\Models\Functie;
use App\Models\Medewerker;
use App\Support\AuditLogger;
use App\Support\HrRapport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HR-rapportages en organisatiestructuur (module HR). Gescoped: een Manager ziet
 * uitsluitend het eigen team, HR/Beheer/Bestuur zien iedereen.
 */
class HrRapportController extends Controller
{
    /** Medewerker-ids binnen de scope, of null (geen beperking). */
    private function scope(Request $request): ?array
    {
        if (! $request->user()->isHrTeamBeperkt()) {
            return null;
        }

        return Medewerker::query()->zichtbaarVoor($request->user())->pluck('id')->all();
    }

    public function rapport(Request $request): View
    {
        $ids = $this->scope($request);

        return view('hr.rapport', [
            'kpi' => HrRapport::kerncijfers($ids),
            'perAfdeling' => HrRapport::perAfdeling($ids),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rijen = HrRapport::rijen($this->scope($request));

        AuditLogger::log(AuditLogger::INZAGE, 'HrRapport', veld: 'hr_rapport_export', context: ['aantal' => $rijen->count()]);

        $kolommen = ['personeelsnummer', 'naam', 'afdeling', 'functie', 'manager', 'fte', 'contracttype', 'status'];

        return response()->streamDownload(function () use ($rijen, $kolommen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $kolommen, ';');
            foreach ($rijen as $rij) {
                fputcsv($out, array_map(fn ($k) => $rij[$k], $kolommen), ';');
            }
            fclose($out);
        }, 'hr-rapport.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Verzuim & verlof per medewerker: een overzicht om elke medewerker te volgen
     * op ziekteverzuim en verlof (aantal ziekmeldingen, ziektedagen, verzuim%,
     * verlofrecht/opgenomen/saldo). Filterbaar op jaar en afdeling; team-gescoped.
     */
    public function verzuimVerlof(Request $request): View
    {
        $jaar = (int) $request->query('jaar', date('Y'));
        $afdelingId = $request->query('afdeling') ?: null;
        $rijen = HrRapport::perMedewerker($this->verzuimScope($request, $afdelingId), $jaar);

        return view('hr.verzuimverlof', [
            'jaar' => $jaar,
            'jaren' => range((int) date('Y'), (int) date('Y') - 3),
            'afdelingId' => $afdelingId,
            'afdelingen' => Afdeling::orderBy('naam')->get(),
            'rijen' => $rijen,
            'totalen' => [
                'medewerkers' => count($rijen),
                'ziektedagen' => array_sum(array_column($rijen, 'ziektedagen')),
                'momenteel_ziek' => count(array_filter(array_column($rijen, 'momenteel_ziek'))),
                'verlof_open' => array_sum(array_column($rijen, 'verlof_open')),
                'verzuim' => $rijen ? round(array_sum(array_column($rijen, 'verzuim')) / count($rijen), 1) : 0.0,
            ],
        ]);
    }

    public function verzuimVerlofExport(Request $request): StreamedResponse
    {
        $jaar = (int) $request->query('jaar', date('Y'));
        $afdelingId = $request->query('afdeling') ?: null;
        $rijen = HrRapport::perMedewerker($this->verzuimScope($request, $afdelingId), $jaar);

        AuditLogger::log(AuditLogger::INZAGE, 'HrRapport', veld: 'hr_verzuim_verlof_export', context: ['jaar' => $jaar, 'aantal' => count($rijen)]);

        $kolommen = ['personeelsnummer', 'naam', 'afdeling', 'status', 'ziekmeldingen', 'ziektedagen', 'verzuim_pct', 'verlof_recht_uren', 'verlof_opgenomen_uren', 'verlof_saldo_uren', 'openstaande_aanvragen'];

        return response()->streamDownload(function () use ($rijen, $kolommen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $kolommen, ';');
            foreach ($rijen as $r) {
                fputcsv($out, [
                    $r['personeelsnummer'], $r['naam'], $r['afdeling'], $r['status']?->label() ?? '',
                    $r['ziek_meldingen'], $r['ziektedagen'], number_format($r['verzuim'], 1, ',', ''),
                    number_format($r['verlof_recht'], 1, ',', ''), number_format($r['verlof_opgenomen'], 1, ',', ''),
                    number_format($r['verlof_saldo'], 1, ',', ''), $r['verlof_open'],
                ], ';');
            }
            fclose($out);
        }, "hr-verzuim-verlof-{$jaar}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Medewerker-ids binnen scope + optioneel afdelingsfilter, of null (alles). */
    private function verzuimScope(Request $request, ?string $afdelingId): ?array
    {
        $query = Medewerker::query()->zichtbaarVoor($request->user());

        if ($afdelingId !== null) {
            return $query->where('afdeling_id', $afdelingId)->pluck('id')->all();
        }

        return $request->user()->isHrTeamBeperkt() ? $query->pluck('id')->all() : null;
    }

    /** Organisatiestructuur: de afdelingenboom met manager en aantal medewerkers. */
    public function organisatie(Request $request): View
    {
        $afdelingen = Afdeling::query()
            ->with(['manager', 'bovenliggende'])
            ->withCount(['medewerkers as medewerkers_actief' => fn ($q) => $q->where('actief', true)])
            ->orderBy('naam')->get();

        // Boom opbouwen: wortels (geen bovenliggende) met hun onderliggende afdelingen.
        $perOuder = $afdelingen->groupBy('bovenliggende_afdeling_id');
        $wortels = $perOuder->get(null) ?? collect();

        // Beheergegevens (alleen relevant voor rollen met magHrBeheer): de volledige
        // lijsten + keuzelijsten om afdelingen/functies te muteren op deze pagina.
        $magBeheer = $request->user()->magHrBeheer();

        return view('hr.organisatie', [
            'wortels' => $wortels->sortBy('naam')->values(),
            'perOuder' => $perOuder,
            'magBeheer' => $magBeheer,
            'afdelingen' => $magBeheer ? $afdelingen->sortBy('naam')->values() : collect(),
            'functies' => $magBeheer ? Functie::orderBy('naam')->get() : collect(),
            'medewerkers' => $magBeheer ? Medewerker::orderBy('achternaam')->orderBy('voornaam')->get() : collect(),
            'categorieen' => Functie::CATEGORIEEN,
        ]);
    }
}
