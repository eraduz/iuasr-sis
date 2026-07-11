<?php

namespace App\Enums;

/**
 * Soort agenda-afspraak binnen de module Relatiebeheer & Stagebeheer. Ontleend
 * aan de planningsbehoeften van het relatiebeheer (schoolbezoek, stagebezoek,
 * evaluatie, overleg, open dag).
 */
enum AfspraakType: string
{
    case Schoolbezoek = 'schoolbezoek';
    case Stagebezoek = 'stagebezoek';
    case Evaluatie = 'evaluatie';
    case Overleg = 'overleg';
    case OpenDag = 'open_dag';

    public function label(): string
    {
        return match ($this) {
            self::Schoolbezoek => 'Schoolbezoek',
            self::Stagebezoek => 'Stagebezoek',
            self::Evaluatie => 'Evaluatie',
            self::Overleg => 'Overleg',
            self::OpenDag => 'Open dag',
        };
    }

    /** @return array<string,string> waarde => label, voor selectlijsten */
    public static function opties(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }

    /** @return array<int,string> */
    public static function waarden(): array
    {
        return array_map(fn (self $t) => $t->value, self::cases());
    }
}
