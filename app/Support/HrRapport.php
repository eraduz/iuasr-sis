<?php

namespace App\Support;

use App\Enums\MedewerkerStatus;
use App\Models\Medewerker;
use App\Models\Verlofaanvraag;
use App\Models\Verlofsaldo;
use App\Models\Ziekmelding;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * HR-rapportage-aggregaties (module HR / Personeelszaken): aantallen per afdeling,
 * FTE-overzicht en (actueel) verzuimpercentage. Alle methodes accepteren een
 * optionele lijst medewerker-ids (`$ids`); null = geen beperking (HR/Beheer/
 * Bestuur), een lijst beperkt de aggregatie tot die medewerkers.
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
     * Verzuim- én verlofoverzicht per medewerker voor een jaar. Per medewerker:
     * het aantal ziekmeldingen, de ziektedagen en het (kalenderdag-gebaseerde)
     * verzuimpercentage, plus het verlofrecht, het opgenomen verlof en het saldo
     * (alle verloftypen samen) en het aantal openstaande verlofaanvragen. Zo is
     * elke medewerker in één oogopslag te volgen op ziekte en verlof.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function perMedewerker(?array $ids = null, ?int $jaar = null): array
    {
        $jaar ??= (int) date('Y');
        $medewerkers = self::medewerkers($ids)->sortBy(fn (Medewerker $m) => $m->achternaam.$m->voornaam);
        $mIds = $medewerkers->pluck('id')->all() ?: [0];

        // Verlof: recht (som per medewerker), opgenomen (goedgekeurd, dit jaar) en
        // het aantal nog openstaande aanvragen — alle in één query per grootheid.
        $recht = Verlofsaldo::whereIn('medewerker_id', $mIds)->where('jaar', $jaar)
            ->selectRaw('medewerker_id, sum(recht_uren) as u')->groupBy('medewerker_id')->pluck('u', 'medewerker_id');
        $opgenomen = Verlofaanvraag::whereIn('medewerker_id', $mIds)->where('status', 'goedgekeurd')
            ->whereYear('van', $jaar)->selectRaw('medewerker_id, sum(uren) as u')->groupBy('medewerker_id')->pluck('u', 'medewerker_id');
        $openAanvragen = Verlofaanvraag::whereIn('medewerker_id', $mIds)->where('status', 'aangevraagd')
            ->selectRaw('medewerker_id, count(*) as c')->groupBy('medewerker_id')->pluck('c', 'medewerker_id');

        // Verzuim: de ziekmeldingen van dit jaar, gegroepeerd per medewerker.
        $meldingen = Ziekmelding::whereIn('medewerker_id', $mIds)->whereYear('ziek_van', $jaar)
            ->get()->groupBy('medewerker_id');

        $periode = self::verstrekenDagenInJaar($jaar);

        return $medewerkers->map(function (Medewerker $m) use ($recht, $opgenomen, $openAanvragen, $meldingen, $periode) {
            $mMeld = $meldingen->get($m->id) ?? collect();
            $ziektedagen = (int) $mMeld->sum(fn (Ziekmelding $z) => $z->dagen());
            $rechtU = (float) ($recht[$m->id] ?? 0);
            $opgU = (float) ($opgenomen[$m->id] ?? 0);

            return [
                'id' => $m->id,
                'personeelsnummer' => $m->personeelsnummer,
                'naam' => $m->volledigeNaam(),
                'afdeling' => $m->afdeling?->naam ?? 'Zonder afdeling',
                'status' => $m->status,
                'ziek_meldingen' => $mMeld->count(),
                'ziektedagen' => $ziektedagen,
                'momenteel_ziek' => $mMeld->contains(fn (Ziekmelding $z) => $z->isOpen()),
                'verzuim' => $periode > 0 ? round($ziektedagen / $periode * 100, 1) : 0.0,
                'verlof_recht' => round($rechtU, 1),
                'verlof_opgenomen' => round($opgU, 1),
                'verlof_saldo' => round($rechtU - $opgU, 1),
                'verlof_open' => (int) ($openAanvragen[$m->id] ?? 0),
            ];
        })->values()->all();
    }

    /** Verstreken kalenderdagen in het jaar t/m vandaag (noemer voor verzuim%). */
    private static function verstrekenDagenInJaar(int $jaar): int
    {
        $start = Carbon::create($jaar, 1, 1)->startOfDay();
        $eindeJaar = Carbon::create($jaar, 12, 31)->startOfDay();
        $vandaag = Carbon::today();

        $eind = $vandaag->lt($start) ? $start : ($vandaag->gt($eindeJaar) ? $eindeJaar : $vandaag);

        return (int) $start->diffInDays($eind) + 1;
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
