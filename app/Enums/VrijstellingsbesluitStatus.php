<?php

namespace App\Enums;

/** Status van een vrijstellingsbesluit (examencommissie -> Studentenzaken). */
enum VrijstellingsbesluitStatus: string
{
    case Open = 'open';
    case Verwerkt = 'verwerkt';
    case Geannuleerd = 'geannuleerd';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Openstaand',
            self::Verwerkt => 'Verwerkt',
            self::Geannuleerd => 'Geannuleerd',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 's-incomplete',
            self::Verwerkt => 's-approved',
            self::Geannuleerd => 's-rejected',
        };
    }
}
