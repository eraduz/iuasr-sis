<?php

namespace App\Enums;

/**
 * Soort medewerker (module HR). Een stichting kent naast betaald personeel ook
 * vrijwilligers en ZZP'ers/freelancers: die worden wél geregistreerd, maar tellen
 * NIET mee in de FTE en worden apart geteld/gefilterd. Standaard is een medewerker
 * betaald personeel.
 */
enum MedewerkerSoort: string
{
    case Personeel = 'personeel';
    case Vrijwilliger = 'vrijwilliger';
    case Zzp = 'zzp';

    public function label(): string
    {
        return match ($this) {
            self::Personeel => 'Personeel',
            self::Vrijwilliger => 'Vrijwilliger',
            self::Zzp => 'ZZP / freelancer',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Personeel => 's-approved',
            self::Vrijwilliger => 's-requested',
            self::Zzp => 's-draft',
        };
    }

    /** Telt deze soort mee in de FTE / personeelsformatie? Alleen betaald personeel. */
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
