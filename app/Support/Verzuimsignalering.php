<?php

namespace App\Support;

use App\Models\Ziekmelding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Verzuimsignalering (module HR / Personeelszaken — Fase G). Twee afgeleide
 * signalen, intranet-veilig en zonder eigen tabel:
 *
 *  1. LANGDURIG verzuim volgens de Wet Verbetering Poortwachter: per open
 *     ziekmelding worden de wettelijke re-integratiemijlpalen (probleemanalyse,
 *     plan van aanpak, UWV-ziekmelding, eerstejaarsevaluatie, WIA-aanvraag)
 *     afgeleid uit de eerste ziektedag, met per mijlpaal een status
 *     (verstreken / binnenkort / gepland).
 *  2. FREQUENT verzuim: medewerkers met meerdere losse ziekmeldingen binnen een
 *     periode — een signaal voor een verzuimgesprek.
 *
 * Alle methodes accepteren een optionele lijst medewerker-ids (`$ids`); null =
 * geen beperking (HR/Beheer/Bestuur), een lijst beperkt tot die medewerkers.
 */
class Verzuimsignalering
{
    /**
     * Langdurig verzuim: per open ziekmelding het Poortwachter-traject met
     * mijlpaaldata en -status, gesorteerd op verzuimduur (langst eerst).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function langdurig(?array $ids = null): Collection
    {
        $config = (array) config('sis.hr.poortwachter');
        $venster = (int) ($config['venster_dagen'] ?? 14);
        $mijlpaalDefs = (array) ($config['mijlpalen'] ?? []);
        $vandaag = Carbon::today();

        return Ziekmelding::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('medewerker_id', $ids))
            ->whereNull('hersteld_op')
            ->with('medewerker')
            ->get()
            ->map(function (Ziekmelding $z) use ($mijlpaalDefs, $venster, $vandaag) {
                $mijlpalen = collect($mijlpaalDefs)->map(function (array $m) use ($z, $venster, $vandaag) {
                    $datum = $z->ziek_van->copy()->addWeeks((int) $m['week']);

                    $status = $datum->lt($vandaag)
                        ? 'verstreken'
                        : ($datum->lte($vandaag->copy()->addDays($venster)) ? 'binnenkort' : 'gepland');

                    return [
                        'sleutel' => $m['sleutel'],
                        'label' => $m['label'],
                        'week' => (int) $m['week'],
                        'datum' => $datum,
                        'status' => $status,
                    ];
                });

                return [
                    'melding' => $z,
                    'medewerker' => $z->medewerker,
                    'dagen' => $z->dagen(),
                    'weken' => intdiv($z->dagen(), 7),
                    'mijlpalen' => $mijlpalen,
                    'eerstvolgende' => $mijlpalen->firstWhere('status', '!=', 'verstreken'),
                    'actie' => $mijlpalen->contains(fn (array $m) => $m['status'] === 'binnenkort'),
                ];
            })
            ->sortByDesc('dagen')->values();
    }

    /**
     * Frequent verzuim: medewerkers met ten minste de ingestelde drempel aan
     * afzonderlijke ziekmeldingen binnen de ingestelde periode (ongeacht of ze
     * inmiddels hersteld zijn), gesorteerd op aantal meldingen (meeste eerst).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function frequent(?array $ids = null): Collection
    {
        $config = (array) config('sis.hr.poortwachter.frequent');
        $maanden = (int) ($config['maanden'] ?? 12);
        $drempel = (int) ($config['drempel'] ?? 3);
        $grens = Carbon::today()->subMonths($maanden);

        return Ziekmelding::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('medewerker_id', $ids))
            ->whereDate('ziek_van', '>=', $grens->toDateString())
            ->with('medewerker')
            ->get()
            ->groupBy('medewerker_id')
            ->filter(fn (Collection $groep) => $groep->count() >= $drempel)
            ->map(fn (Collection $groep) => [
                'medewerker' => $groep->first()->medewerker,
                'aantal' => $groep->count(),
                'laatste' => $groep->max('ziek_van'),
                'maanden' => $maanden,
            ])
            ->sortByDesc('aantal')->values();
    }
}
