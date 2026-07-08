<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Models\Land;
use App\Models\Nationaliteit;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Student;
use App\Support\AuditLogger;
use App\Support\CsvLezer;
use App\Support\StudentnummerGenerator;
use App\Support\Vaktoewijzer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bulk-inschrijving van nieuwe studenten uit een CSV-export van het publieke
 * aanmeldportaal. Twee stappen: eerst controleren (wat wordt aangemaakt, wat
 * wordt overgeslagen), daarna definitief inschrijven. Studentnummers worden
 * gegenereerd; de vakken van het studiejaar worden automatisch toegewezen.
 *
 * AVG: het BSN wordt NIET geïmporteerd (pas na akkoord FG). In ontwikkeling
 * uitsluitend synthetische data gebruiken.
 */
class BulkInschrijvingController extends Controller
{
    /** Kanonieke velden -> geaccepteerde (genormaliseerde) kopnamen. */
    private const VELDEN = [
        'studentnummer' => ['studentnummer', 'studentnr'],
        'voornaam' => ['voornaam', 'voornamen', 'first name', 'firstname'],
        'tussenvoegsel' => ['tussenvoegsel'],
        'achternaam' => ['achternaam', 'familienaam', 'last name', 'lastname', 'naam'],
        'geboortedatum' => ['geboortedatum', 'geboorte datum', 'birthdate', 'date of birth'],
        'geslacht' => ['geslacht', 'sekse', 'gender'],
        'nationaliteit' => ['nationaliteit', 'nationality'],
        'email' => ['email', 'emailadres', 'e mailadres', 'e mail', 'mail'],
        'telefoon' => ['telefoon', 'telefoonnummer', 'tel', 'phone'],
        'straat' => ['straat', 'adres', 'street'],
        'huisnummer' => ['huisnummer', 'huisnr', 'nr'],
        'postcode' => ['postcode', 'zip'],
        'stad' => ['stad', 'woonplaats', 'plaats', 'city'],
        'provincie' => ['provincie', 'province'],
        'land' => ['land', 'country'],
        'iban' => ['iban', 'iban kandidaat', 'iban rekeningnummer', 'rekeningnummer'],
        'diploma' => ['diploma', 'vooropleiding type', 'hoogst behaalde diploma', 'hoogste diploma'],
        'onderwijsinstelling' => ['onderwijsinstelling', 'naam onderwijsinstelling van vorige opleiding', 'naam onderwijsinstelling', 'vorige instelling', 'school'],
        'afstudeerjaar' => ['afstudeerjaar', 'jaar afstuderen', 'afstudeerjaar van vorige opleiding', 'afstudeer jaar'],
        'opleiding' => ['opleiding', 'programme label', 'programme slug', 'programme', 'opleidingcode', 'opleiding code', 'studie', 'programma'],
        'leerjaar' => ['leerjaar', 'jaar'],
    ];

    public function form(): View
    {
        return view('bulk-inschrijven.index');
    }

