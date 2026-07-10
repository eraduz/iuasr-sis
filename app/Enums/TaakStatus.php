<?php

namespace App\Enums;

/**
 * Status van een taak. Bewust drie waarden, ontleend aan het Microsoft
 * Graph-model (notStarted / inProgress / completed) zonder de weinig gebruikte
 * 'waitingOnOthers' en 'deferred'.
 *
 * 'Te laat' is GEEN status: dat wordt afgeleid uit de vervaldatum, zodat een
 * taak nooit twee waarheden kan hebben.
 */
enum TaakStatus: string
{
    case Open = 'open';
    case Bezig = 'bezig';
    case Afgerond = 'afgerond';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Bezig => 'Bezig',
            self::Afgerond => 'Afgerond',
        };
    }

    /** Badge-klasse uit het design system. */
    public function badge(): string
    {
        return match ($this) {
            self::Open => 's-draft',
            self::Bezig => 's-incomplete',
            self::Afgerond => 's-approved',
        };
    }

    /** @return array<string, string> waarde => label, voor selectlijsten */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
