<?php

namespace App\Enums;

/** Status van een verlofaanvraag (module HR / Personeelszaken). */
enum Verlofstatus: string
{
    case Aangevraagd = 'aangevraagd';
    case Goedgekeurd = 'goedgekeurd';
    case Afgewezen = 'afgewezen';
    case Ingetrokken = 'ingetrokken';

    public function label(): string
    {
        return match ($this) {
            self::Aangevraagd => 'Aangevraagd',
            self::Goedgekeurd => 'Goedgekeurd',
            self::Afgewezen => 'Afgewezen',
            self::Ingetrokken => 'Ingetrokken',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Aangevraagd => 's-requested',
            self::Goedgekeurd => 's-approved',
            self::Afgewezen => 's-rejected',
            self::Ingetrokken => 's-draft',
        };
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
