<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Afdeling;
use App\Models\Functie;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Beheer van de HR-referentiedata (afdelingen/teams en functies) rechtstreeks op
 * de Organisatiestructuur-pagina. Voorheen kon dit alleen de Beheerder via
 * Opzoektabellen; nu beheert de HR-medewerker (rol met <code>magHrBeheer</code>)
 * deze variabelen zelf. Alle mutaties worden gelogd; verwijderen kan alleen als er
 * niets (meer) aan hangt.
 */
class OrganisatiebeheerController extends Controller
{
    /* ---------------------------------------------------------------- Afdelingen */

    public function afdelingStore(Request $request): RedirectResponse
    {
        $data = $this->valideerAfdeling($request, null);
        $afdeling = Afdeling::create($data);

        AuditLogger::log(AuditLogger::AANMAAK, $afdeling, veld: 'afdeling', context: ['naam' => $afdeling->naam]);

        return redirect()->route('hr.organisatie')->with('status', 'Afdeling "'.$afdeling->naam.'" toegevoegd.');
    }

    public function afdelingUpdate(Request $request, Afdeling $afdeling): RedirectResponse
    {
        $data = $this->valideerAfdeling($request, $afdeling);

        // Een afdeling mag niet haar eigen bovenliggende zijn (geen directe lus).
        abort_if(($data['bovenliggende_afdeling_id'] ?? null) === $afdeling->id, 422, 'Een afdeling kan niet onder zichzelf vallen.');

        $afdeling->update($data);

        AuditLogger::log(AuditLogger::WIJZIGING, $afdeling, veld: 'afdeling', context: ['naam' => $afdeling->naam]);

        return redirect()->route('hr.organisatie')->with('status', 'Afdeling "'.$afdeling->naam.'" bijgewerkt.');
    }

    public function afdelingDestroy(Afdeling $afdeling): RedirectResponse
    {
        abort_if($afdeling->medewerkers()->exists(), 422, 'Deze afdeling heeft nog medewerkers; verplaats die eerst.');
        abort_if(Afdeling::where('bovenliggende_afdeling_id', $afdeling->id)->exists(), 422, 'Deze afdeling heeft nog onderliggende teams.');

        $naam = $afdeling->naam;
        $afdeling->delete();

        AuditLogger::log(AuditLogger::VERWIJDERING, 'Afdeling', veld: 'afdeling', context: ['naam' => $naam]);

        return redirect()->route('hr.organisatie')->with('status', 'Afdeling "'.$naam.'" verwijderd.');
    }

    /** @return array<string, mixed> */
    private function valideerAfdeling(Request $request, ?Afdeling $afdeling): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('afdelingen', 'code')->ignore($afdeling)],
            'naam' => ['required', 'string', 'max:255'],
            'bovenliggende_afdeling_id' => ['nullable', 'exists:afdelingen,id'],
            'manager_id' => ['nullable', 'exists:medewerkers,id'],
            'actief' => ['sometimes', 'boolean'],
        ]);
        $data['actief'] = $request->boolean('actief');

        return $data;
    }

    /* ------------------------------------------------------------------ Functies */

    public function functieStore(Request $request): RedirectResponse
    {
        $data = $this->valideerFunctie($request, null);
        $functie = Functie::create($data);

        AuditLogger::log(AuditLogger::AANMAAK, $functie, veld: 'functie', context: ['naam' => $functie->naam]);

        return redirect()->route('hr.organisatie')->with('status', 'Functie "'.$functie->naam.'" toegevoegd.');
    }

    public function functieUpdate(Request $request, Functie $functie): RedirectResponse
    {
        $functie->update($this->valideerFunctie($request, $functie));

        AuditLogger::log(AuditLogger::WIJZIGING, $functie, veld: 'functie', context: ['naam' => $functie->naam]);

        return redirect()->route('hr.organisatie')->with('status', 'Functie "'.$functie->naam.'" bijgewerkt.');
    }

    public function functieDestroy(Functie $functie): RedirectResponse
    {
        abort_if($functie->medewerkers()->exists(), 422, 'Deze functie is nog aan medewerkers gekoppeld.');

        $naam = $functie->naam;
        $functie->delete();

        AuditLogger::log(AuditLogger::VERWIJDERING, 'Functie', veld: 'functie', context: ['naam' => $naam]);

        return redirect()->route('hr.organisatie')->with('status', 'Functie "'.$naam.'" verwijderd.');
    }

    /** @return array<string, mixed> */
    private function valideerFunctie(Request $request, ?Functie $functie): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', Rule::unique('functies', 'code')->ignore($functie)],
            'naam' => ['required', 'string', 'max:255'],
            'categorie' => ['required', Rule::in(array_keys(Functie::CATEGORIEEN))],
            'actief' => ['sometimes', 'boolean'],
        ]);
        $data['actief'] = $request->boolean('actief');

        return $data;
    }
}
