<?php

namespace App\Enums;

/**
 * Betaalmethode van een cursusgeldbetaling. De meeste cursisten betalen tijdens
 * de inschrijving online (iDEAL); een kleiner deel via overboeking of contant.
 *
 * iDEAL is nu een geregistreerde methode, geen live betaalprovider — de
 * boekhouding legt de ontvangen betaling vast. Een echte koppeling kan later.
 */
enum Betaalmethode: string
{
    case Ideal = 'ideal';
    case Overboeking = 'overboeking';
    case Contant = 'contant';

    public function label(): string
    {
        return match ($this) {
            self::Ideal => 'iDEAL / online',
            self::Overboeking => 'Bankoverschrijving',
            self::Contant => 'Contant',
        };
    }

    /** @return array<string, string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $m) => [$m->value => $m->label()])->all();
    }
}
