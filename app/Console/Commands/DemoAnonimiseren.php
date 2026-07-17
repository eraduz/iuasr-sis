<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vervangt ALLE persoonsgegevens in een DEMO-database door synthetische, zodat
 * er een dump gemaakt kan worden voor een omgeving waar echte gegevens niet
 * mogen staan (de publieke Plesk-server). Het curriculum, de bibliotheek, de
 * opzoektabellen en de aantallen blijven intact, zodat de demo realistisch blijft.
 *
 * VEILIGHEIDSGRENDEL — LEES DIT VOORDAT U IETS WIJZIGT:
 * Dit commando OVERSCHRIJFT gegevens en is dus onomkeerbaar. Het weigert daarom
 * te draaien tenzij de databasenaam op `_demo` eindigt. Die grendel is er niet
 * voor de vorm: op 2026-07-17 is de complete ontwikkeldatabase gewist doordat een
 * commando via `--env=testing` op de verkeerde database uitkwam. Een commando dat
 * data vernietigt moet zelf onmogelijk kunnen maken dat het de verkeerde database
 * raakt — vertrouw niet op de oplettendheid van degene die het aanroept.
 * Verwijder deze grendel niet, ook niet "even tijdelijk".
 *
 * Werkwijze: kopieer eerst de database naar `<naam>_demo`, draai dit commando
 * daar, en dump pas daarna. Zie de technische handleiding, hoofdstuk 6f.
 */
class DemoAnonimiseren extends Command
{
    protected $signature = 'sis:demo-anonimiseren {--force : Niet om bevestiging vragen}';

    protected $description = 'Vervangt alle persoonsgegevens in een *_demo-database door synthetische (voor een demo-dump).';

    /** Synthetische namen die passen bij de instelling; nooit die van echte mensen. */
    private const VOORNAMEN = [
        'Amina', 'Yusuf', 'Fatima', 'Omar', 'Zeynep', 'Ibrahim', 'Khadija', 'Mehmet',
        'Layla', 'Hamza', 'Nour', 'Bilal', 'Salma', 'Idris', 'Rania', 'Tarik',
        'Meryem', 'Anas', 'Sara', 'Younes', 'Hafsa', 'Kaan', 'Nadia', 'Rachid',
        'Esra', 'Soufiane', 'Imane', 'Emre', 'Latifa', 'Karim',
    ];

    private const ACHTERNAMEN = [
        'Yilmaz', 'El Amrani', 'Demir', 'Bouzid', 'Kaya', 'Ait Taleb', 'Aydin', 'Benali',
        'Ozturk', 'El Idrissi', 'Celik', 'Haddad', 'Sahin', 'Chakir', 'Arslan', 'Belkacem',
        'Dogan', 'Ouali', 'Korkmaz', 'Zaoui', 'Polat', 'Mansouri', 'Erdem', 'Cherif',
        'Kurt', 'Rachidi', 'Bulut', 'Naciri', 'Tekin', 'Ziani',
    ];

    private const PLAATSEN = [
        'Rotterdam', 'Schiedam', 'Vlaardingen', 'Capelle aan den IJssel', 'Spijkenisse',
        'Dordrecht', 'Delft', 'Den Haag', 'Zoetermeer', 'Gouda',
    ];

    private const STRATEN = [
        'Voorbeeldstraat', 'Proefweg', 'Testlaan', 'Demoplein', 'Fictiekade',
        'Modelstraat', 'Steekproeflaan', 'Voorbeeldhof',
    ];

