<?php

namespace App\Http\Controllers\Scriptie;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Scriptie;
use App\Models\ScriptieDocument;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documenten binnen een scriptietraject (plan van aanpak, eindversie,
 * plagiaatrapport, presentatie, ...). Op de private schijf, met versiebeheer.
 * Upload/inzage/verwijdering wordt gelogd. Downloaden mag iedereen die het traject
 * mag inzien; beheren de coördinator, de begeleider (docent) of de examencommissie.
 */
class ScriptieDocumentController extends Controller
{
    private const DISK = 'local';

    private const MIMES = 'pdf,doc,docx,jpg,jpeg,png,webp,ppt,pptx';

    public function store(Request $request, Scriptie $scriptie): RedirectResponse
    {
        abort_unless($this->magBeheren($request, $scriptie), 403);

        $data = $request->validate([
            'categorie' => ['required', Rule::in(array_keys(ScriptieDocument::CATEGORIEEN))],
            'titel' => ['nullable', 'string', 'max:255'],
            'bestand' => ['required', 'file', 'mimes:'.self::MIMES, 'max:20480'],
        ]);

        $bestand = $request->file('bestand');
        $pad = $bestand->store('scripties/'.$scriptie->id, self::DISK);

        $document = $scriptie->documenten()->create([
            'categorie' => $data['categorie'],
            'titel' => $data['titel'] ?? null,
            'bestandsnaam' => $bestand->getClientOriginalName(),
            'pad' => $pad,
            'mime' => $bestand->getClientMimeType(),
            'grootte' => $bestand->getSize(),
            'versie' => 1,
            'geupload_door_id' => $request->user()->id,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $scriptie, veld: 'scriptie_document', context: [
            'categorie' => $document->categorie,
            'document_id' => $document->id,
        ]);

        return $this->terug($scriptie, 'Document geüpload.');
    }

    /** Een nieuwe versie van een bestaand document (het oude blijft bewaard). */
    public function versie(Request $request, Scriptie $scriptie, ScriptieDocument $document): RedirectResponse
    {
        abort_unless($document->scriptie_id === $scriptie->id, 404);
        abort_unless($this->magBeheren($request, $scriptie), 403);

        $request->validate([
            'bestand' => ['required', 'file', 'mimes:'.self::MIMES, 'max:20480'],
        ]);

        $bestand = $request->file('bestand');
        $pad = $bestand->store('scripties/'.$scriptie->id, self::DISK);

        $nieuw = $scriptie->documenten()->create([
            'categorie' => $document->categorie,
            'titel' => $document->titel,
            'bestandsnaam' => $bestand->getClientOriginalName(),
            'pad' => $pad,
            'mime' => $bestand->getClientMimeType(),
            'grootte' => $bestand->getSize(),
            'versie' => $document->versie + 1,
            'vorige_versie_id' => $document->id,
            'geupload_door_id' => $request->user()->id,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $scriptie, veld: 'scriptie_document_versie', context: [
            'document_id' => $nieuw->id,
            'versie' => $nieuw->versie,
        ]);

        return $this->terug($scriptie, 'Nieuwe versie geüpload (v'.$nieuw->versie.').');
    }

    public function download(Request $request, Scriptie $scriptie, ScriptieDocument $document): StreamedResponse
    {
        abort_unless($document->scriptie_id === $scriptie->id, 404);
        abort_unless($scriptie->zichtbaarVoor($request->user()), 403);

        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($document->pad), 404);

        AuditLogger::log(AuditLogger::INZAGE, $scriptie, veld: 'scriptie_document', context: [
            'document_id' => $document->id,
        ]);

        return $disk->response(
            $document->pad,
            $document->bestandsnaam,
            ['Content-Type' => $document->mime ?? 'application/octet-stream'],
            $request->boolean('bekijken') ? 'inline' : 'attachment'
        );
    }

    public function destroy(Request $request, Scriptie $scriptie, ScriptieDocument $document): RedirectResponse
    {
        abort_unless($document->scriptie_id === $scriptie->id, 404);
        abort_unless($this->magBeheren($request, $scriptie), 403);

        Storage::disk(self::DISK)->delete($document->pad);
        $document->delete();

        AuditLogger::log(AuditLogger::VERWIJDERING, $scriptie, veld: 'scriptie_document', context: [
            'document_id' => $document->id,
        ]);

        return $this->terug($scriptie, 'Document verwijderd.');
    }

    /** Mag deze gebruiker documenten van dit traject beheren? */
    private function magBeheren(Request $request, Scriptie $scriptie): bool
    {
        $gebruiker = $request->user();

        if (! $scriptie->zichtbaarVoor($gebruiker)) {
            return false;
        }

        return $gebruiker->magScriptieBeheren()
            || $gebruiker->isScriptieBegeleider()
            || $gebruiker->heeftRol(Rol::Examencommissie)
            || $gebruiker->heeftRol(Rol::Beheerder);
    }

    private function terug(Scriptie $scriptie, string $melding): RedirectResponse
    {
        return redirect()
            ->route('scriptie.show', $scriptie)
            ->with('status', $melding);
    }
}
