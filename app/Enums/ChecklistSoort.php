<?php

namespace App\Enums;

/** Soort HR-checklist: bij in- of uitdiensttreding (module HR / Personeelszaken). */
enum ChecklistSoort: string
{
    case Onboarding = 'onboarding';
    case Offboarding = 'offboarding';

    public function label(): string
    {
        return match ($this) {
            self::Onboarding => 'Onboarding',
            self::Offboarding => 'Offboarding',
        };
    }

    /** Standaardsjabloon: de taken die bij het starten worden aangemaakt. */
    public function sjabloon(): array
    {
        return match ($this) {
            self::Onboarding => [
                'Arbeidscontract laten tekenen',
                'Account en e-mail aanvragen',
                'Werkplek en materialen gereedmaken',
                'Toegangspas / sleutels verstrekken',
                'Introductie op de afdeling',
                'Inschrijven in de systemen (rooster, HR)',
            ],
            self::Offboarding => [
                'Exitgesprek voeren',
                'Account en toegang blokkeren',
                'Toegangspas / sleutels innemen',
                'Laptop en materialen innemen',
                'Openstaand verlof en eindafrekening afhandelen',
                'Uitschrijven uit de systemen',
            ],
        };
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
