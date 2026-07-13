<?php

namespace App\Console\Commands;

use App\Enums\BibliotheekMailsoort;
use App\Models\Bibliotheek\Emaillog;
use App\Models\Bibliotheek\Uitlening;
use App\Support\BibliotheekMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Automatische bibliotheekherinneringen (opdracht §8). Dagelijks via de
 * scheduler; zie routes/console.php.
 *
 * Drie stromen:
 *   1. HERINNERING VOORAF — X dagen vóór de vervaldatum
 *      (config `sis.bibliotheek.herinnering_dagen_vooraf`, standaard 3). Eén keer
 *      per uitlening.
 *   2. TE LAAT, STUDENT — waarschuwing zodra de retourdatum is verstreken. Eén
 *      keer per uitlening; geen boete (die regels zijn nog niet vastgesteld).
 *   3. TE LAAT, DOCENT — herinnering die elke `docent_herinnering_interval_dagen`
 *      dagen wordt herhaald (opdracht: elke 3 dagen), zonder boete of sanctie.
 *
 * IDEMPOTENT: het commando kijkt in het e-maillogboek wat er al is verstuurd.
 * Draait de scheduler twee keer op een dag, dan gaat er niets dubbel de deur uit.
 */
class BibliotheekHerinneringen extends Command
{
    protected $signature = 'bibliotheek:herinneringen';

    protected $description = 'Verstuurt de bibliotheekherinneringen (vooraf, te laat student, te laat docent)';

    public function handle(): int
    {
        $verstuurd = 0;

        $verstuurd += $this->herinneringVooraf();
        $verstuurd += $this->teLaat();

        $this->info($verstuurd === 0
            ? 'Geen herinneringen te versturen.'
            : $verstuurd.' herinnering(en) verstuurd.');

        return self::SUCCESS;
    }

    /** X dagen vóór de vervaldatum, eenmalig per uitlening. */
    private function herinneringVooraf(): int
    {
        $dagen = (int) config('sis.bibliotheek.herinnering_dagen_vooraf', 3);
        $doeldatum = Carbon::today()->addDays($dagen);
        $aantal = 0;

        $uitleningen = Uitlening::lopend()
            ->whereDate('verwachte_retour_op', $doeldatum)
            ->with(['exemplaar.publicatie', 'student', 'medewerker'])
            ->get();

        foreach ($uitleningen as $uitlening) {
            if ($this->alVerstuurd($uitlening, BibliotheekMailsoort::HerinneringVooraf)) {
                continue;
            }

            BibliotheekMailer::verstuur($uitlening, BibliotheekMailsoort::HerinneringVooraf);
            $aantal++;
        }

        return $aantal;
    }

    /**
     * Te laat. Studenten krijgen één waarschuwing; docenten een herhaling elke
     * `docent_herinnering_interval_dagen` dagen.
     */
    private function teLaat(): int
    {
        $interval = (int) config('sis.bibliotheek.docent_herinnering_interval_dagen', 3);
        $aantal = 0;

        $uitleningen = Uitlening::teLaat()
            ->with(['exemplaar.publicatie', 'student', 'medewerker'])
            ->get();

        foreach ($uitleningen as $uitlening) {
            if ($uitlening->isStudentlening()) {
                if ($this->alVerstuurd($uitlening, BibliotheekMailsoort::TeLaatStudent)) {
                    continue;
                }

                BibliotheekMailer::verstuur($uitlening, BibliotheekMailsoort::TeLaatStudent);
                $aantal++;

                continue;
            }

            // Docent: herhalen, maar niet vaker dan het interval.
            $laatste = $this->laatsteVerzending($uitlening, BibliotheekMailsoort::TeLaatDocent);

            if ($laatste !== null && $laatste->diffInDays(Carbon::now()) < $interval) {
                continue;
            }

            BibliotheekMailer::verstuur($uitlening, BibliotheekMailsoort::TeLaatDocent);
            $aantal++;
        }

        return $aantal;
    }

    private function alVerstuurd(Uitlening $uitlening, BibliotheekMailsoort $soort): bool
    {
        return Emaillog::where('uitlening_id', $uitlening->id)
            ->where('soort', $soort)
            ->where('gelukt', true)
            ->exists();
    }

    private function laatsteVerzending(Uitlening $uitlening, BibliotheekMailsoort $soort): ?Carbon
    {
        $moment = Emaillog::where('uitlening_id', $uitlening->id)
            ->where('soort', $soort)
            ->where('gelukt', true)
            ->max('verzonden_op');

        return $moment !== null ? Carbon::parse($moment) : null;
    }
}
