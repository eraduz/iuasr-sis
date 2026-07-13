<?php

namespace App\Enums;

/**
 * Status van één fysiek exemplaar. 'Uitgeleend' wordt NIET met de hand gezet:
 * die volgt uit de uitleenadministratie (zie Exemplaar::statusBijwerken()).
 */
enum ExemplaarStatus: string
{
    case Beschikbaar = 'beschikbaar';
    case Uitgeleend = 'uitgeleend';
    case Gereserveerd = 'gereserveerd';
    case Verloren = 'verloren';
    case Beschadigd = 'beschadigd';

    public function label(): string
    {
        return match ($this) {
            self::Beschikbaar => 'Beschikbaar',
            self::Uitgeleend => 'Uitgeleend',
            self::Gereserveerd => 'Gereserveerd',
            self::Verloren => 'Verloren',
            self::Beschadigd => 'Beschadigd',
        };
    }

    /** Mag dit exemplaar worden uitgeleend? */
    public function isUitleenbaar(): bool
    {
        return in_array($this, [self::Beschikbaar, self::Gereserveerd], true);
    }

    /** CSS-klasse uit het design system (iuasr-dash-status). */
    public function badge(): string
    {
        return match ($this) {
            self::Beschikbaar => 's-approved',
            self::Uitgeleend => 's-submitted',
            self::Gereserveerd => 's-requested',
            self::Verloren => 's-rejected',
            self::Beschadigd => 's-incomplete',
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
