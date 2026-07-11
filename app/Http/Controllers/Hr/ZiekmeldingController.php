<?php

namespace App\Http\Controllers\Hr;

use App\Enums\MedewerkerStatus;
use App\Http\Controllers\Controller;
use App\Models\Medewerker;
use App\Models\Ziekmelding;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ziek- en herstelmeldingen + verzuimoverzicht (module HR). HR ziet iedereen,
 * een Manager uitsluitend het eigen team. Registreren door HR en Manager.
 */
class ZiekmeldingController extends Controller
{
    public function index(Request $request): View
    {
        $query = Ziekmelding::query()
            ->whereHas('medewerker', fn ($q) => $q->zichtbaarVoor($request->user()))
            ->with(['medewerker', 'gemeldDoor'])
            ->orderByRaw('hersteld_op is null desc')->orderByDesc('ziek_van');

        if ($request->query('status', 'open') === 'open') {
            $query->whereNull('hersteld_op');
        }

        return view('hr.verzuim-index', [
            'meldingen' => $query->paginate(25)->withQueryString(),
            'statusFilter' => (string) $request->query('status', 'open'),
            'medewerkers' => Medewerker::query()->zichtbaarVoor($request->user())->where('actief', true)->orderBy('achternaam')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'medewerker_id' => ['required', 'integer', 'exists:medewerkers,id'],
            'ziek_van' => ['required', 'date'],
            'percentage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'opmerking' => ['nullable', 'string', 'max:1000'],
        ]);

        $medewerker = Medewerker::findOrFail($data['medewerker_id']);
        abort_unless($medewerker->zichtbaarVoor($request->user()), 403, 'Deze medewerker valt buiten uw team.');

        $medewerker->ziekmeldingen()->create([
            'ziek_van' => $data['ziek_van'],
            'percentage' => $data['percentage'] ?? 100,
            'opmerking' => $data['opmerking'] ?? null,
            'gemeld_door_id' => $request->user()->id,
        ]);
        $medewerker->update(['status' => MedewerkerStatus::Ziek->value]);

        AuditLogger::log(AuditLogger::AANMAAK, $medewerker, veld: 'ziekmelding');

        return back()->with('status', 'Ziekmelding geregistreerd.');
    }

    public function herstel(Request $request, Ziekmelding $ziekmelding): RedirectResponse
    {
        $medewerker = $ziekmelding->medewerker;
        abort_unless($medewerker->zichtbaarVoor($request->user()), 403, 'Deze medewerker valt buiten uw team.');

        $data = $request->validate([
            'hersteld_op' => ['required', 'date', 'after_or_equal:'.$ziekmelding->ziek_van->toDateString()],
        ]);

        $ziekmelding->update(['hersteld_op' => $data['hersteld_op']]);

        // Geen open ziekmelding meer? Zet de status terug op actief.
        if (! $medewerker->ziekmeldingen()->whereNull('hersteld_op')->exists()) {
            $medewerker->update(['status' => MedewerkerStatus::Actief->value]);
        }

        AuditLogger::log(AuditLogger::WIJZIGING, $medewerker, veld: 'herstelmelding');

        return back()->with('status', 'Herstel geregistreerd.');
    }
}