    public function handle(): int
    {
        $database = (string) config('database.connections.mysql.database');

        if (! str_ends_with($database, '_demo')) {
            $this->error("GEWEIGERD. Dit commando draait alleen op een database waarvan de naam op '_demo' eindigt.");
            $this->line("De huidige database is '{$database}'.");
            $this->newLine();
            $this->line('Het overschrijft gegevens onomkeerbaar. Kopieer eerst uw database naar een');
            $this->line('*_demo-database en draai het commando daar. Zie de technische handleiding, 6f.');

            return self::FAILURE;
        }

        $studenten = DB::table('studenten')->count();
        $medewerkers = DB::table('medewerkers')->count();

        $this->warn("Database: {$database}");
        $this->line("Te anonimiseren: {$studenten} studenten, {$medewerkers} medewerkers, plus docenten, accounts, cursisten en contactpersonen.");
        $this->line('Vrije tekst (notities, opmerkingen), het audit-logboek en de ondertekende documenten worden gewist.');

        if (! $this->option('force') && ! $this->confirm('Dit is onomkeerbaar. Doorgaan?')) {
            $this->line('Afgebroken.');

            return self::SUCCESS;
        }

        // Eén transactie: een half-geanonimiseerde database is gevaarlijker dan
        // geen, want die ziet er schoon uit terwijl er nog echte namen in staan.
        DB::transaction(function () {
            $this->anonimiseerStudenten();
            $this->anonimiseerMedewerkers();
            $this->anonimiseerDocenten();
            $this->anonimiseerGebruikers();
            $this->anonimiseerCursisten();
            $this->anonimiseerContactpersonen();
            $this->anonimiseerOverig();
            $this->wisVrijeTekst();
            $this->wisSporen();
        });

        $this->newLine();
        $this->info('Klaar. Controleer het resultaat met: php artisan sis:demo-controleren');

        return self::SUCCESS;
    }

    /** Deterministisch, zodat dezelfde rij altijd dezelfde schijnnaam krijgt. */
    private function voornaam(int $id): string
    {
        return self::VOORNAMEN[$id % count(self::VOORNAMEN)];
    }

    private function achternaam(int $id): string
    {
        return self::ACHTERNAMEN[intdiv($id, 7) % count(self::ACHTERNAMEN)];
    }

    private function plaats(int $id): string
    {
        return self::PLAATSEN[$id % count(self::PLAATSEN)];
    }

    private function adres(int $id): string
    {
        return self::STRATEN[$id % count(self::STRATEN)].' '.(($id % 120) + 1);
    }

    private function postcode(int $id): string
    {
        return (3000 + ($id % 200)).' '.chr(65 + ($id % 26)).chr(65 + (($id * 3) % 26));
    }

    private function telefoon(int $id): string
    {
        return '06'.str_pad((string) (10000000 + ($id * 7919) % 89999999), 8, '0', STR_PAD_LEFT);
    }

    private function anonimiseerStudenten(): void
    {
        $balk = $this->output->createProgressBar(DB::table('studenten')->count());
        $balk->setFormat(' Studenten      %current%/%max% [%bar%]');

        DB::table('studenten')->orderBy('id')->chunkById(500, function ($rijen) use ($balk) {
            foreach ($rijen as $s) {
                $voornaam = $this->voornaam($s->id);
                $achternaam = $this->achternaam($s->id);

                DB::table('studenten')->where('id', $s->id)->update([
                    'voornaam' => $voornaam,
                    'roepnaam' => $voornaam,
                    'achternaam' => $achternaam,
                    // Geboortedatum vervangen door 1 januari van hetzelfde jaar:
                    // de leeftijdsopbouw blijft realistisch, de datum is niet meer
                    // herleidbaar tot een persoon.
                    'geboortedatum' => $s->geboortedatum ? substr((string) $s->geboortedatum, 0, 4).'-01-01' : null,
                    'geboorteplaats' => $this->plaats($s->id),
                    'adres' => $this->adres($s->id),
                    'postcode' => $this->postcode($s->id),
                    'woonplaats' => $this->plaats($s->id),
                    'telefoon' => $this->telefoon($s->id),
                    'email' => 'student'.$s->studentnummer.'@voorbeeld.test',
                    'email_prive' => null,
                    'bsn' => null,
                    'bsn_hash' => null,
                    'rekeningnummer' => null,
                    'opmerkingen' => null,
                ]);
                $balk->advance();
            }
        });

        $balk->finish();
        $this->newLine();
    }

