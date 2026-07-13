<?php

namespace App\Enums;

/**
 * Richting van een balieregistratie: komt het binnen bij de school (inkomend
 * telefoongesprek, ontvangen poststuk) of gaat het naar buiten (uitgaand
 * gesprek, verzonden poststuk)? Een bezoek is altijd inkomend.
 */
enum BalieRichting: string
{
    case Inkomend = 'inkomend';
    case Uitgaand = 'uitgaand';

    public function label(): string
    {
        return match ($this) {
            self::Inkomend => 'Inkomend',
            self::Uitgaand => 'Uitgaand',
        };
    }

    /**
     * Hoe heet de tegenpartij bij deze richting? Bepaalt het label boven het
     * contactveld: bij inkomend is dat de beller/afzender, bij uitgaand degene
     * die is gebeld of aan wie is verzonden.
     */
    public function contactLabel(BalieSoort $soort): string
    {
        return match (true) {
            $soort === BalieSoort::Bezoek => 'Naam bezoeker',
            $soort === BalieSoort::Telefoon && $this === self::Inkomend => 'Beller',
            $soort === BalieSoort::Telefoon && $this === self::Uitgaand => 'Gebeld met',
            $soort === BalieSoort::Post && $this === self::Inkomend => 'Afzender',
            default => 'Verzonden aan',
        };
    }

    /** @return array<string,string> */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
