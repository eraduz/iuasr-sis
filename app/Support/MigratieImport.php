<?php

namespace App\Support;

use App\Models\Nationaliteit;
use App\Models\Student;
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