    private function anonimiseerMedewerkers(): void
    {
        foreach (DB::table('medewerkers')->orderBy('id')->get() as $m) {
            $voornaam = $this->voornaam($m->id + 3);
            $achternaam = $this->achternaam($m->id + 3);

            DB::table('medewerkers')->where('id', $m->id)->update([
                'voornaam' => $voornaam,
                'achternaam' => $achternaam,
                'geboortedatum' => $m->geboortedatum ? substr((string) $m->geboortedatum, 0, 4).'-01-01' : null,
                'adres' => $this->adres($m->id),
                'postcode' => $this->postcode($m->id),
                'woonplaats' => $this->plaats($m->id),
                'telefoon' => $this->telefoon($m->id),
                'email' => 'medewerker'.$m->id.'@voorbeeld.test',
                'email_prive' => null,
                'bsn' => null,
                'bsn_hash' => null,
                'opmerkingen' => null,
            ]);
        }

        $this->line(' Medewerkers    klaar');
    }

    private function anonimiseerDocenten(): void
    {
        foreach (DB::table('docenten')->orderBy('id')->get() as $d) {
            DB::table('docenten')->where('id', $d->id)->update([
                'voornaam' => $this->voornaam($d->id + 11),
                'achternaam' => $this->achternaam($d->id + 11),
                'email' => 'docent'.$d->id.'@voorbeeld.test',
            ]);
        }

        $this->line(' Docenten       klaar');
    }

    /**
     * De accounts. E-mail is uniek, dus de nieuwe waarde moet dat ook zijn: het
     * id erin verwerken garandeert dat. Een account dat aan een docentprofiel
     * hangt krijgt dezelfde naam als dat profiel — anders zou de demo een docent
     * "Amina Yilmaz" tonen die inlogt als "Omar Demir".
     */
    private function anonimiseerGebruikers(): void
    {
        foreach (DB::table('users')->orderBy('id')->get() as $u) {
            $docent = $u->docent_id ? DB::table('docenten')->find($u->docent_id) : null;

            $naam = $docent
                ? $docent->voornaam.' '.$docent->achternaam
                : $this->voornaam($u->id + 5).' '.$this->achternaam($u->id + 5);

            DB::table('users')->where('id', $u->id)->update([
                'naam' => $naam,
                'email' => $u->rol.$u->id.'@voorbeeld.test',
                'entra_oid' => null,
                // Noodaccounts horen niet mee te reizen naar een demo-omgeving:
                // het wachtwoord is daar niet bekend en de plaatsen kunnen beter
                // vrij zijn.
                'password' => null,
                'noodaccount_slot' => null,
                'wachtwoord_gewijzigd_op' => null,
            ]);
        }

        $this->line(' Accounts       klaar');
    }

    private function anonimiseerCursisten(): void
    {
        foreach (DB::table('cursisten')->orderBy('id')->get() as $c) {
            DB::table('cursisten')->where('id', $c->id)->update([
                'voornaam' => $this->voornaam($c->id + 17),
                'achternaam' => $this->achternaam($c->id + 17),
                'geboortedatum' => $c->geboortedatum ? substr((string) $c->geboortedatum, 0, 4).'-01-01' : null,
                'adres' => $this->adres($c->id),
                'postcode' => $this->postcode($c->id),
                'woonplaats' => $this->plaats($c->id),
                'telefoon' => $this->telefoon($c->id),
                'email' => 'cursist'.$c->id.'@voorbeeld.test',
                'opmerkingen' => null,
            ]);
        }

        $this->line(' Cursisten      klaar');
    }

