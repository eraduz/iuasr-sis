<?php

namespace App\Enums;

/**
 * Taalbeheersingsniveau (3 niveaus). Gebruikt voor Nederlands en Arabisch.
 */
enum TaalNiveau: string
{
    case Onvoldoende = 'onvoldoende';
    case Voldoende = 'voldoende';
    case Goed = 'goed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** @return array<int, string> */
    public static function waarden(): array
    {
        return array_map(fn (self $n) => $n->value, self::cases());
    }
}
