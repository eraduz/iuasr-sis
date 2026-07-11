<?php

namespace App\Http\Controllers\Relatie;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use App\Support\Relatierapport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dashboard en rapportages van de module Relatiebeheer & Stagebeheer. Alle
 * cijfers zijn opleidinggebonden gescoped: een relatiebeheerder/stagecoördinator/
 * directielid ziet uitsluitend de eigen opleiding(en); Bestuur en Beheer zien alles.
 */
class RelatieDashboardController extends Controller
{
    /** Opleiding-scope van de gebruiker: null = geen beperking. */
    private function scope(Request $request): ?array
    {
        return $request->user()->isRelatieBeperkt() ? $request->user()->opleidingIds()->all() : null;
    }

    public function index(Request $request): View
    {
        $oplIds = $this->scope($request);

        return view('relaties.dashboard', [
            'kpi' => Relatierapport::kerncijfers($oplIds),
            'evaluatie' => Relatierapport::evaluatie($oplIds),
            'stagesPerStatus' => Relatierapport::stagesPerStatus($oplIds),
            'organisatiesPerType' => Relatierapport::organisatiesPerType($oplIds),
            'teBeoordelen' => Relatierapport::teBeoordelen($oplIds)->orderBy('einddatum')->limit(10)->get(),
        ]);
    }

    public function rapport(Request $request): View
    {
        $oplIds = $this->scope($request);

        return view('relaties.rapport', [
            'kpi' => Relatierapport::kerncijfers($oplIds),
            'evaluatie' => Relatierapport::evaluatie($oplIds),
            'stagesPerStatus' => Relatierapport::stagesPerStatus($oplIds),
            'organisatiesPerType' => Relatierapport::organisatiesPerType($oplIds),
            'rijen' => Relatierapport::rijen($oplIds),
        ]);
    }

    /** CSV-export op organisatieniveau (gelogd). */
    public function export(Request $request): StreamedResponse
    {
        $rijen = Relatierapport::rijen($this->scope($request));

        AuditLogger::log(AuditLogger::INZAGE, 'Relatierapport', veld: 'relatierapport_export', context: ['aantal' => $rijen->count()]);

        $kolommen = ['relatienummer', 'naam', 'type', 'opleidingen', 'plaats', 'contactpersonen', 'stageplaatsen', 'lopende_stages', 'open_taken', 'actief'];

        return response()->streamDownload(function () use ($rijen, $kolommen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM voor Excel
            fputcsv($out, $kolommen, ';');
            foreach ($rijen as $rij) {
                fputcsv($out, array_map(fn ($k) => $rij[$k], $kolommen), ';');
            }
            fclose($out);
        }, 'relatierapport.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
