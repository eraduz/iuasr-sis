<?php

namespace App\Console\Commands;

use App\Models\Bibliotheek\Publicatie;
use App\Support\AuditLogger;
use App\Support\BibliotheekTaalcontrole as Taalcontrole;
use Illuminate\Console\Command;

/**
 * Controleert de boektitels op waarschijnlijke spel-/typefouten met de
 * corpus-methode (zie App\Support\BibliotheekTaalcontrole). Levert een reviewlijst
 * op het scherm en optioneel als CSV; corrigeert zelf niets.
 */
class BibliotheekTaalcontrole extends Command
{
    protected $signature = 'bibliotheek:taalcontrole
        {--taal=nl,en,tr : Talen om te controleren (ISO-codes, komma-gescheiden)}
        {--max-verdacht=1 : Een verdacht woord komt in hoogstens zoveel titels voor}
        {--min-suggestie=5 : Een suggestie komt in ten minste zoveel titels voor}
        {--factor=4 : De suggestie moet minstens deze factor vaker voorkomen}
        {--min-lengte=5 : Verdachte woorden korter dan dit worden overgeslagen}
        {--met-verbuigingen : Ook paren tonen die alleen in een achtervoegsel verschillen}
        {--toon=25 : Aantal voorbeelden per taal op het scherm}
        {--csv= : Schrijf alle bevindingen naar dit CSV-bestand}
        {--toepassen : Pas de zekerste correcties daadwerkelijk toe (anders alleen tonen)}
        {--toepassen-talen=nl,en : Talen die automatisch gecorrigeerd mogen worden (Turks standaard NIET: te veel geldige woorden op afstand 1)}
        {--apply-min-suggestie=12 : Voor toepassen: de suggestie moet in ten minste zoveel titels voorkomen}
        {--toegepast-csv= : Schrijf de toegepaste correcties (oud → nieuw) naar dit CSV-bestand}';

    protected $description = 'Controleert boektitels op waarschijnlijke spel-/typefouten (Turks, Engels, Nederlands)';

    public function handle(): int
    {
        $codes = array_filter(array_map('trim', explode(',', (string) $this->option('taal'))));

        $controle = new Taalcontrole(
            maxVerdachtFreq: (int) $this->option('max-verdacht'),
            minSuggestieFreq: (int) $this->option('min-suggestie'),
            factor: (int) $this->option('factor'),
            minLengte: (int) $this->option('min-lengte'),
            negeerVerbuiging: ! $this->option('met-verbuigingen'),
        );

        $alle = [];
        foreach ($codes as $code) {
            $id = Taalcontrole::taalId($code);
            if ($id === null) {
                $this->warn("Onbekende taal overgeslagen: {$code}");

                continue;
            }

            $flags = $controle->voorTaal($id);
            $this->newLine();
            $this->info(strtoupper($code).': '.count($flags).' vermoedelijke typefouten gevonden.');

            $toon = (int) $this->option('toon');
            $voorbeelden = array_map(fn ($f) => [
                $f['id'],
                mb_strimwidth($f['titel'], 0, 52, '…'),
                $f['verdacht'].' → '.$f['suggestie'],
                $f['freq_verdacht'].' / '.$f['freq_suggestie'],
            ], array_slice($flags, 0, $toon));

            if ($voorbeelden !== []) {
                $this->table(['ID', 'Titel', 'Verdacht → suggestie', 'freq'], $voorbeelden);
            }

            foreach ($flags as $f) {
                $f['taal'] = $code;
                $alle[] = $f;
            }
        }

        $csv = $this->option('csv');
        if ($csv) {
            $fh = fopen($csv, 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['taal', 'publicatie_id', 'titel', 'verdacht_woord', 'suggestie', 'afstand', 'freq_verdacht', 'freq_suggestie'], ';');
            foreach ($alle as $f) {
                fputcsv($fh, [$f['taal'], $f['id'], $f['titel'], $f['verdacht'], $f['suggestie'], $f['afstand'], $f['freq_verdacht'], $f['freq_suggestie']], ';');
            }
            fclose($fh);
            $this->newLine();
            $this->info('CSV geschreven: '.$csv.' ('.count($alle).' regels).');
        }

        $this->verwerkZekereCorrecties($alle);

        return self::SUCCESS;
    }

    /**
     * De zekerste correcties (interne typefout, groot frequentieverschil) tonen en,
     * met --toepassen, daadwerkelijk doorvoeren — per titel, gelogd en met een
     * oud→nieuw-CSV zodat elke wijziging te herzien is.
     *
     * @param  list<array<string, mixed>>  $alle
     */
    private function verwerkZekereCorrecties(array $alle): void
    {
        $applyMin = (int) $this->option('apply-min-suggestie');
        $applyTalen = array_filter(array_map('trim', explode(',', (string) $this->option('toepassen-talen'))));

        $zeker = array_values(array_filter($alle, fn ($f) => $f['afstand'] === 1
            && in_array($f['taal'], $applyTalen, true)
            && $f['freq_suggestie'] >= $applyMin
            && Taalcontrole::isInterneTypfout($f['verdacht'], $f['suggestie'])));

        $this->newLine();
        $this->info('Zekerste correcties in '.strtoupper(implode('/', $applyTalen)).' (kandidaat voor automatisch toepassen): '.count($zeker).'.');
        if ($zeker === []) {
            return;
        }

        $this->table(
            ['ID', 'Verdacht → suggestie', 'freq'],
            array_map(fn ($f) => [$f['id'], $f['verdacht'].' → '.$f['suggestie'], $f['freq_verdacht'].' / '.$f['freq_suggestie']], array_slice($zeker, 0, 40)),
        );

        if (! $this->option('toepassen')) {
            $this->warn('Proefdraai: er is niets gewijzigd. Voeg --toepassen toe om deze correcties door te voeren.');

            return;
        }

        // Per titel groeperen (een titel kan meerdere te corrigeren woorden hebben).
        $perId = [];
        foreach ($zeker as $f) {
            $perId[$f['id']][] = $f;
        }

        $toegepast = [];
        foreach ($perId as $id => $flags) {
            $pub = Publicatie::find($id);
            if ($pub === null) {
                continue;
            }
            $oud = $pub->titel;
            $nieuw = $oud;
            foreach ($flags as $f) {
                [$nieuw, $n] = Taalcontrole::vervangWoord($nieuw, $f['verdacht'], $f['suggestie']);
            }
            if ($nieuw === $oud) {
                continue;
            }

            $pub->titel = $nieuw;
            $pub->save();

            AuditLogger::log(AuditLogger::WIJZIGING, $pub, veld: 'bibliotheek_titel_correctie', context: [
                'oud' => $oud,
                'nieuw' => $nieuw,
            ]);

            $toegepast[] = ['id' => $id, 'oud' => $oud, 'nieuw' => $nieuw];
        }

        $this->info(count($toegepast).' titels gecorrigeerd (gelogd).');

        $pad = $this->option('toegepast-csv');
        if ($pad) {
            $fh = fopen($pad, 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['publicatie_id', 'oude_titel', 'nieuwe_titel'], ';');
            foreach ($toegepast as $r) {
                fputcsv($fh, [$r['id'], $r['oud'], $r['nieuw']], ';');
            }
            fclose($fh);
            $this->info('Toegepaste correcties: '.$pad.'.');
        }
    }
}
