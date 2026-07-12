<?php

namespace App\Console\Commands;

use App\Mail\VerjaardagFelicitatie;
use App\Mail\VerlofStartMelding;
use App\Models\HrNotificatie;
use App\Models\Medewerker;
use App\Models\Verlofaanvraag;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Dagelijkse automatische HR-e-mails:
 *  1. verjaardagsfelicitatie aan medewerkers die vandaag jarig zijn;
 *  2. melding aan Personeelszaken van wettelijk verlof (zwangerschaps-/geboorte-/
 *     ouderschapsverlof) dat vandaag ingaat.
 *
 * Idempotent via `hr_notificaties`: draait de taak vaker op een dag, dan wordt
 * niets dubbel verstuurd.
 */
class HrNotificaties extends Command
{
    protected $signature = 'hr:notificaties';

    protected $description = 'Verstuurt HR-verjaardagsfelicitaties en meldingen van startend wettelijk verlof.';

    public function handle(): int
    {
        $vandaag = Carbon::today();
        $felicitaties = $this->verjaardagen($vandaag);
        $verlofmeldingen = $this->verlofStarts($vandaag);

        $this->info("HR-notificaties verstuurd: {$felicitaties} felicitatie(s), {$verlofmeldingen} verlofmelding(en).");

        return self::SUCCESS;
    }

    /** Feliciteert medewerkers die vandaag jarig zijn (eenmaal per jaar). */
    private function verjaardagen(Carbon $vandaag): int
    {
        $aantal = 0;

        $jarigen = Medewerker::query()
            ->where('actief', true)
            ->whereNotNull('geboortedatum')
            ->whereNotNull('email')
            ->get()
            ->filter(fn (Medewerker $m) => $m->geboortedatum->format('m-d') === $vandaag->format('m-d'));

        foreach ($jarigen as $m) {
            if (! HrNotificatie::eersteKeer('verjaardag', $m->id.':'.$vandaag->year, $m->id, $m->email)) {
                continue;
            }
            Mail::to($m->email)->send(new VerjaardagFelicitatie($m->voornaam ?: 'collega'));
            $aantal++;
        }

        return $aantal;
    }

    /** Meldt Personeelszaken het wettelijk verlof dat vandaag ingaat. */
    private function verlofStarts(Carbon $vandaag): int
    {
        $aantal = 0;
        $ontvanger = (string) config('sis.hr.notificatie_email');

        $aanvragen = Verlofaanvraag::query()
            ->where('status', 'goedgekeurd')
            ->whereDate('van', $vandaag->toDateString())
            ->with('medewerker')
            ->get()
            ->filter(fn (Verlofaanvraag $a) => $a->verloftype?->wettelijk());

        foreach ($aanvragen as $a) {
            if (! HrNotificatie::eersteKeer('verlof_start', 'aanvraag:'.$a->id, $a->medewerker_id, $ontvanger)) {
                continue;
            }
            Mail::to($ontvanger)->send(new VerlofStartMelding(
                $a->medewerker?->volledigeNaam() ?? '—',
                $a->verloftype->label(),
                $a->van->format('d-m-Y'),
                $a->tot->format('d-m-Y'),
            ));
            $aantal++;
        }

        return $aantal;
    }
}
