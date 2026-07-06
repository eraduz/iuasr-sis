<?php

namespace App\Support;

use App\Models\Student;

/**
 * Genereert het leesbare studentnummer.
 *
 * BEVESTIGD formaat: 2-cijferige jaarprefix + volgnummer, totaal 6 tekens
 * (voorbeeld: 261234 = jaar 2026, volgnummer 1234). De lengte is instelbaar via
 * config('sis.studentnummer').
 *
 * Belangrijk: dit nummer is een uniek, leesbaar VELD — nooit een koppelsleutel.
 * De interne identiteit blijft de surrogaatsleutel (Student::id).
 */
class StudentnummerGenerator
{
    /**
     * Bepaal het volgende vrije studentnummer voor het gegeven inschrijfjaar.
     * De unieke index op studenten.studentnummer vangt gelijktijdige inserts af;
     * de aanroeper hoort bij een unieke-sleutelbotsing opnieuw te genereren.
     */
    public static function genereer(int $inschrijfjaar): string
    {
        $prefixLengte = (int) config('sis.studentnummer.jaarprefix_lengte', 2);
        $volgLengte = (int) config('sis.studentnummer.volgnummer_lengte', 4);

        $prefix = substr((string) $inschrijfjaar, -$prefixLengte); // '2026' -> '26'

        // Hoogste bestaande volgnummer binnen deze jaarreeks.
        $laatste = Student::query()
            ->where('studentnummer', 'like', $prefix.'%')
            ->orderByRaw('LENGTH(studentnummer) DESC')
            ->orderByDesc('studentnummer')
            ->value('studentnummer');

        $volgnummer = 1;
        if ($laatste !== null) {
            $volgnummer = ((int) substr($laatste, $prefixLengte)) + 1;
        }

        return $prefix.str_pad((string) $volgnummer, $volgLengte, '0', STR_PAD_LEFT);
    }
}
