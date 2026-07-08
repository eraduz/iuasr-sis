<?php

namespace App\Support;

use App\Models\OndertekendDocument;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Genereert PDF's uit HTML (dompdf) en voorziet ze automatisch van een digitaal
 * echtheidskenmerk: een zichtbaar handtekeningblok met verificatiecode en een
 * SHA-256 van de PDF-bytes. Het document wordt gearchiveerd op de private schijf
 * met logregistratie (wie, wanneer, aan wie verstrekt).
 *
 * Dit is de HASH-gebaseerde variant (tamper-evident + publieke verificatie).
 * Cryptografische certificaat-ondertekening (PAdES) kan hier later op aansluiten
 * zodra er een signing-certificaat beschikbaar is.
 */
class Documentondertekening
{
    private const DISK = 'local';

    /**
     * @param  array{type?:string,titel?:string,student_id?:int|null,ontvanger?:string|null,uitgegeven_door_id?:int|null}  $meta
     */
    public static function ondertekenHtml(string $html, array $meta): OndertekendDocument
    {
        $code = self::genereerCode();
        $user = isset($meta['uitgegeven_door_id']) ? User::find($meta['uitgegeven_door_id']) : null;

        $blok = self::handtekeningBlok($code, $user, $meta);
        $volledig = str_contains($html, '</body>')
            ? str_replace('</body>', $blok.'</body>', $html)
            : $html.$blok;
        $pdf = self::renderPdf($volledig);
        $sha = hash('sha256', $pdf);

        $pad = 'ondertekend/'.$code.'.pdf';
        Storage::disk(self::DISK)->put($pad, $pdf);

        return OndertekendDocument::create([
            'code' => $code,
            'type' => $meta['type'] ?? 'document',
            'titel' => $meta['titel'] ?? 'Document',
            'student_id' => $meta['student_id'] ?? null,
            'ontvanger' => $meta['ontvanger'] ?? null,
            'uitgegeven_door_id' => $meta['uitgegeven_door_id'] ?? null,
            'sha256' => $sha,
            'bestandsnaam' => Str::slug($meta['titel'] ?? 'document').'-'.$code.'.pdf',
            'pad' => $pad,
        ]);
    }

    /**
     * Waarmerkt een door de gebruiker geüploade PDF: archiveert het originele
     * bestand, berekent het SHA-256-echtheidskenmerk en genereert een digitaal
     * waarmerk-certificaat (met verificatiecode). Het origineel wordt NIET
     * gewijzigd, zodat elk PDF-formaat werkt.
     *
     * @param  array{titel?:string,ontvanger?:string|null,uitgegeven_door_id?:int|null}  $meta
     */
    public static function ondertekenUpload(string $origineleBytes, string $origineleNaam, array $meta): OndertekendDocument
    {
        $code = self::genereerCode();
        $sha = hash('sha256', $origineleBytes);
        $user = isset($meta['uitgegeven_door_id']) ? User::find($meta['uitgegeven_door_id']) : null;

        $origineelPad = 'ondertekend/'.$code.'-origineel.pdf';
        Storage::disk(self::DISK)->put($origineelPad, $origineleBytes);

        $waarmerkHtml = view('pdf.waarmerk', [
            'code' => $code,
            'titel' => $meta['titel'] ?? $origineleNaam,
            'ontvanger' => $meta['ontvanger'] ?? null,
            'origineleNaam' => $origineleNaam,
            'sha256' => $sha,
            'ondertekenaar' => $user,
            'verifyUrl' => route('verificatie'),
        ])->render();
        $waarmerkPad = 'ondertekend/'.$code.'-waarmerk.pdf';
        Storage::disk(self::DISK)->put($waarmerkPad, self::renderPdf($waarmerkHtml));

        return OndertekendDocument::create([
            'code' => $code,
            'type' => 'upload',
            'titel' => $meta['titel'] ?? $origineleNaam,
            'ontvanger' => $meta['ontvanger'] ?? null,
            'uitgegeven_door_id' => $meta['uitgegeven_door_id'] ?? null,
            'sha256' => $sha,
            'bestandsnaam' => $origineleNaam,
            'pad' => $origineelPad,
            'waarmerk_pad' => $waarmerkPad,
        ]);
    }

    public static function pdfBytes(OndertekendDocument $document): ?string
    {
        return self::bestandBytes($document->pad);
    }

    public static function bestandBytes(?string $pad): ?string
    {
        if (! $pad) {
            return null;
        }
        $disk = Storage::disk(self::DISK);

        return $disk->exists($pad) ? $disk->get($pad) : null;
    }

    /** Controleert of geüploade PDF-bytes overeenkomen met het gearchiveerde origineel. */
    public static function isOngewijzigd(OndertekendDocument $document, string $bytes): bool
    {
        return hash_equals($document->sha256, hash('sha256', $bytes));
    }

    private static function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private static function handtekeningBlok(string $code, ?User $user, array $meta): string
    {
        $naam = e($user?->naam ?? 'Onbekend');
        $rol = e($user?->rol?->label() ?? '');
        $datum = now()->format('d-m-Y H:i');
        $ontvanger = e($meta['ontvanger'] ?? '—');
        $verifyUrl = route('verificatie');

        return '<div style="margin-top:26px;padding-top:8px;border-top:2px solid #1E1446;font-family:DejaVu Sans;font-size:9.5px;color:#1E1446;line-height:1.5;">'
            .'<b>Digitaal ondertekend</b> door '.$naam.($rol ? ' ('.$rol.')' : '').' namens IUASR op '.$datum.'.<br>'
            .'Verstrekt aan: <b>'.$ontvanger.'</b><br>'
            .'Verificatiecode: <b>'.$code.'</b> — controleer echtheid op '.e($verifyUrl).'<br>'
            .'<span style="color:#666;">Dit document draagt een digitaal echtheidskenmerk (SHA-256). Elke wijziging maakt het ongeldig.</span>'
            .'</div>';
    }

    private static function genereerCode(): string
    {
        do {
            $code = 'IUASR-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        } while (OndertekendDocument::where('code', $code)->exists());

        return $code;
    }
}
