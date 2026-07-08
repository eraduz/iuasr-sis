<?php

namespace App\Enums;

/** Grondslag voor een vrijstelling (HBO-praktijk: OER / examencommissie). */
enum VrijstellingGrondslag: string
{
    case Vooropleiding = 'vooropleiding';
    case Evc = 'evc';
    case EerderBehaald = 'eerder_behaald';
    case Overig = 'overig';

    public function label(): string
    {
        return match ($this) {
            self::Vooropleiding => 'Eerdere vooropleiding',
            self::Evc => 'EVC (verworven competenties)',
            self::EerderBehaald => 'Eerder behaald (elders)',
            self::Overig => 'Overig',
        };
    }

    /** @return array<string, string> value => label, voor selectievelden. */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])->all();
    }
}
