<?php

namespace App\Http\Controllers;

use App\Models\Inschrijving;
use App\Models\Vak;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * De Studentenadministratie past de (automatische) vaktoewijzing van een
 * student per studiejaar (inschrijving) aan — bijvoorbeeld voor openstaande
 * vakken uit een vorig jaar of een vrijstelling.
 */
class VaktoewijzingController extends Controller
{
    public function edit(Inschrijving $inschrijving): View
    {
        $inschrijving->load(['student', 'opleiding', 'periode', 'vaktoewijzingen']);
        $toegewezen = $inschrijving->vaktoewijzingen->pluck('vak_id')->all();

        $vakken = Vak::where('opleiding_id', $inschrijving->opleiding_id)->with('docent')
            ->orderBy('leerjaar')->orderBy('blok')->orderBy('code')->get();

        $structuur = $vakken->groupBy(fn ($v) => $v->leerjaar ?? 0)
            ->map(fn ($g) => $g->groupBy(fn ($v) => $v->blok ?? 0)->sortKeys())
            ->sortKeys();

        return view('vaktoewijzing.edit', compact('inschrijving', 'structuur', 'toegewezen'));
    }

    public function update(Request $request, Inschrijving $inschrijving): RedirectResponse
    {
        $data = $request->validate([
            'vak_ids' => ['array'],
            'vak_ids.*' => [Rule::exists('vakken', 'id')],
        ]);

        $gekozen = collect($data['vak_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $huidig = $inschrijving->vaktoewijzingen()->pluck('vak_id');

        // Verwijder niet langer gekozen vakken.
        $inschrijving->vaktoewijzingen()->whereNotIn('vak_id', $gekozen->all() ?: [0])->delete();

        // Voeg handmatig gekozen vakken toe.
        foreach ($gekozen->diff($huidig) as $vakId) {
            $inschrijving->vaktoewijzingen()->create(['vak_id' => $vakId, 'automatisch' => false]);
        }

        return redirect()->route('studenten.show', $inschrijving->student_id)
            ->with('status', 'Vaktoewijzing bijgewerkt.');
    }
}
