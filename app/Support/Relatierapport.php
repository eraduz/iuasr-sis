<?php

namespace App\Support;

use App\Models\Afspraak;
use App\Models\Contactpersoon;
use App\Models\Organisatie;
use App\Models\Overeenkomst;
use App\Models\RelatieDocument;
use App\Models\Relatietaak;
use App\Models\Stage;
use App\Models\Stageplaats;
use Illuminate\Support\Collection;

/**
 * Aggregaties voor het dashboard en de rapportages van de module Relatiebeheer &
 * Stagebeheer. Alle methodes accepteren een optionele lijst opleiding-ids
 * (`$oplIds`); null = geen beperking (Bestuur/Beheer), een lijst beperkt tot die
 * opleiding(en) — zo blijft de opleidinggebonden scoping consistent.
 */
class Relatierapport
{
    /** Organisatie-ids binnen de scope (voor tellingen op gekoppelde tabellen). */
    private static function organisatieIds(?array $oplIds): Collection
    {
        return Organisatie::query()
            ->when($oplIds !== null, fn ($q) => $q->whereHas('opleidingen', fn ($o) => $o->whereIn('opleidingen.id', $oplIds)))
            ->pluck('id');
    }

    /** Kerncijfers voor de dashboardtegels. */
    public static function kerncijfers(?array $oplIds = null): array
    {
        $orgIds = self::organisatieIds($oplIds);

        $stagesQ = fn () => Stage::query()->when($oplIds !== null, fn ($q) => $q->whereIn('opleiding_id', $oplIds));
        $stageplaatsQ = fn () => Stageplaats::query()->when($oplIds !== null, fn ($q) => $q->whereIn('opleiding_id', $oplIds));

        return [
            'organisaties' => Organisatie::whereIn('id', $orgIds)->where('actief', true)->count(),
            'organisaties_nieuw' => Organisatie::whereIn('id', $orgIds)->where('created_at', '>=', now()->subDays(30))->count(),
            'contactpersonen' => Contactpersoon::whereIn('organisatie_id', $orgIds)->where('actief', true)->count(),
            'stageplaatsen' => $stageplaatsQ()->where('actief', true)->count(),
            'stages_lopend' => $stagesQ()->where('status', 'lopend')->count(),
            'stages_te_beoordelen' => self::teBeoordelen($oplIds)->count(),
            'taken_open' => Relatietaak::whereIn('organisatie_id', $orgIds)->where('status', '!=', 'afgerond')->count(),
            'afspraken_komend' => Afspraak::whereIn('organisatie_id', $orgIds)->where('status', 'gepland')
                ->whereDate('datum', '>=', now()->toDateString())->whereDate('datum', '<=', now()->addDays(7)->toDateString())->count(),
            'contracten_verlopen' => Overeenkomst::whereIn('organisatie_id', $orgIds)->whereNotNull('verloopdatum')
                ->where('status', '!=', 'opgezegd')->whereDate('verloopdatum', '<=', now()->addDays(60)->toDateString())->count(),
            'documenten_nieuw' => RelatieDocument::whereIn('organisatie_id', $orgIds)->where('created_at', '>=', now()->subDays(30))->count(),
            'bezettingsgraad' => self::bezettingsgraad($oplIds),
        ];
    }

    /** Bezettingsgraad: bezette plaatsen ÷ totale capaciteit (%), of null zonder capaciteit. */
    public static function bezettingsgraad(?array $oplIds = null): ?int
    {
        $capaciteit = (int) Stageplaats::query()
            ->when($oplIds !== null, fn ($q) => $q->whereIn('opleiding_id', $oplIds))
            ->whereNotNull('max_studenten')->sum('max_studenten');

        if ($capaciteit === 0) {
            return null;
        }

        $bezet = Stage::query()
            ->when($oplIds !== null, fn ($q) => $q->whereIn('opleiding_id', $oplIds))
            ->whereNotNull('stageplaats_id')
            ->whereIn('status', ['aangevraagd', 'lopend'])->count();

        return (int) round($bezet / $capaciteit * 100);
    }

