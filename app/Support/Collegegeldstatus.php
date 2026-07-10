<?php

namespace App\Support;

use App\Enums\InschrijvingStatus;
use App\Models\CollegegeldTarief;
use App\Models\Inschrijving;
use App\Models\Student;
use Carbon\Carbon;

/**
 * Berekent de financiële status van een student.
 *
 * Het collegegeld wordt PER OPLEIDING geheven en gefactureerd in TERMIJNEN
 * (september, november, januari, maart en mei), of in één factuur wanneer de
 * student daarvoor kiest. Zie {@see Collegegeldtermijnen} voor het schema.
 *
 * Volgt een student twee opleidingen, dan heeft elke inschrijving een eigen
 * rekening. Op de tweede opleiding legt Studentenzaken doorgaans een korting
 * vast (`inschrijvingen.korting_percentage`). Een achterstand bij één van de
 * opleidingen blokkeert herinschrijven en verklaringen: de student heeft dan
 * een schuld aan de instelling.
 *
 *   verschuldigd  = som van de niet-vervallen termijnen van dit studiejaar
 *   achterstallig = het openstaande deel van de termijnen waarvan de
 *                   VERVALDATUM verstreken is
 *
 * Een student heeft dus pas een betalingsACHTERSTAND wanneer een vervallen
 * termijn niet (volledig) is voldaan — niet zodra het pro rata bedrag van de
 * lopende maand nog niet binnen is. Dat is hoe een factuuradministratie werkt
 * en het is navolgbaar voor student, Studentenzaken en boekhouding.
 *
 * Onderliggend blijft het pro rata beginsel gelden: het studiejaar loopt van
 * 1 september t/m 31 juli, maandbedrag = jaarbedrag ÷ 12, en wie zich
 * tussentijds uitschrijft is alleen de verstreken maanden verschuldigd
 * (t/m het einde van de uitschrijfmaand). De termijnen worden dan herrekend.
 */
class Collegegeldstatus
{
    public const MAANDEN_PER_JAAR = 12;

    /**
     * @return array{jaarbedrag: float|null, maandbedrag: float|null, maanden: int,
     *   verschuldigd: float, betaald: float, openstaand: float, achterstallig: float,
     *   terugbetaling: float, saldo: float, achterstand: bool}
     */
    public static function voor(Student $student, ?Carbon $peildatum = null): array
    {
        $student->loadMissing(['inschrijvingen.periode', 'inschrijvingen.betalingen', 'betalingen']);
        $peildatum ??= Carbon::now();

        $verschuldigd = 0.0;
        $achterstallig = 0.0;
        $maanden = 0;
        $jaarbedrag = 0.0;
        $heeftTarief = false;

        // Collegegeld wordt PER OPLEIDING geheven: elke inschrijving telt mee,
        // elk met een eigen termijnschema en een eventuele korting. Volgt een
        // student twee opleidingen, dan betaalt hij voor beide.
        foreach ($student->inschrijvingen as $inschrijving) {
            $verschuldigd += Collegegeldtermijnen::totaal($inschrijving, $peildatum);
            $achterstallig += Collegegeldtermijnen::achterstallig($inschrijving, $peildatum);

            $eigenJaarbedrag = self::jaarbedrag($inschrijving);
            if ($eigenJaarbedrag !== null) {
                $heeftTarief = true;
                $jaarbedrag += $eigenJaarbedrag;
            }
            // 'maanden' beschrijft de inschrijvingsduur, niet een geldbedrag:
            // bij twee gelijktijdige opleidingen is dat de langste, niet de som.
            $maanden = max($maanden, self::maanden($inschrijving, $peildatum));
        }

        $betaald = (float) $student->betalingen->sum('bedrag');
        $openstaand = round(max(0, $verschuldigd - $betaald), 2);
        $achterstallig = round($achterstallig, 2);
        $teveel = round(max(0, $betaald - $verschuldigd), 2);

        // Een lopende (nog niet beëindigde) inschrijving is het volledige jaar
        // verschuldigd; wie vooruit betaalt heeft een TEGOED, geen terugbetaling.
        // Een terugbetaling ontstaat pas als de inschrijving is beëindigd
        // (uitgeschreven/afgestudeerd) en er meer is betaald dan pro rata verschuldigd.
        $lopend = $student->inschrijvingen->contains(fn ($i) => in_array($i->status, [
            InschrijvingStatus::Actief,
            InschrijvingStatus::Geschorst,
            InschrijvingStatus::Aangemeld,
        ], true));

        // Een lopende betalingsafspraak heft de blokkades op; de schuld blijft.
        $afspraak = \App\Models\Betalingsafspraak::lopendVoor($student, $peildatum);

        return [
            // Som van de jaarbedragen ná korting, over alle opleidingen.
            'jaarbedrag' => $heeftTarief ? round($jaarbedrag, 2) : null,
            'maandbedrag' => $heeftTarief ? round($jaarbedrag / self::MAANDEN_PER_JAAR, 2) : null,
            'maanden' => $maanden,
            'verschuldigd' => round($verschuldigd, 2),
            'betaald' => round($betaald, 2),
            // Nog te betalen over het hele studiejaar (ook termijnen die nog moeten vervallen).
            'openstaand' => $openstaand,
            // Direct opeisbaar: vervallen termijnen die nog niet zijn voldaan.
            'achterstallig' => $achterstallig,
            'terugbetaling' => $lopend ? 0.0 : $teveel,
            'vooruitbetaald' => $lopend ? $teveel : 0.0,
            'lopend' => $lopend,
            'saldo' => round($betaald - $verschuldigd, 2),
            // Een achterstand bestaat pas bij een onbetaalde VERVALLEN termijn.
            // Dit beschrijft de SCHULD en blijft staan, ook bij een afspraak.
            'achterstand' => $achterstallig > 0,
            // Het GEVOLG van de schuld: geen verklaringen, geen herinschrijving.
            // Een lopende betalingsafspraak schort dat op.
            'geblokkeerd' => $achterstallig > 0 && $afspraak === null,
            'afspraak' => $afspraak,
        ];
    }

