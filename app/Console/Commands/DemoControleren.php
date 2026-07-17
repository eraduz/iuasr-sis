<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controleert of er in een demo-database écht geen persoonsgegevens meer staan.
 *
 * Waarom een apart commando: "ik heb geanonimiseerd" is een bewering, en bij een
 * dump die naar een publieke server gaat is een bewering niet genoeg. Dit toetst
 * het resultaat en geeft exitcode 1 zodra er iets doorheen glipt, zodat het in een
 * script vóór het uploaden kan staan.
 *
 * De controle is bewust ACHTERDOCHTIG: hij kijkt niet of de anonimisering "gedraaid
 * heeft", maar of er nog waarden staan die op echte gegevens lijken — een e-mailadres
 * buiten @voorbeeld.test, een gevulde BSN, een niet-leeg audit-logboek.
 */
class DemoControleren extends Command
{
    protected $signature = 'sis:demo-controleren';

    protected $description = 'Controleert of een demo-database vrij is van persoonsgegevens (exitcode 1 zodra er iets doorheen glipt).';

    public function handle(): int
    {
        $database = (string) config('database.connections.mysql.database');
        $this->line("Database: {$database}");
        $this->newLine();

        $bevindingen = [];

        // 1. E-mailadressen die niet naar het voorbeelddomein wijzen.
        foreach ([
            ['studenten', 'email'], ['studenten', 'email_prive'],
            ['medewerkers', 'email'], ['medewerkers', 'email_prive'],
            ['docenten', 'email'], ['users', 'email'],
            ['cursisten', 'email'], ['contactpersonen', 'email'],
            ['organisaties', 'email'], ['scripties', 'begeleider_email'],
        ] as [$tabel, $kolom]) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, $kolom)) {
                continue;
            }

            $aantal = DB::table($tabel)
                ->whereNotNull($kolom)->where($kolom, '!=', '')
                ->where($kolom, 'not like', '%@voorbeeld.test')
                ->count();

            if ($aantal > 0) {
                $bevindingen[] = "{$tabel}.{$kolom}: {$aantal} adres(sen) buiten @voorbeeld.test";
            }
        }

        // 2. Bijzondere persoonsgegevens die per definitie leeg horen te zijn.
        foreach ([
            ['studenten', 'bsn'], ['studenten', 'bsn_hash'], ['studenten', 'rekeningnummer'],
            ['medewerkers', 'bsn'], ['medewerkers', 'bsn_hash'],
        ] as [$tabel, $kolom]) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, $kolom)) {
                continue;
            }

            $aantal = DB::table($tabel)->whereNotNull($kolom)->count();
            if ($aantal > 0) {
                $bevindingen[] = "{$tabel}.{$kolom}: {$aantal} gevuld — moet leeg zijn";
            }
        }

        // 3. Sporen en vrije tekst die geheel gewist horen te zijn.
        foreach ([
            'audit_logs', 'ondertekende_documenten', 'sessions', 'student_notities',
            'medewerker_notities', 'relatie_notities', 'examencommissie_notities',
        ] as $tabel) {
            if (! Schema::hasTable($tabel)) {
                continue;
            }

            $aantal = DB::table($tabel)->count();
            if ($aantal > 0) {
                $bevindingen[] = "{$tabel}: {$aantal} rij(en) — moet leeg zijn";
            }
        }

        // 4. Wachtwoorden en noodtoegang horen niet mee te reizen.
        if (Schema::hasColumn('users', 'password')) {
            $aantal = DB::table('users')->whereNotNull('password')->count();
            if ($aantal > 0) {
                $bevindingen[] = "users.password: {$aantal} gevuld — noodtoegang hoort niet in een demo";
            }
        }

        if ($bevindingen === []) {
            $this->info('Schoon: geen persoonsgegevens aangetroffen.');
            $this->newLine();
            $this->toonSteekproef();

            return self::SUCCESS;
        }

        $this->error('NIET SCHOON — deze database mag niet naar een publieke server:');
        foreach ($bevindingen as $b) {
            $this->line('  - '.$b);
        }

        return self::FAILURE;
    }

    /** Laat zien wat er nu écht in staat; een groen vinkje leest niemand kritisch. */
    private function toonSteekproef(): void
    {
        $this->line('Steekproef (5 willekeurige studenten):');
        $this->table(
            ['Nummer', 'Naam', 'E-mail', 'Woonplaats'],
            collect(DB::table('studenten')->inRandomOrder()->limit(5)->get())
                ->map(fn ($s) => [$s->studentnummer, $s->voornaam.' '.$s->achternaam, $s->email, $s->woonplaats])
                ->all()
        );

        $this->line('Behouden (niet-persoonsgebonden):');
        foreach (['bibliotheek_publicaties' => 'bibliotheektitels', 'bibliotheek_artikelen' => 'artikelen', 'vakken' => 'vakken', 'quotes' => 'quotes'] as $tabel => $wat) {
            if (Schema::hasTable($tabel)) {
                $this->line('  '.str_pad((string) DB::table($tabel)->count(), 8).$wat);
            }
        }
    }
}
