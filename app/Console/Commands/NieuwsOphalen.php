<?php

namespace App\Console\Commands;

use App\Models\Nieuwsbericht;
use App\Models\Nieuwsbron;
use App\Support\Nieuwsophaler;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Haalt het onderwijsnieuws van de actieve (automatische) bronnen op en slaat het
 * lokaal op. Draait via de scheduler (dagelijks 23:00) en kan handmatig door Beheer
 * worden gestart. Het bestuursdashboard leest uitsluitend uit de lokale tabel.
 */
class NieuwsOphalen extends Command
{
    protected $signature = 'nieuws:ophalen {--bron= : optioneel: alleen deze bron-id}';

    protected $description = 'Haalt onderwijsnieuws op van de whitelisted bronnen (feeds/scrape).';

    public function handle(Nieuwsophaler $ophaler): int
    {
        $max = (int) config('sis.nieuws.max_per_bron', 15);

        $bronnen = Nieuwsbron::where('actief', true)
            ->when($this->option('bron'), fn ($q) => $q->whereKey($this->option('bron')))
            ->orderBy('volgorde')->get()
            ->filter(fn (Nieuwsbron $b) => $b->type->automatisch());

        $totaalNieuw = 0;
        foreach ($bronnen as $bron) {
            try {
                $items = $ophaler->haalOp($bron);

                $nieuw = 0;
                foreach ($items as $item) {
                    $bericht = Nieuwsbericht::firstOrCreate(
                        ['link_hash' => Nieuwsbericht::hashVoor($item['link'])],
                        [
                            'nieuwsbron_id' => $bron->id,
                            'titel' => $item['titel'],
                            'samenvatting' => $item['samenvatting'],
                            'link' => $item['link'],
                            'gepubliceerd_op' => $item['gepubliceerd_op'],
                            'opgehaald_op' => now(),
                        ]
                    );
                    if ($bericht->wasRecentlyCreated) {
                        $nieuw++;
                    }
                }

                $this->snoei($bron, $max);
                $bron->update(['laatst_opgehaald_op' => now(), 'laatste_fout' => null]);
                $totaalNieuw += $nieuw;
                $this->info("{$bron->naam}: {$nieuw} nieuw (".count($items).' opgehaald).');
            } catch (\Throwable $e) {
                $bron->update(['laatste_fout' => Carbon::now()->format('Y-m-d H:i').' — '.$e->getMessage()]);
                $this->warn("{$bron->naam}: mislukt — {$e->getMessage()}");
            }
        }

        $this->info("Klaar. {$totaalNieuw} nieuwe berichten.");

        return self::SUCCESS;
    }

    /** Houd per bron alleen de nieuwste N berichten. */
    private function snoei(Nieuwsbron $bron, int $max): void
    {
        $overbodig = $bron->berichten()
            ->orderByDesc('gepubliceerd_op')->orderByDesc('id')
            ->skip($max)->take(1000)->pluck('id');
        if ($overbodig->isNotEmpty()) {
            Nieuwsbericht::whereKey($overbodig)->delete();
        }
    }
}
