<?php

namespace App\Support;

use App\Enums\Cursusbetaalstatus;
use App\Models\Cursusinschrijving;

/**
 * Financiële status van één cursusinschrijving. Het cursusgeld
 * (`totaalbedrag`, momentopname bij inschrijving) wordt afgezet tegen de
 * daadwerkelijk voldane betalingen. Alleen betalingen met status 'Betaald'
 * tellen mee; een openstaande iDEAL-transactie, een mislukte poging of een
 * terugbetaling niet.
 */
class Cursusgeldstatus
{
    public const VOLDAAN = 'voldaan';
    public const DEELS = 'deels';
    public const OPEN = 'open';

    /**
     * @return array{totaal: float, betaald: float, openstaand: float, status: string}
     */
    public static function voor(Cursusinschrijving $inschrijving): array
    {
        $totaal = (float) $inschrijving->totaalbedrag;

        $betaald = (float) $inschrijving->betalingen
            ->where('betalingsstatus', Cursusbetaalstatus::Betaald)
            ->sum('bedrag');

        $openstaand = round(max(0, $totaal - $betaald), 2);

        $status = match (true) {
            $totaal > 0 && $openstaand <= 0.001 => self::VOLDAAN,
            $betaald > 0 => self::DEELS,
            default => self::OPEN,
        };

        return [
            'totaal' => round($totaal, 2),
            'betaald' => round($betaald, 2),
            'openstaand' => $openstaand,
            'status' => $status,
        ];
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::VOLDAAN => 'Voldaan',
            self::DEELS => 'Deels betaald',
            self::OPEN => 'Openstaand',
            default => $status,
        };
    }

    public static function statusBadge(string $status): string
    {
        return match ($status) {
            self::VOLDAAN => 's-approved',
            self::DEELS => 's-incomplete',
            self::OPEN => 's-rejected',
            default => 's-draft',
        };
    }
}
