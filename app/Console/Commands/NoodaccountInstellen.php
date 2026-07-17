<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Wijst een bestaand Beheerder-account aan als noodaccount (break-glass) en zet
 * het wachtwoord. Dit is de BOOTSTRAP: zolang Entra ID nog niet gekoppeld is en
 * de dev-login in productie 404 geeft, is er geen enkele manier om in te loggen,
 * dus kan het eerste noodwachtwoord onmogelijk via het beheerscherm worden gezet.
 * Daarna blijft dit commando de terugval als u buitengesloten raakt.
 *
 * Het commando maakt BEWUST geen accounts aan — een noodaccount is altijd een
 * bestaand, via Beheer aangemaakt account. Zo blijft er één plek waar accounts
 * ontstaan (GebruikerController::store).
 *
 * Het wachtwoord wordt uitsluitend interactief gevraagd (verborgen invoer), nooit
 * als argument of optie: dat zou het in de shell-historie en in de procestabel
 * (`ps`) achterlaten.
 */
class NoodaccountInstellen extends Command
{
    protected $signature = 'sis:noodaccount-instellen
                            {email : E-mailadres van het bestaande Beheerder-account}
                            {--intrekken : Trekt de noodtoegang van dit account in}
                            {--force : Niet om bevestiging vragen}';

    protected $description = 'Wijst een Beheerder-account aan als noodaccount (break-glass) en zet het wachtwoord.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $gebruiker = User::where('email', $email)->first();

        if (! $gebruiker) {
            $this->error("Geen account met het e-mailadres {$email}.");
            $this->line('Een noodaccount is altijd een bestaand account. Maak het eerst aan via Beheer → Gebruikers & rollen.');

            return self::FAILURE;
        }

