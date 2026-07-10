<?php

namespace App\Support;

use App\Enums\Betaalregeling;
use App\Enums\InschrijvingStatus;
use App\Models\Inschrijving;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Het termijnschema (de facturen) van één inschrijving.
 *
 * IUASR factureert elke twee maanden: september, november, januari, maart en
 * mei. Een student kan er ook voor kiezen het volledige jaarbedrag in ÉÉN
 * factuur te voldoen (Betaalregeling::Volledig) — dan is er één termijn die in
 * september vervalt.
 *
 * Er is bewust geen facturentabel: het schema wordt volledig afgeleid uit het
 * jaartarief, de betaalregeling en de inschrijvingsduur. Een betaling verwijst
 * met `betalingen.termijn` naar het termijnnummer; blijft dat leeg, dan wordt
 * de betaling toegerekend aan de oudste nog openstaande termijn.
 *
 * Bedragen: jaarbedrag ÷ aantal termijnen; het afrondingsrestje komt op de
 * laatste termijn, zodat de som exact het jaarbedrag is.
 *
 * Uitschrijving halverwege: termijnen die vervallen ná het einde van de
 * inschrijving worden 'vervallen' (geen factuur). Het totaal wordt herrekend
 * naar het pro rata verschuldigde bedrag; reeds vervallen termijnen behouden
 * hun bedrag en de laatste nog geldende termijn wordt bijgesteld.
 *
 * DUBBELE INSCHRIJVING. Collegegeld is per STUDIEJAAR eenmaal verschuldigd, ook
 * als de student dat jaar twee opleidingen volgt. Er is dus per studiejaar maar
 * één termijnschema: dat van de MAATGEVENDE inschrijving (het hoogste bedrag).
 * De andere inschrijving levert geen termijnen op. Betalingen worden verrekend
 * over het hele studiejaar — ook wanneer de boekhouding ze op de andere
 * inschrijving heeft geboekt. Zou dat niet gebeuren, dan zou een student die
 * alles betaald heeft toch een achterstand houden en geblokkeerd raken.
 */
class Collegegeldtermijnen
{
    /** Vervalmaanden binnen het studiejaar (1 sep – 31 jul). */
    public const VERVALMAANDEN = [9, 11, 1, 3, 5];

    public const BETAALD = 'betaald';
    public const ACHTERSTALLIG = 'achterstallig';
    public const DEELS = 'deels';
    public const OPEN = 'open';
    public const VERVALLEN = 'vervallen';

    /**
     * Het volledige termijnschema van een inschrijving.
     *
     * @return Collection<int, array{nr:int, naam:string, vervaldatum:Carbon, bedrag:float,
     *   betaald:float, open:float, status:string, vervallen:bool}>
     */
    public static function voor(Inschrijving $inschrijving, ?Carbon $peildatum = null): Collection
    {
        $peildatum ??= Carbon::now();
        $jaarbedrag = Collegegeldstatus::tarief($inschrijving);
        $start = Collegegeldstatus::studiejaarStart($inschrijving);

        // Zonder tarief of zonder studiejaar valt er niets te factureren. Een
        // aangemelde student is nog geen collegegeld verschuldigd.
        if ($jaarbedrag === null || $start === null
            || $inschrijving->status === InschrijvingStatus::Aangemeld) {
            return collect();
        }

        // Bij een dubbele inschrijving hangt het schema aan de maatgevende
        // inschrijving; de andere levert geen facturen op.
        if (! self::isMaatgevend($inschrijving, $peildatum)) {
            return collect();
        }

        $termijnen = self::nominaalSchema($inschrijving, $jaarbedrag, $start);
        $termijnen = self::herrekenBijBeeindiging($inschrijving, $termijnen, $peildatum);
        $termijnen = self::rekenBetalingenToe($inschrijving, $termijnen);

        return self::bepaalStatus($termijnen, $peildatum);
    }

    /** Totaal verschuldigd voor deze inschrijving = som van de niet-vervallen termijnen. */
    public static function totaal(Inschrijving $inschrijving, ?Carbon $peildatum = null): float
    {
        return round(self::voor($inschrijving, $peildatum)
            ->reject(fn ($t) => $t['vervallen'])->sum('bedrag'), 2);
    }

    /** Achterstallig = som van het openstaande deel van de VERVALLEN termijnen (vervaldatum verstreken). */
    public static function achterstallig(Inschrijving $inschrijving, ?Carbon $peildatum = null): float
    {
        return round(self::voor($inschrijving, $peildatum)
            ->where('status', self::ACHTERSTALLIG)->sum('open'), 2);
    }

    public static function regeling(Inschrijving $inschrijving): Betaalregeling
    {
        return $inschrijving->betaalregeling ?? Betaalregeling::Termijnen;
    }

    /*
    |----------------------------------------------------------------------
    | Studiejaar: één schema, ook bij een dubbele inschrijving
    |----------------------------------------------------------------------
    */

