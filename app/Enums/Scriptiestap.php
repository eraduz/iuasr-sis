<?php

namespace App\Enums;

use App\Models\User;

/**
 * De elf vaste stappen van het scriptietraject (module Scriptie Coördinatie),
 * naar het model van de afstudeer-/HR-checklist maar rijker: elke stap heeft een
 * eigen formulier, een eigen set toegestane statussen en soms een ja/nee-checklist.
 *
 * De stappen worden als tabbladen getoond en SEQUENTIEEL afgevinkt. Per stap ligt
 * de eindverantwoordelijkheid bij één rol (de Beheerder mag altijd corrigeren):
 *  - Scriptiecoördinator: toelating, voorstel, begeleidertoewijzing, overeenkomst,
 *    plagiaatcontrole en afronding (de coördinerende/administratieve stappen).
 *  - Examencommissie (scriptiecommissie/examinator): onderwerpbeoordeling,
 *    beoordeling en verdediging (de academische besluiten).
 *  - Docent (scriptiebegeleider): plan van aanpak en definitieve inlevering
 *    (de begeleider geeft akkoord/toestemming).
 *
 * Alle stap-metadata leeft hier in de enum; de database bewaart per traject alleen
 * de STAND per stap (status, gereed, wie/wanneer, opmerking) — zie ScriptieStapstand.
 */
enum Scriptiestap: string
{
    case Toelating = 'toelating';
    case Voorstel = 'voorstel';
    case Onderwerpbeoordeling = 'onderwerpbeoordeling';
    case Begeleider = 'begeleider';
    case Overeenkomst = 'overeenkomst';
    case PlanVanAanpak = 'plan_van_aanpak';
    case Inlevering = 'inlevering';
    case Plagiaat = 'plagiaat';
    case Beoordeling = 'beoordeling';
    case Verdediging = 'verdediging';
    case Afronding = 'afronding';

    /** Korte titel voor het tabblad. */
    public function label(): string
    {
        return match ($this) {
            self::Toelating => 'Toelatingseisen',
            self::Voorstel => 'Scriptievoorstel',
            self::Onderwerpbeoordeling => 'Onderwerpbeoordeling',
            self::Begeleider => 'Begeleider',
            self::Overeenkomst => 'Scriptieovereenkomst',
            self::PlanVanAanpak => 'Plan van Aanpak',
            self::Inlevering => 'Definitieve inlevering',
            self::Plagiaat => 'Plagiaatcontrole',
            self::Beoordeling => 'Beoordeling',
            self::Verdediging => 'Verdediging',
            self::Afronding => 'Afronding',
        };
    }

    /** Uitgebreide omschrijving (kop van het tabblad-paneel). */
    public function omschrijving(): string
    {
        return match ($this) {
            self::Toelating => 'Controle of de student aan de toelatingseisen voldoet (minimaal '
                .(int) config('sis.scriptie.toelating_ec', 180).' EC behaald en Methoden en Technieken I én II afgerond).',
            self::Voorstel => 'Het scriptievoorstel van de student wordt ontvangen en gecontroleerd op volledigheid.',
            self::Onderwerpbeoordeling => 'Het voorgestelde onderwerp wordt beoordeeld door de scriptiecoördinator, de opleidingsdirecteur en de scriptiecommissie.',
            self::Begeleider => 'Na goedkeuring van het onderwerp wordt een scriptiebegeleider toegewezen.',
            self::Overeenkomst => 'De scriptieovereenkomst wordt opgesteld en digitaal ondertekend door student, begeleider, coördinator en opleidingsdirecteur.',
            self::PlanVanAanpak => 'De student schrijft het Plan van Aanpak; de begeleider en de scriptiecommissie beoordelen het.',
            self::Inlevering => 'Vóór de definitieve inlevering wordt een digitale inleverchecklist ingevuld.',
            self::Plagiaat => 'De scriptie wordt digitaal gecontroleerd op plagiaat.',
            self::Beoordeling => 'De begeleider en de examinator beoordelen de scriptie onafhankelijk van elkaar.',
            self::Verdediging => 'De student verdedigt de scriptie voor de scriptiecommissie.',
            self::Afronding => 'Het scriptietraject wordt afgerond, het eindcijfer geregistreerd en de scriptie gearchiveerd.',
        };
    }

