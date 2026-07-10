<?php

namespace App\Enums;

/** Status van een cursusinschrijving. */
enum CursusinschrijvingStatus: string
{
    case Aangemeld = 'aangemeld';
    case Actief = 'actief';
    case Afgerond = 'afgerond';
    case Geannuleerd = 'geannuleerd';

    public function label(): string
    {
        return match ($this) {
            self::Aangemeld => 'Aangemeld',
            self::Actief => 'Actief',
            self::Afgerond => 'Afgerond',
            self::Geannuleerd => 'Geannuleerd',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Aangemeld => 's-draft',
            self::Actief => 's-approved',
            self::Afgerond => 's-submitted',
            self::Geannuleerd => 's-rejected',
        };
    }

    /** @return array<string, string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
