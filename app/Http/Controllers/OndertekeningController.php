<?php

namespace App\Http\Controllers;

use App\Models\OndertekendDocument;
use App\Support\AuditLogger;
use App\Support\Documentondertekening;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archief en logregistratie van digitaal ondertekende documenten, plus de
 * publieke echtheidscontrole. Het archief is voorbehouden aan Beheerder,
 * Directie en Studentenzaken; de verificatiepagina is openbaar.
 */
class OndertekeningController extends Controller
{
    /** Archief/log van ondertekende documenten. */
    public function index(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));

        $documenten = OndertekendDocument::with(['student', 'uitgegevenDoor'])
            ->when($zoek !== '', function ($q) use ($zoek) {
                $q->where('code', 'like', '%'.$zoek.'%')
                    ->orWhere('titel', 'like', '%'.$zoek.'%')
                    ->orWhere('ontvanger', 'like', '%'.$zoek.'%');
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('ondertekening.index', compact('documenten', 'zoek'));
    }

    /** Gearchiveerd ondertekend document opnieuw downloaden (gelogd). */
    public function download(OndertekendDocument $document): StreamedResponse
    {
        $bytes = Documentondertekening::pdfBytes($document);
        abort_if($bytes === null, 404, 'Bestand niet gevonden.');

        AuditLogger::log(AuditLogger::INZAGE, $document->student ?? $document, veld: 'ondertekend_document', context: [
            'code' => $document->code,
        ]);

        return response()->streamDownload(fn () => print($bytes), $document->bestandsnaam, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Publieke echtheidscontrole. Op verificatiecode wordt de metadata getoond;
     * optioneel kan de ontvanger de PDF uploaden om te controleren of deze
     * ongewijzigd is (SHA-256-vergelijking).
     */
    public function verificatie(Request $request): View
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        $document = $code !== '' ? OndertekendDocument::with('uitgegevenDoor')->where('code', $code)->first() : null;

        $bestandStatus = null;
        if ($document && $request->hasFile('bestand')) {
            $bytes = (string) file_get_contents($request->file('bestand')->getRealPath());
            $bestandStatus = Documentondertekening::isOngewijzigd($document, $bytes) ? 'ongewijzigd' : 'gewijzigd';
        }

        return view('ondertekening.verificatie', compact('code', 'document', 'bestandStatus'));
    }
}
