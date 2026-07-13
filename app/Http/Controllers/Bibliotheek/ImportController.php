<?php

namespace App\Http\Controllers\Bibliotheek;

use App\Http\Controllers\Controller;
use App\Support\AuditLogger;
use App\Support\BibliotheekImport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Importwizard voor de bestaande Excel-bibliotheek.
 *
 * Twee stappen, bewust in deze volgorde: eerst PROEFDRAAIEN (inlezen, rapporteren,
 * niets opslaan), dan pas importeren. Zo ziet de bibliotheekmedewerker vooraf wat
 * er gebeurt en welke regels worden overgeslagen.
 *
 * Voor het volledige bestand (ruim 11.000 titels) is het artisan-commando
 * `bibliotheek:importeren` de aangewezen weg: dat schrijft tienduizenden regels
 * weg, wat niet in een webverzoek thuishoort. Dit scherm is bedoeld voor kleinere
 * bestanden en om de bron te controleren vóór de grote import.
 */
class ImportController extends Controller
{
    /** Hoeveel titels dit scherm maximaal zelf wegschrijft; daarboven: artisan. */
    private const MAX_VIA_SCHERM = 500;

    public function index(Request $request): View
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        return view('bibliotheek.import', [
            'rapport' => session('rapport'),
            'maxViaScherm' => self::MAX_VIA_SCHERM,
        ]);
    }

    /** Stap 1: inlezen en rapporteren. Slaat niets op. */
    public function proef(Request $request, BibliotheekImport $import): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $pad = $this->bewaarTijdelijk($request);

        try {
            $resultaat = $import->lees($pad);
        } catch (\Throwable $e) {
            return back()->with('fout', 'Het bestand kon niet worden gelezen: '.$e->getMessage());
        }

        return back()->with('rapport', [
            'bestandsnaam' => $request->file('bestand')->getClientOriginalName(),
            'pad' => $pad,
            'statistiek' => $resultaat['statistiek'],
            // Alleen de eerste 50 overgeslagen regels tonen; de rest staat in het bestand.
            'overgeslagen' => array_slice($resultaat['overgeslagen'], 0, 50),
            'overgeslagen_totaal' => count($resultaat['overgeslagen']),
            'voorbeeld' => array_slice($resultaat['rijen'], 0, 15),
        ]);
    }

    /** Stap 2: daadwerkelijk importeren (alleen kleinere bestanden; zie MAX_VIA_SCHERM). */
    public function importeer(Request $request, BibliotheekImport $import): RedirectResponse
    {
        abort_unless($request->user()->magBibliotheekBeheren(), 403, 'U mag de bibliotheek niet beheren.');

        $data = $request->validate(['pad' => ['required', 'string']]);

        if (! is_file($data['pad'])) {
            return back()->with('fout', 'Het ingelezen bestand is niet meer beschikbaar. Lees het opnieuw in.');
        }

        $resultaat = $import->lees($data['pad']);
        $aantal = $resultaat['statistiek']['titels'];

        if ($aantal > self::MAX_VIA_SCHERM) {
            return back()->with('fout', sprintf(
                'Dit bestand bevat %d titels. Meer dan %d titels importeert u met het commando: php artisan bibliotheek:importeren "%s"',
                $aantal,
                self::MAX_VIA_SCHERM,
                $data['pad'],
            ));
        }

        $geschreven = DB::transaction(fn () => $import->importeer($resultaat['rijen']));

        AuditLogger::log(AuditLogger::AANMAAK, 'Bibliotheekimport', veld: 'bibliotheek_import', context: $geschreven);

        return redirect()->route('bibliotheek.publicaties')->with('status', sprintf(
            '%d titels en %d exemplaren geïmporteerd; %d regels bestonden al.',
            $geschreven['titels'],
            $geschreven['exemplaren'],
            $geschreven['overgeslagen_bestond_al'],
        ));
    }

    /** Zet het geüploade bestand op een tijdelijke plek buiten de webroot. */
    private function bewaarTijdelijk(Request $request): string
    {
        $request->validate([
            'bestand' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ], [], ['bestand' => 'Excel-bestand']);

        $pad = $request->file('bestand')->store('bibliotheek-import');

        return \Illuminate\Support\Facades\Storage::disk('local')->path($pad);
    }
}
