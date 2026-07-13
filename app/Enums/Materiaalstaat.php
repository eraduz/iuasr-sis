<?php

namespace App\Enums;

/** Staat van het materiaal bij inname (retourverwerking). */
enum Materiaalstaat: string
{
    case Uitstekend = 'uitstekend';
    case Goed = 'goed';
    case LichtBeschadigd = 'licht_beschadigd';
    case Beschadigd = 'beschadigd';
    case ErnstigBeschadigd = 'ernstig_beschadigd';

    public function label(): string
    {
        return match ($this) {
            self::Uitstekend => 'Uitstekend',
            self::Goed => 'Goed',
            self::LichtBeschadigd => 'Licht beschadigd',
            self::Beschadigd => 'Beschadigd',
            self::ErnstigBeschadigd => 'Ernstig beschadigd',
        };
    }

    /**
     * Is dit schade die gemeld moet worden? Vanaf 'beschadigd' zet de inname het
     * exemplaar op de status Beschadigd en volgt er een melding.
     */
    public function isSchade(): bool
    {
        return in_array($this, [self::Beschadigd, self::ErnstigBeschadigd], true);
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
