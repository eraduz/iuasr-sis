<?php

namespace App\Enums;

/**
 * De vaste stappen van het afstudeerproces (examencommissie-gedreven, naar het
 * model van de HR-offboarding-checklist). De stappen worden SEQUENTIEEL afgevinkt;
 * per stap ligt de verantwoordelijkheid strikt bij één rol (de Beheerder mag
 * altijd corrigeren). De laatste stap studeert de student af.
 */
enum Afstudeerstap: string
{
    case Verzoek = 'verzoek';
    case Vakken = 'vakken';
    case StageScriptie = 'stage_scriptie';
    case DiplomaKlaarmaken = 'diploma_klaarmaken';
    case DiplomaUitreiken = 'diploma_uitreiken';

    public function label(): string
    {
        return match ($this) {
            self::Verzoek => 'Afstudeerverzoek ingediend',
            self::Vakken => 'Alle vakken gecontroleerd',
            self::StageScriptie => 'Stage en scriptie gecontroleerd',
            self::DiplomaKlaarmaken => 'Diploma klaarmaken',
            self::DiplomaUitreiken => 'Diploma uitgereikt',
        };
    }

    public function omschrijving(): string
    {
        return match ($this) {
            self::Verzoek => 'De student heeft een verzoek tot afstuderen ingediend; de examencommissie registreert de ontvangst.',
            self::Vakken => 'De examencommissie controleert of alle vakken (met de bijbehorende EC) zijn behaald.',
            self::StageScriptie => 'De examencommissie controleert de stage en de scriptie.',
            self::DiplomaKlaarmaken => 'Studentenzaken maakt het diploma en de gewaarmerkte cijferlijst gereed.',
            self::DiplomaUitreiken => 'Het diploma is uitgereikt; hiermee is de student afgestudeerd.',
        };
    }

    /** 1..5 — de stappen worden in deze volgorde doorlopen. */
    public function volgorde(): int
    {
        return match ($this) {
            self::Verzoek => 1,
            self::Vakken => 2,
            self::StageScriptie => 3,
            self::DiplomaKlaarmaken => 4,
            self::DiplomaUitreiken => 5,
        };
    }

    /** De rol die deze stap afvinkt (naast de Beheerder). */
    public function verantwoordelijke(): Rol
    {
        return match ($this) {
            self::Verzoek, self::Vakken, self::StageScriptie => Rol::Examencommissie,
            self::DiplomaKlaarmaken, self::DiplomaUitreiken => Rol::Studentenzaken,
        };
    }

    /** De afrondende stap: het afvinken hiervan studeert de student af. */
    public function isAfrondend(): bool
    {
        return $this === self::DiplomaUitreiken;
    }

    /** Mag deze gebruiker deze stap afvinken? Alleen de verantwoordelijke rol of de Beheerder. */
    public function magAfvinkenDoor(\App\Models\User $user): bool
    {
        return $user->heeftRol(Rol::Beheerder) || $user->heeftRol($this->verantwoordelijke());
    }

    /**
     * De stappen in volgorde 1..5.
     *
     * @return array<int, self>
     */
    public static function inVolgorde(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b) => $a->volgorde() <=> $b->volgorde());

        return $cases;
    }
}
