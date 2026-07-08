<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ZipArchive;

/**
 * Pakt een met wachtwoord versleutelde recovery-backup uit. Nodig omdat de
 * Windows Verkenner AES-versleutelde ZIP-archieven niet kan openen; PHP's
 * ZipArchive kan dat wél. Niet-destructief: pakt uit naar een aparte map.
 */
class BackupUitpakken extends Command
{
    protected $signature = 'backup:uitpakken
        {archief : pad naar het .zip-archief}
        {--doel= : doelmap (standaard naast het archief)}
        {--wachtwoord= : wachtwoord (anders wordt er veilig naar gevraagd)}';

    protected $description = 'Pakt een met wachtwoord versleutelde recovery-backup veilig uit';

    public function handle(): int
    {
        $archief = $this->argument('archief');
        if (! is_file($archief)) {
            $this->error("Archief niet gevonden: {$archief}");

            return self::FAILURE;
        }

        $wachtwoord = $this->option('wachtwoord') ?: $this->secret('Wachtwoord van het archief');
        if (! $wachtwoord) {
            $this->error('Geen wachtwoord opgegeven.');

            return self::FAILURE;
        }

        $doel = $this->option('doel')
            ?: dirname($archief).DIRECTORY_SEPARATOR.pathinfo($archief, PATHINFO_FILENAME).'-hersteld';

        $zip = new ZipArchive();
        if ($zip->open($archief) !== true) {
            $this->error('Kon het archief niet openen (beschadigd of geen geldig ZIP-bestand).');

            return self::FAILURE;
        }

        $zip->setPassword($wachtwoord);

        // Wachtwoord verifiëren op de eerste entry vóór het uitpakken.
        $proef = $zip->getNameIndex(0);
        if ($proef !== false && @$zip->getFromName($proef) === false) {
            $this->error('Onjuist wachtwoord — uitpakken afgebroken.');
            $zip->close();

            return self::FAILURE;
        }

        if (! is_dir($doel) && ! mkdir($doel, 0755, true) && ! is_dir($doel)) {
            $this->error("Kon doelmap niet aanmaken: {$doel}");
            $zip->close();

            return self::FAILURE;
        }

        if (! $zip->extractTo($doel)) {
            $this->error('Uitpakken mislukt — controleer het wachtwoord en de schijfruimte.');
            $zip->close();

            return self::FAILURE;
        }

        $aantal = $zip->numFiles;
        $zip->close();

        $this->info("Uitgepakt: {$aantal} bestanden naar {$doel}");
        $this->newLine();
        $this->line('Vervolgstappen voor volledig herstel:');
        $this->line('  1. composer install            (herstelt vendor/)');
        $this->line('  2. database importeren:        mariadb -u <user> -p -P 3307 <db> < database.sql');
        $this->line('  3. .env controleren            (APP_KEY ONGEWIJZIGD laten)');
        $this->line('  4. php artisan optimize:clear');
        $this->line('Zie LEESMIJ-herstel.txt in het archief voor de volledige procedure.');

        return self::SUCCESS;
    }
}
