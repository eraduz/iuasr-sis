<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Herstelt de database uit een eerder gemaakte snapshot (zie `sis:snapshot`).
 * OVERSCHRIJFT de huidige database. Zonder bestandsnaam wordt de nieuwste snapshot
 * gebruikt. Bedoeld als vangnet als testdata per ongeluk is gewist.
 */
class SisRestore extends Command
{
    protected $signature = 'sis:restore {bestand? : bestandsnaam of pad; standaard de nieuwste} {--force : niet bevestigen}';

    protected $description = 'Herstelt de database uit een snapshot (overschrijft de huidige data).';

    public function handle(): int
    {
        $map = storage_path('app/db-snapshots');
        $pad = $this->bestandspad($map);

        if ($pad === null || ! is_readable($pad)) {
            $this->error('Geen snapshot gevonden. Maak er eerst een met: php artisan sis:snapshot');

            return self::FAILURE;
        }

        $db = config('database.connections.'.config('database.default').'.database');
        if (! $this->option('force') && ! $this->confirm("Dit OVERSCHRIJFT database '{$db}' met de snapshot ".basename($pad).'. Doorgaan?')) {
            $this->warn('Geannuleerd.');

            return self::SUCCESS;
        }

        $sql = file_get_contents($pad);
        DB::unprepared($sql); // de dump zet zelf FOREIGN_KEY_CHECKS uit/aan

        $this->info('Hersteld uit: '.$pad);

        return self::SUCCESS;
    }

    private function bestandspad(string $map): ?string
    {
        $arg = $this->argument('bestand');
        if ($arg) {
            return is_file($arg) ? $arg : $map.DIRECTORY_SEPARATOR.$arg;
        }

        $bestanden = glob($map.DIRECTORY_SEPARATOR.'*.sql') ?: [];
        if (! $bestanden) {
            return null;
        }
        usort($bestanden, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $bestanden[0];
    }
}
