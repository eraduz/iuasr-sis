<?php

namespace App\Enums;

/** Contracttype van een dienstverband (module HR / Personeelszaken). */
enum Contracttype: string
{
    case Vast = 'vast';
    case Tijdelijk = 'tijdelijk';

    public function label(): string
    {
        return match ($this) {
            self::Vast => 'Vast',
            self::Tijdelijk => 'Tijdelijk',
        };
    }

    /** @return array<string,string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
