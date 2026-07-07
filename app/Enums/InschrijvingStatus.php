<?php

namespace App\Enums;

/**
 * Lifecycle-status van een inschrijving. Centraliseert de toegestane waarden,
 * de leesbare labels en de bijbehorende badge-klasse uit het design system.
 */
enum InschrijvingStatus: string
{
    case Aangemeld = 'aangemeld';
    case Actief = 'actief';
    case Geschorst = 'geschorst';
    case Uitgeschreven = 'uitgeschreven';
    case Afgestudeerd = 'afgestudeerd';

    public function label(): string
    {
        return match ($this) {
            self::Aangemeld => 'Aangemeld',
            self::Actief => 'Actief',
            self::Geschorst => 'Geschorst',
            self::Uitgeschreven => 'Uitgeschreven',
            self::Afgestudeerd => 'Afgestudeerd',
        };
    }

    /** Badge-klasse uit iuasr-plugin-dash.css. */
    public function badge(): string
    {
        return match ($this) {
            self::Aangemeld => 's-requested',
            self::Actief => 's-approved',
            self::Geschorst => 's-rejected',
            self::Uitgeschreven => 's-draft',
            self::Afgestudeerd => 's-pay',
        };
    }

    public function isActief(): bool
    {
        return $this === self::Actief;
    }

    /** @return array<int, string> */
    public static function waarden(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
