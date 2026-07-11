<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrDocument;
use App\Models\Medewerker;
use App\Support\AuditLogger;
use App\Support\Icsagenda;
use App\Support\Verlofoverzicht;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Self-service "Mijn HR": het eigen personeelsdossier voor elke ingelogde
 * medewerker (afgeleid uit de koppeling <code>medewerkers.user_id</code>, geen
 * aparte rol). De medewerker ziet uitsluitend het <b>eigen</b> record — eigen
 * gegevens, verlofsaldo/-aanvragen, gesprekken, documenten en checklists — en kan
 * de eigen agenda (gesprekken + goedgekeurd verlof) als iCal downloaden.
 */
class MijnHrController extends Controller
{
    /** Het eigen dossier (360°, alleen-lezen). */
    public function index(Request $request): View
    {
        $medewerker = $this->eigenMedewerker($request);
        $medewerker->load(['afdeling', 'functie', 'manager', 'dienstverbanden',
            'documenten', 'checklisttaken', 'gesprekken.gespreksvoerder']);

        return view('hr.mijn', [
            'medewerker' => $medewerker,
            'huidig' => $medewerker->huidigDienstverband(),
            'saldo' => Verlofoverzicht::voor($medewerker),
            'aanvragen' => $medewerker->verlofaanvragen()->with('beoordelaar')->get(),
            'gesprekken' => $medewerker->gesprekken,
            'documenten' => $medewerker->documenten,
            'checklisttaken' => $medewerker->checklisttaken,
            'jaar' => (int) date('Y'),
        ]);
    }

    /** iCal-download van de eigen agenda (geplande gesprekken + goedgekeurd verlof). */
    public function agenda(Request $request): Response
    {
        $medewerker = $this->eigenMedewerker($request);
        $ics = Icsagenda::voor($medewerker, now()->utc()->format('Ymd\THis\Z'));

        return response($ics, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="mijn-hr-agenda.ics"',
        ]);
    }

    /** Download van een eigen HR-document (uitsluitend het eigen dossier; gelogd). */
    public function document(Request $request, HrDocument $document): StreamedResponse
    {
        $medewerker = $this->eigenMedewerker($request);
        abort_unless($document->medewerker_id === $medewerker->id, 403, 'Dit document hoort niet bij uw dossier.');
        abort_unless(Storage::disk('local')->exists($document->pad), 404, 'Bestand niet gevonden.');

        AuditLogger::log(AuditLogger::INZAGE, $document, veld: 'hr_document', context: [
            'bestand' => $document->bestandsnaam, 'zelfservice' => true,
        ]);

        return Storage::disk('local')->download($document->pad, $document->bestandsnaam);
    }

    /** Het eigen personeelsrecord, of 403 als de gebruiker er geen heeft. */
    private function eigenMedewerker(Request $request): Medewerker
    {
        $medewerker = $request->user()->medewerker;
        abort_if($medewerker === null, 403, 'Aan uw account is geen personeelsdossier gekoppeld.');

        return $medewerker;
    }
}
