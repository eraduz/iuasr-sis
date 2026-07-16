<?php

namespace App\Http\Controllers\Scriptie;

use App\Enums\Scriptiestap;
use App\Http\Controllers\Controller;
use App\Models\Docent;
use App\Models\Scriptie;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Het scriptietraject-overzicht en de tabblad-werkflowpagina (elf stappen). Elke
 * stap is een tabblad met een formulier/checklist; de stappen worden sequentieel
 * afgevinkt. Alle mutaties op de afzonderlijke stappen lopen via de gespecialiseerde
 * controllers (ScriptieStapController, ScriptieGesprekController, ScriptieDocumentController).
 */
class ScriptietrajectController extends Controller
{
    /** Overzicht van alle (zichtbare) scriptietrajecten, filterbaar. */
    public function index(Request $request): View
    {
        $gebruiker = $request->user();

        $trajecten = Scriptie::query()
            ->zichtbaarVoor($gebruiker)
            ->with(['student', 'opleiding', 'begeleider', 'stapstanden'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('opleiding'), fn ($q) => $q->where('opleiding_id', (int) $request->query('opleiding')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $zoek = trim((string) $request->query('q'));
                $q->where(fn ($sub) => $sub
                    ->where('scriptienummer', 'like', "%{$zoek}%")
                    ->orWhere('titel_voorlopig', 'like', "%{$zoek}%")
                    ->orWhere('titel_definitief', 'like', "%{$zoek}%")
                    ->orWhereHas('student', fn ($s) => $s
                        ->where('achternaam', 'like', "%{$zoek}%")
                        ->orWhere('voornaam', 'like', "%{$zoek}%")
                        ->orWhere('studentnummer', 'like', "%{$zoek}%")));
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $opleidingen = \App\Models\Opleiding::orderBy('naam')->get();

        return view('scriptie.trajecten', [
            'trajecten' => $trajecten,
            'opleidingen' => $opleidingen,
            'status' => $request->query('status'),
            'opleiding' => $request->query('opleiding'),
            'q' => $request->query('q'),
        ]);
    }

    /** De tabblad-werkflowpagina van één traject. */
    public function show(Request $request, Scriptie $scriptie): View
    {
        $gebruiker = $request->user();
        abort_unless($scriptie->zichtbaarVoor($gebruiker), 403);

        $scriptie->load([
            'student', 'inschrijving.opleiding', 'opleiding', 'begeleider',
            'coordinator', 'gestartDoor', 'overeenkomstDocument',
            'stapstanden.gereedDoor',
            'checklistpunten.beoordelaar',
            'gesprekken.geregistreerdDoor',
            'documenten.geuploadDoor',
        ]);

        $stappen = Scriptiestap::inVolgorde();
        $docenten = Docent::where('actief', true)->orderBy('achternaam')->orderBy('voornaam')->get();

        return view('scriptie.traject', [
            'scriptie' => $scriptie,
            'stappen' => $stappen,
            'docenten' => $docenten,
        ]);
    }

    /** De kerngegevens (titel, taal, begeleider) los bijwerken. */
    public function updateKern(Request $request, Scriptie $scriptie): RedirectResponse
    {
        $gebruiker = $request->user();
        abort_unless($scriptie->zichtbaarVoor($gebruiker) && $gebruiker->magScriptieBeheren(), 403);
        abort_unless($scriptie->isLopend() || $gebruiker->heeftRol(\App\Enums\Rol::Beheerder), 403, 'Traject niet meer te bewerken.');

        $data = $request->validate([
            'titel_voorlopig' => ['nullable', 'string', 'max:255'],
            'titel_definitief' => ['nullable', 'string', 'max:255'],
            'taal' => ['nullable', 'string', 'max:40'],
            'begeleider_id' => ['nullable', 'integer', 'exists:docenten,id'],
        ]);

        $scriptie->update($data);
        AuditLogger::log(AuditLogger::WIJZIGING, $scriptie, veld: 'scriptie_kern');

        return redirect()->route('scriptie.show', $scriptie)->with('status', 'Kerngegevens bijgewerkt.');
    }

    /** Het traject afbreken (terminale status; Beheer kan corrigeren). */
    public function afbreken(Request $request, Scriptie $scriptie): RedirectResponse
    {
        $gebruiker = $request->user();
        abort_unless($scriptie->zichtbaarVoor($gebruiker) && $gebruiker->magScriptieBeheren(), 403);

        $request->validate(['reden' => ['nullable', 'string', 'max:255']]);

        $scriptie->update(['status' => Scriptie::AFGEBROKEN]);
        AuditLogger::log(AuditLogger::WIJZIGING, $scriptie, veld: 'scriptie_afbreken', context: [
            'reden' => $request->string('reden')->toString(),
        ]);

        return redirect()->route('scriptie.show', $scriptie)->with('status', 'Het scriptietraject is afgebroken.');
    }
}
