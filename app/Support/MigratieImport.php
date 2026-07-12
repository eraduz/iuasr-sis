<?php

namespace App\Support;

use App\Models\Faculteit;
use App\Models\Inschrijving;
use App\Models\Nationaliteit;
use App\Models\Opleiding;
use App\Models\Periode;
use App\Models\Resultaat;
use App\Models\Student;
use App\Models\Toetsonderdeel;
use App\Models\Vak;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Import van de oude Access-database (IUTSTD) naar het SIS. Werkt op de per-jaar
 * geëxporteerde CSV's. Deze eerste fase importeert de STUDENTEN (studentnummer +
 * persoonsgegevens); cijfers/inschrijvingen volgen in een aparte stap.
 *
 * Altijd met een DRY-RUN vooraf: `verwerkStudenten($rijen, dryRun: true)` schrijft
 * niets en rapporteert wat er zou gebeuren. Idempotent: een bestaand studentnummer
 * wordt niet overschreven (behoudt eventuele latere SIS-wijzigingen).
 */
class MigratieImport
{
    /** Code van de aparte historische opleiding waar alle gemigreerde data onder valt. */
    public const HIST_OPLEIDING_CODE = 'BA-HIST';

    /** Standaard Nederlandse voldoende-grens (op 0–10-schaal) voor gemigreerde cijfers. */
    private const VOLDOENDE = 5.5;

    /**
     * @param  list<array<string,string>>  $rijen  associatieve CSV-rijen (Access-kolomnamen)
     * @return array{nieuw:int,overgeslagen:int,fouten:list<string>,voorbeeld:list<array<string,string>>,totaal:int}
     */
    public function verwerkStudenten(array $rijen, bool $dryRun = true): array
    {
        $nieuw = 0;
        $overgeslagen = 0;
        $leeg = 0;
        $fouten = [];
        $voorbeeld = [];

        $verwerk = function () use ($rijen, &$nieuw, &$overgeslagen, &$leeg, &$fouten, &$voorbeeld, $dryRun) {
            foreach ($rijen as $index => $rij) {
                $nummer = trim($this->veld($rij, 'SDT-NR'));
                $voornaam = trim($this->veld($rij, 'Voornaam'));
                $achternaam = trim($this->veld($rij, 'Achternaam'));

                // Junk-/lege regels overslaan (geen nummer, of geen enkele naam).
                if ($nummer === '' || $nummer === '0' || ($voornaam === '' && $achternaam === '')) {
                    $leeg++;

                    continue;
                }
                if (Student::where('studentnummer', $nummer)->exists()) {
                    $overgeslagen++;

                    continue;
                }

                try {
                    $velden = $this->mapStudent($rij, $nummer);
                    if (! $dryRun) {
                        Student::create($velden);
                    }
                    $nieuw++;
                    if (count($voorbeeld) < 8) {
                        $voorbeeld[] = [
                            'studentnummer' => $nummer,
                            'naam' => trim(($velden['voornaam'] ?? '').' '.($velden['achternaam'] ?? '')),
                            'geboortedatum' => (string) ($velden['geboortedatum'] ?? ''),
                            'diploma' => ! empty($velden['diploma']) ? 'ja' : 'nee',
                        ];
                    }
                } catch (\Throwable $e) {
                    $fouten[] = 'rij '.($index + 2).' ('.$nummer.'): '.$e->getMessage();
                }
            }
        };

        if ($dryRun) {
            $verwerk();
        } else {
            DB::transaction($verwerk);
        }

        return [
            'nieuw' => $nieuw,
            'overgeslagen' => $overgeslagen,
            'leeg' => $leeg,
            'fouten' => $fouten,
            'voorbeeld' => $voorbeeld,
            'totaal' => count($rijen),
        ];
    }

