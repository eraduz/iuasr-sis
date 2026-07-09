<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\Opleiding;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

/**
 * Gebruikers & rollen (Beheerder). De toegangsmatrix maakt zichtbaar wat elke
 * rol mag; rolwijzigingen worden gelogd. Authenticatie zelf loopt via Entra ID.
 */
class GebruikerController extends Controller
{
    public function index(): View
    {
        $gebruikers = User::with('opleidingen')->orderBy('rol')->orderBy('naam')->get();
        $rollen = Rol::cases();

        // Directieleden krijgen per opleiding toegewezen wat zij mogen zien.
        $directie = $gebruikers->where('rol', Rol::Directie)->values();
        $opleidingen = Opleiding::where('actief', true)->orderBy('naam')->get();

        return view('gebruikers.index', compact('gebruikers', 'rollen', 'directie', 'opleidingen'));
    }

    /** Wijs een directielid toe aan één of meer opleidingen (zichtbaarheidsgrens). */
    public function updateOpleidingen(Request $request, User $gebruiker): RedirectResponse
    {
        abort_unless($gebruiker->rol === Rol::Directie, 403,
            'Opleidingtoewijzing geldt alleen voor de rol Directie.');

        $data = $request->validate([
            'opleidingen' => ['array'],
            'opleidingen.*' => ['integer', 'exists:opleidingen,id'],
        ]);

        $ids = $data['opleidingen'] ?? [];
        $gebruiker->opleidingen()->sync($ids);

        AuditLogger::log(AuditLogger::WIJZIGING, $gebruiker, veld: 'opleidingtoewijzing', context: [
            'opleiding_ids' => array_values($ids),
        ]);

        return redirect()->route('gebruikers')
            ->with('status', 'Opleidingtoewijzing bijgewerkt voor '.$gebruiker->naam.'.');
    }

    public function updateRol(Request $request, User $gebruiker): RedirectResponse
    {
        $data = $request->validate([
            'rol' => ['required', new Enum(Rol::class)],
            'actief' => ['sometimes', 'boolean'],
        ]);

        $oud = $gebruiker->rol->value;
        $gebruiker->update([
            'rol' => $data['rol'],
            'actief' => $request->boolean('actief', $gebruiker->actief),
        ]);

        AuditLogger::log(AuditLogger::WIJZIGING, $gebruiker, veld: 'rol', context: [
            'van' => $oud,
            'naar' => $gebruiker->rol->value,
        ]);

        return redirect()->route('gebruikers')->with('status', 'Gebruiker bijgewerkt: '.$gebruiker->naam.'.');
    }
}
