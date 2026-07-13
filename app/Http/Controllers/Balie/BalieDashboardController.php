<?php

namespace App\Http\Controllers\Balie;

use App\Enums\BalieRichting;
use App\Enums\BalieSoort;
use App\Http\Controllers\Controller;
use App\Models\BalieRegistratie;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Overzichtsscherm en export van de module Balie/Receptie.
 *
 * Het dashboard beantwoordt de vragen die aan een balie werkelijk spelen: wat is
 * er vandaag binnengekomen, en wie is er op dit moment in het pand (van belang
 * bij een ontruiming). De export levert het logboek als CSV voor rapportage en
 * archivering; die inzage wordt gelogd.
 */
class BalieDashboardController extends Controller
{
    public function dashboard(Request $request): View
    {
        $vandaag = BalieRegistratie::query()->whereDate('datum_tijd', today());

        return view('balie.dashboard', [
            'kpi' => [
                'telefoon_in' => (clone $vandaag)->where('soort', BalieSoort::Telefoon)->where('richting', BalieRichting::Inkomend)->count(),
                'telefoon_uit' => (clone $vandaag)->where('soort', BalieSoort::Telefoon)->where('richting', BalieRichting::Uitgaand)->count(),
                'bezoekers' => (clone $vandaag)->where('soort', BalieSoort::Bezoek)->count(),
                'post_in' => (clone $vandaag)->where('soort', BalieSoort::Post)->where('richting', BalieRichting::Inkomend)->count(),
                'post_uit' => (clone $vandaag)->where('soort', BalieSoort::Post)->where('richting', BalieRichting::Uitgaand)->count(),
            ],
            // Bezoekers die zijn aangemeld maar nog niet afgemeld.
            'aanwezig' => BalieRegistratie::query()->nogAanwezig()->with('medewerker')
                ->orderBy('datum_tijd')->get(),
            // De laatste registraties, ongeacht soort: het logboek in vogelvlucht.
            'recent' => BalieRegistratie::query()->with(['medewerker', 'geregistreerdDoor'])
                ->chronologisch()->limit(10)->get(),
        ]);
    }

    /**
     * Het logboek als CSV (puntkomma-gescheiden, met BOM zodat Excel de accenten
     * goed toont). Respecteert dezelfde filters als het logboekscherm, zodat u
     * exporteert wat u ziet.
     */
    public function export(Request $request): StreamedResponse
    {
        $rijen = BalieRegistratie::query()
            ->with(['medewerker', 'geregistreerdDoor'])
            ->when($request->filled('q'), fn ($q) => $q->zoek((string) $request->query('q')))
            ->when($request->filled('soort'), fn ($q) => $q->where('soort', (string) $request->query('soort')))
            ->when($request->filled('richting'), fn ($q) => $q->where('richting', (string) $request->query('richting')))
            ->when($request->filled('medewerker'), fn ($q) => $q->where('medewerker_id', (int) $request->query('medewerker')))
            ->when($request->filled('vanaf'), fn ($q) => $q->whereDate('datum_tijd', '>=', $request->date('vanaf')))
            ->when($request->filled('tot'), fn ($q) => $q->whereDate('datum_tijd', '<=', $request->date('tot')))
            ->chronologisch()
            ->get();

        AuditLogger::log(AuditLogger::INZAGE, 'Balielogboek', veld: 'balie_export', context: ['aantal' => $rijen->count()]);

        $kolommen = [
            'Datum en tijd', 'Soort', 'Richting', 'Onderwerp', 'Naam', 'Organisatie',
            'Telefoon', 'Bestemd voor', 'Vertrokken op', 'Toelichting', 'Geregistreerd door',
        ];

        return response()->streamDownload(function () use ($rijen, $kolommen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $kolommen, ';');

            foreach ($rijen as $r) {
                fputcsv($out, [
                    $r->datum_tijd->format('d-m-Y H:i'),
                    $r->soort->label(),
                    $r->soort->heeftRichting() ? $r->richting->label() : '',
                    $r->onderwerp ?? '',
                    $r->contact_naam,
                    $r->contact_organisatie ?? '',
                    $r->contact_telefoon ?? '',
                    $r->bestemdVoor(),
                    $r->vertrokken_op?->format('d-m-Y H:i') ?? '',
                    $r->toelichting ?? '',
                    $r->geregistreerdDoor?->naam ?? '',
                ], ';');
            }

            fclose($out);
        }, 'balielogboek.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
