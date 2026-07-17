<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Beheer van de noodaccounts (break-glass) — uitsluitend Beheerder.
 *
 * Anders dan de rest van het systeem, waar de authenticatie via Entra ID loopt en
 * geen wachtwoorden worden beheerd, worden hier de wachtwoorden van maximaal twee
 * noodaccounts gezet. Het maximum is database-afgedwongen (unieke
 * `users.noodaccount_slot`, CHECK 1..2); de controle hieronder is de tweede laag
 * en geeft een nette melding in plaats van een SQL-fout.
 *
 * Het wachtwoord komt NOOIT in de audit-log — ook de hash niet.
 */
class NoodaccountController extends Controller
{
    public function index(): View
    {
        $noodaccounts = User::noodaccount()->orderBy('noodaccount_slot')->get();

        // Kandidaten: actieve accounts met de rol Beheerder die nog geen noodaccount zijn.
        $kandidaten = User::actief()->whereNull('noodaccount_slot')->orderBy('naam')->get()
            ->filter(fn (User $u) => $u->heeftRol(Rol::Beheerder))->values();

        $maximum = (int) config('sis.noodaccount.maximum');
        $vrijeSlots = $maximum - $noodaccounts->count();

        return view('noodaccounts.index', compact('noodaccounts', 'kandidaten', 'maximum', 'vrijeSlots'));
    }

    /** Wijst een bestaand Beheerder-account aan als noodaccount en zet meteen het wachtwoord. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'wachtwoord' => ['required', 'confirmed', $this->wachtwoordregel()],
        ]);

        $gebruiker = User::findOrFail($data['user_id']);

        abort_unless($gebruiker->heeftRol(Rol::Beheerder), 403, 'Alleen een account met de rol Beheerder kan noodaccount worden.');
        abort_unless($gebruiker->actief, 403, 'Een inactief account kan geen noodaccount worden.');

        // Een bestaand noodaccount hoort hier NIET langs te komen. Het keuzemenu
        // filtert ze weg, maar dat is een view-filter, geen controle: zonder deze
        // regel zou een handmatige POST het account naar het andere slot verhuizen
        // én het wachtwoord resetten zonder de bevestig_email-stap van
        // wachtwoord(). De houder van het oude wachtwoord zou dan stil buitengesloten
        // raken, en het auditspoor zou een wachtwoordreset als 'aanmaak' vastleggen.
        abort_if($gebruiker->isNoodaccount(), 403, 'Dit account is al een noodaccount. Wijzig het wachtwoord via het formulier op deze pagina.');

        // De telling en de toewijzing MOETEN in één transactie met een rij-lock:
        // zonder lockForUpdate kunnen twee gelijktijdige verzoeken allebei 'nog
        // één slot vrij' zien. De unieke index vangt dat alsnog af, maar dan met
        // een SQL-fout in plaats van deze melding.
        $slot = null;
        DB::transaction(function () use ($gebruiker, $data, &$slot) {
            $bezet = User::noodaccount()->lockForUpdate()->pluck('noodaccount_slot')->all();

            if (count($bezet) >= (int) config('sis.noodaccount.maximum')) {
                $slot = false;

                return;
            }

            $slot = in_array(1, $bezet) ? 2 : 1;

            $gebruiker->forceFill([
                'noodaccount_slot' => $slot,
                'password' => Hash::make($data['wachtwoord']),
                'wachtwoord_gewijzigd_op' => now(),
            ])->save();

            AuditLogger::log(AuditLogger::AANMAAK, $gebruiker, veld: 'noodaccount', context: [
                'slot' => $slot,
                'reden' => 'noodaccount aangewezen',
            ]);
        });

        if ($slot === false) {
            return back()->with('fout', 'Aanwijzen afgebroken: er zijn al '.config('sis.noodaccount.maximum').' noodaccounts. Trek er eerst één in.');
        }

        return redirect()->route('noodaccounts')
            ->with('status', "{$gebruiker->naam} is aangewezen als noodaccount {$slot} en het wachtwoord is gezet.");
    }

    /** Zet een nieuw wachtwoord op een bestaand noodaccount. */
    public function wachtwoord(Request $request, User $gebruiker): RedirectResponse
    {
        abort_unless($gebruiker->isNoodaccount(), 403, 'Dit account is geen noodaccount.');

        $data = $request->validate([
            'bevestig_email' => ['required', 'string'],
            'wachtwoord' => ['required', 'confirmed', $this->wachtwoordregel()],
        ]);

        // Zelfde dubbele beveiliging als bij het verwijderen van een student:
        // het e-mailadres moet exact worden overgetypt (StudentController::destroy).
        if ($data['bevestig_email'] !== $gebruiker->email) {
            return back()->with('fout', 'Wachtwoord zetten afgebroken: het ingevoerde e-mailadres komt niet overeen.');
        }

        $gebruiker->forceFill([
            'password' => Hash::make($data['wachtwoord']),
            'wachtwoord_gewijzigd_op' => now(),
        ])->save();

        // Nooit het wachtwoord (ook niet de hash) in de context.
        AuditLogger::log(AuditLogger::WIJZIGING, $gebruiker, veld: 'noodaccount_wachtwoord', context: [
            'slot' => $gebruiker->noodaccount_slot,
        ]);

        return redirect()->route('noodaccounts')
            ->with('status', "Het noodwachtwoord van {$gebruiker->naam} is gewijzigd.");
    }

    /** Trekt de noodtoegang in: slot vrij, wachtwoord weg. Het account zelf blijft bestaan. */
    public function destroy(Request $request, User $gebruiker): RedirectResponse
    {
        abort_unless($gebruiker->isNoodaccount(), 403, 'Dit account is geen noodaccount.');

        $slot = $gebruiker->noodaccount_slot;

        // Log vóór de mutatie, zodat het spoor het slot nog bevat.
        AuditLogger::log(AuditLogger::VERWIJDERING, $gebruiker, veld: 'noodaccount', context: [
            'slot' => $slot,
            'reden' => 'noodtoegang ingetrokken',
        ]);

        $gebruiker->forceFill([
            'noodaccount_slot' => null,
            'password' => null,
            'wachtwoord_gewijzigd_op' => null,
        ])->save();

        return redirect()->route('noodaccounts')
            ->with('status', "De noodtoegang van {$gebruiker->naam} (slot {$slot}) is ingetrokken.");
    }

    /**
     * Eén lange wachtwoordzin (NCSC: lengte boven complexiteit). Bewust GEEN
     * ->uncompromised(): die controle belt HaveIBeenPwned, en dit systeem is
     * IP-beperkt zonder algemeen uitgaand internet. De oproep zou dan alleen een
     * time-out kosten en vervolgens 'niet gelekt' teruggeven (Laravel faalt open) —
     * schijnzekerheid. De lengte is hier de daadwerkelijke verdediging.
     */
    private function wachtwoordregel(): Password
    {
        return Password::min((int) config('sis.noodaccount.wachtwoord_min_lengte'));
    }
}
