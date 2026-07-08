<?php

namespace App\Support;

/**
 * Leest een CSV met kopregel in als associatieve rijen. Detecteert het
 * scheidingsteken (`;` of `,`), verwijdert de BOM en normaliseert de kopnamen
 * (kleine letters, zonder accenten/leestekens) zodat de import tolerant is voor
 * verschillende exportformaten van het aanmeldportaal.
 */
class CsvLezer
{
    /** @return array<int, array<string, string>> lijst van rijen, gekoppeld op genormaliseerde kop */
    public static function associatief(string $pad): array
    {
        $handle = fopen($pad, 'r');
        if ($handle === false) {
            return [];
        }

        $eerste = fgets($handle);
        $delim = substr_count((string) $eerste, ';') >= substr_count((string) $eerste, ',') ? ';' : ',';
        rewind($handle);

        $rijen = [];
        while (($cols = fgetcsv($handle, 0, $delim)) !== false) {
            $rijen[] = $cols;
        }
        fclose($handle);

        if ($rijen === []) {
            return [];
        }

        $koppen = array_map([self::class, 'normaliseer'], $rijen[0]);

        $uit = [];
        $aantal = count($rijen);
        for ($i = 1; $i < $aantal; $i++) {
            $rij = $rijen[$i];
            // Lege regels overslaan.
            if (count(array_filter($rij, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach ($koppen as $idx => $kop) {
                if ($kop !== '') {
                    $assoc[$kop] = isset($rij[$idx]) ? trim((string) $rij[$idx]) : '';
                }
            }
            $uit[] = $assoc;
        }

        return $uit;
    }

    public static function normaliseer(string $kop): string
    {
        $kop = ltrim($kop, "\xEF\xBB\xBF");
        $kop = mb_strtolower(trim($kop));
        $kop = strtr($kop, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ü' => 'u', 'û' => 'u', 'ç' => 'c',
        ]);
        $kop = preg_replace('/[^a-z0-9]+/', ' ', $kop);

        return trim((string) $kop);
    }
}
