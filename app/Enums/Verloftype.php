<?php

namespace App\Enums;

/** Soort verlof (module HR / Personeelszaken). */
enum Verloftype: string
{
    case Vakantie = 'vakantie';
    case Bijzonder = 'bijzonder';
    case Ouderschap = 'ouderschap';
    case Studie = 'studie';
    case Zwangerschap = 'zwangerschap';
    case Geboorte = 'geboorte';
    case AanvullendGeboorte = 'aanvullend_geboorte';

    public function label(): string
    {
        return match ($this) {
            self::Vakantie => 'Vakantie',
            self::Bijzonder => 'Bijzonder verlof',
            self::Ouderschap => 'Ouderschapsverlof',
            self::Studie => 'Studieverlof',
            self::Zwangerschap => 'Zwangerschaps- en bevallingsverlof',
            self::Geboorte => 'Geboorteverlof (partner)',
            self::AanvullendGeboorte => 'Aanvullend geboorteverlof',
        };
    }

    /**
     * Wettelijk verlof (WAZO): loopt via een UWV-uitkering, niet via het
     * vakantiesaldo. Wordt daarom apart getoond en niet van een saldo afgetrokken.
     */
    public function wettelijk(): bool
    {
        return match ($this) {
            self::Zwangerschap, self::Geboorte, self::AanvullendGeboorte => true,
            default => false,
        };
    }

    /** Korte wettelijke toelichting (bron: rijksoverheid.nl / Wet arbeid en zorg). */
    public function toelichting(): ?string
    {
        return match ($this) {
            self::Zwangerschap => 'Zwangerschaps- en bevallingsverlof duren samen minimaal 16 weken. Het zwangerschapsverlof begint 6 weken vóór de uitgerekende datum (uiterlijk 4 weken ervoor); het bevallingsverlof duurt minimaal 10 weken na de bevalling. De uitkering (100% dagloon, gemaximeerd) loopt via het UWV (WAZO).',
            self::Geboorte => 'De partner heeft recht op eenmaal het aantal werkuren per week aan geboorteverlof, volledig doorbetaald door de werkgever, op te nemen binnen 4 weken na de geboorte.',
            self::AanvullendGeboorte => 'De partner kan daarna maximaal 5× het aantal werkuren per week aanvullend geboorteverlof opnemen, binnen 6 maanden na de geboorte en ná het gewone geboorteverlof. De uitkering is 70% van het dagloon via het UWV.',
            default => null,
        };
    }

    /** @return array<string,string> */
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
