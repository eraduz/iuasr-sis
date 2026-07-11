<?php

namespace App\Http\Controllers\Hr;

use App\Enums\Contracttype;
use App\Http\Controllers\Controller;
use App\Models\Afdeling;
use App\Models\Dienstverband;
use App\Models\Functie;
use App\Models\Medewerker;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dienstverbanden (contracthistorie) per medewerker — module HR. Alleen HR en
 * Beheer. De FTE wordt afgeleid uit de uren per week ÷ de voltijdsnorm.
 */
class DienstverbandController extends Controller
{
    public function store(Request $request, Medewerker $medewerker): RedirectResponse
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag dit personeelsdossier niet wijzigen.');

        $dienstverband = $medewerker->dienstverbanden()->create($this->valideer($request));
        AuditLogger::log(AuditLogger::AANMAAK, $dienstverband, veld: 'dienstverband', context: ['medewerker' => $medewerker->personeelsnummer]);

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Dienstverband toegevoegd.');
    }

    public function edit(Request $request, Dienstverband $dienstverband): View
    {
        abort_unless($dienstverband->medewerker->beheerbaarVoor($request->user()), 403, 'U mag dit personeelsdossier niet wijzigen.');

        return view('hr.dienstverband-form', [
            'medewerker' => $dienstverband->medewerker,
            'dienstverband' => $dienstverband,
            'functies' => Functie::orderBy('naam')->get(),
            'afdelingen' => Afdeling::orderBy('naam')->get(),
            'contracttypes' => Contracttype::opties(),
        ]);
    }

    public function update(Request $request, Dienstverband $dienstverband): RedirectResponse
    {
        $medewerker = $dienstverband->medewerker;
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag dit personeelsdossier niet wijzigen.');

        $dienstverband->update($this->valideer($request));
        AuditLogger::log(AuditLogger::WIJZIGING, $dienstverband, veld: 'dienstverband', context: ['medewerker' => $medewerker->personeelsnummer]);

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Dienstverband bijgewerkt.');
    }

    public function create(Request $request, Medewerker $medewerker): View
    {
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag dit personeelsdossier niet wijzigen.');

        return view('hr.dienstverband-form', [
            'medewerker' => $medewerker,
            'dienstverband' => new Dienstverband(['contracttype' => Contracttype::Tijdelijk->value, 'startdatum' => now()->toDateString()]),
            'functies' => Functie::orderBy('naam')->get(),
            'afdelingen' => Afdeling::orderBy('naam')->get(),
            'contracttypes' => Contracttype::opties(),
        ]);
    }

    public function destroy(Request $request, Dienstverband $dienstverband): RedirectResponse
    {
        $medewerker = $dienstverband->medewerker;
        abort_unless($medewerker->beheerbaarVoor($request->user()), 403, 'U mag dit personeelsdossier niet wijzigen.');

        $dienstverband->delete();
        AuditLogger::log(AuditLogger::VERWIJDERING, $medewerker, veld: 'dienstverband');

        return redirect()->route('medewerkers.show', $medewerker)->with('status', 'Dienstverband verwijderd.');
    }

    private function valideer(Request $request): array
    {
        return $request->validate([
            'contracttype' => ['required', Rule::in(Contracttype::waarden())],
            'startdatum' => ['required', 'date'],
            'einddatum' => ['nullable', 'date', 'after_or_equal:startdatum'],
            'uren_per_week' => ['required', 'numeric', 'min:0', 'max:80'],
            'functie_id' => ['nullable', 'integer', 'exists:functies,id'],
            'afdeling_id' => ['nullable', 'integer', 'exists:afdelingen,id'],
            'opmerking' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
