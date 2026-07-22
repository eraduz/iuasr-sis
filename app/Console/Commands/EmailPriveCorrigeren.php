<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Eenmalige correctie voor studenten die vóór de mapping-fix zijn geïmporteerd:
 * hun (persoonlijke) e-mailadres uit de oude Access-database stond in het
 * IUASR-veld (`email`). Sinds het IUASR-veld standaard verborgen is, waren die
 * adressen onzichtbaar. Dit commando verplaatst ze naar het privé-veld
 * (`email_prive`) en maakt het IUASR-veld leeg.
 *
 * Alleen studenten met een IUASR-e-mail én een LEEG privé-veld worden aangeraakt;
 * er gaat geen data verloren (het adres blijft bestaan, in het juiste veld). De
 * migratie-import zelf schrijft sinds de fix rechtstreeks naar `email_prive`.
 */
class EmailPriveCorrigeren extends Command
{
    protected $signature = 'sis:email-prive-corrigeren {--apply : Voer de correctie daadwerkelijk uit (standaard: alleen tonen)}';

    protected $description = 'Verplaatst geïmporteerde e-mailadressen van het IUASR-veld (email) naar het privé-veld (email_prive).';

    public function handle(): int
    {
        $basis = Student::query()
            ->whereNotNull('email')->where('email', '!=', '')
            ->where(fn ($q) => $q->whereNull('email_prive')->orWhere('email_prive', ''));

        $aantal = $basis->clone()->count();

        if ($aantal === 0) {
            $this->info('Niets te doen: geen studenten met een e-mail in het IUASR-veld en een leeg privé-veld.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->warn("PROEFDRAAI — voor {$aantal} studenten zou `email` naar `email_prive` verplaatst worden (en `email` leeggemaakt).");
            foreach ($basis->clone()->limit(5)->get(['studentnummer', 'email']) as $s) {
                $this->line("  {$s->studentnummer} · {$s->email}  ->  email_prive");
            }
            $this->line('Voer opnieuw uit met --apply om de correctie door te voeren.');

            return self::SUCCESS;
        }

        // Bulk-update: email (plein tekst, niet versleuteld) naar email_prive; IUASR-veld leeg.
        $verplaatst = DB::table('studenten')
            ->whereNotNull('email')->where('email', '!=', '')
            ->where(fn ($q) => $q->whereNull('email_prive')->orWhere('email_prive', ''))
            ->update([
                'email_prive' => DB::raw('email'),
                'email' => null,
            ]);

        $this->info("Klaar: e-mailadres verplaatst naar email_prive voor {$verplaatst} studenten; het IUASR-veld is nu leeg.");

        return self::SUCCESS;
    }
}
