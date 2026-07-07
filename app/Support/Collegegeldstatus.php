<?php

namespace App\Support;

use App\Models\CollegegeldTarief;
use App\Models\Inschrijving;
use App\Models\Student;

/**
 * Bepaalt de financiële status van een student: het verschuldigde collegegeld
 * (op basis van de tarieven per studiejaar/opleiding), het betaalde bedrag, en
 * of er een betalingsachterstand is.
 *
 * De achterstand stuurt de blokkades aan (studievoortgang, documenten) en de
 * waarschuwing op het studentdossier.
 */
class Collegegeldstatus
{
    /**
     * @return array{verschuldigd: float, betaald: float, openstaand: float, achterstand: bool}
     */
    public static function voor(Student $student): array
    {
        $student->loadMissing(['inschrijvingen', 'betalingen']);

        $verschuldigd = 0.0;
        foreach ($student->inschrijvingen as $insch) {
            $verschuldigd += self::tarief($insch) ?? 0.0;
        }

        $betaald = (float) $student->betalingen->sum('bedrag');
        $openstaand = round(max(0, $verschuldigd - $betaald), 2);

        return [
            'verschuldigd' => round($verschuldigd, 2),
            'betaald' => round($betaald, 2),
            'openstaand' => $openstaand,
            'achterstand' => $openstaand > 0,
        ];
    }

    public static function heeftAchterstand(Student $student): bool
    {
        return self::voor($student)['achterstand'];
    }

    /**
     * Het geldende tarief voor een inschrijving: een opleiding-specifiek tarief
     * gaat vóór het standaardtarief (opleiding_id null) van hetzelfde studiejaar.
     */
    public static function tarief(Inschrijving $insch): ?float
    {
        $tarief = CollegegeldTarief::query()
            ->where('periode_id', $insch->periode_id)
            ->where(function ($q) use ($insch) {
                $q->where('opleiding_id', $insch->opleiding_id)->orWhereNull('opleiding_id');
            })
            ->orderByRaw('opleiding_id is null') // false (0) eerst => specifiek tarief wint
            ->first();

        return $tarief ? (float) $tarief->bedrag : null;
    }
}