    /**
     * Het nominaal verschuldigde bedrag van deze inschrijving, VÓÓR betalingen.
     * Bepaalt welke inschrijving maatgevend is; mag daarom nooit het termijnschema
     * aanroepen (dat zou een kringverwijzing opleveren).
     */
    public static function verschuldigdTotaal(Inschrijving $inschrijving, ?Carbon $peildatum = null): float
    {
        $jaarbedrag = Collegegeldstatus::tarief($inschrijving);
        if ($jaarbedrag === null || $inschrijving->status === InschrijvingStatus::Aangemeld) {
            return 0.0;
        }

        // Beëindigd: pro rata. Lopend: het volledige jaarbedrag.
        return self::eindeInschrijving($inschrijving) !== null
            ? Collegegeldstatus::verschuldigd($inschrijving, $peildatum)
            : round($jaarbedrag, 2);
    }

    /** Alle inschrijvingen van deze student in hetzelfde studiejaar (incl. zichzelf). */
    public static function inschrijvingenVanStudiejaar(Inschrijving $inschrijving): Collection
    {
        return Inschrijving::where('student_id', $inschrijving->student_id)
            ->where('periode_id', $inschrijving->periode_id)
            ->orderBy('id')->get();
    }

    /**
     * De inschrijving die het collegegeld van dit studiejaar bepaalt: die met het
     * hoogste verschuldigde bedrag. Bij gelijke bedragen wint de laagste id, zodat
     * de uitkomst deterministisch is.
     */
    public static function maatgevende(Inschrijving $inschrijving, ?Carbon $peildatum = null): Inschrijving
    {
        $broers = self::inschrijvingenVanStudiejaar($inschrijving);
        if ($broers->count() <= 1) {
            return $inschrijving;
        }

        return $broers
            ->sortBy([
                fn ($a, $b) => self::verschuldigdTotaal($b, $peildatum) <=> self::verschuldigdTotaal($a, $peildatum),
                fn ($a, $b) => $a->id <=> $b->id,
            ])
            ->first();
    }

    public static function isMaatgevend(Inschrijving $inschrijving, ?Carbon $peildatum = null): bool
    {
        return self::maatgevende($inschrijving, $peildatum)->id === $inschrijving->id;
    }

    /**
     * De opleiding waarbij het collegegeld van een niet-maatgevende inschrijving
     * wordt verrekend — voor de uitleg op het scherm.
     */
    public static function verrekendBij(Inschrijving $inschrijving, ?Carbon $peildatum = null): ?Inschrijving
    {
        $maatgevend = self::maatgevende($inschrijving, $peildatum);

        return $maatgevend->id === $inschrijving->id ? null : $maatgevend->load('opleiding');
    }

    /** Nominale termijnen: gelijke bedragen, afrondingsrestje op de laatste. */
    private static function nominaalSchema(Inschrijving $inschrijving, float $jaarbedrag, Carbon $start): Collection
    {
        $aantal = self::regeling($inschrijving)->aantalTermijnen();
        $maanden = array_slice(self::VERVALMAANDEN, 0, $aantal);

        $per = round($jaarbedrag / $aantal, 2);
        $termijnen = collect();

        foreach ($maanden as $index => $maand) {
            $laatste = $index === $aantal - 1;
            // De laatste termijn vangt het afrondingsverschil op.
            $bedrag = $laatste ? round($jaarbedrag - ($per * ($aantal - 1)), 2) : $per;
            $vervaldatum = self::vervaldatum($start, $maand);

            $termijnen->push([
                'nr' => $index + 1,
                'naam' => $aantal === 1
                    ? 'Volledig jaarbedrag'
                    : ucfirst($vervaldatum->locale('nl')->translatedFormat('F Y')),
                'vervaldatum' => $vervaldatum,
                'bedrag' => $bedrag,
                'vervallen' => false,
            ]);
        }

        return $termijnen;
    }

    /**
     * Bij een beëindigde inschrijving: termijnen ná het einde vervallen, en het
     * totaal wordt herrekend naar het pro rata verschuldigde bedrag. Het restant
     * wordt van voor naar achter over de nog geldende termijnen verdeeld, zodat
     * reeds gefactureerde termijnen hun bedrag houden en alleen de laatste
     * geldende termijn wordt bijgesteld.
     */
    private static function herrekenBijBeeindiging(Inschrijving $inschrijving, Collection $termijnen, Carbon $peildatum): Collection
    {
        $einde = self::eindeInschrijving($inschrijving);
        if ($einde === null) {
            return $termijnen; // lopend: het volledige jaarbedrag is verschuldigd
        }

        $totaal = Collegegeldstatus::verschuldigd($inschrijving, $peildatum);

        $geldig = $termijnen->filter(fn ($t) => $t['vervaldatum']->lte($einde))->values();
        $laatsteNr = $geldig->last()['nr'] ?? null;

        $rest = $totaal;

        return $termijnen->map(function (array $t) use ($einde, &$rest, $laatsteNr) {
            if ($t['vervaldatum']->gt($einde)) {
                return [...$t, 'bedrag' => 0.0, 'vervallen' => true];
            }

            // De laatste geldende termijn krijgt exact wat er nog resteert.
            $bedrag = $t['nr'] === $laatsteNr ? $rest : min($t['bedrag'], $rest);
            $bedrag = round(max(0.0, $bedrag), 2);
            $rest = round($rest - $bedrag, 2);

            return [...$t, 'bedrag' => $bedrag];
        });
    }