        return $this->option('intrekken')
            ? $this->intrekken($gebruiker)
            : $this->instellen($gebruiker);
    }

    private function intrekken(User $gebruiker): int
    {
        if (! $gebruiker->isNoodaccount()) {
            $this->error("{$gebruiker->naam} is geen noodaccount.");

            return self::FAILURE;
        }

        $slot = $gebruiker->noodaccount_slot;

        if (! $this->option('force') && ! $this->confirm("Noodtoegang van {$gebruiker->naam} ({$gebruiker->email}, slot {$slot}) intrekken?")) {
            $this->line('Afgebroken.');

            return self::SUCCESS;
        }

        AuditLogger::log(AuditLogger::VERWIJDERING, $gebruiker, veld: 'noodaccount', context: [
            'slot' => $slot,
            'reden' => 'noodtoegang ingetrokken',
            'bron' => 'artisan',
        ]);

        $gebruiker->forceFill([
            'noodaccount_slot' => null,
            'password' => null,
            'wachtwoord_gewijzigd_op' => null,
        ])->save();

        $this->info("De noodtoegang van {$gebruiker->naam} (slot {$slot}) is ingetrokken.");

        return self::SUCCESS;
    }

    private function instellen(User $gebruiker): int
    {
        if (! $gebruiker->heeftRol(Rol::Beheerder)) {
            $this->error("{$gebruiker->naam} heeft niet de rol Beheerder. Een noodaccount moet volledige beheerrechten hebben.");

            return self::FAILURE;
        }

        if (! $gebruiker->actief) {
            $this->error("{$gebruiker->naam} is een inactief account.");

            return self::FAILURE;
        }

        $this->toonHuidige();

        $minimum = (int) config('sis.noodaccount.wachtwoord_min_lengte');
        $wachtwoord = $this->vraagWachtwoord($minimum);
        if ($wachtwoord === null) {
            return self::FAILURE;
        }

        $actie = $gebruiker->isNoodaccount() ? 'wijzigt het noodwachtwoord van' : 'wijst als noodaccount aan:';
        if (! $this->option('force') && ! $this->confirm("Dit {$actie} {$gebruiker->naam} ({$gebruiker->email}). Doorgaan?")) {
            $this->line('Afgebroken.');

            return self::SUCCESS;
        }

        // Zelfde transactie + rij-lock als NoodaccountController::store: zonder
        // lockForUpdate kunnen twee gelijktijdige aanroepen allebei een vrij slot
        // zien. De unieke index vangt dat alsnog af, maar met een SQL-fout.
        $slot = null;
        $wasNoodaccount = $gebruiker->isNoodaccount();

        DB::transaction(function () use ($gebruiker, $wachtwoord, $wasNoodaccount, &$slot) {
            $bezet = User::noodaccount()->lockForUpdate()->pluck('noodaccount_slot')->all();

            if ($wasNoodaccount) {
                // Bestaand noodaccount: alleen het wachtwoord wijzigen, slot blijft.
                $slot = $gebruiker->noodaccount_slot;
            } elseif (count($bezet) >= (int) config('sis.noodaccount.maximum')) {
                $slot = false;

                return;
            } else {
                $slot = in_array(1, $bezet) ? 2 : 1;
            }

            $gebruiker->forceFill([
                'noodaccount_slot' => $slot,
                'password' => Hash::make($wachtwoord),
                'wachtwoord_gewijzigd_op' => now(),
            ])->save();

            // Een NIEUWE toekenning is een aanmaak, geen wachtwoordwijziging — anders
            // ontbreekt juist het allereerste noodaccount in het overzicht 'wie heeft
            // ooit break-glass-toegang gekregen'. En dat eerste kan per definitie
            // alleen hier ontstaan: zonder Entra is het beheerscherm onbereikbaar.
            // Auth::id() is in artisan-context null; 'bron' houdt het spoor herleidbaar.
            $wasNoodaccount
                ? AuditLogger::log(AuditLogger::WIJZIGING, $gebruiker, veld: 'noodaccount_wachtwoord', context: [
                    'slot' => $slot,
                    'bron' => 'artisan',
                ])
                : AuditLogger::log(AuditLogger::AANMAAK, $gebruiker, veld: 'noodaccount', context: [
                    'slot' => $slot,
                    'reden' => 'noodaccount aangewezen',
                    'bron' => 'artisan',
                ]);
        });

        if ($slot === false) {
            $maximum = config('sis.noodaccount.maximum');
            $this->error("Er zijn al {$maximum} noodaccounts. Trek er eerst één in met --intrekken.");

            return self::FAILURE;
        }

        $this->info("{$gebruiker->naam} is noodaccount {$slot}; het wachtwoord is gezet.");
        $this->line('Bewaar het wachtwoord in de kluis/wachtwoordmanager. Het is nergens meer op te vragen.');

        return self::SUCCESS;
    }

    private function toonHuidige(): void
    {
        $bestaand = User::noodaccount()->orderBy('noodaccount_slot')->get();

        if ($bestaand->isEmpty()) {
            $this->line('Er zijn nog geen noodaccounts.');

            return;
        }

        $this->line('Huidige noodaccounts:');
        $this->table(
            ['Slot', 'Naam', 'E-mail', 'Wachtwoord gewijzigd'],
            $bestaand->map(fn (User $u) => [
                $u->noodaccount_slot,
                $u->naam,
                $u->email,
                $u->wachtwoord_gewijzigd_op?->format('d-m-Y H:i') ?? '—',
            ])->all()
        );
    }

    /** Verborgen invoer, twee keer. Geeft null als de invoer niet deugt. */
    private function vraagWachtwoord(int $minimum): ?string
    {
        $wachtwoord = (string) $this->secret("Nieuw noodwachtwoord (minimaal {$minimum} tekens)");

        if (mb_strlen($wachtwoord) < $minimum) {
            $this->error("Het wachtwoord moet minimaal {$minimum} tekens hebben.");

            return null;
        }

        if ($wachtwoord !== (string) $this->secret('Herhaal het wachtwoord')) {
            $this->error('De wachtwoorden komen niet overeen.');

            return null;
        }

        return $wachtwoord;
    }
}
