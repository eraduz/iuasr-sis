<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapporten — de web-opvolger van de oude Access-rapporten. In deze fase is de
 * klassenlijst functioneel (geen cijfers). Cijferrapporten/tentamenlijsten
 * volgen in Fase 5, na de cijferregistratie (Fase 4).
 */
class RapportController extends Controller
{
    public function index(): View
    {
        $opleidingen = Opleiding::orderBy('naam')->get();
        $perioden = Periode::orderByDesc('code')->get();
        $klassen = Klas::with('opleiding')->orderBy('code')->get();

        return view('rapporten.index', compact('opleidingen', 'perioden', 'klassen'));
    }

    /**
     * Excel-export van ALLE actief ingeschreven studenten met alle gegevens
     * behalve het BSN. Het IBAN-rekeningnummer wordt WÉL opgenomen (boekhouding
     * en facturatie). Waarden worden als tekst weggeschreven zodat IBAN,
     * telefoon en postcode niet worden verminkt. De export wordt gelogd.
     */
    public function actieveStudentenExport(): StreamedResponse
    {
        $inschrijvingen = Inschrijving::query()
            ->with(['student.nationaliteit', 'student.land', 'opleiding', 'klas', 'periode'])
            ->where('status', 'actief')
            ->get()
            ->sortBy(fn ($i) => $i->student->studentnummer)
            ->values();

        $kolommen = [
            'Studentnummer', 'Voornaam', 'Tussenvoegsel', 'Achternaam', 'Geboortedatum', 'Geboorteplaats',
            'Geslacht', 'Nationaliteit', 'E-mail (IUASR)', 'E-mail privé', 'Telefoon',
            'Straat', 'Huisnummer', 'Postcode', 'Stad', 'Provincie', 'Land', 'IBAN',
            'Hoogst behaalde diploma', 'Onderwijsinstelling', 'Afstudeerjaar',
            'Nederlandse taal', 'Arabische taal', 'NT2 vereist', 'NT2 behaald op',
            'Opleiding', 'Klas', 'Leerjaar', 'Studiejaar', 'Inschrijfdatum', 'Status',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Actieve studenten');

        foreach ($kolommen as $i => $kop) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $kop);
        }
        $laatsteKolom = Coordinate::stringFromColumnIndex(count($kolommen));
        $sheet->getStyle('A1:'.$laatsteKolom.'1')->getFont()->setBold(true);