    /** Heeft de student een openstaande schuld? (los van een betalingsafspraak) */
    public static function heeftAchterstand(Student $student): bool
    {
        return self::voor($student)['achterstand'];
    }

    /**
     * Zijn verklaringen en herinschrijven geblokkeerd? Dat is niet hetzelfde als
     * een achterstand hebben: met een lopende betalingsafspraak blijft de schuld
     * bestaan, maar vervallen de blokkades.
     */
    public static function isGeblokkeerd(Student $student): bool
    {
        return self::voor($student)['geblokkeerd'];
    }

    /**
     * Het jaarbedrag van één inschrijving NA korting. Dit is wat deze opleiding
     * de student dit studiejaar kost; het brutotarief staat in {@see tarief()}.
     */
    public static function jaarbedrag(Inschrijving $inschrijving): ?float
    {
        $tarief = self::tarief($inschrijving);
        if ($tarief === null) {
            return null;
        }

        $korting = min(100.0, max(0.0, (float) ($inschrijving->korting_percentage ?? 0)));

        return round($tarief * (1 - $korting / 100), 2);
    }

    /** Verschuldigd collegegeld voor één inschrijving (pro rata, ná korting). */
    public static function verschuldigd(Inschrijving $inschrijving, ?Carbon $peildatum = null): float
    {
        $jaarbedrag = self::jaarbedrag($inschrijving);
        if ($jaarbedrag === null) {
            return 0.0;
        }

        return round(($jaarbedrag / self::MAANDEN_PER_JAAR) * self::maanden($inschrijving, $peildatum), 2);
    }

    /** Aantal maanden dat de student (in dit studiejaar) ingeschreven is (geweest). */
    public static function maanden(Inschrijving $inschrijving, ?Carbon $peildatum = null): int
    {
        // Een aangemelde (nog niet ingeschreven) student is nog geen collegegeld
        // verschuldigd.
        if ($inschrijving->status === InschrijvingStatus::Aangemeld) {
            return 0;
        }

        $start = self::studiejaarStart($inschrijving);
        if ($start === null) {
            return 0;
        }
        $peildatum ??= Carbon::now();

        if ($inschrijving->status === InschrijvingStatus::Uitgeschreven && $inschrijving->uitschrijfdatum) {
            $eind = $inschrijving->uitschrijfdatum->copy();
        } elseif ($inschrijving->status === InschrijvingStatus::Afgestudeerd) {
            $eind = self::studiejaarEind($start);
        } else {
            $eind = $peildatum->copy();
        }

        if ($eind->lt($start)) {
            return 0;
        }

        $maanden = ($eind->year - $start->year) * 12 + ($eind->month - $start->month) + 1;

        return (int) max(0, min(self::MAANDEN_PER_JAAR, $maanden));
    }

    /**
     * Het geldende jaartarief voor een inschrijving: een opleiding-specifiek
     * tarief gaat vóór het standaardtarief (opleiding_id null) van hetzelfde jaar.
     */
    public static function tarief(Inschrijving $inschrijving): ?float
    {
        $tarief = CollegegeldTarief::query()
            ->where('periode_id', $inschrijving->periode_id)
            ->where(function ($q) use ($inschrijving) {
                $q->where('opleiding_id', $inschrijving->opleiding_id)->orWhereNull('opleiding_id');
            })
            ->orderByRaw('opleiding_id is null')
            ->first();

        return $tarief ? (float) $tarief->bedrag : null;
    }

    public static function studiejaarStart(Inschrijving $inschrijving): ?Carbon
    {
        $periode = $inschrijving->periode;
        if ($periode?->startdatum) {
            return $periode->startdatum->copy()->startOfDay();
        }
        // Terugval: leid 1 september af uit de periodecode "2025-2026".
        if ($periode && preg_match('/^(\d{4})/', (string) $periode->code, $m)) {
            return Carbon::create((int) $m[1], 9, 1);
        }

        return null;
    }

    private static function studiejaarEind(Carbon $start): Carbon
    {
        // Studiejaar: 1 september t/m 31 juli van het volgende jaar.
        return Carbon::create($start->year + 1, 7, 31)->endOfDay();
    }
}
