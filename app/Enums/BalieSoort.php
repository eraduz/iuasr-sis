<?php

namespace App\Enums;

/**
 * Soort balieregistratie (module Balie/Receptie). Samen met BalieRichting
 * beschrijft dit de vijf stromen die aan de ingang worden bijgehouden:
 * telefoon in/uit, bezoek, en post in/uit.
 */
enum BalieSoort: string
{
    case Telefoon = 'telefoon';
    case Bezoek = 'bezoek';
    case Post = 'post';

    public function label(): string
    {
        return match ($this) {
            self::Telefoon => 'Telefoongesprek',
            self::Bezoek => 'Bezoek',
            self::Post => 'Poststuk',
        };
    }

    /** Kent dit soort een richting (inkomend/uitgaand)? Een bezoek niet. */
    public function heeftRichting(): bool
    {
        return $this !== self::Bezoek;
    }

    /** Wordt er bij dit soort een onderwerp vastgelegd? Bij post niet (bron: opdracht). */
    public function heeftOnderwerp(): bool
    {
        return $this !== self::Post;
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
