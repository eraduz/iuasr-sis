<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\Resultaat;
use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\Documentondertekening;
use App\Support\MigratieImport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Historisch studentdossier — alleen-lezen inzage in de uit het oude Access-
 * systeem gemigreerde cijfers (onder de aparte opleiding BA-HIST), per studiejaar.
 *
 * Bevat cijfers, dus voorbehouden aan de cijfer-bevoegde rollen (Examencommissie,
 * Directie) plus Beheerder (voor verificatie van de migratie). Studentenzaken
 * heeft hier bewust GEEN toegang — rolscheiding op cijfers (zie CLAUDE.md).
 */
class HistorischDossierController extends Controller
{
    private function opleiding(): ?Opleiding
    {
        return Opleiding::where('code', MigratieImport::HIST_OPLEIDING_CODE)->first();
    }

    /** Zoekbare lijst van studenten met een gemigreerd (historisch) dossier. */
    public function index(Request $request): View
    {
        $opleiding = $this->opleiding();
        $zoek = trim((string) $request->query('q', ''));

        $studenten = collect();
        if ($opleiding) {
            $studenten = Student::query()
                ->whereHas('inschrijvingen', fn ($q) => $q->where('opleiding_id', $opleiding->id))
                ->when($zoek !== '', fn ($q) => $q->where(fn ($s) => $s
                    ->where('studentnummer', 'like', $zoek.'%')
                    ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                    ->orWhere('voornaam', 'like', '%'.$zoek.'%')))
                ->withCount(['resultaten as historische_resultaten_count' => fn ($q) => $q
                    ->whereHas('inschrijving', fn ($i) => $i->where('opleiding_id', $opleiding->id))])
                ->orderBy('studentnummer')
                ->paginate(20)
                ->withQueryString();
        }

        $studiejaren = $opleiding ? $this->historischeStudiejaren($opleiding) : collect();

        return view('historisch.index', compact('studenten', 'zoek', 'opleiding', 'studiejaren'));
    }

    /** Cijferlijst van één student, gegroepeerd per studiejaar. */
    public function show(Student $student): View
    {
        [$opleiding, $data] = $this->dossierOf404($student);

        // Inzage door de examen-/directierollen wordt gelogd (zoals bij het reguliere dossier).
        if (in_array(auth()->user()->rol, [Rol::Examencommissie, Rol::Directie], true)) {
            AuditLogger::log(AuditLogger::INZAGE, $student, veld: 'cijfers (historisch)');
        }

        return view('historisch.show', ['student' => $student, 'opleiding' => $opleiding] + $data);
    }

