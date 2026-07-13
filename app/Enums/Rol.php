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
    // Module Cursussen Administratie. De cursusdirecteur volgt in een latere fase.
    case Cursusadministratie = 'cursusadministratie';
    // Module Relatiebeheer & Stagebeheer (opleidingoverstijgend: PABO, ISLTH, MGV).
    // De relatiebeheerder onderhoudt organisaties/contactpersonen; de
    // stagecoördinator doet daarnaast de stageplaatsen en plaatsingen.
    case Relatiebeheerder = 'relatiebeheerder';
    case Stagecoordinator = 'stagecoordinator';
    // Module HR / Personeelszaken. Eén gecombineerde rol: de HR-medewerker doet de
    // personeelsadministratie én is tevens leidinggevende (manager). Bij IUASR zijn
    // dit dezelfde persoon; de rol ziet daarom alle medewerkers en keurt verlof goed.
    case Hrmedewerker = 'hrmedewerker';

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
            self::Cursusadministratie => 'Cursusadministratie',
            self::Relatiebeheerder => 'Relatiebeheerder',
            self::Stagecoordinator => 'Stagecoördinator',
            self::Hrmedewerker => 'HR-medewerker',
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
            self::Studentenzaken, self::Bestuur, self::Beheerder,
            self::Cursusadministratie,
            self::Relatiebeheerder, self::Stagecoordinator,
            self::Hrmedewerker => false,
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
            self::Docent, self::Examencommissie, self::Directie, self::Bestuur,
            self::Cursusadministratie,
            self::Relatiebeheerder, self::Stagecoordinator,
            self::Hrmedewerker => false,
        };
    }

    /**
     * Mag deze rol vervroegd afstuderen VRIJGEVEN? Dat is een academisch besluit van
     * de EXAMENCOMMISSIE (bij vrijstellingen/eerder behaalde EC); de Beheerder kan
     * corrigeren. Studentenzaken voert het afstuderen daarna administratief uit.
     */
    public function magVervroegdAfstuderenVrijgeven(): bool
    {
        return in_array($this, [self::Examencommissie, self::Beheerder], true);
    }

    /**
     * Mag deze rol de EXAMENCOMMISSIE-notities per student beheren/zien? Uitsluitend
     * de examencommissie (en de Beheerder als systeembeheer). Bewust NIET gedeeld met
     * Studentenzaken, Directie of Bestuur — het zijn de eigen aantekeningen van de
     * commissie (anders dan de interne notities van Studentenzaken).
     */
    public function magExamencommissieNotities(): bool
    {
        return in_array($this, [self::Examencommissie, self::Beheerder], true);
    }

    /**
     * Mag deze rol de aanwezigheid registreren? Alleen de Docent, en uitsluitend
     * voor het EIGEN vak (die vakcontrole staat in de Gate 'presentie-registreren').
     * Registreren is voor de docent verplicht, niet optioneel.
     */
    public function magPresentieRegistreren(): bool
    {
        return $this === self::Docent;
    }

    /**
     * Mag deze rol presentielijsten en aanwezigheidspercentages inzien?
     * Docent (eigen vak), Examencommissie, Directie (eigen opleiding) en het
     * Schoolbestuur (kwaliteitsbewaking). Studentenzaken en Financiën niet:
     * aanwezigheid is onderwijsinhoudelijke procesinformatie.
     */
    public function magPresentieInzien(): bool
    {
        return match ($this) {
            self::Docent, self::Examencommissie, self::Directie, self::Bestuur => true,
            self::Studentenzaken, self::Financien, self::Beheerder,
            self::Cursusadministratie,
            self::Relatiebeheerder, self::Stagecoordinator,
            self::Hrmedewerker => false,
        };
    }

    /**
     * Mag deze rol zien dat een student de 50%-aanwezigheidsregeling heeft?
     * De docent heeft dit nodig op de presentielijst, de overige rollen op het
     * studentdossier en het dashboard.
     */
    public function magAanwezigheidsregelingZien(): bool
    {
        return match ($this) {
            self::Studentenzaken, self::Docent, self::Examencommissie,
            self::Directie, self::Bestuur, self::Beheerder => true,
            self::Financien, self::Cursusadministratie,
            self::Relatiebeheerder, self::Stagecoordinator,
            self::Hrmedewerker => false,
        };
    }

    /**
     * Mag deze rol de 50%-aanwezigheidsregeling toekennen of intrekken?
     * Studentenzaken legt het vinkje vast (met toestemming van de directie);
     * de mutatie wordt gelogd.
     */
    public function magAanwezigheidsregelingBeheren(): bool
    {
        return match ($this) {
            self::Studentenzaken, self::Beheerder => true,
            default => false,
        };
    }

    /**
     * Mag deze rol de takenlijst gebruiken? Deze is uitsluitend bedoeld voor de
     * werkverdeling binnen Studentenzaken; Beheer heeft toegang voor onderhoud.
     */
    public function magTakenBeheren(): bool
    {
        return match ($this) {
            self::Studentenzaken, self::Beheerder => true,
            default => false,
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

    /**
     * Tot welke platform-modules geeft deze rol toegang? Sterretje = alle modules
     * (Beheerder). De Financiële Administratie behandelt zowel het collegegeld
     * (Studentenzaken) als de cursusgelden (Cursussen). De onderwijsrollen werken
     * binnen Studentenzaken.
     *
     * De cursus-specifieke rollen worden in een latere fase toegevoegd, samen met
     * de Cursussen-module zelf.
     *
     * @return array<int, string> moduleSleutels, of ['*'] voor alle modules
     */
    public function moduleSleutels(): array
    {
        return match ($this) {
            self::Beheerder => ['*'],
            self::Financien => ['studentenzaken', 'cursussen'],
            self::Cursusadministratie => ['cursussen'],
            // De module Relatiebeheer & Stagebeheer: de relatiebeheerder en de
            // stagecoördinator werken uitsluitend daarbinnen.
            self::Relatiebeheerder, self::Stagecoordinator => ['relatiebeheer'],
            // Module HR / Personeelszaken: de HR-medewerker werkt uitsluitend daarbinnen.
            self::Hrmedewerker => ['hr'],
            // Het Schoolbestuur heeft brede inzage en ziet naast Studentenzaken ook
            // de Cursussen-, Relatiebeheer- en HR-module (alleen-lezen).
            self::Bestuur => ['studentenzaken', 'cursussen', 'relatiebeheer', 'hr'],
            // De Directie (opleidingsmanager) beheert haar opleiding, inclusief de
            // relaties/stages van die opleiding (opleidinggebonden gescoped).
            self::Directie => ['studentenzaken', 'relatiebeheer'],
            self::Studentenzaken, self::Docent,
            self::Examencommissie => ['studentenzaken'],
        };
    }

    /** Mag deze rol de personeelsadministratie beheren (module HR)? */
    public function magHrBeheer(): bool
    {
        return match ($this) {
            self::Hrmedewerker, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol de module HR inzien (medewerkers, dashboards)? */
    public function magHrInzien(): bool
    {
        return match ($this) {
            self::Hrmedewerker, self::Beheerder, self::Bestuur => true,
            default => false,
        };
    }

    /**
     * Is de zichtbaarheid binnen HR beperkt tot het eigen team? Sinds het
     * samenvoegen van HR-medewerker en Manager tot één rol is niemand meer
     * teamgebonden; de gecombineerde HR-rol ziet alle medewerkers. Het
     * scopingsmechanisme blijft aanwezig voor eventuele herintroductie.
     */
    public function isHrTeamBeperkt(): bool
    {
        return false;
    }

    /** Mag deze rol verlofaanvragen beoordelen? De HR-medewerker (tevens manager) en Beheer. */
    public function magVerlofBeoordelen(): bool
    {
        return match ($this) {
            self::Hrmedewerker, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol cursussen en cursisten beheren (module Cursussen)? */
    public function magCursusBeheer(): bool
    {
        return match ($this) {
            self::Cursusadministratie, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol de cursusgelden/boekhouding van de module Cursussen doen? */
    public function magCursusFinancien(): bool
    {
        return match ($this) {
            self::Financien, self::Beheerder => true,
            default => false,
        };
    }

    /**
     * Mag deze rol cursussen/cursisten inzien (dashboard, cursistenoverzicht)?
     * De cursusadministratie en Beheer beheren; het Schoolbestuur kijkt mee
     * (alleen-lezen, voor de statistieken en het cursistenbeeld).
     */
    public function magCursusInzien(): bool
    {
        return match ($this) {
            self::Cursusadministratie, self::Beheerder, self::Bestuur => true,
            default => false,
        };
    }

    /**
     * Is de zichtbaarheid binnen de Cursussen-module beperkt tot de eigen
     * cursus(sen)? Alleen de cursusadministratie (cursusdirecteur) is
     * cursusgebonden; Financiën, Beheer en Bestuur zien alle cursussen.
     */
    public function isCursusBeperkt(): bool
    {
        return $this === self::Cursusadministratie;
    }

    /**
     * Mag deze rol organisaties/relaties beheren (aanmaken, wijzigen)?
     * De relatiebeheerder en de stagecoördinator; Beheer voor onderhoud.
     * Directie en Bestuur kijken mee (alleen-lezen).
     */
    public function magRelatiebeheer(): bool
    {
        return match ($this) {
            self::Relatiebeheerder, self::Stagecoordinator, self::Beheerder => true,
            default => false,
        };
    }

    /**
     * Mag deze rol de stageplaatsen en plaatsingen beheren? De stagecoördinator
     * en Beheer. (De schermen hiervoor komen in een latere fase; de bevoegdheid
     * staat nu al vast.)
     */
    public function magStagebeheer(): bool
    {
        return match ($this) {
            self::Stagecoordinator, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol de module Relatiebeheer inzien (lijsten, relatiekaart)? */
    public function magRelatieInzien(): bool
    {
        return match ($this) {
            self::Relatiebeheerder, self::Stagecoordinator,
            self::Directie, self::Bestuur, self::Beheerder => true,
            default => false,
        };
    }

    /**
     * Is de zichtbaarheid binnen Relatiebeheer beperkt tot de eigen opleiding(en)?
     * De relatiebeheerder, de stagecoördinator en de Directie zijn
     * opleidinggebonden; Bestuur en Beheer zien alle relaties.
     */
    public function isRelatieBeperkt(): bool
    {
        return match ($this) {
            self::Relatiebeheerder, self::Stagecoordinator, self::Directie => true,
            default => false,
        };
    }

    /**
     * Ziet deze rol álle opleidingen (dus niet opleidinggebonden) binnen de
     * Studentenzaken-context? Gebruikt bij multi-rol om de scoping te verruimen:
     * heeft iemand naast Directie ook zo'n rol, dan vervalt de opleidinggrens.
     * De Directie is juist wél opleidinggebonden en staat hier daarom niet in.
     */
    public function zietAlleOpleidingen(): bool
    {
        return match ($this) {
            self::Studentenzaken, self::Financien, self::Examencommissie,
            self::Bestuur, self::Beheerder => true,
            default => false,
        };
    }

    /** Mag deze rol de opgegeven module benaderen? */
    public function magModule(string $sleutel): bool
    {
        $toegestaan = $this->moduleSleutels();

        return in_array('*', $toegestaan, true) || in_array($sleutel, $toegestaan, true);
    }

    /** @return array<int, string> */
    public static function waarden(): array
    {
        return array_map(fn (self $r) => $r->value, self::cases());
    }
}
