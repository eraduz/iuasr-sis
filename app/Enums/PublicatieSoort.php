<?php

namespace App\Enums;

/** Soort publicatie in de bibliotheek: boek, tijdschrift of digitaal document. */
enum PublicatieSoort: string
{
    case Boek = 'boek';
    case Tijdschrift = 'tijdschrift';
    case Digitaal = 'digitaal';

    public function label(): string
    {
        return match ($this) {
            self::Boek => 'Boek',
            self::Tijdschrift => 'Tijdschrift',
            self::Digitaal => 'Digitaal document',
        };
    }

    /**
     * Kent dit soort fysieke exemplaren die uitgeleend worden? Een digitaal
     * document niet: dat is onbeperkt beschikbaar en wordt niet uitgeleend.
     */
    public function heeftExemplaren(): bool
    {
        return $this !== self::Digitaal;
    }

    /** Alleen een tijdschrift kent uitgaven met artikelen. */
    public function heeftUitgaven(): bool
    {
        return $this === self::Tijdschrift;
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
