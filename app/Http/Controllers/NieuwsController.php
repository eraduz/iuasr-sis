<?php

namespace App\Http\Controllers;

use App\Models\Nieuwsbericht;
use App\Models\Nieuwsbron;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Beheer van de onderwijsnieuwsbronnen (Beheerder). Automatische bronnen worden
 * door de scheduler opgehaald; hier kan Beheer handmatig ophalen, bronnen aan/uit
 * zetten en bij handmatige bronnen (bv. Onderwijsinspectie) zelf items toevoegen.
 */
class NieuwsController extends Controller
{
    public function index(): View
    {
        $bronnen = Nieuwsbron::withCount('berichten')->orderBy('volgorde')->orderBy('naam')->get();
        $berichten = Nieuwsbericht::with('bron')->orderByDesc('gepubliceerd_op')->orderByDesc('id')->limit(40)->get();
        $handmatige = $bronnen->where('type', \App\Enums\Nieuwsbrontype::Handmatig);

        return view('nieuws.index', compact('bronnen', 'berichten', 'handmatige'));
    }

    /** Handmatig alle automatische bronnen ophalen. */
    public function ophalen(): RedirectResponse
    {
        Artisan::call('nieuws:ophalen');
        $uitvoer = trim(Artisan::output());

        return redirect()->route('nieuws')->with('status', 'Nieuws opgehaald. '.str_replace("\n", ' ', $uitvoer));
    }

    public function bronToggle(Nieuwsbron $bron): RedirectResponse
    {
        $bron->update(['actief' => ! $bron->actief]);

        return redirect()->route('nieuws')->with('status', "Bron '{$bron->naam}' ".($bron->actief ? 'geactiveerd' : 'gedeactiveerd').'.');
    }

    /** Handmatig een nieuwsbericht toevoegen (voor bronnen zonder feed). */
    public function berichtToevoegen(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nieuwsbron_id' => ['required', 'exists:nieuwsbronnen,id'],
            'titel' => ['required', 'string', 'max:200'],
            'link' => ['required', 'url', 'max:700'],
            'samenvatting' => ['nullable', 'string', 'max:300'],
            'gepubliceerd_op' => ['nullable', 'date'],
        ]);

        Nieuwsbericht::updateOrCreate(
            ['link_hash' => Nieuwsbericht::hashVoor($data['link'])],
            [
                'nieuwsbron_id' => $data['nieuwsbron_id'],
                'titel' => $data['titel'],
                'samenvatting' => $data['samenvatting'] ?? null,
                'link' => $data['link'],
                'gepubliceerd_op' => $data['gepubliceerd_op'] ?? now(),
                'opgehaald_op' => now(),
            ]
        );

        return redirect()->route('nieuws')->with('status', 'Nieuwsbericht toegevoegd.');
    }

    public function berichtVerwijderen(Nieuwsbericht $bericht): RedirectResponse
    {
        $bericht->delete();

        return redirect()->route('nieuws')->with('status', 'Nieuwsbericht verwijderd.');
    }
}
