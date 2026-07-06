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
        // Cijfers/resultaten INZIEN.
        Gate::define('cijfers-inzien', fn (User $user) => $user->rol->magCijfersInzien());

        // Cijfers INVOEREN/MUTEREN — Docent enkel voor het eigen vak.
        Gate::define('cijfers-invoeren', function (User $user, ?Vak $vak = null) {
            if (! $user->rol->magCijfersInvoeren()) {
                return false;
            }
            // Docent: alleen het eigen vak.
            if ($user->rol === \App\Enums\Rol::Docent) {
                return $vak !== null && $user->docent_id !== null
                    && $vak->docent_id === $user->docent_id;
            }
            // Examencommissie: alle vakken.
            return true;
        });

        // Identiteit/inschrijving beheren (Studentenzaken, Beheerder).
        Gate::define('inschrijving-beheren', fn (User $user) => $user->rol->magInschrijvingBeheren());

        // BSN inzien — gelogd (Studentenzaken, Beheerder).
        Gate::define('bsn-inzien', fn (User $user) => $user->rol->magBsnInzien());

        // Gebruikers/rollen/referentiedata beheren (Beheerder).
        Gate::define('beheer', fn (User $user) => $user->rol === \App\Enums\Rol::Beheerder);
    }
}