    /** Printbare, informatieve PDF van de historische cijferlijst (niet gewaarmerkt). */
    public function pdf(Student $student): StreamedResponse
    {
        [$opleiding, $data] = $this->dossierOf404($student);

        if (in_array(auth()->user()->rol, [Rol::Examencommissie, Rol::Directie], true)) {
            AuditLogger::log(AuditLogger::UITGIFTE, $student, veld: 'cijfers (historisch, PDF)');
        }

        $html = view('pdf.historisch-cijferlijst', [
            'student' => $student,
            'opleiding' => $opleiding,
            'uitgegevenDoor' => auth()->user()->naam,
        ] + $data)->render();

        $bytes = Documentondertekening::pdfVanHtml($html);

        return response()->streamDownload(
            fn () => print($bytes),
            'historisch-dossier-'.$student->studentnummer.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Bulk-export: cijferoverzicht van een heel studiejaar (één gecombineerde PDF)
     * of van de hele opleiding (ZIP met één PDF per studiejaar).
     */
    public function bulk(Request $request): StreamedResponse
    {
        $opleiding = $this->opleiding();
        abort_unless($opleiding !== null, 404, 'Er is nog geen gemigreerde historische data.');

        $scope = (string) $request->query('scope', '');
        $jaren = $this->historischeStudiejaren($opleiding);
        abort_if($jaren->isEmpty(), 404, 'Geen historische cijfers gevonden.');

        // dompdf is geheugen-/tijdintensief; render per studiejaar in brokken van CHUNK
        // studenten (±1,6s en <100 MB per stuk) en ruim budget voor de hele export.
        @ini_set('memory_limit', '768M');
        @set_time_limit(600);

        if ($request->user() && in_array($request->user()->rol, [Rol::Examencommissie, Rol::Directie], true)) {
            AuditLogger::log(AuditLogger::UITGIFTE, 'HistorischDossier', $opleiding->id,
                veld: 'bulk-cijfers (historisch)', context: ['scope' => $scope ?: 'onbekend']);
        }

        // Hele opleiding → ZIP met per studiejaar (en zo nodig per deel) een PDF.
        if ($scope === 'alle') {
            return $this->zipDownload('historische-cijferlijsten-'.$opleiding->code.'.zip',
                function ($zip) use ($opleiding, $jaren) {
                    foreach ($jaren as $code) {
                        $this->voegJaarToe($zip, $opleiding, $code);
                    }
                });
        }

        // Eén studiejaar.
        abort_unless($jaren->contains($scope), 404, 'Onbekend of leeg studiejaar.');
        $delen = $this->jaarBlokken($opleiding, $scope)->chunk(self::CHUNK)->values();

        // Past het in één deel? Dan een gewone PDF; anders een ZIP met de delen.
        if ($delen->count() <= 1) {
            $bytes = $this->overzichtPdf($opleiding, $scope, $delen->first() ?? collect(), null);

            return response()->streamDownload(fn () => print($bytes),
                'cijferoverzicht-'.$scope.'.pdf', ['Content-Type' => 'application/pdf']);
        }

        return $this->zipDownload('cijferoverzicht-'.$scope.'.zip',
            fn ($zip) => $this->voegJaarToe($zip, $opleiding, $scope));
    }

    /** Aantal studenten per gecombineerde PDF (dompdf-veilig). */
    private const CHUNK = 50;

    /** Voegt de PDF('s) van één studiejaar toe aan een open ZIP-archief. */
    private function voegJaarToe(\ZipArchive $zip, Opleiding $opleiding, string $code): void
    {
        $delen = $this->jaarBlokken($opleiding, $code)->chunk(self::CHUNK)->values();
        $aantal = $delen->count();
        foreach ($delen as $i => $deel) {
            $naam = $aantal > 1 ? "cijferoverzicht-{$code}-deel".($i + 1).".pdf" : "cijferoverzicht-{$code}.pdf";
            $label = $aantal > 1 ? 'deel '.($i + 1).' van '.$aantal : null;
            $zip->addFromString($naam, $this->overzichtPdf($opleiding, $code, $deel, $label));
        }
    }

    /** Bouwt een ZIP in een tempbestand, leest hem in en biedt hem als download aan. */
    private function zipDownload(string $bestandsnaam, callable $vul): StreamedResponse
    {
        $pad = tempnam(sys_get_temp_dir(), 'histzip');
        $zip = new \ZipArchive;
        $zip->open($pad, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $vul($zip);
        $zip->close();
        $bytes = (string) file_get_contents($pad);
        @unlink($pad);

        return response()->streamDownload(fn () => print($bytes), $bestandsnaam,
            ['Content-Type' => 'application/zip']);
    }

    /** De studentblokken (met cijfers van dat jaar) voor één studiejaar, op studentnummer. */
    private function jaarBlokken(Opleiding $opleiding, string $periodeCode): \Illuminate\Support\Collection
    {
        return Resultaat::query()
            ->whereHas('inschrijving', fn ($q) => $q->where('opleiding_id', $opleiding->id)
                ->whereHas('periode', fn ($p) => $p->where('code', $periodeCode)))
            ->with(['toetsonderdeel.vak', 'student'])
            ->get()
            ->groupBy('student_id')
            ->map(function ($rs) {
                $rijen = $rs->sortBy(fn ($r) => optional($r->toetsonderdeel->vak)->code)->values();
                $cijfers = $rijen->filter(fn ($r) => $r->cijfer !== null);

                return [
                    'student' => $rs->first()->student,
                    'rijen' => $rijen,
                    'ec_behaald' => $rijen->filter(fn ($r) => $r->voldoende)
                        ->sum(fn ($r) => (float) optional($r->toetsonderdeel->vak)->ec),
                    'gemiddelde' => $cijfers->isNotEmpty() ? round($cijfers->avg('cijfer'), 1) : null,
                ];
            })
            ->filter(fn ($blok) => $blok['student'] !== null)
            ->sortBy(fn ($blok) => $blok['student']->studentnummer)
            ->values();
    }

    /** Rendert één (deel van een) jaaroverzicht naar PDF-bytes. */
    private function overzichtPdf(Opleiding $opleiding, string $periodeCode, \Illuminate\Support\Collection $blokken, ?string $deelLabel): string
    {
        $html = view('pdf.historisch-jaaroverzicht', [
            'opleiding' => $opleiding,
            'periodeCode' => $periodeCode,
            'studenten' => $blokken,
            'deelLabel' => $deelLabel,
            'uitgegevenDoor' => auth()->user()?->naam ?? 'IUASR',
        ])->render();

        return Documentondertekening::pdfVanHtml($html);
    }

    /** Studiejaarcodes (periodes) waarvoor historische resultaten bestaan, oplopend. */
    private function historischeStudiejaren(Opleiding $opleiding): \Illuminate\Support\Collection
    {
        return Resultaat::query()
            ->join('inschrijvingen', 'inschrijvingen.id', '=', 'resultaten.inschrijving_id')
            ->join('perioden', 'perioden.id', '=', 'inschrijvingen.periode_id')
            ->where('inschrijvingen.opleiding_id', $opleiding->id)
            ->orderBy('perioden.code')
            ->distinct()
            ->pluck('perioden.code');
    }

    /**
     * Bouwt het historische dossier (per studiejaar) of stopt met 404.
     *
     * @return array{0:Opleiding,1:array{jaren:\Illuminate\Support\Collection,totaalEcBehaald:float,resultaten:\Illuminate\Support\Collection}}
     */
    private function dossierOf404(Student $student): array
    {
        $opleiding = $this->opleiding();
        abort_unless($opleiding !== null, 404, 'Er is nog geen gemigreerde historische data.');

        $resultaten = Resultaat::where('student_id', $student->id)
            ->whereHas('inschrijving', fn ($q) => $q->where('opleiding_id', $opleiding->id))
            ->with(['toetsonderdeel.vak', 'inschrijving.periode'])
            ->get();

        abort_unless($resultaten->isNotEmpty(), 404, 'Deze student heeft geen historisch dossier.');

        // Per studiejaar (periodecode) de vakken met cijfer, gesorteerd op vakcode.
        $jaren = $resultaten
            ->groupBy(fn ($r) => optional($r->inschrijving->periode)->code ?? '—')
            ->sortKeys()
            ->map(function ($rs) {
                $rijen = $rs->sortBy(fn ($r) => optional($r->toetsonderdeel->vak)->code)->values();
                $cijfers = $rijen->filter(fn ($r) => $r->cijfer !== null);

                return [
                    'rijen' => $rijen,
                    'ec_behaald' => $rijen->filter(fn ($r) => $r->voldoende)
                        ->sum(fn ($r) => (float) optional($r->toetsonderdeel->vak)->ec),
                    'ec_totaal' => $rijen->sum(fn ($r) => (float) optional($r->toetsonderdeel->vak)->ec),
                    'gemiddelde' => $cijfers->isNotEmpty() ? round($cijfers->avg('cijfer'), 1) : null,
                ];
            });

        $totaalEcBehaald = $resultaten->filter(fn ($r) => $r->voldoende)
            ->sum(fn ($r) => (float) optional($r->toetsonderdeel->vak)->ec);

        return [$opleiding, compact('jaren', 'totaalEcBehaald', 'resultaten')];
    }
}
