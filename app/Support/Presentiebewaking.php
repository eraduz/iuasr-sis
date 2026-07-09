<?php

namespace App\Support;

use App\Models\Inschrijving;
use App\Models\Presentie;
use App\Models\Vak;
use App\Models\Vaktoewijzing;
use Illuminate\Support\Collection;

/**
 * Aanwezigheidsbewaking. Een blok telt een vast aantal onderwijsweken
 * (config sis.presentie.weken_per_blok); de docent registreert per week
 * 1 (aanwezig) of 0 (afwezig). Een niet-geregistreerde week telt NIET mee
 * als afwezigheid — dat zou de docent zijn nalatigheid op de student afwentelen.
 *
 * Norm: 80% aanwezigheid, of 50% voor studenten aan wie Studentenzaken de
 * 50%-aanwezigheidsregeling heeft toegekend. Vrijgestelde studenten volgen
 * het vak niet en worden buiten de registratie en de statistiek gehouden.
 */
class Presentiebewaking
{
    /** @return list<int> de onderwijsweken van een blok, 1..n */
    public static function weken(): array
    {
        return range(1, (int) config('sis.presentie.weken_per_blok', 8));
    }

    /** De aanwezigheidsnorm voor deze inschrijving, als fractie (0.8 of 0.5). */
    public static function norm(Inschrijving $inschrijving): float
    {
        return $inschrijving->aanwezigheidsregeling_50
            ? (float) config('sis.presentie.norm_regeling', 0.50)
            : (float) config('sis.presentie.norm', 0.80);
    }

    /** Inschrijving-ids die voor dit vak zijn vrijgesteld (geen presentieplicht). */
    public static function vrijgesteldeInschrijvingen(Vak $vak, Collection $inschrijvingIds): Collection
    {
        return Vaktoewijzing::where('vak_id', $vak->id)
            ->where('vrijgesteld', true)
            ->whereIn('inschrijving_id', $inschrijvingIds)
            ->pluck('inschrijving_id');
    }

    /**
     * Aanwezigheidsstatus van één student voor één vak.
     *
     * @param  Collection<int, Presentie>  $eigen  de presenties van deze inschrijving
     * @return array{geregistreerd:int, aanwezig:int, afwezig:int, percentage:?int, norm:int, status:string}
     *                                              status: voldoende | onvoldoende | onbekend
     */
    public static function status(Inschrijving $inschrijving, Collection $eigen): array
    {
        $geregistreerd = $eigen->count();
        $aanwezig = $eigen->where('aanwezig', true)->count();
        $norm = self::norm($inschrijving);

        $percentage = $geregistreerd > 0 ? (int) round($aanwezig / $geregistreerd * 100) : null;

        return [
            'geregistreerd' => $geregistreerd,
            'aanwezig' => $aanwezig,
            'afwezig' => $geregistreerd - $aanwezig,
            'percentage' => $percentage,
            'norm' => (int) round($norm * 100),
            'status' => $percentage === null
                ? 'onbekend'
                : ($percentage >= $norm * 100 ? 'voldoende' : 'onvoldoende'),
        ];
    }

    /**
     * Volledige presentielijst van een vak: één rij per deelnemer, met de
     * registratie per week, het percentage en de status.
     *
     * @return array{rijen: Collection, samenvatting: array}
     */
    public static function voorVak(Vak $vak): array
    {
        $deelnemers = $vak->deelnemers()->get();
        $ids = $deelnemers->pluck('id');

        $presenties = Presentie::where('vak_id', $vak->id)
            ->whereIn('inschrijving_id', $ids)->get()->groupBy('inschrijving_id');
        $vrijgesteld = self::vrijgesteldeInschrijvingen($vak, $ids)->flip();

        $rijen = $deelnemers->map(function (Inschrijving $insch) use ($presenties, $vrijgesteld) {
            $eigen = $presenties->get($insch->id, collect());
            $vrij = isset($vrijgesteld[$insch->id]);

            return [
                'inschrijving' => $insch,
                'student' => $insch->student,
                'vrijgesteld' => $vrij,
                'regeling' => (bool) $insch->aanwezigheidsregeling_50,
                'weken' => $eigen->keyBy('week')->map(fn (Presentie $p) => $p->aanwezig),
                'status' => $vrij ? null : self::status($insch, $eigen),
            ];
        });

        return ['rijen' => $rijen, 'samenvatting' => self::samenvatting($rijen)];
    }

    /**
     * Samenvatting over de rijen van één vak: hoeveel weken volledig
     * geregistreerd, gemiddelde aanwezigheid, hoeveel studenten onder de norm.
     */
    public static function samenvatting(Collection $rijen): array
    {
        $plichtig = $rijen->where('vrijgesteld', false);
        $weken = self::weken();

        // Een week is 'volledig geregistreerd' als élke presentieplichtige
        // deelnemer voor die week een registratie heeft.
        $volledig = 0;
        $ontbrekend = [];
        foreach ($weken as $week) {
            $gevuld = $plichtig->filter(fn ($r) => $r['weken']->has($week))->count();
            if ($plichtig->isNotEmpty() && $gevuld === $plichtig->count()) {
                $volledig++;
            } else {
                $ontbrekend[] = $week;
            }
        }

        $percentages = $plichtig->pluck('status.percentage')->filter(fn ($p) => $p !== null);
        $onderNorm = $plichtig->filter(fn ($r) => $r['status']['status'] === 'onvoldoende')->count();

        return [
            'deelnemers' => $plichtig->count(),
            'vrijgesteld' => $rijen->count() - $plichtig->count(),
            'met_regeling' => $plichtig->where('regeling', true)->count(),
            'weken_totaal' => count($weken),
            'weken_geregistreerd' => $volledig,
            'weken_ontbrekend' => $ontbrekend,
            'volledig' => $plichtig->isNotEmpty() && $volledig === count($weken),
            'gemiddeld' => $percentages->count() ? (int) round($percentages->avg()) : null,
            'onder_norm' => $onderNorm,
        ];
    }

    /**
     * Compacte registratiestand per vak, zonder de volledige lijst op te
     * bouwen. Voor 'Mijn vakken', het presentieoverzicht en de dashboards.
     *
     * @param  Collection<int, Vak>  $vakken
     * @return Collection<int, array> gekeyd op vak_id
     */
    public static function standPerVak(Collection $vakken): Collection
    {
        return $vakken->mapWithKeys(function (Vak $vak) {
            ['samenvatting' => $s] = self::voorVak($vak);

            return [$vak->id => $s];
        });
    }
}
