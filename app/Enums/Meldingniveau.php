<?php

namespace App\Enums;

/**
 * De toon van een systeemmelding. Bepaalt de kleur én of de medewerker de
 * melding mag wegklikken: een storing of onderhoud wegklikken zou het doel
 * voorbijschieten, een mededeling wegklikken niet.
 */
enum Meldingniveau: string
{
    case Info = 'info';
    case Waarschuwing = 'waarschuwing';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Mededeling',
            self::Waarschuwing => 'Let op',
            self::Urgent => 'Urgent',
        };
    }

    public function omschrijving(): string
    {
        return match ($this) {
            self::Info => 'Algemene mededeling, bijvoorbeeld een nieuwe functie.',
            self::Waarschuwing => 'Gepland onderhoud of iets waar men rekening mee moet houden.',
            self::Urgent => 'Storing of directe actie nodig. Kan niet worden weggeklikt.',
        };
    }

    /** De klasse uit het design system (iuasr-dash-alert--*). */
    public function alertKlasse(): string
    {
        return match ($this) {
            self::Info => 'iuasr-dash-alert--info',
            self::Waarschuwing => 'iuasr-dash-alert--warn',
            self::Urgent => 'iuasr-dash-alert--danger',
        };
    }

    /**
     * Mag de medewerker deze melding zelf wegklikken? Een urgente melding niet:
     * die staat er juist omdat iedereen hem moet zien. Dit is de STANDAARD bij
     * het aanmaken; de Beheerder kan er per melding van afwijken.
     */
    public function standaardAfsluitbaar(): bool
    {
        return $this !== self::Urgent;
    }

    /** @return array<int, string> voor de enum-kolom in de migratie. */
    public static function waarden(): array
    {
        return array_map(fn (self $n) => $n->value, self::cases());
    }
}