    /** Stages die op een beoordeling wachten: geen beoordeling en (afgerond of einddatum verstreken). */
    public static function teBeoordelen(?array $oplIds = null)
    {
        return Stage::query()
            ->when($oplIds !== null, fn ($q) => $q->whereIn('opleiding_id', $oplIds))
            ->whereNull('beoordeling')
            ->where(function ($q) {
                $q->where('status', 'afgerond')
                    ->orWhere(fn ($s) => $s->where('status', 'lopend')->whereNotNull('einddatum')->whereDate('einddatum', '<', now()->toDateString()));
            })
            ->with(['student', 'organisatie', 'opleiding']);
    }

    /** Gemiddelde evaluatie: percentage voldoende van de beoordeelde stages. */
    public static function evaluatie(?array $oplIds = null): array
    {
        $q = Stage::query()->when($oplIds !== null, fn ($x) => $x->whereIn('opleiding_id', $oplIds))->whereNotNull('beoordeling');
        $beoordeeld = (clone $q)->count();
        $voldoende = (clone $q)->where('beoordeling', 'voldoende')->count();

        return [
            'beoordeeld' => $beoordeeld,
            'voldoende' => $voldoende,
            'percentage' => $beoordeeld > 0 ? (int) round($voldoende / $beoordeeld * 100) : null,
        ];
    }

    /** Stages per status (voor de donut). */
    public static function stagesPerStatus(?array $oplIds = null): array
    {
        $kleuren = ['aangevraagd' => 'var(--secColor100)', 'lopend' => 'var(--priColor200)', 'afgerond' => '#285C4D', 'afgebroken' => '#999'];
        $tellingen = Stage::query()->when($oplIds !== null, fn ($q) => $q->whereIn('opleiding_id', $oplIds))
            ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        $segments = [];
        foreach (\App\Enums\Stagestatus::cases() as $status) {
            $segments[] = ['label' => $status->label(), 'value' => (int) ($tellingen[$status->value] ?? 0), 'kleur' => $kleuren[$status->value]];
        }

        return $segments;
    }

    /** Organisaties per type (voor de staafgrafiek). */
    public static function organisatiesPerType(?array $oplIds = null): array
    {
        return Organisatie::query()
            ->when($oplIds !== null, fn ($q) => $q->whereHas('opleidingen', fn ($o) => $o->whereIn('opleidingen.id', $oplIds)))
            ->with('type')->get()
            ->groupBy(fn ($o) => $o->type?->naam ?? 'Onbekend')
            ->map(fn ($groep, $label) => ['label' => $label, 'value' => $groep->count()])
            ->sortByDesc('value')->values()->all();
    }

    /**
     * Rijen per organisatie voor het rapport en de CSV-export.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function rijen(?array $oplIds = null): Collection
    {
        return Organisatie::query()
            ->when($oplIds !== null, fn ($q) => $q->whereHas('opleidingen', fn ($o) => $o->whereIn('opleidingen.id', $oplIds)))
            ->with(['type', 'opleidingen'])
            ->withCount([
                'contactpersonen as contactpersonen_actief' => fn ($q) => $q->where('actief', true),
                'stageplaatsen as stageplaatsen_actief' => fn ($q) => $q->where('actief', true),
                'stages as stages_lopend' => fn ($q) => $q->where('status', 'lopend'),
                'relatietaken as taken_open' => fn ($q) => $q->where('status', '!=', 'afgerond'),
            ])
            ->orderBy('naam')->get()
            ->map(fn (Organisatie $o) => [
                'relatienummer' => $o->relatienummer,
                'naam' => $o->naam,
                'type' => $o->type?->naam ?? '',
                'opleidingen' => $o->opleidingen->pluck('code')->implode(' '),
                'plaats' => $o->plaats ?? '',
                'contactpersonen' => $o->contactpersonen_actief,
                'stageplaatsen' => $o->stageplaatsen_actief,
                'lopende_stages' => $o->stages_lopend,
                'open_taken' => $o->taken_open,
                'actief' => $o->actief ? 'ja' : 'nee',
            ]);
    }
}
