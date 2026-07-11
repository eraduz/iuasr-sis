<?php

namespace App\Enums;

/** De status van een stage (plaatsing) binnen de module Relatiebeheer & Stagebeheer. */
enum Stagestatus: string
{
    case Aangevraagd = 'aangevraagd';
    case Lopend = 'lopend';
    case Afgerond = 'afgerond';
    case Afgebroken = 'afgebroken';

    public function label(): string
    {
        return match ($this) {
            self::Aangevraagd => 'Aangevraagd',
            self::Lopend => 'Lopend',
            self::Afgerond => 'Afgerond',
            self::Afgebroken => 'Afgebroken',
        };
    }

    /** Telt deze status mee voor de bezetting van een stageplaats? */
    public function teltVoorBezetting(): bool
    {
        return match ($this) {
            self::Aangevraagd, self::Lopend => true,
            self::Afgerond, self::Afgebroken => false,
        };
    }

    /** Design-system statuskleur voor de badge. */
    public function badge(): string
    {
        return match ($this) {
            self::Aangevraagd => 's-requested',
            self::Lopend => 's-submitted',
            self::Afgerond => 's-approved',
            self::Afgebroken => 's-rejected',
        };
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