    public function sjabloon(): StreamedResponse
    {
        $rijen = [
            ['voornaam', 'tussenvoegsel', 'achternaam', 'geboortedatum', 'geslacht', 'nationaliteit', 'email', 'telefoon', 'straat', 'huisnummer', 'postcode', 'stad', 'provincie', 'land', 'iban', 'diploma', 'onderwijsinstelling', 'afstudeerjaar', 'opleiding', 'leerjaar'],
            ['Yasmin', '', 'Demir', '14-03-2004', 'V', 'Nederlandse', 'yasmin.demir@example.com', '0612345678', 'Bergsingel', '12', '3037 AB', 'Rotterdam', 'Zuid-Holland', 'Nederland', 'NL00BANK0123456789', 'VWO', 'Erasmiaans Gymnasium', '2022', 'ISLTH', '1'],
        ];

        return response()->streamDownload(function () use ($rijen) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rijen as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, 'bulk-inschrijven-sjabloon.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stap 1 — controle: lees en valideer, sla nog niets op. */
    public function controle(Request $request): View|RedirectResponse
    {
        $request->validate(['bestand' => ['required', 'file', 'max:5120']]);

        $bestand = $request->file('bestand');
        if (! in_array(strtolower($bestand->getClientOriginalExtension()), ['csv', 'txt'], true)) {
            return back()->withErrors(['bestand' => 'Upload een CSV-bestand (Excel → Opslaan als CSV).']);
        }

        $rijen = CsvLezer::associatief($bestand->getRealPath());
        if ($rijen === []) {
            return back()->withErrors(['bestand' => 'Het bestand bevat geen gegevens.']);
        }

        $perioden = Periode::orderByDesc('startdatum')->get();
        if ($perioden->isEmpty()) {
            return back()->withErrors(['bestand' => 'Er is geen studiejaar ingesteld.']);
        }
        $standaardPeriode = $perioden->firstWhere('actief', true) ?? $perioden->first();

        $geldig = [];
        $fouten = [];

        foreach ($rijen as $index => $rij) {
            $regelnr = $index + 2; // +1 kopregel, +1 voor 1-indexering
            $voornaam = $this->haal($rij, 'voornaam');
            $achternaam = $this->haal($rij, 'achternaam');
            $oplWaarde = $this->haal($rij, 'opleiding');

            if (! $voornaam || ! $achternaam) {
                $fouten[] = "Regel {$regelnr}: voornaam en achternaam zijn verplicht.";

                continue;
            }
            $opleiding = $this->vindOpleiding($oplWaarde);
            if (! $opleiding) {
                $fouten[] = "Regel {$regelnr}: opleiding '".($oplWaarde ?? '')."' niet gevonden.";

                continue;
            }

            $geboortedatum = $this->parseDatum($this->haal($rij, 'geboortedatum'));
            $email = $this->haal($rij, 'email');

            // Duplicaatdetectie op e-mail of naam + geboortedatum.
            if ($this->bestaatAl($voornaam, $achternaam, $geboortedatum, $email)) {
                $fouten[] = "Regel {$regelnr}: {$voornaam} {$achternaam} lijkt al te bestaan (naam/e-mail) — overgeslagen.";

                continue;
            }

            $geldig[] = [
                'voornaam' => $voornaam,
                'tussenvoegsel' => $this->haal($rij, 'tussenvoegsel'),
                'achternaam' => $achternaam,
                'naam' => trim($voornaam.' '.$achternaam),
                'geboortedatum' => $geboortedatum,
                'geslacht' => $this->parseGeslacht($this->haal($rij, 'geslacht')),
                'nationaliteit_id' => $this->vindId(Nationaliteit::class, $this->haal($rij, 'nationaliteit')),
                'land_id' => $this->vindLand($this->haal($rij, 'land')),
                'email_prive' => $email,
                'telefoon' => $this->haal($rij, 'telefoon'),
                'adres' => $this->haal($rij, 'straat'),
                'huisnummer' => $this->haal($rij, 'huisnummer'),
                'postcode' => $this->haal($rij, 'postcode'),
                'woonplaats' => $this->haal($rij, 'stad'),
                'provincie' => $this->haal($rij, 'provincie'),
                'rekeningnummer' => $this->haal($rij, 'iban'),
                'diploma' => $this->haal($rij, 'diploma'),
                'vorige_instelling' => $this->haal($rij, 'onderwijsinstelling'),
                'afstudeerjaar' => $this->haal($rij, 'afstudeerjaar'),
                'opleiding_id' => $opleiding->id,
                'opleiding' => $opleiding->code,
                'leerjaar' => (int) ($this->haal($rij, 'leerjaar') ?: 1),
            ];
        }

        session()->put('bulk_preview', $geldig);
        session()->put('bulk_fouten', $fouten);

        return view('bulk-inschrijven.controle', [
            'geldig' => $geldig,
            'fouten' => $fouten,
            'perioden' => $perioden,
            'standaardPeriodeId' => $standaardPeriode->id,
            'bestandsnaam' => $bestand->getClientOriginalName(),
        ]);
    }

    /** Stap 2 — bevestigen: schrijf de gecontroleerde rijen definitief in. */
    public function importeer(Request $request): RedirectResponse
    {
        $rijen = session('bulk_preview', []);
        if (empty($rijen)) {
            return redirect()->route('bulk-inschrijven')
                ->withErrors(['bestand' => 'Geen gecontroleerde import. Upload eerst een CSV.']);
        }

        $data = $request->validate(['periode_id' => ['nullable', 'exists:perioden,id']]);
        $periode = ! empty($data['periode_id'])
            ? Periode::findOrFail($data['periode_id'])
            : Periode::where('actief', true)->firstOrFail();

        // Studentnummer-jaarprefix volgt het intakejaar (start van het studiejaar).
        $jaar = (int) ($periode->startdatum?->format('Y') ?? now()->format('Y'));
        // Toekomstig studiejaar: inschrijfdatum = start van dat studiejaar; anders vandaag.
        $inschrijfdatum = ($periode->startdatum && $periode->startdatum->isFuture())
            ? $periode->startdatum->toDateString()
            : now()->toDateString();
        $aantal = 0;

        foreach ($rijen as $r) {
            DB::transaction(function () use ($r, $periode, $jaar, $inschrijfdatum, &$aantal) {
                $student = null;
                for ($poging = 0; $poging < 5; $poging++) {
                    try {
                        $student = Student::create([
                            'studentnummer' => StudentnummerGenerator::genereer($jaar),
                            'voornaam' => $r['voornaam'],
                            'tussenvoegsel' => $r['tussenvoegsel'] ?? null,
                            'achternaam' => $r['achternaam'],
                            'geboortedatum' => $r['geboortedatum'] ?? null,
                            'geslacht' => $r['geslacht'] ?? null,
                            'nationaliteit_id' => $r['nationaliteit_id'] ?? null,
                            'land_id' => $r['land_id'] ?? null,
                            'email_prive' => $r['email_prive'] ?? null,
                            'telefoon' => $r['telefoon'] ?? null,
                            'adres' => $r['adres'] ?? null,
                            'huisnummer' => $r['huisnummer'] ?? null,
                            'postcode' => $r['postcode'] ?? null,
                            'woonplaats' => $r['woonplaats'] ?? null,
                            'provincie' => $r['provincie'] ?? null,
                            'rekeningnummer' => $r['rekeningnummer'] ?: null,
                            'diploma' => $r['diploma'] ?? null,
                            'vorige_instelling' => $r['vorige_instelling'] ?? null,
                            'afstudeerjaar' => $r['afstudeerjaar'] ?? null,
                            // BSN wordt NIET geïmporteerd (pas na akkoord FG).
                        ]);
                        break;
                    } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                        continue;
                    }
                }
                if ($student === null) {
                    return;
                }

                $inschrijving = Inschrijving::create([
                    'student_id' => $student->id,
                    'opleiding_id' => $r['opleiding_id'],
                    'periode_id' => $periode->id,
                    'leerjaar' => $r['leerjaar'] ?? 1,
                    'status' => 'actief',
                    'inschrijfdatum' => $inschrijfdatum,
                    'invoerdatum' => now()->toDateString(),
                ]);

                Vaktoewijzer::wijsToe($inschrijving);

                AuditLogger::log(AuditLogger::AANMAAK, $student, veld: 'inschrijving', context: [
                    'studentnummer' => $student->studentnummer, 'bron' => 'bulk-import',
                ]);
                $aantal++;
            });
        }

        $fouten = session('bulk_fouten', []);
        session()->forget(['bulk_preview', 'bulk_fouten']);

        return redirect()->route('studenten.index')
            ->with('status', "{$aantal} student(en) ingeschreven via bulk-import.".
                (count($fouten) ? ' '.count($fouten).' regel(s) overgeslagen.' : ''));
    }

    private function haal(array $rij, string $veld): ?string
    {
        foreach (self::VELDEN[$veld] as $syn) {
            if (array_key_exists($syn, $rij) && trim((string) $rij[$syn]) !== '') {
                return trim((string) $rij[$syn]);
            }
        }

        return null;
    }

    private function vindOpleiding(?string $waarde): ?Opleiding
    {
        if (! $waarde) {
            return null;
        }
        $w = trim($waarde);

        $opleiding = Opleiding::where('code', $w)->orWhere('naam', 'like', '%'.$w.'%')->first();
        if ($opleiding) {
            return $opleiding;
        }

        // Slug uit het portaal (bv. "bachelor-islamitische-theologie").
        if (str_contains($w, '-')) {
            return Opleiding::where('naam', 'like', '%'.str_replace('-', ' ', $w).'%')->first();
        }

        return null;
    }

    private function vindLand(?string $waarde): ?int
    {
        if (! $waarde) {
            return null;
        }

        return Land::where('naam', 'like', $waarde)->orWhere('code', $waarde)->value('id');
    }

    private function vindId(string $model, ?string $naam): ?int
    {
        if (! $naam) {
            return null;
        }

        return $model::where('naam', 'like', $naam)->value('id');
    }

    private function bestaatAl(string $voornaam, string $achternaam, ?string $geboortedatum, ?string $email): bool
    {
        if ($email && Student::where('email', $email)->orWhere('email_prive', $email)->exists()) {
            return true;
        }

        return Student::where('voornaam', $voornaam)
            ->where('achternaam', $achternaam)
            ->when($geboortedatum, fn ($q) => $q->where('geboortedatum', $geboortedatum))
            ->exists();
    }

    private function parseDatum(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y', 'd.m.Y', 'd-m-y'] as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $raw);
            if ($d !== false && $d->format($fmt) === $raw) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseGeslacht(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $r = mb_strtolower($raw);

        return match (true) {
            in_array($r, ['m', 'man', 'male', 'jongen'], true) => 'M',
            in_array($r, ['v', 'vrouw', 'female', 'f', 'meisje'], true) => 'V',
            default => 'X',
        };
    }
}
