<?php

namespace App\Enums;

/** Soort overeenkomst met een organisatie (module Relatiebeheer & Stagebeheer). */
enum OvereenkomstType: string
{
    case Samenwerkingsovereenkomst = 'samenwerkingsovereenkomst';
    case Convenant = 'convenant';
    case Stagecontract = 'stagecontract';

    public function label(): string
    {
        return match ($this) {
            self::Samenwerkingsovereenkomst => 'Samenwerkingsovereenkomst',
            self::Convenant => 'Convenant',
            self::Stagecontract => 'Stagecontract',
        };
    }

    /** @return array<string,string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
