<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Models\Klas;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\Documentondertekening;
use App\Support\Transcript;
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
     * Excel-contactlijst van ÁLLE studenten in de database — ongeacht de status
     * van hun inschrijving (actief, uitgeschreven, afgestudeerd, …). Alleen de
     * contactvelden: naam, telefoon en e-mail. Geen IBAN, geen BSN. Waarden als
     * tekst zodat telefoonnummers niet worden verminkt. De export wordt gelogd.
     */
    public function alleStudentenExport(): StreamedResponse
    {
        $studenten = Student::query()
            ->orderBy('achternaam')->orderBy('voornaam')->orderBy('studentnummer')
            ->get();

        $kolommen = ['Studentnummer', 'Voornaam', 'Tussenvoegsel', 'Achternaam', 'Telefoon', 'E-mail (IUASR)', 'E-mail privé'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Alle studenten');

        foreach ($kolommen as $i => $kop) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $kop);
        }
        $laatsteKolom = Coordinate::stringFromColumnIndex(count($kolommen));
        $sheet->getStyle('A1:'.$laatsteKolom.'1')->getFont()->setBold(true);

        $rij = 2;
        foreach ($studenten as $s) {
            $waarden = [
                $s->studentnummer, $s->voornaam, $s->tussenvoegsel, $s->achternaam,
                $s->telefoon, $s->email, $s->email_prive,
                // Bewust GEEN IBAN en GEEN BSN in deze contactlijst.
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

        AuditLogger::log(AuditLogger::UITGIFTE, 'AlleStudentenContactExport', veld: 'export', context: [
            'aantal' => $studenten->count(), 'bevat_iban' => false, 'bevat_bsn' => false,
        ]);

        $bestandsnaam = 'alle-studenten-contact-'.now()->format('Ymd-Hi').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $bestandsnaam, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Cijferlijst / transcript per student: volledig cijferoverzicht per
     * studiejaar met eindcijfer, EC en status. Cijferinzage → Examencommissie
     * en Directie. Inzage wordt gelogd.
     */
    public function cijferlijst(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));
        $gebruiker = $request->user();
        $student = $request->filled('student') ? Student::find($request->query('student')) : null;

        // Directie: geen inzage in dossiers buiten de eigen opleiding(en).
        if ($student) {
            $student->load('inschrijvingen');
            abort_unless($student->zichtbaarVoor($gebruiker), 403,
                'Deze student valt buiten uw opleiding(en).');
        }

        $resultaten = collect();
        if ($zoek !== '' && ! $student) {
            $resultaten = Student::query()
                ->zichtbaarVoor($gebruiker)
                ->where(function ($q) use ($zoek) {
                    $q->where('studentnummer', 'like', $zoek.'%')
                        ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                        ->orWhere('voornaam', 'like', '%'.$zoek.'%');
                })
                ->orderBy('studentnummer')->limit(20)
                ->get(['id', 'studentnummer', 'voornaam', 'tussenvoegsel', 'achternaam']);
        }

        $transcript = null;
        if ($student) {
            $transcript = Transcript::voor($student);
            AuditLogger::log(AuditLogger::INZAGE, $student, veld: 'cijferlijst', context: ['bron' => 'transcript']);
        }

        // Tweede weergave: alle actieve studenten van een opleiding met behaalde EC.
        $opleidingen = $this->zichtbareOpleidingen($gebruiker);
        $opleidingId = $request->integer('opleiding_id') ?: null;
        // Directie mag geen opleiding buiten de eigen scope kiezen.
        if ($opleidingId && $gebruiker->isOpleidingBeperkt() && ! $gebruiker->opleidingIds()->contains($opleidingId)) {
            $opleidingId = null;
        }
        $perOpleiding = collect();
        if ($opleidingId && ! $student) {
            $perOpleiding = Inschrijving::where('status', 'actief')->where('opleiding_id', $opleidingId)
                ->with(['student', 'klas', 'opleiding'])
                ->get()
                ->sortBy(fn ($i) => $i->student->achternaam)
                ->map(fn ($i) => [
                    'inschrijving' => $i,
                    'behaald' => Transcript::voor($i->student)['behaaldeEc'],
                    'totaal' => $i->opleiding?->ec_totaal,
                ])->values();
        }

        return view('rapporten.cijferlijst', compact(
            'student', 'transcript', 'zoek', 'resultaten', 'opleidingen', 'opleidingId', 'perOpleiding'
        ));
    }

    /** Officiële cijferlijst als ondertekende PDF (op briefpapier). */
    public function cijferlijstPdf(Request $request, Student $student): StreamedResponse
    {
        $data = $request->validate(['ontvanger' => ['required', 'string', 'max:255']]);

        $student->load('inschrijvingen');
        abort_unless($student->zichtbaarVoor($request->user()), 403,
            'Deze student valt buiten uw opleiding(en).');

        $transcript = Transcript::voor($student);
        $html = view('pdf.cijferlijst', [
            'student' => $student, 'transcript' => $transcript, 'ondertekenaar' => auth()->user()->naam,
        ])->render();

        $doc = Documentondertekening::ondertekenHtml($html, [
            'type' => 'cijferlijst',
            'titel' => 'Cijferlijst '.$student->studentnummer,
            'student_id' => $student->id,
            'ontvanger' => $data['ontvanger'],
            'uitgegeven_door_id' => auth()->id(),
        ]);

        AuditLogger::log(AuditLogger::UITGIFTE, $student, veld: 'cijferlijst', context: [
            'code' => $doc->code, 'ontvanger' => $data['ontvanger'],
        ]);

        return response()->streamDownload(
            fn () => print(Documentondertekening::pdfBytes($doc)),
            $doc->bestandsnaam,
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * EC-rapport: studievoortgang per opleiding/klas — cumulatief behaalde EC
     * per student t.o.v. het nominale totaal. Voor Examencommissie en Directie.
     */
    public function ecRapport(Request $request): View
    {
        $data = $request->validate([
            'opleiding_id' => ['nullable', 'exists:opleidingen,id'],
            'leerjaar' => ['nullable', 'integer', 'min:1', 'max:10'],
            'klas_id' => ['nullable', 'exists:klassen,id'],
        ]);
        $zoek = trim((string) $request->query('q', ''));
        $gebruiker = $request->user();

        $opleidingen = $this->zichtbareOpleidingen($gebruiker);
        $klassen = Klas::with('opleiding')
            ->when($gebruiker->isOpleidingBeperkt(),
                fn ($q) => $q->whereIn('opleiding_id', $gebruiker->opleidingIds()))
            ->orderBy('code')->get();

        $rijen = Inschrijving::query()
            ->with(['student', 'opleiding', 'klas'])
            ->where('status', 'actief')
            ->when($gebruiker->isOpleidingBeperkt(),
                fn ($q) => $q->whereIn('opleiding_id', $gebruiker->opleidingIds()))
            ->when($data['opleiding_id'] ?? null, fn ($q, $v) => $q->where('opleiding_id', $v))
            ->when($data['leerjaar'] ?? null, fn ($q, $v) => $q->where('leerjaar', $v))
            ->when($data['klas_id'] ?? null, fn ($q, $v) => $q->where('klas_id', $v))
            ->when($zoek !== '', fn ($q) => $this->zoekStudent($q, $zoek))
            ->get()
            ->sortBy(fn ($i) => $i->student->studentnummer)
            ->map(function ($i) {
                $tr = Transcript::voor($i->student);

                return ['inschrijving' => $i, 'behaald' => $tr['behaaldeEc'], 'totaal' => $tr['ecTotaal']];
            })->values();

        $gemiddeld = $rijen->count() ? round($rijen->avg('behaald'), 1) : null;

        return view('rapporten.ec-rapport', [
            'rijen' => $rijen,
            'opleidingen' => $opleidingen,
            'klassen' => $klassen,
            'gemiddeld' => $gemiddeld,
            'gekozenOpleiding' => $data['opleiding_id'] ?? null,
            'gekozenLeerjaar' => $data['leerjaar'] ?? null,
            'gekozenKlas' => $data['klas_id'] ?? null,
            'zoek' => $zoek,
        ]);
    }

    /**
     * Opleidingen die deze gebruiker mag zien. Directie: alleen de eigen
     * toegewezen opleiding(en); overige rollen: alle opleidingen.
     */
    private function zichtbareOpleidingen(\App\Models\User $gebruiker)
    {
        return Opleiding::when($gebruiker->isOpleidingBeperkt(),
            fn ($q) => $q->whereIn('id', $gebruiker->opleidingIds()))
            ->orderBy('naam')->get();
    }

    /** Filtert een inschrijvingen-query op studentnummer (prefix) of naam. */
    private function zoekStudent($query, string $zoek)
    {
        return $query->whereHas('student', function ($s) use ($zoek) {
            $s->where('studentnummer', 'like', $zoek.'%')
                ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                ->orWhere('voornaam', 'like', '%'.$zoek.'%');
        });
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
        $zoek = trim((string) $request->query('q', ''));
        $gebruiker = $request->user();

        $opleidingen = $this->zichtbareOpleidingen($gebruiker);

        $rijen = Inschrijving::query()
            ->with(['student', 'opleiding'])
            ->where('status', 'actief')
            ->when($gebruiker->isOpleidingBeperkt(),
                fn ($q) => $q->whereIn('opleiding_id', $gebruiker->opleidingIds()))
            ->when($data['opleiding_id'] ?? null, fn ($q, $v) => $q->where('opleiding_id', $v))
            ->when($data['leerjaar'] ?? null, fn ($q, $v) => $q->where('leerjaar', $v))
            ->when($zoek !== '', fn ($q) => $this->zoekStudent($q, $zoek))
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
            'zoek' => $zoek,
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
        $zoek = trim((string) $request->query('q', ''));

        $q = Inschrijving::query()
            ->with(['student', 'opleiding', 'klas', 'periode'])
            ->when($data['opleiding_id'] ?? null, fn ($q, $v) => $q->where('opleiding_id', $v))
            ->when($data['periode_id'] ?? null, fn ($q, $v) => $q->where('periode_id', $v))
            ->when($data['klas_id'] ?? null, fn ($q, $v) => $q->where('klas_id', $v))
            ->when($zoek !== '', fn ($q) => $this->zoekStudent($q, $zoek))
            ->when($request->boolean('alleen_actief'), fn ($q) => $q->where('status', 'actief'));

        $inschrijvingen = $q->get()->sortBy(fn ($i) => $i->student->studentnummer)->values();

        $opleiding = ($data['opleiding_id'] ?? null) ? Opleiding::find($data['opleiding_id']) : null;
        $periode = ($data['periode_id'] ?? null) ? Periode::find($data['periode_id']) : null;
        $klas = ($data['klas_id'] ?? null) ? Klas::find($data['klas_id']) : null;

        return view('rapporten.klassenlijst', compact('inschrijvingen', 'opleiding', 'periode', 'klas', 'zoek'));
    }

    /**
     * Alumni-rapport: afgestudeerde studenten met contactgegevens (naam,
     * telefoon, e-mail). Zichtbaar voor Studentenzaken en Directie. Bevat geen
     * cijfers of BSN.
     */
    public function alumni(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));
        $gebruiker = $request->user();

        // Alleen studenten waarvan de MEEST RECENTE inschrijving 'afgestudeerd' is.
        $alumni = Inschrijving::query()
            ->with(['student', 'opleiding'])
            ->where('status', 'afgestudeerd')
            ->when($gebruiker->isOpleidingBeperkt(),
                fn ($q) => $q->whereIn('opleiding_id', $gebruiker->opleidingIds()))
            ->when($zoek !== '', fn ($q) => $this->zoekStudent($q, $zoek))
            ->whereRaw('inschrijvingen.inschrijfdatum = (select max(i2.inschrijfdatum) from inschrijvingen i2 where i2.student_id = inschrijvingen.student_id)')
            ->get()
            ->sortBy(fn ($i) => $i->student->achternaam)
            ->values();

        return view('rapporten.alumni', compact('alumni', 'zoek'));
    }
}
