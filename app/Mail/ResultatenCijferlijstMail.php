<?php

namespace App\Mail;

use App\Mail\Concerns\AfdelingsCc;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail met de officiële (ondertekende) cijferlijst als PDF-bijlage, gericht
 * aan één student. Wordt individueel verstuurd — nooit in bulk-zichtbaar — zodat
 * geen enkele student de gegevens van een ander ziet (AVG).
 */
class ResultatenCijferlijstMail extends Mailable
{
    use AfdelingsCc, Queueable, SerializesModels;

    public function __construct(
        public string $studentNaam,
        public string $periodeNaam,
        public string $pdfBytes,
        public string $bestandsnaam,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Uw studieresultaten — Islamic University of Applied Sciences Rotterdam',
            cc: $this->afdelingsCc('examencommissie'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.resultaten', with: [
            'naam' => $this->studentNaam,
            'periode' => $this->periodeNaam,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfBytes, $this->bestandsnaam)->withMime('application/pdf'),
        ];
    }
}
