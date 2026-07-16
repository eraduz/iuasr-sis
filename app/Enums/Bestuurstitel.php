<?php

namespace App\Enums;

/**
 * De titel/functie van een bestuurslid. Het dagelijks bestuur bestaat uit de
 * voorzitter, de penningmeester en de secretaris; daarnaast zijn er gewone leden.
 * De Raad van Toezicht bestaat uit commissarissen (titel voorzitter, lid of
 * commissaris).
 */
enum Bestuurstitel: string
{
    case Voorzitter = 'voorzitter';
    case Penningmeester = 'penningmeester';
    case Secretaris = 'secretaris';
    case Lid = 'lid';
    case Commissaris = 'commissaris';

    public function label(): string
    {
        return match ($this) {
            self::Voorzitter => 'Voorzitter',
            self::Penningmeester => 'Penningmeester',
            self::Secretaris => 'Secretaris',
            self::Lid => 'Lid',
            self::Commissaris => 'Commissaris',
        };
    }

    /** Hoort deze titel tot het dagelijks bestuur? */
    public function isDagelijksBestuur(): bool
    {
        return in_array($this, [self::Voorzitter, self::Penningmeester, self::Secretaris], true);
    }

    /** @return array<string, string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }

    /** @return array<int, string> */
    public static function waarden(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