    /** @return array<string,mixed> */
    private function mapStudent(array $rij, string $nummer): array
    {
        $voornaam = trim($this->veld($rij, 'Voornaam'));
        $achternaam = trim($this->veld($rij, 'Achternaam'));

        return [
            'studentnummer' => $nummer,
            'aanhef' => $this->leeg($this->veld($rij, 'Aanhef')),
            'voornaam' => $voornaam !== '' ? $voornaam : '—',
            'roepnaam' => $voornaam !== '' ? $voornaam : null,
            'achternaam' => $achternaam !== '' ? $achternaam : '—',
            'geslacht' => $this->geslacht($this->veld($rij, 'Aanhef')),
            'geboortedatum' => $this->datum($this->veld($rij, 'Gb Datum')),
            'geboorteplaats' => $this->leeg($this->veld($rij, 'Gb Plaats')),
            'nationaliteit_id' => $this->nationaliteit($this->veld($rij, 'Nationaliteit1')),
            'adres' => $this->leeg($this->veld($rij, 'Adres')),
            'postcode' => $this->leeg($this->veld($rij, 'Pc')),
            'woonplaats' => $this->leeg($this->veld($rij, 'Plaats')),
            'telefoon' => $this->leeg($this->veld($rij, 'Tel')),
            'email' => $this->leeg($this->veld($rij, 'E-mail')),
            'vooropleiding' => $this->leeg($this->veld($rij, 'Opleiding')), // Access 'Opleiding' = vooropleiding
            'diploma' => $this->boolean($this->veld($rij, 'Diploma')),
            'opmerkingen' => $this->opmerking($rij),
        ];
    }

    /**
     * Fase 2a — VAKKEN uit `_vaklijsten.csv`. Elk oud vak wordt als (inactief)
     * historisch vak onder de aparte historische opleiding gezet; de EC-punten
     * komen uit de vaklijst. Idempotent op (opleiding, code).
     *
     * @param  list<array<string,string>>  $rijen
     * @return array{nieuw:int,overgeslagen:int,leeg:int,fouten:list<string>,voorbeeld:list<array<string,string>>,totaal:int}
     */
    public function verwerkVakken(array $rijen, bool $dryRun = true): array
    {
        $nieuw = 0;
        $overgeslagen = 0;
        $leeg = 0;
        $fouten = [];
        $voorbeeld = [];

        $verwerk = function () use ($rijen, &$nieuw, &$overgeslagen, &$leeg, &$fouten, &$voorbeeld, $dryRun) {
            $opleiding = $this->historischeOpleiding(! $dryRun);
            $bestaand = $opleiding?->id
                ? Vak::where('opleiding_id', $opleiding->id)->pluck('id', 'code')->all()
                : [];

            foreach ($rijen as $index => $rij) {
                $code = trim($this->veld($rij, 'Vak id'));
                $naam = trim($this->veld($rij, 'Vak naam'));

                if ($code === '' || $code === '0' || $naam === '') {
                    $leeg++;

                    continue;
                }
                if (array_key_exists($code, $bestaand)) {
                    $overgeslagen++;

                    continue;
                }

                try {
                    $ec = $this->parseGetal($this->veld($rij, 'EC'));
                    if (! $dryRun && $opleiding) {
                        $vak = Vak::create([
                            'opleiding_id' => $opleiding->id,
                            'code' => $code,
                            'naam' => $naam,
                            'ec' => $ec ?? 0,
                            'keuzevak' => false,
                            'actief' => false,
                        ]);
                        $bestaand[$code] = $vak->id;
                    } else {
                        $bestaand[$code] = true; // voorkom dubbeltelling binnen dezelfde preview
                    }
                    $nieuw++;
                    if (count($voorbeeld) < 8) {
                        $voorbeeld[] = ['code' => $code, 'naam' => $naam, 'ec' => $ec !== null ? rtrim(rtrim(number_format($ec, 1, '.', ''), '0'), '.') : '—'];
                    }
                } catch (\Throwable $e) {
                    $fouten[] = 'rij '.($index + 2).' ('.$code.'): '.$e->getMessage();
                }
            }
        };

        $dryRun ? $verwerk() : DB::transaction($verwerk);

        return compact('nieuw', 'overgeslagen', 'leeg', 'fouten', 'voorbeeld') + ['totaal' => count($rijen)];
    }

