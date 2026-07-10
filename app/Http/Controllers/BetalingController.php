<?php

namespace App\Http\Controllers;

use App\Models\Betaling;
use App\Models\Student;
use App\Support\Collegegeldstatus;
use App\Support\Collegegeldtermijnen;
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

        $alle = Student::with(['inschrijvingen.periode', 'inschrijvingen.betalingen', 'betalingen'])->get()
            ->map(fn (Student $s) => ['student' => $s, 'status' => Collegegeldstatus::voor($s)]);

        // Studenten met een ACHTERSTAND: een vervallen termijn die niet is voldaan.
        // Een nog niet vervallen termijn is géén achterstand.
        $achterstanden = $alle
            ->filter(fn ($r) => $r['status']['achterstallig'] > 0)
            ->sortByDesc(fn ($r) => $r['status']['achterstallig'])
            ->values();

        // Studenten die teveel hebben betaald (terugbetaling) — inschrijving beëindigd.
        $terugbetalingen = $alle
            ->filter(fn ($r) => $r['status']['terugbetaling'] > 0)
            ->sortByDesc(fn ($r) => $r['status']['terugbetaling'])
            ->values();

        // Nog ingeschreven studenten met een tegoed (teveel betaald, geen terugbetaling).
        $vooruitbetalingen = $alle
            ->filter(fn ($r) => $r['status']['vooruitbetaald'] > 0)
            ->sortByDesc(fn ($r) => $r['status']['vooruitbetaald'])
            ->values();

        $resultaten = $zoek !== ''
            ? Student::where('studentnummer', 'like', $zoek.'%')
                ->orWhere('achternaam', 'like', '%'.$zoek.'%')
                ->orWhere('voornaam', 'like', '%'.$zoek.'%')
                ->orderBy('studentnummer')->limit(20)->get()
            : collect();

        return view('financien.index', compact('achterstanden', 'terugbetalingen', 'vooruitbetalingen', 'resultaten', 'zoek'));
    }

    public function student(Student $student): View
    {
        $student->load([
            'inschrijvingen.opleiding', 'inschrijvingen.periode', 'inschrijvingen.betalingen',
            'betalingen.geregistreerdDoor',
        ]);
        $status = Collegegeldstatus::voor($student);

        // Per inschrijving het termijnschema (de facturen) met betaalstand.
        $regels = $student->inschrijvingen->sortByDesc('inschrijfdatum')->map(fn ($i) => [
            'inschrijving' => $i,
            'tarief' => Collegegeldstatus::tarief($i),
            'maanden' => Collegegeldstatus::maanden($i),
            'regeling' => Collegegeldtermijnen::regeling($i),
            'termijnen' => Collegegeldtermijnen::voor($i),
            'verschuldigd' => Collegegeldtermijnen::totaal($i),
        ]);

        return view('financien.student', compact('student', 'status', 'regels'));
    }

    /** CSV-sjabloon voor de bulk-import van betalingen. */
    public function importSjabloon(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $rijen = [
            ['studentnummer', 'bedrag', 'termijn', 'datum', 'betaalwijze', 'opmerking'],
            ['261001', '4000,00', '', '15-09-2025', 'overboeking', 'Volledig jaarbedrag'],
            ['261011', '800,00', '1', '15-09-2025', 'overboeking', 'Termijn september'],
            ['261011', '800,00', '2', '03-11-2025', 'overboeking', 'Termijn november'],
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

    /**
     * Stap 1 — Controle: lees en valideer het CSV-bestand en toon wat er wordt
     * geïmporteerd (en wat wordt overgeslagen). Er wordt nog NIETS opgeslagen;
     * de te importeren regels worden in de sessie bewaard voor de bevestiging.
     */
    public function importControle(Request $request): View|RedirectResponse
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

        [$regels, $kolom] = $this->leesCsv($bestand->getRealPath());
        if ($regels === []) {
            return back()->withErrors(['bestand' => 'Het bestand bevat geen gegevens.']);
        }

        $geldig = [];
        $fouten = [];

        // $kolom bevat per veldnaam de kolomindex; zo blijven oudere bestanden
        // zonder termijnkolom gewoon werken.
        $veld = fn (array $rij, string $naam) => $kolom[$naam] !== null
            ? trim((string) ($rij[$kolom[$naam]] ?? '')) : '';

        foreach ($regels as $index => $kolommen) {
            $regelnr = $index + 1;
            $nr = $veld($kolommen, 'studentnummer');
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

            $bedrag = $this->parseBedrag($veld($kolommen, 'bedrag'));
            if ($bedrag === null || $bedrag <= 0) {
                $fouten[] = "Regel {$regelnr}: ongeldig bedrag '".$veld($kolommen, 'bedrag')."'.";

                continue;
            }

            // Termijn is optioneel: leeg = automatisch toerekenen aan de oudste
            // openstaande termijn.
            $termijnRaw = $veld($kolommen, 'termijn');
            $termijn = null;
            if ($termijnRaw !== '') {
                if (! ctype_digit($termijnRaw) || (int) $termijnRaw < 1 || (int) $termijnRaw > 5) {
                    $fouten[] = "Regel {$regelnr}: ongeldige termijn '{$termijnRaw}' (verwacht 1 t/m 5, of leeg).";

                    continue;
                }
                $termijn = (int) $termijnRaw;

                $bestaat = Collegegeldtermijnen::voor($insch)
                    ->reject(fn ($t) => $t['vervallen'])
                    ->contains(fn ($t) => $t['nr'] === $termijn);
                if (! $bestaat) {
                    $fouten[] = "Regel {$regelnr}: termijn {$termijn} bestaat niet voor de inschrijving van {$nr}.";

                    continue;
                }
            }

            $datum = $this->parseDatum($veld($kolommen, 'datum'));
            if ($datum === null) {
                $fouten[] = "Regel {$regelnr}: ongeldige datum '".$veld($kolommen, 'datum')."'.";

                continue;
            }

            $geldig[] = [
                'student_id' => $student->id,
                'inschrijving_id' => $insch->id,
                'studentnummer' => $student->studentnummer,
                'naam' => $student->volledigeNaam(),
                'bedrag' => $bedrag,
                'termijn' => $termijn,
                'datum' => $datum,
                'betaalwijze' => $veld($kolommen, 'betaalwijze') ?: null,
                'opmerking' => $veld($kolommen, 'opmerking') ?: null,
            ];
        }

        session()->put('import_preview', $geldig);
        session()->put('import_fouten', $fouten);

        return view('financien.import-controle', [
            'geldig' => $geldig,
            'fouten' => $fouten,
            'bestandsnaam' => $bestand->getClientOriginalName(),
        ]);
    }

    /**
     * Stap 2 — Bevestigen: sla de eerder gecontroleerde regels definitief op.
     * Leest uit de sessie (gevuld door importControle), zodat exact wordt
     * opgeslagen wat de gebruiker heeft gezien.
     */
    public function import(Request $request): RedirectResponse
    {
        $rijen = session('import_preview', []);
        if (empty($rijen)) {
            return redirect()->route('financien')
                ->withErrors(['bestand' => 'Er is geen gecontroleerde import. Upload eerst een CSV.']);
        }

        $aantal = 0;
        foreach ($rijen as $r) {
            Betaling::create([
                'student_id' => $r['student_id'],
                'inschrijving_id' => $r['inschrijving_id'],
                'bedrag' => $r['bedrag'],
                'termijn' => $r['termijn'] ?? null,
                'datum' => $r['datum'],
                'betaalwijze' => $r['betaalwijze'],
                'opmerking' => $r['opmerking'],
                'geregistreerd_door_id' => auth()->id(),
            ]);
            $aantal++;
        }

        $fouten = session('import_fouten', []);
        session()->forget(['import_preview', 'import_fouten']);

        return redirect()->route('financien')->with('import_resultaat', [
            'aantal' => $aantal,
            'fouten' => $fouten,
        ]);
    }

    /**
     * Leest een CSV in, detecteert het scheidingsteken en herkent de kolommen
     * op NAAM uit de kopregel. Zo blijven bestanden zonder termijnkolom — en
     * bestanden met een afwijkende kolomvolgorde — gewoon werken. Zonder
     * kopregel geldt de klassieke volgorde: studentnummer, bedrag, datum,
     * betaalwijze, opmerking.
     *
     * @return array{0: list<array>, 1: array<string, int|null>}
     */
    private function leesCsv(string $pad): array
    {
        $handle = fopen($pad, 'r');
        if ($handle === false) {
            return [[], []];
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

        // Klassieke volgorde zonder termijnkolom (bestanden van vóór de termijnmodule).
        $kolom = ['studentnummer' => 0, 'bedrag' => 1, 'termijn' => null,
            'datum' => 2, 'betaalwijze' => 3, 'opmerking' => 4];

        $heeftKop = isset($regels[0][0]) && ! ctype_digit(trim((string) $regels[0][0]));
        if ($heeftKop) {
            $kop = array_shift($regels);
            $gevonden = [];
            foreach ($kop as $index => $naam) {
                $sleutel = strtolower(trim((string) $naam));
                if (array_key_exists($sleutel, $kolom)) {
                    $gevonden[$sleutel] = $index;
                }
            }
            // Alleen vertrouwen op de kopregel als de verplichte velden erin staan.
            if (isset($gevonden['studentnummer'], $gevonden['bedrag'], $gevonden['datum'])) {
                $kolom = array_merge(['termijn' => null, 'betaalwijze' => null, 'opmerking' => null], $gevonden);
            }
        }

        return [$regels, $kolom];
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
            'termijn' => ['nullable', 'integer', 'min:1', 'max:5'],
            'bedrag' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'datum' => ['required', 'date'],
            'betaalwijze' => ['nullable', 'string', 'max:40'],
            'opmerking' => ['nullable', 'string', 'max:500'],
        ]);

        $inschrijving = $student->inschrijvingen()->findOrFail($data['inschrijving_id']);
        // Uit een formulier komt de termijn als string binnen; het schema werkt
        // met integers. Zonder deze cast faalt de vergelijking hieronder altijd.
        $termijn = ($data['termijn'] ?? null) !== null ? (int) $data['termijn'] : null;

        // Boeken op een termijn die in dit schema niet bestaat (of vervallen is)
        // zou een onvindbaar bedrag opleveren; weiger dat expliciet.
        if ($termijn !== null) {
            $bestaat = Collegegeldtermijnen::voor($inschrijving)
                ->reject(fn ($t) => $t['vervallen'])
                ->contains(fn ($t) => $t['nr'] === $termijn);

            if (! $bestaat) {
                return back()->withInput()->withErrors([
                    'termijn' => 'Deze termijn bestaat niet (meer) voor de gekozen inschrijving.',
                ]);
            }
        }

        $student->betalingen()->create([
            'inschrijving_id' => $inschrijving->id,
            'termijn' => $termijn,
            'bedrag' => $data['bedrag'],
            'datum' => $data['datum'],
            'betaalwijze' => $data['betaalwijze'] ?? null,
            'opmerking' => $data['opmerking'] ?? null,
            'geregistreerd_door_id' => auth()->id(),
        ]);

        return redirect()->route('financien.student', $student)
            ->with('status', $termijn !== null
                ? "Betaling geboekt op termijn {$termijn}."
                : 'Betaling geregistreerd en toegerekend aan de oudste openstaande termijn.');
    }
}
