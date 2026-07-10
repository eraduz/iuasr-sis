<?php

namespace App\Http\Controllers\Cursus;

use App\Http\Controllers\Controller;
use App\Models\Cursus;
use App\Support\AuditLogger;
use App\Support\Cursusgeldstatus;
use App\Support\Cursusrapport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cursusrapportage: inschrijvingen en cursusgelden per cursus, met totalen en
 * een verdeling naar betaalmethode. Voor de cursusdirecteur (eigen cursus[sen]),
 * de Financiële Administratie, de Beheerder en het Schoolbestuur — allen
 * alleen-lezen. De cursusdirecteur ziet uitsluitend de eigen cursus(sen).
 */
class CursusrapportController extends Controller
{
    public function index(Request $request): View
    {
        $cursussen = $this->cursussen($request);
        $rapport = Cursusrapport::voor($cursussen);

        return view('cursussen.rapport', [
            'rijen' => $rapport['rijen'],
            'totalen' => $rapport['totalen'],
            'methoden' => $rapport['methoden'],
            'inschrijvingenPerCursus' => $rapport['rijen']
                ->map(fn ($r) => ['label' => $r['cursus']->naam, 'value' => $r['inschrijvingen']])
                ->filter(fn ($r) => $r['value'] > 0)->values()->all(),
            'openstaandPerCursus' => $rapport['rijen']
                ->map(fn ($r) => ['label' => $r['cursus']->naam, 'value' => (int) round($r['openstaand'])])
                ->filter(fn ($r) => $r['value'] > 0)->values()->all(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $cursussen = $this->cursussen($request);

        AuditLogger::log(AuditLogger::INZAGE, 'Cursus', veld: 'cursusrapport_export',
            context: ['cursussen' => $cursussen->count()]);

        $bestand = 'cursusrapport-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($cursussen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cursistnummer', 'Naam', 'Cursuscode', 'Cursus', 'Inschrijvingsstatus',
                'Verschuldigd', 'Betaald', 'Openstaand', 'Betaalstatus'], ';');

            foreach ($cursussen as $cursus) {
                foreach ($cursus->inschrijvingen->sortBy(fn ($i) => $i->cursist?->achternaam) as $inschrijving) {
                    $geld = Cursusgeldstatus::voor($inschrijving);
                    fputcsv($out, [
                        $inschrijving->cursist?->cursistnummer,
                        $inschrijving->cursist?->volledigeNaam(),
                        $cursus->code,
                        $cursus->naam,
                        $inschrijving->status->label(),
                        number_format($geld['totaal'], 2, ',', ''),
                        number_format($geld['betaald'], 2, ',', ''),
                        number_format($geld['openstaand'], 2, ',', ''),
                        Cursusgeldstatus::statusLabel($geld['status']),
                    ], ';');
                }
            }
            fclose($out);
        }, $bestand, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** De voor deze gebruiker zichtbare cursussen, met inschrijvingen/cursist/betalingen. */
    private function cursussen(Request $request)
    {
        return Cursus::query()->zichtbaarVoor($request->user())
            ->with(['inschrijvingen.cursist', 'inschrijvingen.betalingen'])
            ->orderBy('naam')->get();
    }
}
