<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\Inschrijving;
use App\Models\Presentie;
use App\Models\User;
use App\Models\Vak;
use App\Support\Presentiebewaking;
use Illuminate\Database\Seeder;

/**
 * Synthetische aanwezigheidsregistratie (AVG: geen echte persoonsgegevens).
 *
 * Kent aan een handvol inschrijvingen de 50%-aanwezigheidsregeling toe en vult
 * de presentielijsten van de actieve vakken deels: sommige vakken volledig
 * geregistreerd, andere half, andere niet — zodat de signalering 'registratie
 * nog niet volledig' en de statistieken herkenbaar gevuld zijn.
 */
class PresentieSeeder extends Seeder
{
    public function run(): void
    {
        $this->regelingToekennen();
        $this->presentiesVullen();
    }

    /**
     * Drie studenten krijgen de 50%-regeling (synthetisch besluit van de directie).
     * Bewust gekozen uit de studenten die daadwerkelijk aan een vak deelnemen,
     * zodat de docent het 50%-label ook echt op zijn presentielijst ziet staan.
     */
    private function regelingToekennen(): void
    {
        $deelnemerIds = Vak::where('actief', true)->get()
            ->flatMap(fn (Vak $vak) => $vak->deelnemers()->pluck('inschrijvingen.id'))
            ->unique();

        Inschrijving::whereIn('inschrijvingen.id', $deelnemerIds)
            ->where('inschrijvingen.status', 'actief')
            ->join('studenten', 'studenten.id', '=', 'inschrijvingen.student_id')
            ->orderBy('studenten.studentnummer')
            ->select('inschrijvingen.*')
            ->limit(3)->get()
            ->each(fn (Inschrijving $i) => $i->update(['aanwezigheidsregeling_50' => true]));
    }

    /**
     * Vult de presentielijsten. De docent van het vak wordt als registrant
     * vastgelegd; vrijgestelde studenten blijven leeg.
     */
    private function presentiesVullen(): void
    {
        $weken = Presentiebewaking::weken();
        $docentUsers = User::where('rol', Rol::Docent)->whereNotNull('docent_id')->get()->keyBy('docent_id');

        foreach (Vak::where('actief', true)->orderBy('id')->get() as $index => $vak) {
            // Vak 1, 4, 7, ... volledig; 2, 5, 8, ... half; 3, 6, 9, ... niet gestart.
            $tot = match ($index % 3) {
                0 => count($weken),
                1 => (int) floor(count($weken) / 2),
                default => 0,
            };

            if ($tot === 0) {
                continue;
            }

            $deelnemers = $vak->deelnemers()->get();
            $vrijgesteld = Presentiebewaking::vrijgesteldeInschrijvingen($vak, $deelnemers->pluck('id'))->flip();
            $registrant = $docentUsers[$vak->docent_id] ?? null;

            foreach ($deelnemers as $rij => $insch) {
                if (isset($vrijgesteld[$insch->id])) {
                    continue;
                }

                foreach (range(1, $tot) as $week) {
                    // Deterministisch patroon: de meeste studenten zijn aanwezig,
                    // een enkeling zakt onder de norm (voor de signalering).
                    $afwezig = (($rij * 3 + $week * 5) % 7) === 0 || ($rij % 6 === 0 && $week % 2 === 0);

                    Presentie::updateOrCreate(
                        ['inschrijving_id' => $insch->id, 'vak_id' => $vak->id, 'week' => $week],
                        ['aanwezig' => ! $afwezig, 'geregistreerd_door_id' => $registrant?->id],
                    );
                }
            }
        }
    }
}
