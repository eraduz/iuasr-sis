<?php

namespace App\Console\Commands;

use App\Models\Melding;
use Illuminate\Console\Command;

/**
 * Ruimt verlopen systeemmeldingen op.
 *
 * LET OP het onderscheid: een melding VERDWIJNT van de schermen zodra `tot`
 * voorbij is — dat is afgeleid uit de klok en heeft dit commando niet nodig. De
 * rij blijft daarna nog staan zodat het overzicht toont wat er is omgeroepen en
 * wie dat deed. Pas na de bewaartermijn (standaard 30 dagen) wordt hij echt
 * verwijderd; dat is wat dit commando doet.
 *
 * Zo hangt de zichtbaarheid nooit af van een geplande taak: valt de cron uit, dan
 * blijft er hooguit oude historie staan, en blijft er nooit een melding op de
 * schermen hangen.
 */
class MeldingenOpruimen extends Command
{
    protected $signature = 'sis:meldingen-opruimen {--dagen= : Bewaartermijn in dagen (standaard uit config)} {--proef : Alleen tonen wat er zou verdwijnen}';

    protected $description = 'Verwijdert systeemmeldingen die langer dan de bewaartermijn verlopen zijn.';

    public function handle(): int
    {
        $dagen = (int) ($this->option('dagen') ?: config('sis.melding.bewaartermijn_dagen', 30));
        $grens = now()->subDays($dagen);

        $oud = Melding::query()->where('tot', '<', $grens)->orderBy('tot')->get();

        if ($oud->isEmpty()) {
            $this->info("Geen meldingen ouder dan {$dagen} dagen.");

            return self::SUCCESS;
        }

        $this->table(
            ['Titel', 'Liep tot', 'Aangemaakt door'],
            $oud->map(fn (Melding $m) => [
                mb_strimwidth($m->titel, 0, 40, '…'),
                $m->tot->format('d-m-Y H:i'),
                $m->aangemaaktDoor?->naam ?? '—',
            ])->all()
        );

        if ($this->option('proef')) {
            $this->warn($oud->count().' melding(en) zouden worden verwijderd. Draai zonder --proef om het echt te doen.');

            return self::SUCCESS;
        }

        $aantal = $oud->count();
        Melding::query()->whereIn('id', $oud->pluck('id'))->delete();

        $this->info("{$aantal} verlopen melding(en) verwijderd (ouder dan {$dagen} dagen).");

        return self::SUCCESS;
    }
}
