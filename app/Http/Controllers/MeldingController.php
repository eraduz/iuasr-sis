<?php

namespace App\Http\Controllers;

use App\Enums\Meldingniveau;
use App\Enums\Rol;
use App\Models\Melding;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

/**
 * Systeemmeldingen beheren — uitsluitend Beheerder.
 *
 * Een melding verschijnt op ELKE pagina van elke module, dus dit is een klein
 * scherm met veel bereik. Aanmaken, wijzigen en intrekken worden daarom gelogd:
 * niet omdat het gevoelige gegevens zijn, maar omdat een mededeling namens de
 * organisatie uitgaat en het achteraf duidelijk moet zijn wie wat heeft
 * omgeroepen.
 */
class MeldingController extends Controller
{
    public function index(): View
    {
        $meldingen = Melding::query()
            ->with('aangemaaktDoor')
            ->orderByDesc('van')
            ->paginate(25);

        return view('meldingen.index', [
            'meldingen' => $meldingen,
            'lopend' => Melding::query()->lopend()->count(),
            'standaardDuur' => (int) config('sis.melding.standaard_duur_uren'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->valideer($request);

        $melding = new Melding($this->velden($data));
        $melding->aangemaakt_door_id = auth()->id();
        $melding->save();

        AuditLogger::log(AuditLogger::AANMAAK, $melding, veld: 'melding', context: [
            'titel' => $melding->titel,
            'van' => $melding->van->toDateTimeString(),
            'tot' => $melding->tot->toDateTimeString(),
            'rollen' => $melding->rollen ?? 'iedereen',
        ]);

        return redirect()->route('meldingen')
            ->with('status', $melding->isGepland()
                ? "Melding klaargezet; verschijnt op {$melding->van->format('d-m-Y H:i')}."
                : 'Melding staat nu op elke pagina.');
    }

    public function update(Request $request, Melding $melding): RedirectResponse
    {
        $data = $this->valideer($request);
        $oud = $melding->only(['titel', 'van', 'tot']);

        $melding->fill($this->velden($data));
        $melding->save();

        AuditLogger::log(AuditLogger::WIJZIGING, $melding, veld: 'melding', context: [
            'van_titel' => $oud['titel'],
            'naar_titel' => $melding->titel,
            'tot' => $melding->tot->toDateTimeString(),
        ]);

        // De sluit-sleutel bevat updated_at, dus een gewijzigde melding verschijnt
        // opnieuw bij wie hem al had weggeklikt. Dat is bedoeld: een correctie moet
        // juist die mensen bereiken.
        return redirect()->route('meldingen')->with('status', 'Melding bijgewerkt; hij verschijnt opnieuw bij iedereen die hem al had weggeklikt.');
    }

    /**
     * Nu stoppen: `tot` op dit moment zetten. Geen aparte status-kolom — het
     * venster is de waarheid, dus intrekken is simpelweg het venster sluiten.
     */
    public function intrekken(Melding $melding): RedirectResponse
    {
        abort_if($melding->isVerlopen(), 403, 'Deze melding is al verlopen.');

        $melding->update(['tot' => now()]);

        AuditLogger::log(AuditLogger::WIJZIGING, $melding, veld: 'melding', context: [
            'titel' => $melding->titel,
            'reden' => 'melding ingetrokken',
        ]);

        return redirect()->route('meldingen')->with('status', "\"{$melding->titel}\" is van de schermen gehaald.");
    }

    public function destroy(Melding $melding): RedirectResponse
    {
        $titel = $melding->titel;

        AuditLogger::log(AuditLogger::VERWIJDERING, 'Melding', $melding->id, veld: 'melding', context: [
            'titel' => $titel,
        ]);

        $melding->delete();

        return redirect()->route('meldingen')->with('status', "\"{$titel}\" is verwijderd.");
    }

    /** @return array<string, mixed> */
    private function valideer(Request $request): array
    {
        return $request->validate([
            'niveau' => ['required', new Enum(Meldingniveau::class)],
            'titel' => ['required', 'string', 'max:120'],
            'tekst' => ['required', 'string', 'max:1000'],
            'van' => ['nullable', 'date'],
            // Moet ná `van` liggen, anders is de melding nooit zichtbaar en zou de
            // Beheerder denken dat het systeem stuk is.
            'tot' => ['required', 'date', 'after:van'],
            'rollen' => ['array'],
            'rollen.*' => [Rule::in(Rol::waarden())],
            'afsluitbaar' => ['sometimes', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function velden(array $data): array
    {
        return [
            'niveau' => $data['niveau'],
            'titel' => $data['titel'],
            'tekst' => $data['tekst'],
            'van' => $data['van'] ?: now(),
            'tot' => $data['tot'],
            // Leeg = iedereen. Bewust null i.p.v. een lege array, zodat de
            // bedoeling in de database afleesbaar is.
            'rollen' => empty($data['rollen']) ? null : array_values($data['rollen']),
            'afsluitbaar' => (bool) ($data['afsluitbaar'] ?? false),
        ];
    }
}
