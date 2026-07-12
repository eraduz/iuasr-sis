<?php

namespace App\Http\Controllers;

use App\Support\CsvLezer;
use App\Support\MigratieImport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * TIJDELIJK migratiescherm (Studentenzaken/Beheer) voor het overzetten van de
 * oude Access-database naar het SIS via de per-jaar geëxporteerde CSV's. Werkt
 * altijd met een DRY-RUN (preview) vóór het echt importeren.
 *
 * Fase 1: studenten (studentnummer + persoonsgegevens). Cijfers/inschrijvingen
 * volgen in een aparte, gevalideerde stap.
 */
class MigratieController extends Controller
{
    public function index(): View
    {
        return view('migratie.index', ['rapport' => null, 'modus' => null, 'type' => null]);
    }

    public function verwerk(Request $request, MigratieImport $import): View
    {
        $request->validate([
            'bestand' => ['required', 'file', 'max:20480'],
            'type' => ['required', 'in:studenten'],
            'modus' => ['required', 'in:preview,import'],
        ]);

        $pad = $request->file('bestand')->getRealPath();
        $rijen = CsvLezer::associatief($pad);
        $dryRun = $request->input('modus') === 'preview';

        $rapport = $import->verwerkStudenten($rijen, $dryRun);

        return view('migratie.index', [
            'rapport' => $rapport,
            'modus' => $request->input('modus'),
            'type' => $request->input('type'),
            'bestandsnaam' => $request->file('bestand')->getClientOriginalName(),
        ]);
    }
}
