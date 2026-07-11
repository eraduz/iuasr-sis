<?php

namespace App\Enums;

/** De status van een medewerker (module HR / Personeelszaken). */
enum MedewerkerStatus: string
{
    case Actief = 'actief';
    case Ziek = 'ziek';
    case Verlof = 'verlof';
    case UitDienst = 'uit_dienst';

    public function label(): string
    {
        return match ($this) {
            self::Actief => 'Actief',
            self::Ziek => 'Ziek',
            self::Verlof => 'Verlof',
            self::UitDienst => 'Uit dienst',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Actief => 's-approved',
            self::Ziek => 's-rejected',
            self::Verlof => 's-requested',
            self::UitDienst => 's-draft',
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
