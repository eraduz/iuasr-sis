<?php

namespace App\Support;

use App\Enums\Verloftype;
use App\Models\Medewerker;
use App\Models\Verlofaanvraag;
use App\Models\Verlofsaldo;

/**
 * Berekent het verlofsaldo per type voor een medewerker in een jaar: recht
 * (uit `verlofsaldi`) minus opgenomen (som van de GOEDGEKEURDE aanvragen in dat
 * jaar). Saldo = recht − opgenomen.
 */
class Verlofoverzicht
{
    /**
     * @return array<int, array{type: Verloftype, recht: float, opgenomen: float, saldo: float}>
     */
    public static function voor(Medewerker $medewerker, ?int $jaar = null): array
    {
        $jaar ??= (int) date('Y');

        $recht = Verlofsaldo::where('medewerker_id', $medewerker->id)->where('jaar', $jaar)
            ->pluck('recht_uren', 'verloftype');

        $opgenomen = Verlofaanvraag::where('medewerker_id', $medewerker->id)
            ->where('status', 'goedgekeurd')
            ->whereYear('van', $jaar)
            ->selectRaw('verloftype, sum(uren) as u')->groupBy('verloftype')
            ->pluck('u', 'verloftype');

        $rijen = [];
        foreach (Verloftype::cases() as $type) {
            // Wettelijk verlof (WAZO) loopt via een UWV-uitkering en kent geen
            // vakantiesaldo; het hoort niet in deze recht/opgenomen/saldo-tabel.
            if ($type->wettelijk()) {
                continue;
            }
            $r = (float) ($recht[$type->value] ?? 0);
            $o = (float) ($opgenomen[$type->value] ?? 0);
            $rijen[] = ['type' => $type, 'recht' => $r, 'opgenomen' => $o, 'saldo' => round($r - $o, 1)];
        }

        return $rijen;
    }
}
