<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\HrDocument;
use App\Models\Medewerker;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HR-documenten per medewerker (contract, diploma, ...). Bestanden op de private
 * schijf; upload/inzage/verwijdering gelogd (AVG). Muteren door HR/Beheer; inzage
 * volgt de zichtbaarheid van de medewerker.
 */
class HrDocumentController extends Controller
{
    private const DISK = 'local';

    private const MIMES = 'pdf,jpg,jpeg,png,webp,doc,docx';

    public function store(Request $request, Medewerker $medewerker): RedirectResponse
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag dit personeelsdossier niet wijzigen.');

        $data = $request->validate([
            'categorie' => ['required', Rule::in(array_keys(HrDocument::CATEGORIEEN))],
            'titel' => ['nullable', 'string', 'max:255'],
            'bestand' => ['required', 'file', 'max:16384', 'mimes:'.self::MIMES],
        ]);

        $bestand = $data['bestand'];
        $pad = $bestand->store("hr/{$medewerker->id}", self::DISK);

        $document = $medewerker->documenten()->create([
            'categorie' => $data['categorie'],
            'titel' => $data['titel'] ?? null,
            'bestandsnaam' => $bestand->getClientOriginalName(),
            'pad' => $pad,
            'mime' => $bestand->getClientMimeType(),
            'grootte' => $bestand->getSize(),
            'geupload_door_id' => $request->user()->id,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $document, veld: 'hr_document', context: ['medewerker' => $medewerker->personeelsnummer, 'categorie' => $document->categorie]);

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Document geüpload.');
    }

    public function download(Request $request, HrDocument $document): StreamedResponse
    {
        abort_unless($document->medewerker->zichtbaarVoor($request->user()), 403, 'Dit document valt buiten uw toegang.');

        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($document->pad), 404, 'Bestand niet gevonden.');

        AuditLogger::log(AuditLogger::INZAGE, $document, veld: 'hr_document', context: ['bestand' => $document->bestandsnaam]);

        $inline = $request->boolean('bekijken');

        return $disk->response($document->pad, $document->bestandsnaam, [
            'Content-Type' => $document->mime ?? 'application/octet-stream',
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(Request $request, HrDocument $document): RedirectResponse
    {
        $medewerker = $document->medewerker;
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag dit personeelsdossier niet wijzigen.');

        Storage::disk(self::DISK)->delete($document->pad);
        $naam = $document->bestandsnaam;
        $document->delete();

        AuditLogger::log(AuditLogger::VERWIJDERING, $medewerker, veld: 'hr_document', context: ['bestand' => $naam]);

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Document verwijderd.');
    }
}