    /** 1..11 — de volgorde waarin de stappen worden doorlopen. */
    public function volgorde(): int
    {
        return match ($this) {
            self::Toelating => 1,
            self::Voorstel => 2,
            self::Onderwerpbeoordeling => 3,
            self::Begeleider => 4,
            self::Overeenkomst => 5,
            self::PlanVanAanpak => 6,
            self::Inlevering => 7,
            self::Plagiaat => 8,
            self::Beoordeling => 9,
            self::Verdediging => 10,
            self::Afronding => 11,
        };
    }

    /** De rol die deze stap afvinkt (naast de Beheerder, die altijd mag corrigeren). */
    public function verantwoordelijke(): Rol
    {
        return match ($this) {
            self::Toelating, self::Voorstel, self::Begeleider,
            self::Overeenkomst, self::Plagiaat, self::Afronding => Rol::Scriptiecoordinator,
            self::Onderwerpbeoordeling, self::Beoordeling, self::Verdediging => Rol::Examencommissie,
            self::PlanVanAanpak, self::Inlevering => Rol::Docent,
        };
    }

    /**
     * Mag deze gebruiker deze stap afvinken/heropenen? De verantwoordelijke rol of
     * de Beheerder. De rolscheiding is bewust strikt: de coördinator kan een
     * academische stap (beoordeling, verdediging, onderwerpbesluit) niet zelf
     * afvinken, en de begeleider niet de coördinerende stappen.
     */
    public function magAfvinkenDoor(User $user): bool
    {
        return $user->heeftRol(Rol::Beheerder) || $user->heeftRol($this->verantwoordelijke());
    }

    /** De afrondende stap: het afvinken hiervan rondt het scriptietraject af. */
    public function isAfrondend(): bool
    {
        return $this === self::Afronding;
    }

    /** Heeft deze stap een ja/nee-checklist (naast het formulier)? */
    public function heeftChecklist(): bool
    {
        return $this->checklistpunten() !== [];
    }

    /**
     * De checklistpunten van deze stap: sleutel => omschrijving. Leeg als de stap
     * geen ja/nee-checklist heeft. Deze sjablonen worden bij het aanmaken van een
     * traject in scriptie_checklistpunten weggeschreven, zodat de tekst ook bij een
     * latere wijziging van deze lijst per traject bewaard blijft.
     *
     * @return array<string, string>
     */
    public function checklistpunten(): array
    {
        return match ($this) {
            self::Onderwerpbeoordeling => [
                'haalbaar' => 'Is het onderwerp haalbaar?',
                'actueel' => 'Is het onderwerp voldoende actueel?',
                'past_opleiding' => 'Past het onderwerp binnen de opleiding?',
                'relevante_bijdrage' => 'Levert het onderzoek een relevante bijdrage?',
                'vraag_duidelijk' => 'Is de onderzoeksvraag voldoende duidelijk?',
                'doelgroep_passend' => 'Is de doelgroep passend?',
                'methode_uitvoerbaar' => 'Is de voorgestelde onderzoeksmethode uitvoerbaar?',
                'eindkwalificaties' => 'Sluit het onderwerp aan bij de eindkwalificaties van de opleiding?',
            ],
            self::Inlevering => [
                'toestemming_begeleider' => 'De begeleider heeft toestemming gegeven voor inlevering.',
                'naam_studentnummer' => 'Naam en studentnummer zijn correct vermeld.',
                'naam_begeleider' => 'De naam van de begeleider is vermeld.',
                'titel_correct' => 'De scriptietitel is correct.',
                'inhoudsopgave' => 'De inhoudsopgave is volledig.',
                'samenvatting' => 'De samenvatting is toegevoegd.',
                'spelling' => 'De spelling en grammatica zijn gecontroleerd.',
                'paginanummers' => 'De paginanummers zijn correct.',
                'literatuurlijst' => 'De literatuurlijst is toegevoegd.',
                'bronverwijzingen' => 'De bronverwijzingen voldoen aan de Chicagostijl.',
                'bijlagen' => 'De bijlagen zijn genummerd en voorzien van titels.',
                'opmaakeisen' => 'Het document voldoet aan de opmaakeisen.',
                'plagiaatcontrole' => 'De plagiaatcontrole is uitgevoerd.',
                'akkoord_definitief' => 'De student heeft akkoord gegeven voor definitieve inlevering.',
            ],
            self::Beoordeling => [
                'probleemstelling' => 'Probleemstelling, hoofdvraag, deelvragen en doelstellingen',
                'theoretisch_kader' => 'Theoretisch kader en literatuuronderzoek',
                'methodiek' => 'Onderzoeksmethodiek',
                'analyse' => 'Analyse van de onderzoeksresultaten',
                'conclusies' => 'Conclusies',
                'aanbevelingen' => 'Aanbevelingen',
                'rapportage' => 'Rapportage en vormgeving',
                'presentatie' => 'Presentatie en verdediging',
            ],
            self::Afronding => [
                'inhoudelijk_goedgekeurd' => 'De scriptie is inhoudelijk goedgekeurd.',
                'verdediging_behaald' => 'De verdediging is behaald.',
                'feedback_verwerkt' => 'De feedback is verwerkt.',
                'eindversie_ingeleverd' => 'De eindversie is ingeleverd.',
                'eindcijfer_geregistreerd' => 'Het eindcijfer is geregistreerd.',
                'formulieren_opgeslagen' => 'De beoordelingsformulieren zijn opgeslagen.',
                'gearchiveerd' => 'De scriptie is gearchiveerd.',
            ],
            default => [],
        };
    }

