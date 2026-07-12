<?php

namespace App\Console\Commands;

use App\Support\Handleiding;
use Illuminate\Console\Command;

/**
 * Genereert de twee PDF-handleidingen (medewerkers + technisch) naar bestanden.
 * Draai dit opnieuw na het bijwerken van de Blade-bronnen wanneer er nieuwe
 * functies bij komen.
 */
class HandleidingenGenereren extends Command
{
    protected $signature = 'handleidingen:genereren {--map= : doelmap (standaard docs/handleidingen)}';

    protected $description = 'Genereert de PDF-handleidingen (medewerkers + technisch)';

    public function handle(): int
    {
        $map = $this->option('map') ?: base_path('docs/handleidingen');
        if (! is_dir($map) && ! mkdir($map, 0755, true) && ! is_dir($map)) {
            $this->error("Kon doelmap niet aanmaken: {$map}");

            return self::FAILURE;
        }

        $bestanden = [
            'IUASR-Management-Systeem-Handleiding-Medewerkers.pdf' => Handleiding::MEDEWERKERS,
            'IUASR-Management-Systeem-Technische-Handleiding.pdf' => Handleiding::TECHNISCH,
        ];

        foreach ($bestanden as $naam => $view) {
            file_put_contents($map.DIRECTORY_SEPARATOR.$naam, Handleiding::pdf($view));
            $this->info('Gegenereerd: '.$naam);
        }

        $this->line('Locatie: '.$map);

        return self::SUCCESS;
    }
}