    /**
     * Fase 2b — CIJFERS uit een `cijfers-JJJJ-JJJJ.csv`. Per regel één eindcijfer
     * (`cl-gemmid`, 0–100 → 0–10) voor student+vak+periode, opgeslagen als één
     * resultaat op het onderdeel "Eindcijfer (gemigreerd)". Ontbrekende vakken
     * worden alsnog aangemaakt; inschrijving per student+periode wordt hergebruikt.
     * Idempotent: een bestaand eindcijfer wordt niet opnieuw aangemaakt.
     *
     * @param  list<array<string,string>>  $rijen
     * @return array{nieuw:int,overgeslagen:int,geen_cijfer:int,student_onbekend:int,vakken_bij:int,inschrijvingen_bij:int,fouten:list<string>,voorbeeld:list<array<string,string>>,totaal:int}
     */
    public function verwerkCijfers(array $rijen, bool $dryRun = true): array
    {
        $nieuw = 0;
        $overgeslagen = 0;
        $geenCijfer = 0;
        $studentOnbekend = 0;
        $vakkenBij = 0;
        $inschrijvingenBij = 0;
        $fouten = [];
        $voorbeeld = [];

        $verwerk = function () use ($rijen, &$nieuw, &$overgeslagen, &$geenCijfer, &$studentOnbekend, &$vakkenBij, &$inschrijvingenBij, &$fouten, &$voorbeeld, $dryRun) {
            $opleiding = $this->historischeOpleiding(! $dryRun);
            $oplId = $opleiding?->id;

            // Caches om per bestand niet honderden queries te doen.
            $studenten = Student::pluck('id', 'studentnummer')->all();
            $vakken = $oplId ? Vak::where('opleiding_id', $oplId)->pluck('id', 'code')->all() : [];
            $onderdelen = [];   // vak_id => toetsonderdeel_id
            $perioden = [];     // code => periode_id
            $inschr = [];       // "student_id|periode_id" => inschrijving_id
            $gezien = [];       // "inschr|onderdeel|student" => true  (dubbele regels binnen één preview)

            foreach ($rijen as $index => $rij) {
                $nummer = trim($this->veld($rij, 'CL-STD-NR'));
                $vakcode = trim($this->veld($rij, 'C-VAK-ID'));
                $vaknaam = trim($this->veld($rij, 'CLVAK NAAM'));
                $periodeCode = $this->normaliseerPeriode($this->veld($rij, 'CL-PERIODE'));
                $vrijstelling = $this->boolean($this->veld($rij, 'vrijstelling'));
                $gemmid = $this->parseGetal($this->veld($rij, 'cl-gemmid'));

                if ($nummer === '' || $vakcode === '' || $vakcode === '0') {
                    $geenCijfer++;

                    continue;
                }
                // Zonder cijfer én zonder vrijstelling valt er niets te bewaren.
                if (! $vrijstelling && (! $gemmid || $gemmid <= 0)) {
                    $geenCijfer++;

                    continue;
                }
                if (! array_key_exists($nummer, $studenten)) {
                    $studentOnbekend++;

                    continue;
                }
                if ($periodeCode === null) {
                    $fouten[] = 'rij '.($index + 2).': onleesbare periode "'.trim($this->veld($rij, 'CL-PERIODE')).'"';

                    continue;
                }

                try {
                    $studentId = $studenten[$nummer];

                    // Vak (maak aan als het nog niet bestaat).
                    if (! array_key_exists($vakcode, $vakken)) {
                        if (! $dryRun && $oplId) {
                            $vak = Vak::create(['opleiding_id' => $oplId, 'code' => $vakcode, 'naam' => $vaknaam !== '' ? $vaknaam : $vakcode, 'ec' => 0, 'keuzevak' => false, 'actief' => false]);
                            $vakken[$vakcode] = $vak->id;
                        } else {
                            $vakken[$vakcode] = 'nieuw:'.$vakcode;
                        }
                        $vakkenBij++;
                    }
                    $vakId = $vakken[$vakcode];

                    // Periode (find/create op code).
                    if (! array_key_exists($periodeCode, $perioden)) {
                        $perioden[$periodeCode] = $this->periodeVoor($periodeCode, $dryRun);
                    }
                    $periodeId = $perioden[$periodeCode];

                    // Inschrijving per student+periode+opleiding.
                    $ik = $studentId.'|'.$periodeId;
                    if (! array_key_exists($ik, $inschr)) {
                        [$inschr[$ik], $nieuweInschr] = $this->inschrijvingVoor($studentId, $oplId, $periodeId, $dryRun);
                        if ($nieuweInschr) {
                            $inschrijvingenBij++;
                        }
                    }
                    $inschrijvingId = $inschr[$ik];

                    // Onderdeel "Eindcijfer (gemigreerd)" per vak.
                    if (! array_key_exists($vakId, $onderdelen)) {
                        $onderdelen[$vakId] = $this->eindcijferOnderdeel($vakId, $dryRun);
                    }
                    $onderdeelId = $onderdelen[$vakId];

                    // Dubbele regel binnen dezelfde run afvangen.
                    $sleutel = $inschrijvingId.'|'.$onderdeelId.'|'.$studentId;
                    if (isset($gezien[$sleutel])) {
                        $overgeslagen++;

                        continue;
                    }
                    $gezien[$sleutel] = true;

                    // Al aanwezig in de database? (idempotent bij herhaalde import)
                    if (! $dryRun && is_int($inschrijvingId) && is_int($onderdeelId)
                        && Resultaat::where('inschrijving_id', $inschrijvingId)->where('toetsonderdeel_id', $onderdeelId)->where('student_id', $studentId)->exists()) {
                        $overgeslagen++;

                        continue;
                    }

                    $cijfer = $vrijstelling ? null : round(min($gemmid, 100) / 10, 1);
                    if (! $dryRun) {
                        Resultaat::create([
                            'inschrijving_id' => $inschrijvingId,
                            'student_id' => $studentId,
                            'toetsonderdeel_id' => $onderdeelId,
                            'poging' => 'tentamen',
                            'poging_nr' => 1,
                            'vrijstelling' => $vrijstelling,
                            'cijfer' => $cijfer,
                            'voldoende' => $vrijstelling ? true : ($cijfer >= self::VOLDOENDE),
                            'definitief' => true,
                            'opmerking' => '[gemigreerd uit Access]',
                        ]);
                    }
                    $nieuw++;
                    if (count($voorbeeld) < 10) {
                        $voorbeeld[] = [
                            'studentnummer' => $nummer,
                            'vak' => $vakcode.' — '.($vaknaam !== '' ? $vaknaam : $vakcode),
                            'periode' => $periodeCode,
                            'cijfer' => $vrijstelling ? 'vrijstelling' : number_format((float) $cijfer, 1, ',', ''),
                        ];
                    }
                } catch (\Throwable $e) {
                    $fouten[] = 'rij '.($index + 2).' ('.$nummer.'/'.$vakcode.'): '.$e->getMessage();
                }
            }
        };

        $dryRun ? $verwerk() : DB::transaction($verwerk);

        return compact('nieuw', 'overgeslagen', 'geenCijfer', 'studentOnbekend', 'vakkenBij', 'inschrijvingenBij', 'fouten', 'voorbeeld')
            + ['geen_cijfer' => $geenCijfer, 'student_onbekend' => $studentOnbekend, 'vakken_bij' => $vakkenBij, 'inschrijvingen_bij' => $inschrijvingenBij, 'totaal' => count($rijen)];
    }