    /**
     * De toegestane statussen van deze stap: sleutel => label. De eerste is de
     * standaard-/beginstatus. Wordt gebruikt voor de statuskeuze én voor validatie.
     *
     * @return array<string, string>
     */
    public function statussen(): array
    {
        return match ($this) {
            self::Toelating => [
                'in_behandeling' => 'Controle in behandeling',
                'behaald' => 'Toelatingseisen behaald',
                'niet_behaald' => 'Toelatingseisen niet behaald',
                'documenten_vereist' => 'Aanvullende documenten vereist',
            ],
            self::Voorstel => [
                'concept' => 'Concept',
                'ingediend' => 'Ingediend',
                'in_behandeling' => 'In behandeling',
                'aanvulling_vereist' => 'Aanvulling vereist',
                'goedgekeurd' => 'Goedgekeurd',
                'afgewezen' => 'Afgewezen',
            ],
            self::Onderwerpbeoordeling => [
                'in_behandeling' => 'In behandeling',
                'goedgekeurd' => 'Onderwerp goedgekeurd',
                'voorlopig_goedgekeurd' => 'Onderwerp voorlopig goedgekeurd',
                'aanpassing_vereist' => 'Aanpassing vereist',
                'nieuw_onderwerp_vereist' => 'Nieuw onderwerp vereist',
                'afgewezen' => 'Onderwerp afgewezen',
            ],
            self::Begeleider => [
                'voorgesteld' => 'Begeleider voorgesteld',
                'toegewezen' => 'Begeleider toegewezen',
                'geaccepteerd' => 'Toewijzing geaccepteerd',
                'eerste_gesprek_gepland' => 'Eerste gesprek gepland',
                'gestart' => 'Begeleiding gestart',
            ],
            self::Overeenkomst => [
                'niet_gestart' => 'Niet gestart',
                'wacht_student' => 'Wacht op student',
                'wacht_begeleider' => 'Wacht op begeleider',
                'wacht_coordinator' => 'Wacht op scriptiecoördinator',
                'wacht_directeur' => 'Wacht op opleidingsdirecteur',
                'volledig_ondertekend' => 'Volledig ondertekend',
            ],
            self::PlanVanAanpak => [
                'concept' => 'Concept',
                'ingediend_begeleider' => 'Ingediend bij begeleider',
                'feedback_ontvangen' => 'Feedback ontvangen',
                'herziening_vereist' => 'Herziening vereist',
                'goedgekeurd_begeleider' => 'Goedgekeurd door begeleider',
                'ingediend_commissie' => 'Ingediend bij scriptiecommissie',
                'goedgekeurd_commissie' => 'Goedgekeurd door scriptiecommissie',
                'afgewezen' => 'Afgewezen',
                'nieuw_onderwerp_vereist' => 'Nieuw onderwerp vereist',
            ],
            self::Inlevering => [
                'checklist_niet_voltooid' => 'Checklist niet voltooid',
                'checklist_voltooid' => 'Checklist voltooid',
                'geblokkeerd' => 'Inlevering geblokkeerd',
                'definitief_ingeleverd' => 'Definitief ingeleverd',
                'ontvangst_bevestigd' => 'Ontvangst bevestigd',
                'teruggestuurd' => 'Teruggestuurd voor herstel',
            ],
            self::Plagiaat => [
                'niet_gecontroleerd' => 'Nog niet gecontroleerd',
                'controle_uitgevoerd' => 'Controle wordt uitgevoerd',
                'rapport_beschikbaar' => 'Rapport beschikbaar',
                'geen_bijzonderheden' => 'Geen bijzonderheden',
                'nadere_controle' => 'Nadere controle vereist',
                'vermoeden' => 'Vermoeden van plagiaat',
                'doorgestuurd' => 'Doorgestuurd naar de examencommissie',
            ],
            self::Beoordeling => [
                'niet_gestart' => 'Beoordeling nog niet gestart',
                'eerste_ontvangen' => 'Eerste beoordeling ontvangen',
                'tweede_ontvangen' => 'Tweede beoordeling ontvangen',
                'kalibratie_vereist' => 'Kalibratie vereist',
                'derde_toegewezen' => 'Derde beoordelaar toegewezen',
                'afgerond' => 'Beoordeling afgerond',
                'eindcijfer_vastgesteld' => 'Eindcijfer vastgesteld',
            ],
            self::Verdediging => [
                'gepland' => 'Verdediging gepland',
                'behaald' => 'Verdediging behaald',
                'onvoldoende' => 'Verdediging onvoldoende',
                'nieuwe_vereist' => 'Nieuwe verdediging vereist',
                'correcties_vereist' => 'Aanvullende correcties vereist',
                'traject_afgerond' => 'Scriptietraject afgerond',
            ],
            self::Afronding => [
                'wacht_eindversie' => 'Wacht op eindversie',
                'wacht_cijfer' => 'Wacht op registratie van het cijfer',
                'afgerond' => 'Afgerond',
                'afgerond_met_cijfer' => 'Afgerond met cijfer',
                'herkansing_vereist' => 'Herkansing vereist',
                'beeindigd' => 'Traject beëindigd',
            ],
        };
    }

