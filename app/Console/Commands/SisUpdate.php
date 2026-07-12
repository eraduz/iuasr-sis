<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * VEILIGE update tijdens het testen: maakt eerst een snapshot van de testdata en
 * draait dan ALLEEN de nieuwe migraties (`migrate`). Dit wist NOOIT data — anders
 * dan `migrate:fresh`/`migrate:refresh`, die alle tabellen droppen en herseeden.
 *
 * Gebruik na een code-update:  git pull  →  php artisan sis:update
 */
class SisUpdate extends Command
{
    protected $signature = 'sis:update {--geen-snapshot : sla de veiligheidssnapshot over}';

    protected $description = 'Veilige update: snapshot + alleen nieuwe migraties (behoudt testdata).';

    public function handle(): int
    {
        if (! $this->option('geen-snapshot')) {
            $this->call('sis:snapshot', ['--naam' => 'voor-update']);
        }

        $this->line('');
        $this->info('Nieuwe migraties uitvoeren (bestaande data blijft behouden)…');
        $code = $this->call('migrate', ['--force' => true]);

        if ($code === self::SUCCESS) {
            $this->info('Klaar. De database is bijgewerkt; je testdata is behouden.');
        }

        return $code;
    }
}
