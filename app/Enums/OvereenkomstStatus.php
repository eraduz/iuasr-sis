<?php

namespace App\Enums;

/** Status van een overeenkomst met een organisatie. */
enum OvereenkomstStatus: string
{
    case Concept = 'concept';
    case Getekend = 'getekend';
    case Verlopen = 'verlopen';
    case Opgezegd = 'opgezegd';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Getekend => 'Getekend',
            self::Verlopen => 'Verlopen',
            self::Opgezegd => 'Opgezegd',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Concept => 's-draft',
            self::Getekend => 's-approved',
            self::Verlopen => 's-rejected',
            self::Opgezegd => 's-rejected',
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
