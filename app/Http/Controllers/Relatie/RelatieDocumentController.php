<?php

namespace App\Http\Controllers\Relatie;

use App\Http\Controllers\Controller;
use App\Models\Organisatie;
use App\Models\RelatieDocument;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documentbeheer met versiebeheer bij een organisatie (module Relatiebeheer &
 * Stagebeheer). Bestanden op de private schijf (buiten de webroot); inzage,
 * upload en verwijdering worden gelogd (AVG). Een nieuwe versie verwijst naar de
 * vorige, zodat de geschiedenis bewaard blijft. Muteren volgt de organisatie.
 */
class RelatieDocumentController extends Controller
{
    private const DISK = 'local';

    private const MIMES = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx';

    public function store(Request $request, Organisatie $organisatie): RedirectResponse
    {
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $data = $this->valideer($request, $organisatie);
        $bestand = $data['bestand'];
        $pad = $bestand->store("relaties/{$organisatie->id}", self::DISK);

        $document = $organisatie->documenten()->create([
            'stage_id' => $data['stage_id'] ?? null,
            'categorie' => $data['categorie'],
            'titel' => $data['titel'] ?? null,
            'bestandsnaam' => $bestand->getClientOriginalName(),
            'pad' => $pad,
            'mime' => $bestand->getClientMimeType(),
            'grootte' => $bestand->getSize(),
            'versie' => 1,
            'geupload_door_id' => $request->user()->id,
        ]);

        AuditLogger::log(AuditLogger::AANMAAK, $document, veld: 'relatie_document', context: ['organisatie' => $organisatie->relatienummer, 'categorie' => $document->categorie]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Document geüpload.');
    }

    /** Upload een nieuwe versie van een bestaand document (de oude blijft bewaard). */
    public function versie(Request $request, RelatieDocument $document): RedirectResponse
    {
        $organisatie = $document->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        $bestand = $request->validate(['bestand' => ['required', 'file', 'max:16384', 'mimes:'.self::MIMES]])['bestand'];
        $pad = $bestand->store("relaties/{$organisatie->id}", self::DISK);

        $nieuw = $organisatie->documenten()->create([
            'stage_id' => $document->stage_id,
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

        AuditLogger::log(AuditLogger::AANMAAK, $nieuw, veld: 'relatie_document', context: ['organisatie' => $organisatie->relatienummer, 'versie' => $nieuw->versie]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Nieuwe versie geüpload (v'.$nieuw->versie.').');
    }

    public function download(Request $request, RelatieDocument $document): StreamedResponse
    {
        abort_unless($document->organisatie->zichtbaarVoor($request->user()), 403, 'Dit document valt buiten uw toegang.');

        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($document->pad), 404, 'Bestand niet gevonden.');

        AuditLogger::log(AuditLogger::INZAGE, $document, veld: 'relatie_document', context: ['bestand' => $document->bestandsnaam]);

        $inline = $request->boolean('bekijken');

        return $disk->response($document->pad, $document->bestandsnaam, [
            'Content-Type' => $document->mime ?? 'application/octet-stream',
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(Request $request, RelatieDocument $document): RedirectResponse
    {
        $organisatie = $document->organisatie;
        abort_unless($organisatie->beheerbaarVoor($request->user()), 403, 'Deze organisatie valt buiten uw beheer.');

        Storage::disk(self::DISK)->delete($document->pad);
        $naam = $document->bestandsnaam;
        $document->delete();

        AuditLogger::log(AuditLogger::VERWIJDERING, $organisatie, veld: 'relatie_document', context: ['bestand' => $naam]);

        return redirect()->route('relaties.show', $organisatie)->with('status', 'Document verwijderd.');
    }

    private function valideer(Request $request, Organisatie $organisatie): array
    {
        $stageIds = $organisatie->stages()->pluck('id')->all();

        return $request->validate([
            'categorie' => ['required', Rule::in(array_keys(RelatieDocument::CATEGORIEEN))],
            'titel' => ['nullable', 'string', 'max:255'],
            'stage_id' => ['nullable', 'integer', Rule::in($stageIds)],
            'bestand' => ['required', 'file', 'max:16384', 'mimes:'.self::MIMES],
        ], [], ['stage_id' => 'stage']);
    }
}