    /** De begin-/standaardstatus van deze stap (de eerste in de lijst). */
    public function standaardStatus(): string
    {
        return array_key_first($this->statussen());
    }

    /** Het label bij een opgeslagen statussleutel (met terugval op de sleutel zelf). */
    public function statusLabel(?string $sleutel): string
    {
        if ($sleutel === null || $sleutel === '') {
            return '—';
        }

        return $this->statussen()[$sleutel] ?? $sleutel;
    }

    /**
     * De CSS-badgeklasse (design system, iuasr-dash-status s-*) bij een status.
     * Positieve eindstatussen groen, afwijzingen rood, begin/neutraal grijs.
     */
    public function badge(?string $sleutel): string
    {
        return match ($sleutel) {
            'behaald', 'goedgekeurd', 'voorlopig_goedgekeurd', 'geaccepteerd', 'gestart',
            'volledig_ondertekend', 'goedgekeurd_begeleider', 'goedgekeurd_commissie',
            'checklist_voltooid', 'definitief_ingeleverd', 'ontvangst_bevestigd',
            'geen_bijzonderheden', 'rapport_beschikbaar', 'afgerond', 'eindcijfer_vastgesteld',
            'eindcijfer_geregistreerd', 'traject_afgerond', 'afgerond_met_cijfer' => 's-approved',
            'niet_behaald', 'afgewezen', 'geblokkeerd', 'teruggestuurd', 'vermoeden',
            'onvoldoende', 'nieuwe_vereist', 'nieuw_onderwerp_vereist', 'beeindigd',
            'herkansing_vereist' => 's-rejected',
            'aanvulling_vereist', 'documenten_vereist', 'aanpassing_vereist',
            'herziening_vereist', 'kalibratie_vereist', 'nadere_controle',
            'correcties_vereist', 'wacht_eindversie', 'wacht_cijfer' => 's-docs',
            'ingediend', 'ingediend_begeleider', 'ingediend_commissie', 'voorgesteld',
            'toegewezen', 'eerste_gesprek_gepland', 'gepland', 'eerste_ontvangen',
            'tweede_ontvangen', 'derde_toegewezen', 'feedback_ontvangen', 'controle_uitgevoerd',
            'doorgestuurd', 'wacht_student', 'wacht_begeleider', 'wacht_coordinator',
            'wacht_directeur' => 's-submitted',
            default => 's-draft',
        };
    }

    /**
     * De stappen in volgorde 1..11.
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
