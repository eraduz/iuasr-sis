<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Vak;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Centrale, server-side afgedwongen rolscheiding. De UI (design system) toont
 * of verbergt op basis van rol, maar de waarheid ligt hier: elke gevoelige
 * actie wordt tegen deze Gates gecontroleerd.
 *
 * Kernregel: Studentenzaken ziet/muteert GEEN cijfers; Docent alleen eigen vak.
 */
class AutorisatieServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Alle Gates gebruiken de gebruiker-methoden, die de rechten als UNIE over
        // alle rollen bepalen (primair + extra). Zo geeft een extra rol daadwerkelijk
        // toegang; de rolscheiding zelf blijft per rol in de Rol-enum vastgelegd.

        // Cijfers/resultaten INZIEN.
        Gate::define('cijfers-inzien', fn (User $user) => $user->magCijfersInzien());

        // Cijfers INVOEREN/MUTEREN — Docent enkel voor het eigen vak.
        Gate::define('cijfers-invoeren', function (User $user, ?Vak $vak = null) {
            if (! $user->magCijfersInvoeren()) {
                return false;
            }
            // Docent: alleen het eigen vak.
            if ($user->heeftRol(\App\Enums\Rol::Docent)) {
                return $vak !== null && $user->docent_id !== null
                    && $vak->docent_id === $user->docent_id;
            }
            // Examencommissie: alle vakken.
            return true;
        });

        // Presentie REGISTREREN — Docent enkel voor het eigen vak (verplicht).
        Gate::define('presentie-registreren', function (User $user, ?Vak $vak = null) {
            return $user->magPresentieRegistreren()
                && $vak !== null && $user->docent_id !== null
                && $vak->docent_id === $user->docent_id;
        });

        // Presentielijsten INZIEN (Docent eigen vak, Examencie, Directie, Bestuur).
        Gate::define('presentie-inzien', fn (User $user) => $user->magPresentieInzien());

        // 50%-aanwezigheidsregeling toekennen/intrekken (Studentenzaken, Beheerder).
        Gate::define('aanwezigheidsregeling-beheren', fn (User $user) => $user->magAanwezigheidsregelingBeheren());

        // Identiteit/inschrijving beheren (Studentenzaken, Beheerder).
        Gate::define('inschrijving-beheren', fn (User $user) => $user->magInschrijvingBeheren());

        // BSN inzien — gelogd (Studentenzaken, Beheerder).
        Gate::define('bsn-inzien', fn (User $user) => $user->magBsnInzien());

        // Balielogboek INZIEN (Balie, Beheer, Schoolbestuur — die laatste leest mee).
        Gate::define('balie-inzien', fn (User $user) => $user->magBalieInzien());

        // Balielogboek MUTEREN — alleen de Balie zelf (en Beheer voor onderhoud).
        Gate::define('balie-beheren', fn (User $user) => $user->magBalieBeheren());

        // Bibliotheek INZIEN (Bibliotheek, Beheer, Schoolbestuur) en MUTEREN.
        Gate::define('bibliotheek-inzien', fn (User $user) => $user->magBibliotheekInzien());
        Gate::define('bibliotheek-beheren', fn (User $user) => $user->magBibliotheekBeheren());

        // Te-late uitleningen zien op het Studentenzaken-dashboard (opdracht bibliotheek §9).
        Gate::define('bibliotheek-signaal', fn (User $user) => $user->magBibliotheekSignaalZien());

        // Module Scriptie Coördinatie INZIEN (coördinator, docent-begeleider,
        // directie, examencommissie, bestuur, beheer) en BEHEREN (coördinator, beheer).
        Gate::define('scriptie-inzien', fn (User $user) => $user->magScriptieInzien());
        Gate::define('scriptie-beheren', fn (User $user) => $user->magScriptieBeheren());

        // Module Stichtingsbestuur (Stichtingsbestuur, Beheer) — geen meekijkers.
        Gate::define('stichtingsbestuur-inzien', fn (User $user) => $user->magStichtingsbestuurInzien());
        Gate::define('stichtingsbestuur-beheren', fn (User $user) => $user->magStichtingsbestuurBeheren());

        // Gebruikers/rollen/referentiedata beheren (Beheerder).
        Gate::define('beheer', fn (User $user) => $user->heeftRol(\App\Enums\Rol::Beheerder));

        // Noodaccounts (break-glass) beheren — Beheerder. Dit is de enige plek in
        // het systeem waar wachtwoorden worden gezet.
        Gate::define('noodaccounts-beheren', fn (User $user) => $user->magNoodaccountsBeheren());
    }
}
