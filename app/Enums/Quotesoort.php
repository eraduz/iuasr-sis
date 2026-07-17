<?php

namespace App\Enums;

/**
 * De herkomst van een zijbalk-quote. Beide soorten worden identiek weergegeven;
 * het onderscheid dient de Beheerder, die zijn eigen spreuken wil kunnen vinden
 * zonder door 99 Schone Namen te scrollen.
 */
enum Quotesoort: string
{
    case SchoneNaam = 'schone_naam';
    case Quote = 'quote';

    public function label(): string
    {
        return match ($this) {
            self::SchoneNaam => 'Schone Naam',
            self::Quote => 'Eigen spreuk',
        };
    }

    public function meervoud(): string
    {
        return match ($this) {
            self::SchoneNaam => '99 Schone Namen',
            self::Quote => 'Eigen spreuken',
        };
    }

    /** @return array<int, string> voor de enum-kolom in de migratie. */
    public static function waarden(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
