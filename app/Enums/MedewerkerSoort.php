<?php

namespace App\Enums;

/**
 * Soort medewerker (module HR). Een stichting kent naast betaald personeel ook
 * vrijwilligers: zij worden wél geregistreerd, maar tellen NIET mee in de FTE en
 * worden apart geteld/gefilterd. Standaard is een medewerker betaald personeel.
 */
enum MedewerkerSoort: string
{
    case Personeel = 'personeel';
    case Vrijwilliger = 'vrijwilliger';

    public function label(): string
    {
        return match ($this) {
            self::Personeel => 'Personeel',
            self::Vrijwilliger => 'Vrijwilliger',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Personeel => 's-approved',
            self::Vrijwilliger => 's-requested',
        };
    }

    /** Telt deze soort mee in de FTE / personeelsformatie? */
    public function teltVoorFte(): bool
    {
        return $this === self::Personeel;
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
