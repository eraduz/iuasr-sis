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
    case Financien = 'financien';
    case Docent = 'docent';
    case Examencommissie = 'examencommissie';
    case Directie = 'directie';
    case Bestuur = 'bestuur';
    case Beheerder = 'beheerder';

    /** Leesbare naam voor UI en documenten (Nederlands, U-vorm). */
    public function label(): string
    {
        return match ($this) {
            self::Studentenzaken => 'Studentenzaken',
            self::Financien => 'Financiële Administratie',
            self::Docent => 'Docent',
            self::Examencommissie => 'Examencommissie',
            self::Directie => 'Directie',
            self::Bestuur => 'Schoolbestuur',
            self::Beheerder => 'Beheerder',
        };
    }

    /**
     * Mag deze rol ALLE ondertekende documenten inzien (niet alleen de eigen)?
     * Alleen het Schoolbestuur en de Beheerder hebben dit brede inzicht; alle
     * overige bevoegde rollen zien uitsluitend hun EIGEN ondertekende documenten.
     */
    public function magAlleOndertekendeDocumentenZien(): bool
    {
        return match ($this) {
            self::Bestuur, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol collegegeldtarieven instellen? (Studentenadministratie, Beheer.) */
    public function magCollegegeldBeheren(): bool
    {
        return match ($this) {
            self::Studentenzaken, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol betalingen registreren? (Financiële Administratie, Beheer.) */
    public function magBetalingenRegistreren(): bool
    {
        return match ($this) {
            self::Financien, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol de financiële status (betaalachterstand) inzien? */
    public function magFinancieelInzien(): bool
    {
        return match ($this) {
            self::Studentenzaken, self::Financien, self::Directie, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol cijfers/resultaten inzien? */
    public function magCijfersInzien(): bool
    {
        return match ($this) {
            self::Docent, self::Examencommissie, self::Directie => true,
            self::Studentenzaken, self::Bestuur, self::Beheerder => false,
        };
    }

    /**
     * Mag deze rol cijfers invoeren/muteren? Alleen de Docent (eigen vak).
     * Het vaststellen en corrigeren door de examencommissie is een aparte,
     * strikt gelogde bevoegdheid (latere fase), niet de reguliere invoer.
     */
    public function magCijfersInvoeren(): bool
    {
        return $this === self::Docent;
    }

    /** Mag deze rol identiteit/inschrijving beheren? */
    public function magInschrijvingBeheren(): bool
    {
        return match ($this) {
            self::Studentenzaken, self::Beheerder => true,
            self::Docent, self::Examencommissie, self::Directie, self::Bestuur => false,
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
