<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Noodtoegang (break-glass): inloggen met gebruikersnaam+wachtwoord voor de
 * maximaal twee noodaccounts, voor het geval Microsoft Entra ID onbereikbaar is.
 *
 * LET OP: deze controller weigert BEWUST NIET in productie — anders zou de
 * noodtoegang precies daar ontbreken waar hij nodig is. Dat is het verschil met
 * {@see DevLoginController}, die met opzet alleen lokaal werkt.
 *
 * De netwerkbeperking (IpBeperking) geldt hier onverkort: de noodtoegang is
 * uitsluitend vanaf het interne netwerk bereikbaar. Een beheerder die van buiten
 * moet werken gaat eerst de VPN op (keuze opdrachtgever, 2026-07-17).
 *
 * Elke poging wordt gelogd. Bij een mislukte poging is er geen ingelogde
 * gebruiker, dus AuditLogger vult `user_id` en `rol` met null; de geprobeerde
 * gebruikersnaam en de reden staan daarom in de context. Naar de gebruiker gaat
 * altijd dezelfde melding terug, zodat niet te achterhalen is welk e-mailadres
 * een noodaccount is.
 */
class NoodloginController extends Controller
{
    private const MELDING = 'Onjuiste gebruikersnaam of wachtwoord.';

    public function toon(): View
    {
        return view('auth.noodtoegang');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gebruikersnaam' => ['required', 'string', 'email', 'max:255'],
            'wachtwoord' => ['required', 'string'],
        ]);

        // De poging wordt gescoopt op noodaccounts: een regulier account kan hier
        // nooit doorheen, ook niet als het ooit een wachtwoord zou krijgen.
        $gelukt = Auth::attempt([
            'email' => $data['gebruikersnaam'],
            'password' => $data['wachtwoord'],
            'actief' => true,
            fn ($query) => $query->whereNotNull('noodaccount_slot'),
        ]);

        if (! $gelukt) {
            $this->logMislukt($request, $data['gebruikersnaam'], 'gebruikersnaam of wachtwoord onjuist, account inactief, of geen noodaccount');

            throw ValidationException::withMessages(['gebruikersnaam' => self::MELDING]);
        }

        /** @var User $gebruiker */
        $gebruiker = Auth::user();

        // Tweede slot: het noodaccount moet ook daadwerkelijk Beheerder zijn.
        // Auth::attempt kan dat niet controleren (multi-rol loopt via
        // roltoewijzingen), dus het gebeurt hier — na de wachtwoordcontrole.
        if (! $gebruiker->magNoodloginGebruiken()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->logMislukt($request, $data['gebruikersnaam'], 'account is geen actieve Beheerder');

            throw ValidationException::withMessages(['gebruikersnaam' => self::MELDING]);
        }

        // Auth::attempt regenereert de sessie al (SessionGuard::updateSession →
        // session()->migrate(true)). Dit is een expliciete herhaling: goedkoop, en
        // het houdt de bescherming tegen sessiefixatie zichtbaar op het enige
        // wachtwoordpad van het systeem.
        $request->session()->regenerate();
        $gebruiker->forceFill(['laatst_ingelogd_op' => now()])->save();

        // Ná de login: user_id en rol vullen zichzelf correct.
        AuditLogger::log(AuditLogger::NOODLOGIN, $gebruiker, veld: 'noodaccount', context: [
            'slot' => $gebruiker->noodaccount_slot,
            'user_agent' => (string) $request->userAgent(),
        ]);

        return redirect()->intended(route('modules.kiezen'));
    }

    /**
     * Legt een mislukte poging vast. Er is geen ingelogde gebruiker, dus
     * AuditLogger vult user_id/rol met null (dat is toegestaan: beide kolommen
     * zijn nullable). De identiteit van de poging gaat mee in de context. De
     * reden staat ALLEEN hier — de gebruiker ziet altijd self::MELDING.
     */
    private function logMislukt(Request $request, string $gebruikersnaam, string $reden): void
    {
        AuditLogger::log(AuditLogger::NOODLOGIN_MISLUKT, 'Noodaccount', veld: 'noodaccount', context: [
            'gebruikersnaam' => $gebruikersnaam,
            'reden' => $reden,
            'user_agent' => (string) $request->userAgent(),
        ]);
    }
}
