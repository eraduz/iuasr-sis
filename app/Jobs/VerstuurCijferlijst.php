<?php

namespace App\Jobs;

use App\Mail\ResultatenCijferlijstMail;
use App\Models\Cijferlijstsjabloon;
use App\Models\Cijferlijstverzending;
use App\Support\Documentondertekening;
use App\Support\Transcript;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Verstuurt de officiële (ondertekende) cijferlijst van één student per e-mail.
 * Draait in de wachtrij zodat een groep studenten zonder time-out en zonder de
 * hele batch te blokkeren wordt verwerkt. De {@see Cijferlijstverzending}-rij
 * volgt de status (in_wachtrij → verzonden / mislukt).
 */
class VerstuurCijferlijst implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public int $verzendingId,
        public string $ondertekenaar,
    ) {}

    public function handle(): void
    {
        $verzending = Cijferlijstverzending::with(['student', 'periode', 'opleiding'])->find($this->verzendingId);
        if ($verzending === null || $verzending->student === null || $verzending->status === 'verzonden') {
            return;
        }

        $student = $verzending->student;

        // Officiële, ondertekende PDF-cijferlijst (alleen vastgestelde resultaten).
        $html = view('pdf.cijferlijst', [
            'student' => $student,
            'transcript' => Transcript::voor($student, alleenDefinitief: true),
            'ondertekenaar' => $this->ondertekenaar,
        ])->render();

        $doc = Documentondertekening::ondertekenHtml($html, [
            'type' => 'cijferlijst',
            'titel' => 'Cijferlijst '.$student->studentnummer,
            'student_id' => $student->id,
            'ontvanger' => $student->volledigeNaam().' (e-mail)',
            'uitgegeven_door_id' => $verzending->verzonden_door_id,
        ]);

        // Onderwerp en tekst uit het bewerkbare sjabloon, met de variabelen ingevuld.
        $sjabloon = Cijferlijstsjabloon::huidige();
        $waarden = [
            'Naam' => $student->volledigeNaam(),
            'Periode' => $verzending->periode?->naam ?? '',
            'Opleiding' => $verzending->opleiding?->naam ?? '',
        ];

        Mail::to($verzending->ontvanger)->send(new ResultatenCijferlijstMail(
            $sjabloon->vulIn($sjabloon->onderwerp, $waarden),
            $sjabloon->vulIn($sjabloon->inhoud, $waarden),
            Documentondertekening::pdfBytes($doc) ?? '',
            'Cijferlijst-'.$student->studentnummer.'.pdf',
        ));

        $verzending->update(['status' => 'verzonden', 'verzonden_op' => now(), 'foutmelding' => null]);
    }

    public function failed(\Throwable $e): void
    {
        Cijferlijstverzending::where('id', $this->verzendingId)
            ->update(['status' => 'mislukt', 'foutmelding' => mb_substr($e->getMessage(), 0, 500)]);
    }
}
