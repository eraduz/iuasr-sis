<?php

namespace App\Enums;

/**
 * Status van één cursusgeldbetaling. Alleen 'Betaald' telt mee als voldaan;
 * een openstaande iDEAL-transactie (In afwachting), een mislukte poging of een
 * terugbetaling tellen niet mee in het betaalde bedrag.
 */
enum Cursusbetaalstatus: string
{
    case InAfwachting = 'in_afwachting';
    case Betaald = 'betaald';
    case Mislukt = 'mislukt';
    case Terugbetaald = 'terugbetaald';

    public function label(): string
    {
        return match ($this) {
            self::InAfwachting => 'In afwachting',
            self::Betaald => 'Betaald',
            self::Mislukt => 'Mislukt',
            self::Terugbetaald => 'Terugbetaald',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::InAfwachting => 's-draft',
            self::Betaald => 's-approved',
            self::Mislukt => 's-rejected',
            self::Terugbetaald => 's-submitted',
        };
    }

    /** Telt deze betaling mee als daadwerkelijk voldaan? */
    public function isVoldaan(): bool
    {
        return $this === self::Betaald;
    }

    /** @return array<string, string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
