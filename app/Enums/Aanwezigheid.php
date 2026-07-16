<?php

namespace App\Enums;

/**
 * De aanwezigheid van een bestuurslid bij een vergadering: fysiek, online of niet
 * bijgewoond.
 */
enum Aanwezigheid: string
{
    case Fysiek = 'fysiek';
    case Online = 'online';
    case NietBijgewoond = 'niet_bijgewoond';

    public function label(): string
    {
        return match ($this) {
            self::Fysiek => 'Fysiek aanwezig',
            self::Online => 'Online aanwezig',
            self::NietBijgewoond => 'Niet bijgewoond',
        };
    }

    public function kort(): string
    {
        return match ($this) {
            self::Fysiek => 'Fysiek',
            self::Online => 'Online',
            self::NietBijgewoond => 'Afwezig',
        };
    }

    /** CSS-badgeklasse (design system, iuasr-dash-status s-*). */
    public function badge(): string
    {
        return match ($this) {
            self::Fysiek => 's-approved',
            self::Online => 's-submitted',
            self::NietBijgewoond => 's-rejected',
        };
    }

    /** Telt deze status als aanwezig (fysiek of online)? */
    public function isAanwezig(): bool
    {
        return $this !== self::NietBijgewoond;
    }

    /** @return array<string, string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $a) => [$a->value => $a->label()])->all();
    }

    /** @return array<int, string> */
    public static function waarden(): array
    {
        return array_map(fn (self $a) => $a->value, self::cases());
    }
}
