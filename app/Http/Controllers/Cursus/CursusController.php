<?php

namespace App\Http\Controllers\Cursus;

use App\Http\Controllers\Controller;
use App\Models\Cursus;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van de cursussen (naam, cursusgeld, looptijd). Nieuwe cursussen en
 * tarieven zijn gewoon extra rijen; het systeem is daar niet op vastgezet.
 */
class CursusController extends Controller
{
    public function index(): View
    {
        return view('cursussen.beheer', [
            'cursussen' => Cursus::withCount('inschrijvingen')->orderBy('naam')->get(),
        ]);
    }

    public function create(): View
    {
        return view('cursussen.form', ['cursus' => new Cursus(['actief' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cursus = Cursus::create($this->valideer($request));
        AuditLogger::log(AuditLogger::AANMAAK, $cursus, veld: 'cursus', context: ['code' => $cursus->code]);

        return redirect()->route('cursussen.beheer')->with('status', 'Cursus toegevoegd.');
    }

    public function edit(Cursus $cursus): View
    {
        return view('cursussen.form', ['cursus' => $cursus]);
    }

    public function update(Request $request, Cursus $cursus): RedirectResponse
    {
        $cursus->update($this->valideer($request, $cursus->id));
        AuditLogger::log(AuditLogger::WIJZIGING, $cursus, veld: 'cursus', context: ['code' => $cursus->code]);

        return redirect()->route('cursussen.beheer')->with('status', 'Cursus bijgewerkt.');
    }

    public function destroy(Cursus $cursus): RedirectResponse
    {
        try {
            $cursus->delete();
        } catch (QueryException) {
            return back()->with('status', 'Kan de cursus niet verwijderen: er zijn inschrijvingen aan gekoppeld. Zet de cursus desgewenst op inactief.');
        }

        return redirect()->route('cursussen.beheer')->with('status', 'Cursus verwijderd.');
    }

    private function valideer(Request $request, ?int $negeerId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('cursussen', 'code')->ignore($negeerId)],
            'naam' => ['required', 'string', 'max:255'],
            'omschrijving' => ['nullable', 'string', 'max:2000'],
            'cursusgeld' => ['required', 'numeric', 'min:0', 'max:100000'],
            'startdatum' => ['nullable', 'date'],
            'einddatum' => ['nullable', 'date', 'after_or_equal:startdatum'],
        ], [
            'einddatum.after_or_equal' => 'De einddatum kan niet vóór de startdatum liggen.',
        ]);

        $data['actief'] = $request->boolean('actief');

        return $data;
    }
}
