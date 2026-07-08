<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Rendert de PDF-handleidingen uit hun Blade-bron. De Blade-templates zijn de
 * enige bron van waarheid; bij nieuwe functies worden die bijgewerkt en wordt
 * de PDF opnieuw gegenereerd (webdownload of `php artisan handleidingen:genereren`).
 */
class Handleiding
{
    public const MEDEWERKERS = 'pdf.handleiding-medewerkers';
    public const TECHNISCH = 'pdf.handleiding-technisch';

    /** Rendert een handleiding-view naar PDF-bytes. */
    public static function pdf(string $view): string
    {
        $opties = new Options();
        $opties->set('isRemoteEnabled', false);
        $opties->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($opties);
        $dompdf->loadHtml(view($view)->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
