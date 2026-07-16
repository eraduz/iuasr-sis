<?php

namespace App\Enums;

/**
 * Het orgaan binnen de stichting: het Stichtingsbestuur of de Raad van Toezicht.
 * Wordt gebruikt zowel voor een bestuurslid (bij welk orgaan hoort hij) als voor
 * een vergadering (welk orgaan vergadert).
 */
enum Bestuursorgaan: string
{
    case Stichtingsbestuur = 'stichtingsbestuur';
    case RaadVanToezicht = 'raad_van_toezicht';

    public function label(): string
    {
        return match ($this) {
            self::Stichtingsbestuur => 'Stichtingsbestuur',
            self::RaadVanToezicht => 'Raad van Toezicht',
        };
    }

    public function kort(): string
    {
        return match ($this) {
            self::Stichtingsbestuur => 'Bestuur',
            self::RaadVanToezicht => 'RvT',
        };
    }

    /** De Raad van Toezicht bestaat uit commissarissen (geen bevoegdheid-veld). */
    public function isRaadVanToezicht(): bool
    {
        return $this === self::RaadVanToezicht;
    }

    /** @return array<string, string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $o) => [$o->value => $o->label()])->all();
    }

    /** @return array<int, string> */
    public static function waarden(): array
    {
        return array_map(fn (self $o) => $o->value, self::cases());
    }
}
