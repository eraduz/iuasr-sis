<?php

namespace App\Support;

use App\Enums\Betaalmethode;
use App\Enums\Cursusbetaalstatus;
use App\Enums\CursusinschrijvingStatus;
use App\Models\Cursus;
use Illuminate\Support\Collection;

/**
 * Rapportage over de cursussen: per cursus de inschrijvingen (uitgesplitst naar
 * status) en de cursusgelden (verschuldigd/betaald/openstaand + betaalgraad),
 * plus totalen en een verdeling naar betaalmethode. Voor het cursusdashboard,
 * de cursusrapportage en de bestuurspagina.
 *
 * De financiële cijfers tellen alleen de NIET-geannuleerde inschrijvingen mee:
 * een geannuleerde inschrijving is geen openstaande schuld. Alleen betalingen
 * met status 'Betaald' tellen als voldaan (zie Cursusgeldstatus).
 */
class Cursusrapport
{
    /**
     * @param  Collection<int,Cursus>  $cursussen  met geladen inschrijvingen.betalingen
     * @return array{rijen: Collection, totalen: array, methoden: array}
     */
    public static function voor(Collection $cursussen): array
    {
        $rijen = $cursussen->map(fn (Cursus $c) => self::regel($c))->values();

        return [
            'rijen' => $rijen,
            'totalen' => self::totalen($rijen),
            'methoden' => self::methodeVerdeling($cursussen),
        ];
    }

    /** @return array{verschuldigd: float, betaald: float, openstaand: float, betaalgraad: int} */
    public static function financieelTotaal(Collection $cursussen): array
    {
        $t = self::totalen($cursussen->map(fn (Cursus $c) => self::regel($c)));

        return [
            'verschuldigd' => $t['verschuldigd'],
            'betaald' => $t['betaald'],
            'openstaand' => $t['openstaand'],
            'betaalgraad' => $t['betaalgraad'],
        ];
    }

    private static function regel(Cursus $c): array
    {
        $inschrijvingen = $c->inschrijvingen;

        $perStatus = [];
        foreach (CursusinschrijvingStatus::cases() as $status) {
            $perStatus[$status->value] = $inschrijvingen->filter(fn ($i) => $i->status === $status)->count();
        }

        $relevant = $inschrijvingen->filter(fn ($i) => $i->status !== CursusinschrijvingStatus::Geannuleerd);
        $verschuldigd = $betaald = $openstaand = 0.0;
        $voldaan = 0;
        foreach ($relevant as $inschrijving) {
            $geld = Cursusgeldstatus::voor($inschrijving);
            $verschuldigd += $geld['totaal'];
            $betaald += $geld['betaald'];
            $openstaand += $geld['openstaand'];
            if ($geld['status'] === Cursusgeldstatus::VOLDAAN) {
                $voldaan++;
            }
        }

        return [
            'cursus' => $c,
            'inschrijvingen' => $inschrijvingen->count(),
            'per_status' => $perStatus,
            'verschuldigd' => round($verschuldigd, 2),
            'betaald' => round($betaald, 2),
            'openstaand' => round($openstaand, 2),
            'betaalgraad' => $verschuldigd > 0 ? (int) round($betaald / $verschuldigd * 100) : 0,
            'voldaan' => $voldaan,
            'te_betalen' => $relevant->count(),
        ];
    }

    private static function totalen(Collection $rijen): array
    {
        $verschuldigd = round($rijen->sum('verschuldigd'), 2);
        $betaald = round($rijen->sum('betaald'), 2);

        return [
            'inschrijvingen' => $rijen->sum('inschrijvingen'),
            'verschuldigd' => $verschuldigd,
            'betaald' => $betaald,
            'openstaand' => round($rijen->sum('openstaand'), 2),
            'betaalgraad' => $verschuldigd > 0 ? (int) round($betaald / $verschuldigd * 100) : 0,
            'voldaan' => $rijen->sum('voldaan'),
            'te_betalen' => $rijen->sum('te_betalen'),
        ];
    }

    /** Verdeling van het betaalde bedrag naar betaalmethode (voor een donut). */
    private static function methodeVerdeling(Collection $cursussen): array
    {
        $kleur = [
            Betaalmethode::Ideal->value => '#285C4D',
            Betaalmethode::Overboeking->value => '#1E1446',
            Betaalmethode::Contant->value => '#D69A2D',
        ];

        $sommen = [];
        foreach ($cursussen as $cursus) {
            foreach ($cursus->inschrijvingen as $inschrijving) {
                foreach ($inschrijving->betalingen as $betaling) {
                    if ($betaling->betalingsstatus === Cursusbetaalstatus::Betaald) {
                        $key = $betaling->betaalmethode->value;
                        $sommen[$key] = ($sommen[$key] ?? 0) + (float) $betaling->bedrag;
                    }
                }
            }
        }

        return collect(Betaalmethode::cases())
            ->map(fn (Betaalmethode $m) => [
                'label' => $m->label(),
                'value' => (int) round($sommen[$m->value] ?? 0),
                'kleur' => $kleur[$m->value],
            ])
            ->filter(fn ($r) => $r['value'] > 0)
            ->values()->all();
    }
}
