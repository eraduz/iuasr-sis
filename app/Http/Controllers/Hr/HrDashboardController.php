<?php

namespace App\Http\Controllers\Hr;

use App\Enums\MedewerkerStatus;
use App\Http\Controllers\Controller;
use App\Models\Dienstverband;
use App\Models\Gesprek;
use App\Models\Medewerker;
use App\Models\Verlofaanvraag;
use App\Models\Ziekmelding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Dashboard van de module HR / Personeelszaken. Cijfers zijn gescoped: een
 * Manager ziet uitsluitend het eigen team; HR, Beheer en Bestuur zien iedereen.
 */
class HrDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $gebruiker = $request->user();

        $medewerkers = Medewerker::query()->zichtbaarVoor($gebruiker)
            ->with('dienstverbanden')->get();

        $statusVerdeling = [];
        foreach (MedewerkerStatus::cases() as $status) {
            $statusVerdeling[$status->value] = $medewerkers->where('status', $status)->count();
        }

        $fteTotaal = round($medewerkers->sum(fn (Medewerker $m) => $m->fte() ?? 0), 2);

        // Aflopende contracten (einddatum binnen 60 dagen), binnen de scope.
        $medewerkerIds = $medewerkers->pluck('id');
        $aflopend = Dienstverband::query()
            ->whereIn('medewerker_id', $medewerkerIds)
            ->whereNotNull('einddatum')
            ->whereDate('einddatum', '>=', now()->toDateString())
            ->whereDate('einddatum', '<=', now()->addDays(60)->toDateString())
            ->with(['medewerker', 'functie'])
            ->orderBy('einddatum')->limit(15)->get();

        $openAanvragen = Verlofaanvraag::query()
            ->whereIn('medewerker_id', $medewerkerIds)
            ->where('status', 'aangevraagd')
            ->with('medewerker')
            ->orderBy('van')->limit(15)->get();

        $geplandeGesprekken = Gesprek::query()
            ->whereIn('medewerker_id', $medewerkerIds)
            ->where('status', 'gepland')
            ->whereDate('datum', '>=', now()->toDateString())
            ->with(['medewerker', 'gespreksvoerder'])
            ->orderBy('datum')->limit(15)->get();

        // Actuele ziekmeldingen (nog niet hersteld), binnen de scope.
        $openZiekmeldingen = Ziekmelding::query()
            ->whereIn('medewerker_id', $medewerkerIds)
            ->whereNull('hersteld_op')
            ->with('medewerker')
            ->orderByDesc('ziek_van')->limit(15)->get();

        // Vrijwilligers en ZZP'ers tellen apart (stichting): niet in de formatie/FTE.
        $actief = $medewerkers->where('actief', true);

        // Aankomende verjaardagen (binnen het ingestelde venster), eerstvolgende bovenaan.
        $vensterDagen = (int) config('sis.hr.verjaardag_venster_dagen', 30);
        $vandaag = now()->startOfDay();
        $verjaardagen = $actief
            ->filter(fn (Medewerker $m) => $m->geboortedatum !== null)
            ->map(function (Medewerker $m) use ($vandaag) {
                $volgende = $m->geboortedatum->copy()->year($vandaag->year)->startOfDay();
                if ($volgende->lt($vandaag)) {
                    $volgende->addYear();
                }

                return ['medewerker' => $m, 'datum' => $volgende, 'dagen' => (int) $vandaag->diffInDays($volgende)];
            })
            ->filter(fn ($r) => $r['dagen'] <= $vensterDagen)
            ->sortBy('dagen')
            ->values();

        return view('hr.dashboard', [
            'aantal' => $actief->filter->teltVoorFte()->count(),
            'vrijwilligers' => $actief->filter->isVrijwilliger()->count(),
            'zzp' => $actief->filter->isZzp()->count(),
            'verjaardagen' => $verjaardagen,
            'fteTotaal' => $fteTotaal,
            'statusVerdeling' => $statusVerdeling,
            'aflopend' => $aflopend,
            'openAanvragen' => $openAanvragen,
            'geplandeGesprekken' => $geplandeGesprekken,
            'openZiekmeldingen' => $openZiekmeldingen,
        ]);
    }
}
