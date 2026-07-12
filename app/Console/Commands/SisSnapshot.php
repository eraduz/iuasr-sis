<?php

namespace App\Console\Commands;

use App\Support\DatabaseDump;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Maakt een SQL-snapshot van de HUIDIGE database (structuur + data). Gebruik dit
 * om je testdata veilig te stellen vóór een update. Snapshots staan in
 * storage/app/db-snapshots (buiten Git). Herstellen met `sis:restore`.
 */
class SisSnapshot extends Command
{
    protected $signature = 'sis:snapshot {--naam= : optioneel label in de bestandsnaam}';

    protected $description = 'Maakt een SQL-snapshot van de database (behoudt testdata).';

    public function handle(): int
    {
        $map = storage_path('app/db-snapshots');
        if (! is_dir($map)) {
            mkdir($map, 0775, true);
        }

        $label = $this->option('naam') ? Str::slug($this->option('naam')).'-' : '';
        $pad = $map.DIRECTORY_SEPARATOR.$label.now()->format('Ymd-His').'.sql';

        $handle = fopen($pad, 'w');
        DatabaseDump::schrijf($handle);
        fclose($handle);

        $this->info('Snapshot gemaakt: '.$pad);
        $this->line('  grootte: '.number_format(filesize($pad) / 1024, 0, ',', '.').' KB · database: '.config('database.connections.'.config('database.default').'.database'));

        return self::SUCCESS;
    }
}
