<?php

namespace App\Console\Commands;

use App\Support\TijdschriftImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importeert de tijdschriftINHOUD: de artikelen per uitgave.
 *
 * Gebruik:
 *   php artisan bibliotheek:tijdschriften "pad/Tijdschriftinhoud-Engels.xlsx" --proef
 *   php artisan bibliotheek:tijdschriften "pad/Tijdschriftinhoud-Engels.xlsx" --forceren
 *   php artisan bibliotheek:tijdschriften "pad/A المجلات العربية.docx" --proef
 *
 * Draai ALTIJD eerst --proef: dan ziet u hoeveel tijdschriften, uitgaven en
 * artikelen er worden herkend, en welke regels worden overgeslagen (met reden).
 * De import is idempotent: een tweede keer draaien maakt niets dubbel.
 */
class TijdschriftenImporteren extends Command
{
    protected $signature = 'bibliotheek:tijdschriften
        {bestand : Pad naar het bestand (.xlsx of .docx)}
        {--proef : Alleen inlezen en rapporteren; niets opslaan}
        {--forceren : Zonder bevestiging wegschrijven}
        {--overgeslagen= : Schrijf alle overgeslagen regels naar dit CSV-bestand}';

    protected $description = 'Importeert de tijdschriftartikelen per uitgave (Engelse xlsx of Arabische docx)';

    public function handle(TijdschriftImport $import): int
    {
        $bestand = $this->argument('bestand');

        if (! is_file($bestand)) {
            $this->error('Bestand niet gevonden: '.$bestand);

            return self::FAILURE;
        }

        $this->info('Inlezen...');

        try {
            $resultaat = $import->lees($bestand);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stat = $resultaat['statistiek'];

        $this->table(['Wat', 'Aantal'], [
            ['Regels in het bestand', $stat['gelezen']],
            ['Tijdschriften herkend', $stat['tijdschriften']],
            ['Uitgaven herkend', $stat['uitgaven']],
            ['Artikelen herkend', $stat['artikelen']],
            ['Waarvan zonder auteur', $stat['zonder_auteur']],
            ['Overgeslagen regels', count($resultaat['overgeslagen'])],
        ]);

        if ($resultaat['overgeslagen'] !== []) {
            $this->warn('Overgeslagen regels (eerste 10) — deze zijn NIET geraden:');
            foreach (array_slice($resultaat['overgeslagen'], 0, 10) as $r) {
                $this->line(sprintf('  regel %-6s %-45s %s', $r['regel'], $r['reden'], mb_substr($r['tekst'], 0, 60)));
            }
        }

        // De volledige lijst overgeslagen regels wegschrijven, zodat de bibliotheek
        // ze met de hand kan nalopen. Niets verdwijnt stilzwijgend.
        if ($pad = $this->option('overgeslagen')) {
            $out = fopen($pad, 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Regel', 'Reden', 'Tekst'], ';');

            foreach ($resultaat['overgeslagen'] as $r) {
                fputcsv($out, [$r['regel'], $r['reden'], $r['tekst']], ';');
            }

            fclose($out);
            $this->info('Alle overgeslagen regels staan in: '.$pad);
        }

        if ($this->option('proef')) {
            $this->info('Proefdraai: er is niets opgeslagen.');

            return self::SUCCESS;
        }

        if (! $this->option('forceren')
            && ! $this->confirm('Deze '.$stat['artikelen'].' artikelen nu wegschrijven?', false)) {
            $this->info('Afgebroken; er is niets opgeslagen.');

            return self::SUCCESS;
        }

        $this->info('Wegschrijven...');
        $geschreven = DB::transaction(fn () => $import->importeer($resultaat['rijen']));

        $this->info(sprintf(
            'Klaar: %d tijdschriften, %d uitgaven en %d artikelen toegevoegd; %d artikelen bestonden al.',
            $geschreven['tijdschriften'],
            $geschreven['uitgaven'],
            $geschreven['artikelen'],
            $geschreven['bestond_al'],
        ));

        return self::SUCCESS;
    }
}
