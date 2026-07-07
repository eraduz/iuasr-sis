<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Gebruikers & rollen (Beheerder). De toegangsmatrix maakt zichtbaar wat elke
 * rol mag; rolwijzigingen worden gelogd. Authenticatie zelf loopt via Entra ID.
 */
class GebruikerController extends Controller
{
    public function index(): View
    {
        $gebruikers = User::orderBy('rol')->orderBy('naam')->get();
        $rollen = Rol::cases();

        return view('gebruikers.index', compact('gebruikers', 'rollen'));
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
