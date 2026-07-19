<?php

namespace App\Support;

use App\Models\Module;

/**
 * In welke module bevindt de gebruiker zich nu? Afgeleid uit de route, want de
 * modules delen één shell (header + zijbalk) en er is bewust geen module-state
 * in de sessie: een link uit een e-mail of een bladwijzer moet in de juiste
 * module uitkomen zonder dat er eerst iets "gekozen" is.
 *
 * Header en zijbalk lazen dit allebei zelf uit de route; die twee liepen uit
 * elkaar (de header kende alleen Studentenzaken en Cursussen en zette overal
 * anders het etiket 'Studentbeheer'). Deze klasse is nu de enige bron.
 */
class Modulecontext
{
    /**
     * Routepatronen per modulesleutel, in volgorde van toetsing. Studentenzaken
     * staat er niet in: dat is de terugval voor alles wat nergens onder valt.
     *
     * @var array<string, array<int, string>>
     */
    private const PATRONEN = [
        'cursussen' => ['cursussen.*', 'cursisten*'],
        'relatiebeheer' => ['relatiebeheer.*', 'relaties*', 'contactpersonen*', 'contactmomenten*', 'stages*', 'stageplaatsen*', 'agenda*', 'afspraken*', 'relatietaken*', 'overeenkomsten*', 'relatiedocumenten*'],
        'hr' => ['hr.*', 'medewerkers*', 'dienstverbanden*', 'hrdocumenten*', 'verlof*', 'verzuim*', 'ziekmeldingen*', 'gesprekken*', 'gespreksdoelen*', 'competentiescores*', 'checklist*'],
        'balie' => ['balie', 'balie.*'],
        'bibliotheek' => ['bibliotheek.*'],
        'scriptie' => ['scriptie.*'],
        'stichtingsbestuur' => ['stichtingsbestuur.*'],
    ];

    /** De sleutel van de module waarin het huidige verzoek valt. */
    public static function sleutel(): string
    {
        foreach (self::PATRONEN as $sleutel => $patronen) {
            if (request()->routeIs(...$patronen)) {
                return $sleutel;
            }
        }

        return 'studentenzaken';
    }

    /**
     * De naam van de huidige module, zoals die in de registry staat — hetzelfde
     * etiket als op het keuzescherm. Valt terug op de sleutel als de tabel nog
     * niet bestaat (verse migratie, console).
     */
    public static function naam(): string
    {
        $sleutel = self::sleutel();

        try {
            $naam = Module::where('sleutel', $sleutel)->value('naam');
        } catch (\Throwable) {
            $naam = null;
        }

        return $naam ?: ucfirst($sleutel);
    }
}
