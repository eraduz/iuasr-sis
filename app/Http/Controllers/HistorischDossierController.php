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

        return view('historisch.index', compact('studenten', 'zoek', 'opleiding'));
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
