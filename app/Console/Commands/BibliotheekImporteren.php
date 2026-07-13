<?php

namespace App\Console\Commands;

use App\Support\BibliotheekImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importeert de bestaande Excel-bibliotheek. Voor de volledige collectie (ruim
 * 11.000 titels, ruim 15.000 exemplaren) is dit de aangewezen weg: het schrijft
 * tienduizenden regels weg en dat hoort niet in een webverzoek.
 *
 * Gebruik:
 *   php artisan bibliotheek:importeren "pad/naar/Boeken bibliotheek.xlsx" --proef
 *   php artisan bibliotheek:importeren "pad/naar/Boeken bibliotheek.xlsx"
 *
 * Met --proef wordt er NIETS opgeslagen: u krijgt alleen het rapport. Draai die
 * altijd eerst. De echte import is idempotent (slaat al ingelezen rekcodes over),
 * dus hem twee keer draaien levert geen dubbele boeken op.
 */
class BibliotheekImporteren extends Command
{
    protected $signature = 'bibliotheek:importeren
        {bestand : Pad naar het Excel-bestand (werkblad "Alle Boeken")}
        {--proef : Alleen inlezen en rapporteren; niets opslaan}
        {--forceren : Zonder bevestiging wegschrijven (voor een niet-interactieve sessie)}';

    protected $description = 'Importeert de bestaande Excel-bibliotheek (titels + exemplaren)';

    public function handle(BibliotheekImport $import): int
    {
        $bestand = $this->argument('bestand');

        if (! is_file($bestand)) {
            $this->error('Bestand niet gevonden: '.$bestand);

            return self::FAILURE;
        }

        $this->info('Inlezen...');
        $resultaat = $import->lees($bestand);
        $stat = $resultaat['statistiek'];

        $this->table(['Wat', 'Aantal'], [
            ['Regels in het werkblad', $stat['gelezen']],
            ['Bruikbare titels', $stat['titels']],
            ['Fysieke exemplaren', $stat['exemplaren']],
            ['Waarvan tijdschriften', $stat['tijdschriften']],
            ['Zonder taal (bron onbruikbaar)', $stat['zonder_taal']],
            ['Aantal gecorrigeerd naar 1', $stat['aantal_gecorrigeerd']],
            ['Overgeslagen regels', count($resultaat['overgeslagen'])],
        ]);

        if ($resultaat['overgeslagen'] !== []) {
            $this->warn('Overgeslagen regels (eerste 10):');
            foreach (array_slice($resultaat['overgeslagen'], 0, 10) as $r) {
                $this->line(sprintf('  regel %-6s %-12s %s', $r['regel'], $r['rekcode'], $r['reden']));
            }
        }

        if ($this->option('proef')) {
            $this->info('Proefdraai: er is niets opgeslagen.');

            return self::SUCCESS;
        }

        if (! $this->option('forceren')
            && ! $this->confirm('Deze '.$stat['titels'].' titels en '.$stat['exemplaren'].' exemplaren nu wegschrijven?', false)) {
            $this->info('Afgebroken; er is niets opgeslagen.');

            return self::SUCCESS;
        }

        $this->info('Wegschrijven...');

        $geschreven = DB::transaction(fn () => $import->importeer($resultaat['rijen']));

        $this->info(sprintf(
            'Klaar: %d titels en %d exemplaren toegevoegd; %d regels bestonden al.',
            $geschreven['titels'],
            $geschreven['exemplaren'],
            $geschreven['overgeslagen_bestond_al'],
        ));

        return self::SUCCESS;
    }
}
