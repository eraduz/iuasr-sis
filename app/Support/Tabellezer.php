<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

/**
 * Leest een tabelbestand (Excel .xlsx of .csv) in als rijen, gekeyd op de
 * genormaliseerde kolomnaam uit de kopregel. Zo maakt de kolomVOLGORDE niet uit
 * en blijven bestanden werken die net iets anders zijn opgebouwd.
 */
class Tabellezer
{
    /**
     * @return list<array<string, string>> rijen als [kolomnaam => waarde]
     */
    public static function rijen(string $pad, string $extensie): array
    {
        $extensie = strtolower($extensie);

        $matrix = $extensie === 'csv'
            ? self::leesCsv($pad)
            : self::leesSpreadsheet($pad);

        if ($matrix === []) {
            return [];
        }

        // Kopregel normaliseren (kleine letters, spaties/underscores weg).
        $kop = array_map(fn ($c) => self::normaliseer((string) $c), array_shift($matrix));
        $kop[0] = ltrim($kop[0], "\xEF\xBB\xBF");

        $rijen = [];
        foreach ($matrix as $rij) {
            $assoc = [];
            foreach ($kop as $i => $naam) {
                if ($naam !== '') {
                    $assoc[$naam] = trim((string) ($rij[$i] ?? ''));
                }
            }
            // Volledig lege rij overslaan.
            if (implode('', $assoc) !== '') {
                $rijen[] = $assoc;
            }
        }

        return $rijen;
    }

    private static function normaliseer(string $naam): string
    {
        return str_replace([' ', '-'], '', strtolower(trim($naam)));
    }

    /** @return list<array<int, string>> */
    private static function leesSpreadsheet(string $pad): array
    {
        // Forceer de Xlsx-reader; auto-detect struikelt soms over de extensie.
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $blad = $reader->load($pad)->getActiveSheet();

        return $blad->toArray(null, true, false, false);
    }

    /** @return list<array<int, string>> */
    private static function leesCsv(string $pad): array
    {
        $eerste = (string) @file_get_contents($pad, false, null, 0, 4096);
        $delim = substr_count($eerste, ';') >= substr_count($eerste, ',') ? ';' : ',';

        $reader = new CsvReader();
        $reader->setDelimiter($delim);
        $reader->setReadDataOnly(true);
        $blad = $reader->load($pad)->getActiveSheet();

        return $blad->toArray(null, true, false, false);
    }
}
