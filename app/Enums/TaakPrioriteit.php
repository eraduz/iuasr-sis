<?php

namespace App\Enums;

/** Prioriteit van een taak (Outlook/Graph: low | normal | high). */
enum TaakPrioriteit: string
{
    case Laag = 'laag';
    case Normaal = 'normaal';
    case Hoog = 'hoog';

    public function label(): string
    {
        return match ($this) {
            self::Laag => 'Laag',
            self::Normaal => 'Normaal',
            self::Hoog => 'Hoog',
        };
    }

    /** Sorteergewicht: hoog eerst. */
    public function gewicht(): int
    {
        return match ($this) {
            self::Hoog => 0,
            self::Normaal => 1,
            self::Laag => 2,
        };
    }

    /** @return array<string, string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $p) => [$p->value => $p->label()])->all();
    }
}