    /**
     * Betalingen toerekenen. Een betaling met een expliciet termijnnummer gaat
     * naar die termijn; betalingen zonder termijn worden op datumvolgorde
     * toegerekend aan de oudste termijn die nog openstaat.
     *
     * Bij een dubbele inschrijving tellen ALLE betalingen van dat studiejaar mee,
     * ook die op de andere inschrijving zijn geboekt: er is maar één factuurreeks.
     */
    private static function rekenBetalingenToe(Inschrijving $inschrijving, Collection $termijnen): Collection
    {
        $betalingen = self::betalingenVanStudiejaar($inschrijving);
        $betaaldPerNr = [];

        foreach ($betalingen->whereNotNull('termijn') as $betaling) {
            $nr = (int) $betaling->termijn;
            $betaaldPerNr[$nr] = ($betaaldPerNr[$nr] ?? 0.0) + (float) $betaling->bedrag;
        }

        $termijnen = $termijnen->map(fn (array $t) => [...$t, 'betaald' => round($betaaldPerNr[$t['nr']] ?? 0.0, 2)]);

        // Ongekoppelde betalingen: FIFO over de nog openstaande, niet-vervallen termijnen.
        $vrij = round($betalingen->whereNull('termijn')->sum('bedrag'), 2);
        if ($vrij <= 0) {
            return $termijnen;
        }

        return $termijnen->map(function (array $t) use (&$vrij) {
            if ($vrij <= 0 || $t['vervallen']) {
                return $t;
            }
            $open = round($t['bedrag'] - $t['betaald'], 2);
            if ($open <= 0) {
                return $t;
            }
            $toe = min($open, $vrij);
            $vrij = round($vrij - $toe, 2);

            return [...$t, 'betaald' => round($t['betaald'] + $toe, 2)];
        });
    }

    /** Alle betalingen van deze student in dit studiejaar, over alle inschrijvingen. */
    private static function betalingenVanStudiejaar(Inschrijving $inschrijving): Collection
    {
        $ids = self::inschrijvingenVanStudiejaar($inschrijving)->pluck('id');

        // Eén inschrijving: gebruik de al geladen relatie (scheelt een query).
        if ($ids->count() <= 1) {
            return $inschrijving->betalingen ?? collect();
        }

        return \App\Models\Betaling::whereIn('inschrijving_id', $ids)->orderBy('datum')->orderBy('id')->get();
    }

    /** Status en openstaand bedrag per termijn. */
    private static function bepaalStatus(Collection $termijnen, Carbon $peildatum): Collection
    {
        return $termijnen->map(function (array $t) use ($peildatum) {
            $open = round(max(0.0, $t['bedrag'] - $t['betaald']), 2);

            $status = match (true) {
                $t['vervallen'] => self::VERVALLEN,
                $open <= 0.001 => self::BETAALD,
                $t['vervaldatum']->lte($peildatum) => self::ACHTERSTALLIG,
                $t['betaald'] > 0 => self::DEELS,
                default => self::OPEN,
            };

            return [...$t, 'open' => $open, 'status' => $status];
        });
    }

    /** Einde van de inschrijving, of null als zij nog loopt. */
    private static function eindeInschrijving(Inschrijving $inschrijving): ?Carbon
    {
        if ($inschrijving->status === InschrijvingStatus::Uitgeschreven && $inschrijving->uitschrijfdatum) {
            return $inschrijving->uitschrijfdatum->copy()->endOfDay();
        }

        if ($inschrijving->status === InschrijvingStatus::Afgestudeerd) {
            $start = Collegegeldstatus::studiejaarStart($inschrijving);

            return $start ? Carbon::create($start->year + 1, 7, 31)->endOfDay() : null;
        }

        return null;
    }

    /** September/november vallen in het startjaar; januari/maart/mei in het jaar erna. */
    private static function vervaldatum(Carbon $start, int $maand): Carbon
    {
        $jaar = $maand >= 9 ? $start->year : $start->year + 1;

        return Carbon::create($jaar, $maand, 1)->startOfDay();
    }

    /** Leesbare status voor de UI. */
    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::BETAALD => 'Betaald',
            self::ACHTERSTALLIG => 'Achterstallig',
            self::DEELS => 'Deels betaald',
            self::OPEN => 'Nog niet vervallen',
            self::VERVALLEN => 'Vervallen',
            default => $status,
        };
    }

    /** Badge-klasse uit het design system. */
    public static function statusBadge(string $status): string
    {
        return match ($status) {
            self::BETAALD => 's-approved',
            self::ACHTERSTALLIG => 's-rejected',
            self::DEELS => 's-incomplete',
            self::OPEN => 's-draft',
            self::VERVALLEN => 's-draft',
            default => 's-draft',
        };
    }
}
