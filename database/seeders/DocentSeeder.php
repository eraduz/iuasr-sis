<?php

namespace Database\Seeders;

use App\Models\Docent;
use Illuminate\Database\Seeder;

/**
 * Docenten van IUASR. Idempotent (firstOrCreate op naam), zodat opnieuw draaien
 * geen duplicaten oplevert. E-mailadressen zijn automatisch gegenereerd
 * (voorletter.achternaam@iuasr.nl) en worden later door de instelling
 * gecorrigeerd.
 */
class DocentSeeder extends Seeder
{
    private const TRANSLIT = [
        'ç' => 'c', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'ş' => 's', 'ü' => 'u', 'ö' => 'o',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ï' => 'i', 'î' => 'i',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ó' => 'o', 'ô' => 'o', 'û' => 'u', 'ñ' => 'n',
    ];

    public function run(): void
    {
        // [voornaam, achternaam]
        $docenten = [
            ['Hasan', 'Yalçınkaya'],
            ['Galal', 'Ali'],
            ['Kawther', 'Al-Hakim'],
            ['Azia', 'Abba'],
            ['Majeda', 'Abu Alhija'],
            ['Mhamed', 'Aarab'],
            ['Naima', 'Bouchtaoui'],
            ['Wim', 'van Ael'],
            ['Abdeslam', 'Chaquer'],
            ['Tom', 'Zwart'],
            ['Hasibe', 'Bicer-Uslu'],
            ['Salima', 'el Ayachi'],
            ['Haroen', 'Vlug'],
            ['Yasin', 'Mol'],
            ['Selim', 'Kocadağ'],
            ['George', 'Muishout'],
        ];

        foreach ($docenten as [$voornaam, $achternaam]) {
            Docent::firstOrCreate(
                ['voornaam' => $voornaam, 'achternaam' => $achternaam],
                [
                    'code' => $this->volgendeCode(),
                    'email' => $this->email($voornaam, $achternaam),
                    'actief' => true,
                ],
            );
        }
    }

    private function volgendeCode(): string
    {
        $max = Docent::query()
            ->where('code', 'like', 'DOC-%')
            ->get()
            ->map(fn ($d) => (int) substr((string) $d->code, 4))
            ->max() ?? 0;

        return 'DOC-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    private function email(string $voornaam, string $achternaam): string
    {
        $schoon = fn (string $s): string => preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($s), self::TRANSLIT));

        return $schoon(mb_substr($voornaam, 0, 1)).'.'.$schoon($achternaam).'@iuasr.nl';
    }
}
