<?php

namespace App\Enums;

/**
 * De vijf e-mailsoorten van de bibliotheek. De sleutel is tevens de sleutel van
 * het e-mailsjabloon dat de beheerder kan aanpassen.
 */
enum BibliotheekMailsoort: string
{
    case Uitleenbevestiging = 'uitleenbevestiging';
    case HerinneringVooraf = 'herinnering_vooraf';
    case TeLaatStudent = 'te_laat_student';
    case TeLaatDocent = 'te_laat_docent';
    case Retourbevestiging = 'retourbevestiging';

    public function label(): string
    {
        return match ($this) {
            self::Uitleenbevestiging => 'Uitleenbevestiging',
            self::HerinneringVooraf => 'Herinnering vóór vervaldatum',
            self::TeLaatStudent => 'Te laat — student',
            self::TeLaatDocent => 'Te laat — docent',
            self::Retourbevestiging => 'Retourbevestiging',
        };
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
