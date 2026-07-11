<?php

namespace App\Enums;

/** Soort verlof (module HR / Personeelszaken). */
enum Verloftype: string
{
    case Vakantie = 'vakantie';
    case Bijzonder = 'bijzonder';
    case Ouderschap = 'ouderschap';
    case Studie = 'studie';

    public function label(): string
    {
        return match ($this) {
            self::Vakantie => 'Vakantie',
            self::Bijzonder => 'Bijzonder verlof',
            self::Ouderschap => 'Ouderschapsverlof',
            self::Studie => 'Studieverlof',
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
