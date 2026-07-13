<?php

namespace App\Console\Commands;

use App\Models\Bibliotheek\Publicatie;
use App\Models\Bibliotheek\Verrijking;
use App\Support\BibliotheekVerrijker;
use Illuminate\Console\Command;

/**
 * Haalt ISBN, uitgavejaar en de juiste schrijfwijze van de titel op bij een
 * externe bibliografische bron (Open Library), voor de NEDERLANDSE, ENGELSE en
 * TURKSE titels.
 *
 * KERNREGEL (opdrachtgever): "skip als je onzeker bent". Alleen een zekere match
 * wijzigt iets; twijfel wordt vastgelegd als 'onzeker' en NIET toegepast.
 *
 * Gebruik:
 *   php artisan bibliotheek:verrijken --limiet=50 --proef     # tonen, niets opslaan
 *   php artisan bibliotheek:verrijken --limiet=500            # echt, in porties
 *   php artisan bibliotheek:verrijken --taal=tr --limiet=200
 *
 * Herhaalbaar: een titel die al is bevraagd, wordt niet opnieuw bevraagd. Draai
 * het commando dus gerust in porties tot alles is gehad.
 */
class BibliotheekVerrijken extends Command
{
    protected $signature = 'bibliotheek:verrijken
        {--taal=* : Taalcodes (standaard nl, en, tr)}
        {--limiet=100 : Hoeveel titels deze ronde}
        {--proef : Alleen tonen wat er zou gebeuren; niets opslaan}';

    protected $description = 'Vult ISBN en uitgavejaar aan en corrigeert de schrijfwijze (alleen bij een zekere match)';

    public function handle(BibliotheekVerrijker $verrijker): int
    {
        $talen = $this->option('taal') ?: BibliotheekVerrijker::TALEN;
        $limiet = (int) $this->option('limiet');
        $pauze = (int) config('sis.bibliotheek.verrijking.pauze_ms', 400) * 1000;

        // Alleen titels in de gekozen talen die nog niet zijn bevraagd.
        $titels = Publicatie::query()
            ->with('auteurs')
            ->whereHas('talen', fn ($q) => $q->whereIn('code', $talen))
            ->whereDoesntHave('verrijkingen')
            ->orderBy('id')
            ->limit($limiet)
            ->get();

        if ($titels->isEmpty()) {
            $this->info('Niets te doen: alle titels in deze talen zijn al bevraagd.');

            return self::SUCCESS;
        }

        $this->info($titels->count().' titels bevragen bij '.config('sis.bibliotheek.verrijking.host').'...');

        if ($this->option('proef')) {
            $this->warn('PROEF: er wordt niets opgeslagen en niets gewijzigd.');
        }

        $balk = $this->output->createProgressBar($titels->count());
        $balk->start();

        $telling = [Verrijking::TOEGEPAST => 0, Verrijking::ONZEKER => 0, Verrijking::GEEN_TREFFER => 0, Verrijking::FOUT => 0];
        $voorbeelden = [];

        foreach ($titels as $publicatie) {
            if ($this->option('proef')) {
                // In een proef wordt niets weggeschreven: draai de verrijking in een
                // transactie die altijd wordt teruggedraaid.
                \Illuminate\Support\Facades\DB::beginTransaction();
                $uitkomst = $verrijker->verrijk($publicatie);
                \Illuminate\Support\Facades\DB::rollBack();
            } else {
                $uitkomst = $verrijker->verrijk($publicatie);
            }

            if ($uitkomst !== null) {
                $telling[$uitkomst->status]++;

                if ($uitkomst->status === Verrijking::TOEGEPAST && count($voorbeelden) < 10) {
                    $voorbeelden[] = [
                        mb_substr($uitkomst->oude_titel ?? '', 0, 40),
                        mb_substr($uitkomst->gevonden_titel ?? '', 0, 40),
                        $uitkomst->isbn ?? '—',
                        $uitkomst->jaar ?? '—',
                        number_format((float) $uitkomst->score, 2),
                    ];
                }
            }

            $balk->advance();
            usleep($pauze);
        }

        $balk->finish();
        $this->newLine(2);

        $this->table(['Uitkomst', 'Aantal'], [
            ['Toegepast (zekere match)', $telling[Verrijking::TOEGEPAST]],
            ['Onzeker — overgeslagen', $telling[Verrijking::ONZEKER]],
            ['Geen treffer', $telling[Verrijking::GEEN_TREFFER]],
            ['Fout bij het ophalen', $telling[Verrijking::FOUT]],
        ]);

        if ($voorbeelden !== []) {
            $this->info('Voorbeelden van toegepaste correcties:');
            $this->table(['Was', 'Wordt', 'ISBN', 'Jaar', 'Score'], $voorbeelden);
        }

        $resterend = Publicatie::whereHas('talen', fn ($q) => $q->whereIn('code', $talen))
            ->whereDoesntHave('verrijkingen')->count();

        $this->info($resterend === 0
            ? 'Alle titels in deze talen zijn bevraagd.'
            : 'Nog '.$resterend.' titels te gaan; draai het commando opnieuw.');

        return self::SUCCESS;
    }
}
