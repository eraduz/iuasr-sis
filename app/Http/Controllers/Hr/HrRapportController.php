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
