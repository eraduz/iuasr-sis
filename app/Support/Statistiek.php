<?php

namespace App\Support;

use App\Enums\Rol;
use App\Models\Cijferlijst;
use App\Models\Inschrijving;
use App\Models\Periode;
use App\Models\Resultaat;
use App\Models\Student;
use App\Models\User;
use App\Models\Vak;
use App\Models\Vaktoewijzing;

/**
 * Berekent geaggregeerde statistieken uit de database voor de rolgerichte
 * dashboards. Alles op basis van synthetische data (AVG). Semantische kleuren
 * volgen de merk-tokens; per-categorie reeksen gebruiken een vaste palet.
 */
class Statistiek
{
    /** Merkkleuren voor grafiekreeksen. */
    public const PALET = ['#1E1446', '#C8102E', '#285C4D', '#D69A2D', '#5B7FBF', '#8E6BB0', '#2F8F8F', '#B5651D'];

    public const GROEN = '#285C4D';
    public const ROOD = '#C8102E';
    public const GOUD = '#D69A2D';
    public const BLAUW = '#1E1446';
    public const GRIJS = '#9A98A8';

    /** Basiskerncijfers (tellingen). */
    public static function kern(): array
    {
        $statussen = Inschrijving::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'studenten' => Student::count(),
            'actief' => (int) ($statussen['actief'] ?? 0),
            'afgestudeerd' => (int) ($statussen['afgestudeerd'] ?? 0),
            'uitgeschreven' => (int) ($statussen['uitgeschreven'] ?? 0),
            'geschorst' => (int) ($statussen['geschorst'] ?? 0),
            'vakken' => Vak::where('actief', true)->count(),
            'gebruikers' => User::count(),
        ];
    }

    /**
     * Normaliseert een opleidingfilter naar een array van ids of null (= alles).
     * Directie geeft de eigen opleiding-ids mee; overige rollen null.
     */
    private static function ids($opleidingIds): ?array
    {
        if ($opleidingIds === null) {
            return null;
        }

        return collect($opleidingIds)->map(fn ($v) => (int) $v)->values()->all();
    }

    /** Actieve studenten per opleiding (code). @return list<array{label:string,value:int}> */
    public static function perOpleiding($opleidingIds = null): array
    {
        $ids = self::ids($opleidingIds);

        return Inschrijving::where('inschrijvingen.status', 'actief')
            ->join('opleidingen', 'opleidingen.id', '=', 'inschrijvingen.opleiding_id')
            ->when($ids !== null, fn ($q) => $q->whereIn('inschrijvingen.opleiding_id', $ids))
            ->selectRaw('opleidingen.code as label, count(*) as value')
            ->groupBy('opleidingen.code')->orderByDesc('value')
            ->get()->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();
    }

    /** Actieve studenten per leerjaar. @return list<array{label:string,value:int}> */
    public static function perLeerjaar(): array
    {
        return Inschrijving::where('status', 'actief')
            ->selectRaw('leerjaar, count(*) as value')->groupBy('leerjaar')->orderBy('leerjaar')
            ->get()->map(fn ($r) => ['label' => 'Jaar '.$r->leerjaar, 'value' => (int) $r->value])->all();
    }

    /** Instroom (nieuwe inschrijvingen) per studiejaar. @return list<array{label:string,value:int}> */
    public static function instroomPerStudiejaar($opleidingIds = null): array
    {
        $ids = self::ids($opleidingIds);

        return Inschrijving::join('perioden', 'perioden.id', '=', 'inschrijvingen.periode_id')
            ->when($ids !== null, fn ($q) => $q->whereIn('inschrijvingen.opleiding_id', $ids))
            ->selectRaw('perioden.naam as label, count(*) as value')
            ->groupBy('perioden.naam')->orderBy('perioden.naam')
            ->get()->map(fn ($r) => ['label' => $r->label, 'value' => (int) $r->value])->all();
    }

    /** Verdeling inschrijvingsstatussen met semantische kleuren. */
    public static function statusVerdeling($opleidingIds = null): array
    {
        $ids = self::ids($opleidingIds);
        $kleur = [
            'actief' => self::GROEN, 'afgestudeerd' => self::BLAUW,
            'uitgeschreven' => self::ROOD, 'geschorst' => self::GOUD,
            'aangemeld' => self::GRIJS,
        ];
        $labels = [
            'actief' => 'Actief', 'afgestudeerd' => 'Afgestudeerd',
            'uitgeschreven' => 'Uitgeschreven', 'geschorst' => 'Geschorst', 'aangemeld' => 'Aangemeld',
        ];

        return Inschrijving::when($ids !== null, fn ($q) => $q->whereIn('opleiding_id', $ids))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')
            ->map(fn ($n, $s) => ['label' => $labels[$s] ?? $s, 'value' => (int) $n, 'kleur' => $kleur[$s] ?? self::GRIJS])
            ->values()->all();
    }

    /** Overgangsadvies-verdeling over actieve inschrijvingen (BSA/doorstroom). */
    public static function overgangVerdeling($opleidingIds = null): array
    {
        $ids = self::ids($opleidingIds);
        $telling = ['positief' => 0, 'voorwaardelijk' => 0, 'negatief' => 0, 'onbekend' => 0];
        $q = Inschrijving::where('status', 'actief')
            ->when($ids !== null, fn ($q) => $q->whereIn('opleiding_id', $ids))
            ->with('opleiding');
        foreach ($q->get() as $insch) {
            $telling[Overgangsbeoordeling::voor($insch)['status']]++;
        }

        return [
            ['label' => 'Positief', 'value' => $telling['positief'], 'kleur' => self::GROEN],
            ['label' => 'Voorwaardelijk', 'value' => $telling['voorwaardelijk'], 'kleur' => self::GOUD],
            ['label' => 'Negatief', 'value' => $telling['negatief'], 'kleur' => self::ROOD],
            ['label' => 'Geen drempel', 'value' => $telling['onbekend'], 'kleur' => self::GRIJS],
        ];
    }

    /** Toets-slaagpercentage (o.b.v. beoordeelde resultaten) en aantallen. */
    public static function slaagpercentage($opleidingIds = null): array
    {
        $ids = self::ids($opleidingIds);
        $scope = fn ($q) => $q->when($ids !== null,
            fn ($q) => $q->whereHas('inschrijving', fn ($i) => $i->whereIn('opleiding_id', $ids)));

        $totaal = Resultaat::whereNotNull('cijfer')->tap($scope)->count();
        $voldoende = Resultaat::whereNotNull('cijfer')->where('voldoende', true)->tap($scope)->count();
        $vrijstelling = Resultaat::where('vrijstelling', true)->tap($scope)->count();

        return [
            'totaal' => $totaal,
            'voldoende' => $voldoende,
            'onvoldoende' => $totaal - $voldoende,
            'percentage' => $totaal > 0 ? (int) round($voldoende / $totaal * 100) : 0,
            'vrijstelling' => $vrijstelling,
        ];
    }

    /** Cijferverdeling in banden (histogram). @return list<array{label:string,value:int}> */
    public static function cijferverdeling(): array
    {
        $cijfers = Resultaat::whereNotNull('cijfer')->pluck('cijfer');
        $banden = ['1–4' => 0, '4–5,5' => 0, '5,5–6,5' => 0, '6,5–7,5' => 0, '7,5–8,5' => 0, '8,5–10' => 0];
        foreach ($cijfers as $c) {
            $c = (float) $c;
            $band = match (true) {
                $c < 4 => '1–4',
                $c < 5.5 => '4–5,5',
                $c < 6.5 => '5,5–6,5',
                $c < 7.5 => '6,5–7,5',
                $c < 8.5 => '7,5–8,5',
                default => '8,5–10',
            };
            $banden[$band]++;
        }

        return collect($banden)->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values()->all();
    }

    /** Cijferlijst-vaststellingsstatus in de actieve periode. */
    public static function cijferlijstStatus(): array
    {
        $periodeId = Periode::where('actief', true)->value('id');
        $vakken = Vak::where('actief', true)->count();
        $ingediend = Cijferlijst::where('periode_id', $periodeId)->where('status', 'ingediend')->count();
        $vastgesteld = Cijferlijst::where('periode_id', $periodeId)->where('status', 'vastgesteld')->count();

        return [
            ['label' => 'Vastgesteld', 'value' => $vastgesteld, 'kleur' => self::GROEN],
            ['label' => 'Ter vaststelling', 'value' => $ingediend, 'kleur' => self::GOUD],
            ['label' => 'Concept/open', 'value' => max(0, $vakken - $ingediend - $vastgesteld), 'kleur' => self::GRIJS],
        ];
    }

    public static function herkansingen(): int
    {
        return Resultaat::where('poging', 'herkansing')->count();
    }

    public static function vrijstellingen(): int
    {
        return Vaktoewijzing::where('vrijgesteld', true)->count();
    }

    /**
     * Studenten met minstens één vrijstelling, met de vrijgestelde vakken.
     * @return \Illuminate\Support\Collection<int, array{student: Student, vakken: \Illuminate\Support\Collection}>
     */
    public static function vrijstellingStudenten(): \Illuminate\Support\Collection
    {
        return Vaktoewijzing::where('vrijgesteld', true)
            ->with(['vak', 'inschrijving.student'])
            ->get()
            ->filter(fn ($t) => $t->inschrijving?->student && $t->vak)
            ->groupBy(fn ($t) => $t->inschrijving->student_id)
            ->map(fn ($groep) => [
                'student' => $groep->first()->inschrijving->student,
                'vakken' => $groep->map(fn ($t) => $t->vak)->unique('id')->sortBy('code')->values(),
            ])
            ->sortBy(fn ($r) => $r['student']->achternaam)
            ->values();
    }

    /** Financieel totaaloverzicht over actieve studenten (synthetisch). */
    public static function financieel($opleidingIds = null): array
    {
        $ids = self::ids($opleidingIds);
        $verschuldigd = 0.0;
        $betaald = 0.0;
        $openstaand = 0.0;
        $achterstand = 0;
        $perOpleiding = [];

        $studenten = Student::whereHas('inschrijvingen', fn ($q) => $q->where('status', 'actief')
            ->when($ids !== null, fn ($q) => $q->whereIn('opleiding_id', $ids)))
            ->with('inschrijvingen.opleiding')->get();

        foreach ($studenten as $student) {
            $s = Collegegeldstatus::voor($student);
            $verschuldigd += $s['verschuldigd'];
            $betaald += $s['betaald'];
            $openstaand += $s['openstaand'];
            if ($s['achterstand']) {
                $achterstand++;
            }
            $code = $student->inschrijvingen->firstWhere('status', 'actief')?->opleiding?->code ?? '—';
            $perOpleiding[$code] = ($perOpleiding[$code] ?? 0) + $s['openstaand'];
        }

        arsort($perOpleiding);

        return [
            'verschuldigd' => round($verschuldigd, 2),
            'betaald' => round($betaald, 2),
            'openstaand' => round($openstaand, 2),
            'achterstand_aantal' => $achterstand,
            'betaalgraad' => $verschuldigd > 0 ? (int) round($betaald / $verschuldigd * 100) : 0,
            'openstaand_per_opleiding' => collect($perOpleiding)
                ->map(fn ($v, $k) => ['label' => $k, 'value' => (int) round($v)])->values()->all(),
        ];
    }

    /** NT2-bewaking: open / verstreken / behaald. */
    public static function nt2Verdeling(): array
    {
        $vereist = Student::where('nt2_examen_vereist', true)->get();
        $behaald = $vereist->whereNotNull('nt2_behaald_op')->count();
        $verlopen = $vereist->filter(fn ($s) => $s->nt2Status() === 'verlopen')->count();
        $open = $vereist->count() - $behaald - $verlopen;

        return [
            ['label' => 'Behaald', 'value' => $behaald, 'kleur' => self::GROEN],
            ['label' => 'Openstaand', 'value' => max(0, $open), 'kleur' => self::GOUD],
            ['label' => 'Verstreken', 'value' => $verlopen, 'kleur' => self::ROOD],
        ];
    }

    /** Gebruikers per rol (Beheer). @return list<array{label:string,value:int}> */
    public static function gebruikersPerRol(): array
    {
        return User::selectRaw('rol, count(*) as value')->groupBy('rol')
            ->get()
            ->map(fn ($r) => [
                'label' => ($r->rol instanceof Rol ? $r->rol : Rol::from($r->rol))->label(),
                'value' => (int) $r->value,
            ])->all();
    }
}
