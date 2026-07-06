<?php

namespace App\Enums;

/*
|--------------------------------------------------------------------------
| Rol — de vijf functionele rollen van het SIS
|--------------------------------------------------------------------------
|
| Rolscheiding is niet-onderhandelbaar en wordt server-side afgedwongen.
| Kernregel (PvA §5):
|   - Studentenzaken beheert identiteit/inschrijving, ziet/muteert GEEN cijfers.
|   - Docent voert cijfers in voor het EIGEN vak (binnen termijn).
|   - Examencommissie en Directie hebben cijferinzage; wijzigen strikt & gelogd.
|   - Beheerder beheert gebruikers, rollen en referentiedata.
|
| De waarde komt overeen met de rolsleutel in het design system
| (data-role / role--*), zodat UI en autorisatie hetzelfde vocabulaire delen.
*/
enum Rol: string
{
    case Studentenzaken = 'studentenzaken';
    case Docent = 'docent';
    case Examencommissie = 'examencommissie';
    case Directie = 'directie';
    case Beheerder = 'beheerder';

    /** Leesbare naam voor UI en documenten (Nederlands, U-vorm). */
    public function label(): string
    {
        return match ($this) {
            self::Studentenzaken => 'Studentenzaken',
            self::Docent => 'Docent',
            self::Examencommissie => 'Examencommissie',
            self::Directie => 'Directie',
            self::Beheerder => 'Beheerder',
        };
    }

    /** Mag deze rol cijfers/resultaten inzien? */
    public function magCijfersInzien(): bool
    {
        return match ($this) {
            self::Docent, self::Examencommissie, self::Directie => true,
            self::Studentenzaken, self::Beheerder => false,
        };
    }

    /** Mag deze rol cijfers invoeren of muteren? (Docent enkel eigen vak.) */
    public function magCijfersInvoeren(): bool
    {
        return match ($this) {
            self::Docent, self::Examencommissie => true,
            self::Studentenzaken, self::Directie, self::Beheerder => false,
        };
    }

    /** Mag deze rol identiteit/inschrijving beheren? */
    public function magInschrijvingBeheren(): bool
    {
        return match ($this) {
            self::Studentenzaken, self::Beheerder => true,
            self::Docent, self::Examencommissie, self::Directie => false,
        };
    }

    /** Mag deze rol het BSN inzien? (gelogd) */
    public function magBsnInzien(): bool
    {
        // Directie: beperkt — hier standaard uit tot expliciet toegekend.
        return match ($this) {
            self::Studentenzaken, self::Beheerder => true,
            default => false,
        };
    }

    /** @return array<int, string> */
    public static function waarden(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