    /** De aparte historische opleiding (aangemaakt zodra $persist true is). */
    private function historischeOpleiding(bool $persist): ?Opleiding
    {
        $bestaand = Opleiding::where('code', self::HIST_OPLEIDING_CODE)->first();
        if ($bestaand || ! $persist) {
            return $bestaand;
        }

        $faculteit = Faculteit::where('code', 'FIW')->first() ?? Faculteit::first();

        return Opleiding::create([
            'faculteit_id' => $faculteit?->id,
            'code' => self::HIST_OPLEIDING_CODE,
            'naam' => 'Bachelor Islamitische Theologie (historisch t/m 2025)',
            'soort' => 'bachelor',
            'actief' => false,
        ]);
    }

    /** Periode-id voor een studiejaarcode ("2016-2017"); maakt de periode aan als die ontbreekt. */
    private function periodeVoor(string $code, bool $dryRun): int|string
    {
        $bestaand = Periode::where('code', $code)->value('id');
        if ($bestaand) {
            return $bestaand;
        }
        if ($dryRun) {
            return 'nieuw:'.$code;
        }
        [$van] = explode('-', $code);
        $start = (int) $van;

        return Periode::create([
            'code' => $code,
            'naam' => 'Studiejaar '.$code,
            'startdatum' => sprintf('%04d-09-01', $start),
            'einddatum' => sprintf('%04d-07-31', $start + 1),
            'actief' => false,
        ])->id;
    }

