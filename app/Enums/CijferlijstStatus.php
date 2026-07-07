<?php

namespace App\Enums;

/**
 * Status van een cijferlijst (vak × periode) in de vaststellingsworkflow:
 * concept (docent voert in) → ingediend (bij examencommissie) → vastgesteld
 * (definitief door examencommissie; daarna alleen gelogde correctie).
 */
enum CijferlijstStatus: string
{
    case Concept = 'concept';
    case Ingediend = 'ingediend';
    case Vastgesteld = 'vastgesteld';

    public function label(): string
    {
        return match ($this) {
            self::Concept => 'Concept',
            self::Ingediend => 'Ingediend',
            self::Vastgesteld => 'Vastgesteld',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Concept => 's-draft',
            self::Ingediend => 's-submitted',
            self::Vastgesteld => 's-approved',
        };
    }
}
