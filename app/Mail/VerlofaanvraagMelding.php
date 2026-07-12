<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Melding aan Personeelszaken dat er via self-service een verlofaanvraag is
 * binnengekomen, zodat HR die direct kan beoordelen.
 */
class VerlofaanvraagMelding extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $medewerkerNaam,
        public string $verloftypeLabel,
        public string $van,
        public string $tot,
        public float $uren,
        public ?string $reden = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nieuwe verlofaanvraag — '.$this->medewerkerNaam);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.hr.verlofaanvraag');
    }
}
