<?php

namespace App\Http\Controllers\Cursus;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Cursus;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van de cursussen (naam, cursusgeld, looptijd). Nieuwe cursussen en
 * tarieven zijn gewoon extra rijen; het systeem is daar niet op vastgezet.
 *
 * Een cursusdirecteur beheert uitsluitend de eigen cursus(sen). Cursussen
 * aanmaken/verwijderen en een directeur toewijzen is voorbehouden aan de
 * Beheerder (server-side via de route-middleware en de guards hieronder).
 */
class CursusController extends Controller
{
    public function index(Request $request): View
    {
        return view('cursussen.beheer', [
            'cursussen' => Cursus::query()->zichtbaarVoor($request->user())
                ->with('directeur')->withCount('inschrijvingen')->orderBy('naam')->get(),
        ]);
    }

    public function create(): View
    {
        return view('cursussen.form', [
            'cursus' => new Cursus(['actief' => true]),
            'directeuren' => $this->directeuren(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Alleen bereikbaar voor de Beheerder (route-middleware); de directeur mag
        // hier dus worden meegegeven.
        $cursus = Cursus::create($this->valideer($request) + [
            'directeur_id' => $this->directeurUitVerzoek($request),
        ]);
        AuditLogger::log(AuditLogger::AANMAAK, $cursus, veld: 'cursus', context: ['code' => $cursus->code]);

        return redirect()->route('cursussen.beheer')->with('status', 'Cursus toegevoegd.');
    }

    public function edit(Request $request, Cursus $cursus): View
    {
        abort_unless($cursus->beheerbaarVoor($request->user()), 403, 'Deze cursus valt buiten uw beheer.');

        return view('cursussen.form', ['cursus' => $cursus, 'directeuren' => $this->directeuren()]);
    }

    /**
     * "Kopieer"-wizard (Beheerder): toont het cursusformulier vooraf ingevuld met
     * de gegevens van de bron-cursus (cursusgeld, omschrijving, looptijd, directeur).
     * De Beheerder geeft alleen een nieuwe, unieke code en naam op; bij opslaan
     * ontstaat een NIEUWE cursus. Inschrijvingen/cursisten worden NIET meegekopieerd.
     */
    public function kopieForm(Cursus $bron): View
    {
        $kopie = new Cursus([
            'naam' => $bron->naam,
            'omschrijving' => $bron->omschrijving,
            'cursusgeld' => $bron->cursusgeld,
            'startdatum' => $bron->startdatum,
            'einddatum' => $bron->einddatum,
            'directeur_id' => $bron->directeur_id,
            'actief' => true,
        ]);

        return view('cursussen.form', [
            'cursus' => $kopie,
            'directeuren' => $this->directeuren(),
            'bron' => $bron,
        ]);
    }

    public function update(Request $request, Cursus $cursus): RedirectResponse
    {
        abort_unless($cursus->beheerbaarVoor($request->user()), 403, 'Deze cursus valt buiten uw beheer.');

        $data = $this->valideer($request, $cursus->id);
        // Alleen de Beheerder mag de directeur (her)toewijzen; een cursusdirecteur
        // kan zichzelf of anderen geen cursussen toekennen.
        if ($request->user()->rol === Rol::Beheerder) {
            $data['directeur_id'] = $this->directeurUitVerzoek($request);
        }

        $cursus->update($data);
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

    /** De gebruikers die als cursusdirecteur toewijsbaar zijn. */
    private function directeuren()
    {
        return User::where('rol', Rol::Cursusadministratie)->orderBy('naam')->get();
    }

    /** Gevalideerde directeurkeuze uit het verzoek (moet een cursusadministratie zijn). */
    private function directeurUitVerzoek(Request $request): ?int
    {
        $data = $request->validate([
            'directeur_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('rol', Rol::Cursusadministratie->value)],
        ], [], ['directeur_id' => 'directeur']);

        return $data['directeur_id'] ?? null;
    }
}