    private function anonimiseerContactpersonen(): void
    {
        foreach (DB::table('contactpersonen')->orderBy('id')->get() as $c) {
            DB::table('contactpersonen')->where('id', $c->id)->update([
                'voornaam' => $this->voornaam($c->id + 23),
                'achternaam' => $this->achternaam($c->id + 23),
                'email' => 'contact'.$c->id.'@voorbeeld.test',
                'mobiel' => $this->telefoon($c->id),
                'telefoon' => $this->telefoon($c->id + 1),
            ]);
        }

        // Organisatienamen blijven staan (die zijn synthetisch en geen
        // persoonsgegeven), maar de contactgegevens gaan eruit.
        foreach (DB::table('organisaties')->orderBy('id')->get() as $o) {
            DB::table('organisaties')->where('id', $o->id)->update([
                'adres' => $this->adres($o->id),
                'postcode' => $this->postcode($o->id),
                'telefoon' => $this->telefoon($o->id + 50),
                'email' => 'organisatie'.$o->id.'@voorbeeld.test',
                'opmerkingen' => null,
            ]);
        }

        $this->line(' Relaties       klaar');
    }

    private function anonimiseerOverig(): void
    {
        // De scriptiebegeleider staat als losse tekst op het traject.
        foreach (DB::table('scripties')->orderBy('id')->get() as $s) {
            DB::table('scripties')->where('id', $s->id)->update([
                'begeleider_naam' => $this->voornaam($s->id + 31).' '.$this->achternaam($s->id + 31),
                'begeleider_email' => 'begeleider'.$s->id.'@voorbeeld.test',
            ]);
        }

        // Balie: bezoekers, bellers en post staan hier met naam en telefoonnummer.
        foreach (DB::table('balie_registraties')->orderBy('id')->get() as $b) {
            DB::table('balie_registraties')->where('id', $b->id)->update([
                'contact_naam' => $this->voornaam($b->id + 37).' '.$this->achternaam($b->id + 37),
                'contact_telefoon' => $this->telefoon($b->id + 37),
                'toelichting' => null,
            ]);
        }

        $this->line(' Overig         klaar');
    }

    /**
     * Vrije tekst kan alles bevatten — een notitie over een student, de reden van
     * een ziekmelding. Er is geen manier om dat betrouwbaar te schonen, dus gaat
     * het er integraal uit.
     */
    private function wisVrijeTekst(): void
    {
        foreach ([
            'student_notities', 'medewerker_notities', 'relatie_notities',
            'examencommissie_notities', 'contactmomenten', 'gesprekken',
        ] as $tabel) {
            if (Schema::hasTable($tabel)) {
                DB::table($tabel)->delete();
            }
        }

        foreach ([
            ['inschrijvingen', 'opmerkingen'],
            ['resultaten', 'opmerking'],
            ['cijferlijsten', 'opmerking'],
            ['dienstverbanden', 'opmerking'],
            ['ziekmeldingen', 'opmerking'],
            ['verlofaanvragen', 'opmerking_beoordelaar'],
            ['cursusbetalingen', 'opmerking'],
            ['cursusinschrijvingen', 'opmerking'],
            ['overeenkomsten', 'opmerking'],
            ['scriptie_stapstanden', 'opmerking'],
            ['bibliotheek_uitleningen', 'retour_opmerking'],
        ] as [$tabel, $kolom]) {
            if (Schema::hasTable($tabel) && Schema::hasColumn($tabel, $kolom)) {
                DB::table($tabel)->whereNotNull($kolom)->update([$kolom => null]);
            }
        }

        $this->line(' Vrije tekst    gewist');
    }

    /**
     * Sporen die per definitie over echte mensen gaan: wie keek wanneer naar welk
     * dossier, vanaf welk IP. Die horen sowieso niet in een demo.
     */
    private function wisSporen(): void
    {
        foreach ([
            'audit_logs', 'ondertekende_documenten', 'sessions', 'cache', 'cache_locks',
            'jobs', 'failed_jobs', 'password_reset_tokens', 'student_documenten',
            'hr_documenten', 'relatie_documenten', 'scriptie_documenten',
            'bibliotheek_emaillogs', 'hr_notificaties',
        ] as $tabel) {
            if (Schema::hasTable($tabel)) {
                DB::table($tabel)->delete();
            }
        }

        $this->line(' Sporen         gewist');
    }
}
