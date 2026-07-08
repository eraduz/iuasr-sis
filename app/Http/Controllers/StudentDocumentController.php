<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentDocument;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Beheer van ontvangen studentdocumenten (identiteitsbewijs, diploma,
 * cijferlijst, pasfoto, ...). Bestanden staan op de private schijf, buiten de
 * webroot; inzage en afgifte worden gelogd (AVG). Alleen Studentenzaken/Beheer.
 */
class StudentDocumentController extends Controller
{
    private const DISK = 'local';

    public function upload(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'soort' => ['required', 'in:'.implode(',', array_keys(StudentDocument::SOORTEN))],
            'bestand' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $bestand = $data['bestand'];
        $pad = $bestand->store("studenten/{$student->id}", self::DISK);

        $student->documenten()->create([
            'soort' => $data['soort'],
            'bestandsnaam' => $bestand->getClientOriginalName(),
            'pad' => $pad,
            'mime' => $bestand->getClientMimeType(),
            'grootte' => $bestand->getSize(),
            'geupload_door_id' => auth()->id(),
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'document', context: [
            'actie' => 'upload', 'soort' => $data['soort'],
        ]);

        return back()->with('status', 'Document geüpload.');
    }

    public function download(Request $request, StudentDocument $document): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($document->pad), 404, 'Bestand niet gevonden.');

        AuditLogger::log(AuditLogger::INZAGE, $document->student, veld: 'document', context: [
            'soort' => $document->soort, 'bestand' => $document->bestandsnaam,
        ]);

        $inline = $request->boolean('bekijken');

        return $disk->response($document->pad, $document->bestandsnaam, [
            'Content-Type' => $document->mime ?? 'application/octet-stream',
        ], $inline ? 'inline' : 'attachment');
    }

    public function destroy(StudentDocument $document): RedirectResponse
    {
        $student = $document->student;
        Storage::disk(self::DISK)->delete($document->pad);
        $soort = $document->soort;
        $document->delete();

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'document', context: [
            'actie' => 'verwijderd', 'soort' => $soort,
        ]);

        return back()->with('status', 'Document verwijderd.');
    }

    /** Markeren dat (diploma/cijferlijst e.d.) later worden aangeleverd. */
    public function later(Request $request, Student $student): RedirectResponse
    {
        $student->update(['documenten_later' => $request->boolean('documenten_later')]);

        AuditLogger::log(AuditLogger::WIJZIGING, $student, veld: 'documenten_later', context: [
            'waarde' => $student->documenten_later,
        ]);

        return back()->with('status', $student->documenten_later
            ? 'Gemarkeerd: documenten worden later aangeleverd.'
            : 'Markering opgeheven.');
    }
}
