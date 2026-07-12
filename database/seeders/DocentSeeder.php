<?php

namespace Database\Seeders;

use App\Models\Docent;
use Illuminate\Database\Seeder;

/**
 * Docenten van IUASR. Idempotent (firstOrCreate op naam), zodat opnieuw draaien
 * geen duplicaten oplevert.
 *
 * E-mailconventie (opdrachtgever 2026-07-12): {achternaam}@iuasr.nl (dus
 * abba@iuasr.nl, niet a.abba@iuasr.nl). Enkele uitzonderingen staan in
 * EMAIL_UITZONDERINGEN; de rest wordt later door de instelling gecorrigeerd.
 */
class DocentSeeder extends Seeder
{
    private const TRANSLIT = [
        'ç' => 'c', 'ı' => 'i', 'İ' => 'i', 'ğ' => 'g', 'ş' => 's', 'ü' => 'u', 'ö' => 'o',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ï' => 'i', 'î' => 'i',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ó' => 'o', 'ô' => 'o', 'û' => 'u', 'ñ' => 'n',
    ];

    /** Uitzonderingen op de conventie {achternaam}@iuasr.nl (achternaam => lokaal deel). */
    public const EMAIL_UITZONDERINGEN = [
        'Ali' => 'amer', // Galal Ali → amer@iuasr.nl
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
            // Docent uit de studiegidsen (ISLTH B-KL03; diverse MGV-modules).
            // Alleen de initiaal 'H.' is bekend uit de gids; niet zelf ingevuld.
            ['H.', 'Bouyazdouzen'],
        ];

        foreach ($docenten as [$voornaam, $achternaam]) {
            Docent::firstOrCreate(
                ['voornaam' => $voornaam, 'achternaam' => $achternaam],
                [
                    'code' => $this->volgendeCode(),
                    'email' => self::emailVoor($achternaam),
                    'actief' => true,
                ],
            );
        }
    }

    /**
     * E-mailadres volgens de conventie {achternaam}@iuasr.nl (genormaliseerd:
     * zonder diakrieten/spaties/koppeltekens, kleine letters), met uitzonderingen.
     * Publiek + static zodat de datamigratie hetzelfde adres kan afleiden.
     */
    public static function emailVoor(string $achternaam): string
    {
        $lokaal = self::EMAIL_UITZONDERINGEN[$achternaam]
            ?? preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($achternaam), self::TRANSLIT));

        return $lokaal.'@iuasr.nl';
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
}
