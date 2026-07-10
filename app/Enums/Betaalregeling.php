<?php

namespace App\Enums;

/**
 * Betaalregeling van een inschrijving: hoe het collegegeld van dat studiejaar
 * gefactureerd wordt. Vastgelegd door Studentenzaken bij de inschrijving.
 *
 * Niet te verwarren met de betaalWIJZE op een betaling (overboeking, contant):
 * dat is de manier waarop een factuur wordt voldaan.
 */
enum Betaalregeling: string
{
    case Termijnen = 'termijnen';
    case Volledig = 'volledig';

    public function label(): string
    {
        return match ($this) {
            self::Termijnen => 'Vijf termijnen',
            self::Volledig => 'Eén factuur (volledig)',
        };
    }

    public function omschrijving(): string
    {
        return match ($this) {
            self::Termijnen => 'Factuur in september, november, januari, maart en mei.',
            self::Volledig => 'Eén factuur voor het volledige jaarbedrag, vervalt in september.',
        };
    }

    /** Aantal facturen dat deze regeling oplevert. */
    public function aantalTermijnen(): int
    {
        return $this === self::Volledig ? 1 : 5;
    }
}