    /**
     * Inschrijving voor student+opleiding+periode; hergebruikt een bestaande.
     *
     * @return array{0:int|string,1:bool}  [id, is_nieuw]
     */
    private function inschrijvingVoor(int $studentId, ?int $oplId, int|string $periodeId, bool $dryRun): array
    {
        if (! $dryRun && $oplId && is_int($periodeId)) {
            $bestaand = Inschrijving::where('student_id', $studentId)->where('opleiding_id', $oplId)->where('periode_id', $periodeId)->value('id');
            if ($bestaand) {
                return [$bestaand, false];
            }

            return [Inschrijving::create([
                'student_id' => $studentId,
                'opleiding_id' => $oplId,
                'periode_id' => $periodeId,
                'status' => 'uitgeschreven',
                'opmerkingen' => '[gemigreerd uit Access]',
            ])->id, true];
        }

        return ['nieuw:'.$studentId.'-'.$periodeId, true];
    }

    /** Toetsonderdeel "Eindcijfer (gemigreerd)" (weging 100%) voor een historisch vak. */
    private function eindcijferOnderdeel(int|string $vakId, bool $dryRun): int|string
    {
        if (! $dryRun && is_int($vakId)) {
            $bestaand = Toetsonderdeel::where('vak_id', $vakId)->where('code', 'EIND-MIG')->value('id');
            if ($bestaand) {
                return $bestaand;
            }

            return Toetsonderdeel::create([
                'vak_id' => $vakId,
                'code' => 'EIND-MIG',
                'naam' => 'Eindcijfer (gemigreerd)',
                'type' => 'tentamen',
                'weging' => 1.0,
                'telt_mee' => true,
                'volgorde' => 1,
            ])->id;
        }

        return 'nieuw:'.$vakId;
    }

    /**
     * Haalt de schone studiejaarcode "JJJJ-JJJJ" (opeenvolgende jaren) uit een
     * mogelijk vervuild Access-veld (bv. met een regeleinde erin). Null als er
     * geen geldig studiejaar in staat.
     */
    private function normaliseerPeriode(string $raw): ?string
    {
        if (! preg_match('/(\d{4})-(\d{4})/', $raw, $m)) {
            return null;
        }

        return (int) $m[2] === (int) $m[1] + 1 ? $m[1].'-'.$m[2] : null;
    }

    /** Getal uit een Access-cel; accepteert zowel "2,5" als "2.5" en negeert lege/0-waarden niet. */
    private function parseGetal(string $v): ?float
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $v = str_replace(',', '.', $v);
        if (! is_numeric($v)) {
            return null;
        }

        return (float) $v;
    }

    /** Case-/spatie-tolerante veldlezer. */
    private function veld(array $rij, string $naam): string
    {
        if (array_key_exists($naam, $rij)) {
            return (string) $rij[$naam];
        }
        $doel = $this->sleutel($naam);
        foreach ($rij as $k => $v) {
            if ($this->sleutel((string) $k) === $doel) {
                return (string) $v;
            }
        }

        return '';
    }

    private function sleutel(string $s): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($s));
    }

    private function leeg(string $v): ?string
    {
        $v = trim($v);

        return $v === '' ? null : $v;
    }

    private function geslacht(string $aanhef): ?string
    {
        $a = mb_strtolower(trim($aanhef));

        return match (true) {
            str_starts_with($a, 'dhr'), str_contains($a, 'heer') => 'M',
            str_starts_with($a, 'mevr'), str_contains($a, 'mw') => 'V',
            default => null,
        };
    }

    private function datum(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            $d = Carbon::parse($raw);

            return $d->year >= 1930 && $d->year <= (int) date('Y') ? $d->format('Y-m-d') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function boolean(string $v): bool
    {
        return in_array(mb_strtolower(trim($v)), ['true', '1', 'waar', 'ja', '-1'], true);
    }

    private function nationaliteit(string $naam): ?int
    {
        $naam = trim($naam);
        if ($naam === '') {
            return null;
        }
        $doel = $this->sleutel($naam);
        $bestaand = Nationaliteit::all()->first(fn ($n) => $this->sleutel((string) $n->naam) === $doel);

        return $bestaand?->id ?? Nationaliteit::firstOrCreate(['naam' => $naam])->id;
    }

    private function opmerking(array $rij): ?string
    {
        $delen = array_filter([
            trim($this->veld($rij, 'Opmerking')),
            trim($this->veld($rij, 'Melding')),
        ]);
        $tekst = trim(implode(' · ', $delen));
        $tekst = $tekst === '' ? '' : $tekst.' ';

        return trim($tekst.'[gemigreerd uit Access]');
    }
}
