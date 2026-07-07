<?php

namespace App\Http\Controllers;

use App\Models\Betaling;
use App\Models\Student;
use App\Support\Collegegeldstatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Financiële Administratie: betalingen registreren en betalingsachterstanden
 * inzien. Het systeem leidt de achterstand automatisch af uit collegegeld en
 * geregistreerde betalingen.
 */
class BetalingController extends Controller
{
    public function index(Request $request): View
    {
        $zoek = trim((string) $request->query('q', ''));

        $alle = Student::with(['inschrijvingen.periode', 'betalingen'])->get()
            ->map(fn (Student $s) => ['student' => $s, 'status' => Collegegeldstatus::voor($s)]);

        // Studenten met een openstaande schuld.
        $achterstanden = $alle
            ->filter(fn ($r) => $r['status']['openstaand'] > 0)
            ->sortByDesc(fn ($r) => $r['status']['openstaand'])
            ->values();

        // Studenten die teveel hebben betaald (terugbetaling).
        $terugbetalingen = $alle
            ->filter(fn ($r) => $r['status']['terugbetaling'] > 0)
            ->sortByDesc(fn ($r) => $r['status']['terugbetaling'])
            ->values();

        $resultaten = $zoek !== ''
            ? Student::where('studentnummer', 'like', $zoek.'%')
                ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                ->orWhere('voornaam', 'like', '%'.$zoek.'%')
                ->orderBy('studentnummer')->limit(20)->get()
            : collect();

        return view('financien.index', compact('achterstanden', 'terugbetalingen', 'resultaten', 'zoek'));
    }

    public function student(Student $student): View
    {
        $student->load(['inschrijvingen.opleiding', 'inschrijvingen.periode', 'betalingen.geregistreerdDoor']);
        $status = Collegegeldstatus::voor($student);

        // Per inschrijving het (pro rata) verschuldigde bedrag tonen.
        $regels = $student->inschrijvingen->sortByDesc('inschrijfdatum')->map(fn ($i) => [
            'inschrijving' => $i,
            'tarief' => Collegegeldstatus::tarief($i),
            'maanden' => Collegegeldstatus::maanden($i),
            'verschuldigd' => Collegegeldstatus::verschuldigd($i),
        ]);

        return view('financien.student', compact('student', 'status', 'regels'));
    }

    /** CSV-sjabloon voor de bulk-import van betalingen. */
    public function importSjabloon(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rijen = [
            ['studentnummer', 'bedrag', 'datum', 'betaalwijze', 'opmerking'],
            ['261001', '4000,00', '15-09-2025', 'overboeking', 'Jaarbetaling'],
            ['261011', '2000,00', '15-09-2025', 'termijn', '1e termijn'],
        ];

        return response()->streamDownload(function () use ($rijen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM zodat Excel accenten goed toont
            foreach ($rijen as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, 'betalingen-sjabloon.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Bulk-import van betalingen uit een CSV-bestand (Excel -> Opslaan als CSV). */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'bestand' => ['required', 'file', 'max:5120'],
        ]);

        $bestand = $request->file('bestand');
        $ext = strtolower($bestand->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'txt'], true)) {
            return back()->withErrors([
                'bestand' => 'Upload een CSV-bestand. Sla het Excel-bestand eerst op als CSV (Bestand → Opslaan als → CSV).',
            ]);
        }

        $regels = $this->leesCsv($bestand->getRealPath());
        if ($regels === []) {
            return back()->withErrors(['bestand' => 'Het bestand bevat geen gegevens.']);
        }

        $aantal = 0;
        $fouten = [];

        foreach ($regels as $index => $kolommen) {
            $regelnr = $index + 1;
            $nr = trim((string) ($kolommen[0] ?? ''));
            if ($nr === '') {
                continue; // lege regel overslaan
            }

            $student = Student::where('studentnummer', $nr)->first();
            if (! $student) {
                $fouten[] = "Regel {$regelnr}: studentnummer {$nr} niet gevonden.";

                continue;
            }

            $insch = $student->inschrijvingen()->orderByDesc('inschrijfdatum')->first();
            if (! $insch) {
                $fouten[] = "Regel {$regelnr}: {$nr} heeft geen inschrijving.";

                continue;
            }

            $bedrag = $this->parseBedrag($kolommen[1] ?? '');
            if ($bedrag === null || $bedrag <= 0) {
                $fouten[] = "Regel {$regelnr}: ongeldig bedrag '".($kolommen[1] ?? '')."'.";

                continue;
            }

            $datum = $this->parseDatum($kolommen[2] ?? '');
            if ($datum === null) {
                $fouten[] = "Regel {$regelnr}: ongeldige datum '".($kolommen[2] ?? '')."'.";

                continue;
            }

            $student->betalingen()->create([
                'inschrijving_id' => $insch->id,
                'bedrag' => $bedrag,
                'datum' => $datum,
                'betaalwijze' => trim((string) ($kolommen[3] ?? '')) ?: null,
                'opmerking' => trim((string) ($kolommen[4] ?? '')) ?: null,
                'geregistreerd_door_id' => auth()->id(),
            ]);
            $aantal++;
        }

        return redirect()->route('financien')->with('import_resultaat', [
            'aantal' => $aantal,
            'fouten' => $fouten,
        ]);
    }

    /** Leest een CSV in, detecteert het scheidingsteken en slaat de kopregel over. */
    private function leesCsv(string $pad): array
    {
        $handle = fopen($pad, 'r');
        if ($handle === false) {
            return [];
        }

        $eerste = fgets($handle);
        $delim = substr_count((string) $eerste, ';') >= substr_count((string) $eerste, ',') ? ';' : ',';
        rewind($handle);

        $regels = [];
        while (($kolommen = fgetcsv($handle, 0, $delim)) !== false) {
            $regels[] = $kolommen;
        }
        fclose($handle);

        // BOM verwijderen uit de eerste cel.
        if (isset($regels[0][0])) {
            $regels[0][0] = ltrim($regels[0][0], "\xEF\xBB\xBF");
        }

        // Kopregel overslaan als de eerste cel geen studentnummer is.
        if (isset($regels[0][0]) && ! ctype_digit(trim((string) $regels[0][0]))) {
            array_shift($regels);
        }

        return $regels;
    }

    /** Normaliseert een bedrag: verwerkt Nederlandse notatie (1.234,56) en eurotekens. */
    private function parseBedrag(string $raw): ?float
    {
        $s = str_replace(['€', ' ', "\xc2\xa0"], '', trim($raw));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);      // duizendtalscheiding
            $s = str_replace(',', '.', $s);     // decimaalkomma
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /** Accepteert diverse datumnotaties en geeft Y-m-d terug. */
    private function parseDatum(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd-m-y', 'Y/m/d', 'd.m.Y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $s);
            if ($d !== false && $d->format($fmt) === $s) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    public function registreer(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'inschrijving_id' => ['required', Rule::exists('inschrijvingen', 'id')->where('student_id', $student->id)],
            'bedrag' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'datum' => ['required', 'date'],
            'betaalwijze' => ['nullable', 'string', 'max:40'],
            'opmerking' => ['nullable', 'string', 'max:500'],
        ]);

        $student->betalingen()->create([
            'inschrijving_id' => $data['inschrijving_id'],
            'bedrag' => $data['bedrag'],
            'datum' => $data['datum'],
            'betaalwijze' => $data['betaalwijze'] ?? null,
            'opmerking' => $data['opmerking'] ?? null,
            'geregistreerd_door_id' => auth()->id(),
        ]);

        return redirect()->route('financien.student', $student)
            ->with('status', 'Betaling geregistreerd.');
    }
}
