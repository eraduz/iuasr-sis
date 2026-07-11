<?php

namespace App\Enums;

/** Status van een HR-gesprek. */
enum Gespreksstatus: string
{
    case Gepland = 'gepland';
    case Gehouden = 'gehouden';
    case Afgerond = 'afgerond';

    public function label(): string
    {
        return match ($this) {
            self::Gepland => 'Gepland',
            self::Gehouden => 'Gehouden',
            self::Afgerond => 'Afgerond',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Gepland => 's-requested',
            self::Gehouden => 's-submitted',
            self::Afgerond => 's-approved',
        };
    }

    /** @return array<string,string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
