<?php

namespace App\Support;

use App\Enums\MedewerkerStatus;
use App\Models\Medewerker;
use App\Models\Ziekmelding;
use Illuminate\Support\Collection;

/**
 * HR-rapportage-aggregaties (module HR / Personeelszaken): aantallen per afdeling,
 * FTE-overzicht en (actueel) verzuimpercentage. Alle methodes accepteren een
 * optionele lijst medewerker-ids (`$ids`); null = geen beperking (HR/Beheer/
 * Bestuur), een lijst beperkt tot het team (Manager).
 */
class HrRapport
{
    private static function medewerkers(?array $ids): Collection
    {
        return Medewerker::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->with(['dienstverbanden', 'afdeling'])
            ->get();
    }

    /** Kerncijfers voor de rapportage-tegels. */
    public static function kerncijfers(?array $ids = null): array
    {
        $medewerkers = self::medewerkers($ids);
        $actief = $medewerkers->where('actief', true);
        $fte = round($actief->sum(fn (Medewerker $m) => $m->fte() ?? 0), 2);
        $ziek = $medewerkers->where('status', MedewerkerStatus::Ziek)->count();
        $totaal = $medewerkers->count();

        return [
            'medewerkers' => $totaal,
            'actief' => $actief->count(),
            'fte' => $fte,
            'gem_fte' => $actief->count() > 0 ? round($fte / $actief->count(), 2) : 0.0,
            'ziek' => $ziek,
            'verzuim' => $totaal > 0 ? round($ziek / $totaal * 100, 1) : 0.0,
            'verzuim_dagen' => self::verzuimDagen($medewerkers->pluck('id')->all()),
        ];
    }

    /** Som van de ziektedagen in het huidige jaar (binnen de scope). */
    public static function verzuimDagen(array $ids): int
    {
        $jaar = (int) date('Y');

        return (int) Ziekmelding::query()
            ->whereIn('medewerker_id', $ids ?: [0])
            ->whereYear('ziek_van', $jaar)
            ->get()->sum(fn (Ziekmelding $z) => $z->dagen());
    }

    /**
     * Per afdeling: aantal medewerkers, FTE, aantal ziek en het (actuele)
     * verzuimpercentage.
     *
     * @return array<int, array{afdeling: string, aantal: int, fte: float, ziek: int, verzuim: float}>
     */
    public static function perAfdeling(?array $ids = null): array
    {
        $medewerkers = self::medewerkers($ids)->where('actief', true);

        return $medewerkers
            ->groupBy(fn (Medewerker $m) => $m->afdeling?->naam ?? 'Zonder afdeling')
            ->map(function (Collection $groep, string $naam) {
                $aantal = $groep->count();
                $ziek = $groep->where('status', MedewerkerStatus::Ziek)->count();

                return [
                    'afdeling' => $naam,
                    'aantal' => $aantal,
                    'fte' => round($groep->sum(fn (Medewerker $m) => $m->fte() ?? 0), 2),
                    'ziek' => $ziek,
                    'verzuim' => $aantal > 0 ? round($ziek / $aantal * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('aantal')->values()->all();
    }

    /**
     * Rijen per medewerker voor de CSV-export.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function rijen(?array $ids = null): Collection
    {
        return Medewerker::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->with(['afdeling', 'functie', 'dienstverbanden', 'manager'])
            ->orderBy('achternaam')->get()
            ->map(fn (Medewerker $m) => [
                'personeelsnummer' => $m->personeelsnummer,
                'naam' => $m->volledigeNaam(),
                'afdeling' => $m->afdeling?->naam ?? '',
                'functie' => $m->functie?->naam ?? '',
                'manager' => $m->manager?->volledigeNaam() ?? '',
                'fte' => $m->fte() !== null ? number_format($m->fte(), 2, '.', '') : '',
                'contracttype' => $m->huidigDienstverband()?->contracttype?->label() ?? '',
                'status' => $m->status?->label() ?? '',
            ]);
    }
}