        $rij = 2;
        foreach ($inschrijvingen as $insch) {
            $s = $insch->student;
            $waarden = [
                $s->studentnummer, $s->voornaam, $s->tussenvoegsel, $s->achternaam,
                $s->geboortedatum?->format('d-m-Y'), $s->geboorteplaats, $s->geslacht,
                $s->nationaliteit?->naam, $s->email, $s->email_prive, $s->telefoon,
                $s->adres, $s->huisnummer, $s->postcode, $s->woonplaats, $s->provincie, $s->land?->naam,
                $s->rekeningnummer, // IBAN — versleuteld opgeslagen, hier ontsleuteld voor boekhouding
                $s->diploma, $s->vorige_instelling, $s->afstudeerjaar,
                $s->taal_nederlands?->label(), $s->taal_arabisch?->label(),
                $s->nt2_examen_vereist ? 'Ja' : 'Nee', $s->nt2_behaald_op?->format('d-m-Y'),
                $insch->opleiding?->naam, $insch->klas?->code, $insch->leerjaar,
                $insch->periode?->naam, $insch->inschrijfdatum?->format('d-m-Y'), $insch->status->label(),
                // BSN wordt bewust NIET geëxporteerd.
            ];
            $kol = 1;
            foreach ($waarden as $waarde) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($kol).$rij,
                    (string) ($waarde ?? ''),
                    DataType::TYPE_STRING,
                );
                $kol++;
            }
            $rij++;
        }

        foreach (range(1, count($kolommen)) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        AuditLogger::log(AuditLogger::UITGIFTE, 'ActieveStudentenExport', veld: 'export', context: [
            'aantal' => $inschrijvingen->count(), 'bevat_iban' => true, 'bevat_bsn' => false,
        ]);

        $bestandsnaam = 'actieve-studenten-'.now()->format('Ymd-Hi').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $bestandsnaam, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Leerjaar-herbeoordeling: per actieve student de behaalde EC t.o.v. de
     * EC-overgangsdrempel van de opleiding, met overgangsadvies (positief /
     * voorwaardelijk / negatief). Voor Examencommissie en Directie.
     */
    public function overgang(Request $request): View
    {
        $data = $request->validate([
            'opleiding_id' => ['nullable', 'exists:opleidingen,id'],
            'leerjaar' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $opleidingen = Opleiding::orderBy('naam')->get();

        $rijen = Inschrijving::query()
            ->with(['student', 'opleiding'])
            ->where('status', 'actief')
            ->when($data['opleiding_id'] ?? null, fn ($q, $v) => $q->where('opleiding_id', $v))
            ->when($data['leerjaar'] ?? null, fn ($q, $v) => $q->where('leerjaar', $v))
            ->get()
            ->sortBy(fn ($i) => $i->student->studentnummer)
            ->map(fn ($i) => ['inschrijving' => $i, 'advies' => \App\Support\Overgangsbeoordeling::voor($i)])
            ->values();

        $telling = $rijen->groupBy(fn ($r) => $r['advies']['status'])->map->count();

        return view('rapporten.overgang', [
            'rijen' => $rijen,
            'opleidingen' => $opleidingen,
            'telling' => $telling,
            'gekozenOpleiding' => $data['opleiding_id'] ?? null,
            'gekozenLeerjaar' => $data['leerjaar'] ?? null,
        ]);
    }

    /** Klassenlijst: alle studenten per opleiding/periode/klas — geen cijfers. */
    public function klassenlijst(Request $request): View
    {
        $data = $request->validate([
            'opleiding_id' => ['nullable', 'exists:opleidingen,id'],
            'periode_id' => ['nullable', 'exists:perioden,id'],
            'klas_id' => ['nullable', 'exists:klassen,id'],
            'alleen_actief' => ['sometimes', 'boolean'],
        ]);

        $q = Inschrijving::query()
            ->with(['student', 'opleiding', 'klas', 'periode'])
            ->when($data['opleiding_id'] ?? null, fn ($q, $v) => $q->where('opleiding_id', $v))
            ->when($data['periode_id'] ?? null, fn ($q, $v) => $q->where('periode_id', $v))
            ->when($data['klas_id'] ?? null, fn ($q, $v) => $q->where('klas_id', $v))
            ->when($request->boolean('alleen_actief'), fn ($q) => $q->where('status', 'actief'));

        $inschrijvingen = $q->get()->sortBy(fn ($i) => $i->student->studentnummer)->values();

        $opleiding = ($data['opleiding_id'] ?? null) ? Opleiding::find($data['opleiding_id']) : null;
        $periode = ($data['periode_id'] ?? null) ? Periode::find($data['periode_id']) : null;
        $klas = ($data['klas_id'] ?? null) ? Klas::find($data['klas_id']) : null;

        return view('rapporten.klassenlijst', compact('inschrijvingen', 'opleiding', 'periode', 'klas'));
    }

    /**
     * Alumni-rapport: afgestudeerde studenten met contactgegevens (naam,
     * telefoon, e-mail). Zichtbaar voor Studentenzaken en Directie. Bevat geen
     * cijfers of BSN.
     */
    public function alumni(): View
    {
        // Alleen studenten waarvan de MEEST RECENTE inschrijving 'afgestudeerd' is.
        $alumni = Inschrijving::query()
            ->with(['student', 'opleiding'])
            ->where('status', 'afgestudeerd')
            ->whereRaw('inschrijvingen.inschrijfdatum = (select max(i2.inschrijfdatum) from inschrijvingen i2 where i2.student_id = inschrijvingen.student_id)')
            ->get()
            ->sortBy(fn ($i) => $i->student->achternaam)
            ->values();

        return view('rapporten.alumni', compact('alumni'));
    }
}
